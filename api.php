<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function out(mixed $data = null): never
{
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function fail(string $msg, int $code = 400, array $extra = []): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return $_POST;
    }
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        fail(t('POST only.'), 405);
    }
}

function require_csrf(): void
{
    if (!Auth::verifyCsrf()) {
        fail(t('Session check (CSRF) failed. Reload the page.'), 419);
    }
}

/** Converts the chat mapping sent by the browser into the shape the engine expects */
function known_map(mixed $v): array
{
    $out = [];
    foreach (array_slice(is_array($v) ? $v : [], 0, 5000) as $k) {
        if (!is_array($k) || !is_string($k['token'] ?? null) || !is_string($k['category'] ?? null) || !is_string($k['original'] ?? null)) {
            continue;
        }
        $out[] = [
            'token'    => $k['token'],
            'category' => $k['category'],
            'original' => $k['original'],
            'variants' => array_values(array_filter(array_slice((array)($k['variants'] ?? []), 0, 50), 'is_string')),
        ];
    }
    return $out;
}

/** Manually marked values: hints = [{category, value}] to mask, allow = [value] to leave uncovered */
function hint_list(mixed $v): array
{
    $out = [];
    foreach (array_slice(is_array($v) ? $v : [], 0, 500) as $h) {
        if (is_array($h) && is_string($h['category'] ?? null) && is_string($h['value'] ?? null) && mb_strlen($h['value']) <= 300) {
            $out[] = ['category' => $h['category'], 'value' => $h['value']];
        }
    }
    return $out;
}
function allow_list(mixed $v): array
{
    return array_values(array_filter(array_slice(is_array($v) ? $v : [], 0, 500), fn($x) => is_string($x) && $x !== '' && mb_strlen($x) <= 300));
}

/** Adds the mappings of a masking result to the known list for the following runs */
function known_add(array $known, array $items): array
{
    foreach ($items as $it) {
        $known[] = ['token' => $it['token'], 'category' => $it['category'], 'original' => $it['original'], 'variants' => array_values((array)($it['variants'] ?? []))];
    }
    return $known;
}

/**
 * Screenshot masked (covered) in the browser: only data:image/(png|jpeg|webp);base64 is accepted.
 * Sensitive text in the image is covered in the browser with OCR + Maskink rules; the server does not process the image itself.
 */
function chat_image(mixed $v): ?string
{
    if (!is_string($v) || strlen($v) > 8 * 1024 * 1024 || !preg_match('#^data:image/(png|jpeg|webp);base64,[A-Za-z0-9+/]+=*$#', $v)) {
        return null;
    }
    return $v;
}

/** System prompt: the {EXAMPLE} and {CATEGORIES} placeholders are filled with the current categories ({ORNEK}/{KATEGORILER} also work) */
function chat_system_prompt(): string
{
    $tpl = (string)Settings::get('placeholder_template', '<{CAT}_{n}>');
    $cats = array_values(array_filter(Repo::categories(), fn($c) => $c['enabled']));
    $tok = fn(array $c, int $n) => strtr($c['placeholder'] ?: $tpl, ['{CAT}' => $c['token'], '{cat}' => strtolower($c['token']), '{n}' => (string)$n, '{hash}' => substr(md5($c['code'] . $n), 0, 8), '{len}' => '8', '{stars}' => '********']);
    $byCode = array_column($cats, null, 'code');
    $ex = [];
    foreach (['IP', 'HOST', 'USER'] as $code) {
        if (isset($byCode[$code])) {
            $ex[] = $tok($byCode[$code], 1);
        }
    }
    if (isset($byCode['IP'])) {
        $ex[] = $tok($byCode['IP'], 2);
    }
    $list = implode(', ', array_map(fn($c) => $c['token'] . ' = ' . $c['label'], $cats));
    $ex = implode(', ', $ex);
    return strtr((string)Settings::get('chat_system_prompt', ''), ['{EXAMPLE}' => $ex, '{CATEGORIES}' => $list, '{ORNEK}' => $ex, '{KATEGORILER}' => $list]);
}

/** Server-Sent Events: sends a single event */
function sse(string $event, array $data): void
{
    echo 'event: ', $event, "\n", 'data: ', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), "\n\n";
    @ob_flush();
    flush();
}

function require_admin(): void
{
    require_post();
    require_csrf();
    if (!Auth::check()) {
        fail(t('Admin sign-in required.'), 401);
    }
}

if (($bootErr = mk_boot()) !== null) {
    fail($bootErr, 503);
}

$action = (string)($_GET['action'] ?? '');
$in = input();

try {
    switch ($action) {
        // ------------------------------------------------------------ general
        case 'mask':
            require_post();
            if ((int)Settings::get('mask_requires_login', '0') && !Auth::check()) {
                fail(t('You need to sign in to mask.'), 401);
            }
            $text = (string)($in['text'] ?? '');
            $maxKb = max(16, (int)Settings::get('max_input_kb', '4096'));
            if (strlen($text) > $maxKb * 1024) {
                fail(t('The input is too large (limit {kb} KB). It can be raised in the settings.', ['kb' => $maxKb]), 413);
            }
            if (trim($text) === '') {
                fail(t('The text to mask is empty.'));
            }
            $res = Repo::engine()->run($text, [
                'mode'          => (string)($in['mode'] ?? Settings::get('default_mode', 'auto')),
                'disabled_cats' => array_values(array_filter((array)($in['disabled_cats'] ?? []), 'is_string')),
                'propagate'     => !isset($in['propagate']) || filter_var($in['propagate'], FILTER_VALIDATE_BOOLEAN),
                'known'         => known_map($in['known'] ?? []),
                'hints'         => hint_list($in['hints'] ?? []),
                'allow'         => allow_list($in['allow'] ?? []),
            ]);
            if (empty($in['preview'])) {   // intermediate runs of the screenshot editor are not counted in the statistics
                Repo::logUsage($res['stats']);
            }
            out($res);

        // ------------------------------------------------------------ chat
        case 'chat.models':
            require_post();
            $r = Llm::models(!empty($in['refresh']));
            if (!$r['models']) {
                fail(t('No models available.') . ' ' . ($r['errors'] ? implode(' · ', $r['errors']) : t('Add or enable a connection under Settings → AI connections.')), 502);
            }
            out(['models' => $r['models'], 'errors' => $r['errors'], 'default' => (string)Settings::get('chat_default_model', '')]);

        case 'llm.list':
            require_admin();
            out(['connections' => Llm::forAdmin(), 'types' => array_map('t', Llm::TYPES)]);

        case 'llm.save':
            require_admin();
            $orig = isset($in['id']) && $in['id'] !== '' ? (string)$in['id'] : null;
            $c = Llm::save((array)($in['connection'] ?? []), $orig);
            Repo::audit('llm.save', ($orig ? 'Connection updated: ' : 'Connection added: ') . $c['name'] . ' (' . $c['base_url'] . ')');
            out(['connections' => Llm::forAdmin()]);

        case 'llm.delete':
            require_admin();
            $c = Llm::connection((string)($in['id'] ?? ''));
            Llm::delete((string)($in['id'] ?? ''));
            Repo::audit('llm.delete', 'Connection deleted: ' . ($c['name'] ?? ''));
            out(['connections' => Llm::forAdmin()]);

        case 'llm.reorder':
            require_admin();
            Llm::reorder((array)($in['ids'] ?? []));
            Repo::audit('llm.reorder', 'Connection order changed');
            out(['connections' => Llm::forAdmin()]);

        case 'llm.test':
            require_admin();
            try {
                out(Llm::test((array)($in['connection'] ?? []), isset($in['id']) && $in['id'] !== '' ? (string)$in['id'] : null));
            } catch (RuntimeException $e) {
                fail(t('Connection test failed:') . ' ' . $e->getMessage(), 502);
            }

        case 'chat.send':
            // The user message is masked here; only the masked history goes to the model.
            // The reply is streamed to the browser still masked; restoring happens in the browser.
            require_post();
            require_csrf();
            if ((int)Settings::get('mask_requires_login', '0') && !Auth::check()) {
                fail(t('You need to sign in to chat.'), 401);
            }
            $text = (string)($in['text'] ?? '');
            $maxKb = max(16, (int)Settings::get('max_input_kb', '4096'));
            // attachments: screenshot covered in the browser + the text read from it with OCR (not masked yet)
            $attach = [];
            foreach (array_slice((array)($in['attach'] ?? []), 0, 4) as $a) {
                if (is_array($a)) {
                    $attach[] = ['image' => chat_image($a['image'] ?? null), 'ocr' => is_string($a['ocr'] ?? null) ? $a['ocr'] : '',
                        'hints' => hint_list($a['hints'] ?? []), 'allow' => allow_list($a['allow'] ?? [])];
                }
            }
            if (trim($text) === '' && !$attach) {
                fail(t('The message is empty.'));
            }
            if (strlen($text) + array_sum(array_map(fn($a) => strlen($a['ocr']), $attach)) > $maxKb * 1024) {
                fail(t('The message is too large (limit {kb} KB).', ['kb' => $maxKb]), 413);
            }
            [$conn, $modelName] = Llm::resolve((string)($in['model'] ?? ''));
            $vision = Llm::supportsVision($conn, $modelName);
            $history = [];
            foreach (array_slice((array)($in['history'] ?? []), -200) as $m) {
                if (is_array($m) && in_array($m['role'] ?? '', ['user', 'assistant'], true) && is_string($m['content'] ?? null) && $m['content'] !== '') {
                    $imgs = $vision && $m['role'] === 'user' ? array_values(array_filter(array_map('chat_image', array_slice((array)($m['images'] ?? []), 0, 4)))) : [];
                    $history[] = ['role' => $m['role'], 'content' => $m['content'], 'images' => $imgs];
                }
            }
            $opt = [
                'mode'          => (string)($in['mode'] ?? 'auto'),
                'disabled_cats' => array_values(array_filter((array)($in['disabled_cats'] ?? []), 'is_string')),
                'known'         => known_map($in['known'] ?? []),
                // values marked manually in the images are applied to the message text the same way
                'hints'         => array_merge(...array_map(fn($a) => $a['hints'], $attach ?: [['hints' => []]])),
                'allow'         => array_merge(...array_map(fn($a) => $a['allow'], $attach ?: [['allow' => []]])),
            ];
            $empty =['masked' => '', 'segments' => [], 'items' => [], 'warnings' => [], 'stats' => ['matches' => 0]];
            $res = $empty;
            if (trim($text) !== '') {
                $res = Repo::engine()->run($text, $opt);
                Repo::logUsage($res['stats']);
                $opt['known'] = known_add($opt['known'], $res['items']);
            }
            // OCR texts are masked with the same mapping, so the tokens the browser drew on the image stay the same
            $modelText = $res['masked'];
            $attOut = [];
            $items = $res['items'];
            foreach ($attach as $i => $a) {
                $r = $empty;
                if (trim($a['ocr']) !== '') {
                    $r = Repo::engine()->run($a['ocr'], $opt);
                    Repo::logUsage($r['stats']);
                    $opt['known'] = known_add($opt['known'], $r['items']);
                    $items = array_merge($items, $r['items']);
                }
                $n = $i + 1;
                $head = $vision && $a['image'] ? "[Attachment $n: screenshot (image attached; sensitive text is covered with token labels)" : "[Attachment $n: screenshot (recognized text only)";
                $modelText .= ($modelText !== '' ? "\n\n" : '') . $head . ($r['masked'] !== '' ? ". Text read from the image:]\n```\n" . $r['masked'] . "\n```" : '. No text could be read from the image.]');
                $attOut[] = ['masked' => $r['masked'], 'segments' => $r['segments'], 'items' => $r['items']];
            }
            $images = $vision ? array_values(array_filter(array_column($attach, 'image'))) : [];
            // to limit the request size, only the last few images of the history are kept
            $budget = max(0, 6 - count($images));
            for ($i = count($history) - 1; $i >= 0; $i--) {
                $keep = array_slice($history[$i]['images'], 0, $budget);
                $budget -= count($keep);
                $history[$i]['images'] = $keep;
            }
            $content = fn(string $t, array $imgs) => $imgs
                ? array_merge([['type' => 'text', 'text' => $t]], array_map(fn($u) => ['type' => 'image_url', 'image_url' => ['url' => $u]], $imgs))
                : $t;
            $temp = (string)Settings::get('chat_temperature', '');
            $messages = array_merge(
                [['role' => 'system', 'content' => chat_system_prompt()]],
                array_map(fn($m) => ['role' => $m['role'], 'content' => $content($m['content'], $m['images'])], $history),
                [['role' => 'user', 'content' => $content($modelText, $images)]]
            );

            // --- streaming (SSE)
            session_write_close();
            set_time_limit(0);
            ignore_user_abort(false);
            @ini_set('zlib.output_compression', '0');
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-store');
            header('X-Accel-Buffering: no');
            echo ':' . str_repeat(' ', 2048) . "\n\n";
            sse('mask', ['masked' => $res['masked'], 'segments' => $res['segments'], 'items' => $items, 'warnings' => $res['warnings'], 'stats' => $res['stats'],
                'model_text' => $modelText, 'attach' => $attOut, 'images_sent' => count($images)]);
            $t0 = microtime(true);
            try {
                $r = Llm::stream($conn, $modelName, $messages,['temperature' => $temp === '' ? null : (float)$temp], function (string $type, array $d) {
                    sse($type, $d);
                    return !connection_aborted();
                });
                sse('done', ['finish' => $r['finish'], 'usage' => $r['usage'], 'ms' => (int)round((microtime(true) - $t0) * 1000)]);
            } catch (Throwable $e) {
                sse('error', ['message' => $e->getMessage()]);
            }
            exit;

        case 'meta':
            out([
                'categories' => array_map(fn($c) => [
                    'code' => $c['code'], 'label' => t($c['label']), 'token' => $c['token'],
                    'color' => $c['color'], 'enabled' => $c['enabled'], 'description' => t((string)$c['description']),
                ], Repo::categories()),
                'default_mode' => Settings::get('default_mode', 'auto'),
                'admin'        => Auth::check(),
            ]);

        case 'login':
            require_post();
            require_csrf();
            if (!Auth::login((string)($in['password'] ?? ''))) {
                fail(t('Wrong password.'), 401);
            }
            out(['csrf' => Auth::csrf()]);

        case 'admin.setup':
            // Setting the admin password on first use (only while none exists)
            require_post();
            require_csrf();
            if ((string)Settings::get('admin_hash', '') !== '') {
                fail(t('The admin password has already been set.'), 409);
            }
            $pw = (string)($in['password'] ?? '');
            if (mb_strlen($pw) < 8) {
                fail(t('The password must be at least 8 characters.'));
            }
            Settings::set(['admin_hash' => password_hash($pw, PASSWORD_DEFAULT)]);
            Repo::audit('admin.setup', 'Initial admin password set');
            Auth::login($pw);
            out(['csrf' => Auth::csrf()]);

        case 'logout':
            require_post();
            require_csrf();
            Auth::logout();
            out();

        // ------------------------------------------------------------ administration
        case 'admin.bootstrap':
            require_admin();
            out([
                'rules'      => Repo::rules(),
                'categories' => Repo::categories(),
                'settings'   => array_intersect_key(Settings::all(), Settings::editable()),
                'invalid'    => Repo::validateAll(),
                'stats'      => Repo::usageStats(),
                'version'    => MK_VERSION,
                'php'        => PHP_VERSION,
                'pcre'       => PCRE_VERSION,
                'jit'        => (bool)ini_get('pcre.jit'),
            ]);

        case 'rules.list':
            require_admin();
            out(['rules' => Repo::rules(), 'invalid' => Repo::validateAll()]);

        case 'rule.save':
            require_admin();
            $id = Repo::saveRule((array)($in['rule'] ?? []));
            out(['id' => $id, 'rule' => Repo::rule($id)]);

        case 'rule.delete':
            require_admin();
            Repo::deleteRule((int)($in['id'] ?? 0));
            out();

        case 'rule.toggle':
            require_admin();
            Repo::toggleRule((int)($in['id'] ?? 0), !empty($in['enabled']));
            out();

        case 'rule.reset':
            require_admin();
            Repo::resetRule((int)($in['id'] ?? 0));
            out(['rule' => Repo::rule((int)$in['id'])]);

        case 'rule.duplicate':
            require_admin();
            $id = Repo::duplicateRule((int)($in['id'] ?? 0));
            out(['id' => $id, 'rule' => Repo::rule($id)]);

        case 'rule.test':
            require_admin();
            $raw = (array)($in['rule'] ?? []);
            if (trim((string)($raw['name'] ?? '')) === '') {
                $raw['name'] = 'Test rule';
            }
            $rule = Repo::sanitizeRule($raw);
            $rule['enabled'] = 1;
            $rule['id'] = $rule['id'] ?: 'test';
            $engine = Repo::engine([$rule]);
            $compiled = array_map(fn($x) => [
                'regex'  => substr($x[0], 1, strrpos($x[0], Engine::D) - 1),
                'groups' => $x[1],
            ], $engine->compile($rule));
            $res = $engine->run((string)($in['sample'] ?? ''), [
                'mode' => (string)($in['mode'] ?? 'auto'), 'propagate' => false, 'debug' => true,
            ]);
            out(['compiled' => $compiled, 'result' => $res]);

        case 'rules.bulk':
            require_admin();
            $ids = array_map('intval', (array)($in['ids'] ?? []));
            $op = (string)($in['op'] ?? '');
            foreach ($ids as $id) {
                match ($op) {
                    'enable'  => Repo::toggleRule($id, true),
                    'disable' => Repo::toggleRule($id, false),
                    default   => fail(t('Invalid bulk operation.')),
                };
            }
            out(['count' => count($ids)]);

        case 'category.save':
            require_admin();
            Repo::saveCategory((array)($in['category'] ?? []), !empty($in['is_new']));
            out(['categories' => Repo::categories()]);

        case 'category.delete':
            require_admin();
            Repo::deleteCategory((string)($in['code'] ?? ''));
            out(['categories' => Repo::categories()]);

        case 'settings.save':
            require_admin();
            $keys = Settings::saveFromInput((array)($in['settings'] ?? []));
            Repo::audit('settings.update', implode(', ', $keys));
            out(['settings' => array_intersect_key(Settings::all(), Settings::editable()), 'invalid' => Repo::validateAll()]);

        case 'settings.reset':
            require_admin();
            $key = (string)($in['key'] ?? '');
            $def = Seeds::settings();
            if (!array_key_exists($key, Settings::editable()) || !array_key_exists($key, $def)) {
                fail(t('This setting cannot be reset.'));
            }
            Settings::set([$key => $def[$key]]);
            Repo::audit('settings.reset', $key);
            out(['value' => $def[$key]]);

        case 'password.change':
            require_admin();
            Auth::changePassword((string)($in['current'] ?? ''), (string)($in['new'] ?? ''));
            out();

        case 'export':
            require_admin();
            Repo::audit('export', 'Configuration exported');
            out(Repo::export());

        case 'import':
            require_admin();
            out(Repo::import((array)($in['data'] ?? []), !empty($in['replace_custom'])));

        case 'reseed':
            require_admin();
            $r = Repo::seed(!empty($in['overwrite']));
            Repo::audit('reseed', (!empty($in['overwrite']) ? 'Built-ins reset' : 'Missing defaults added') . ' – ' . json_encode($r));
            out($r);

        case 'audit':
            require_admin();
            out(Repo::auditList((int)($in['limit'] ?? 300)));

        default:
            fail(t('Unknown action.'), 404);
    }
} catch (InvalidArgumentException $e) {
    fail($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('[maskink] ' . $e);
    fail(t('Server error:') . ' ' . $e->getMessage(), 500);
}
