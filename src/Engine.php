<?php
declare(strict_types=1);

/**
 * Maskink masking engine.
 *
 * Flow: rules are compiled → each rule runs on the text → candidates are filtered
 * (reclassification, keep lists, validators) → non-overlapping selection by
 * priority/length → propagation (a found value is also masked in the rest of
 * the text) → consistent token assignment → output + mapping table.
 *
 * All offsets are in bytes; the text is first converted to valid UTF-8.
 * Rule names and reasons are stored in English and translated only in the output.
 */
final class Engine
{
    public const D = "\x01";

    /** Value part of key=value rules (4 alternative groups) */
    private const KV_VALUE = <<<'RE'
(?:"((?:[^"\\\r\n]|\\.)*)"|'((?:[^'\\\r\n]|\\.)*)'|`([^`\r\n]*)`|(?![A-Za-z_][\w.\-]*=[^=\s])((?>[^\s,;"'`&<>()\[\]{}|]+))(?!\(|[ \t]+["'(\[{$\-]))
RE;

    private const SECRET_CATS = ['PASSWORD', 'SECRET'];

    private array $rules;
    private array $cats = [];
    private array $set;
    private array $keepValues = [];
    private array $keepCidrs = [];
    private array $keepDomains = [];
    private array $denyExt = [];
    private array $codeDeny = [];
    private array $fqdnDenyFirst = [];
    private array $stopwords = [];
    private array $tlds = [];
    private array $firstNames = [];
    private array $compiled = [];
    private array $termCats = [];
    private string $secret;

    private string $mode = 'log';
    private string $text = '';
    private array $wordCount = [];
    private array $offCats = [];
    private array $cands = [];
    private array $rejects = [];
    private bool $debug = false;
    private array $ruleHits = [];
    public array $errors = [];

    public function __construct(array $rules, array $cats, array $settings, string $secret = '')
    {
        $this->rules = $rules;
        foreach ($cats as $c) {
            $this->cats[$c['code']] = $c;
        }
        $this->set = $settings;
        $this->secret = $secret !== '' ? $secret : 'maskink';

        $lower = fn(array $a) => array_map([self::class, 'lower'], $a);
        $this->keepValues    = array_flip($lower(self::lines($settings['keep_values'] ?? '')));
        $this->keepCidrs     = self::lines($settings['keep_cidrs'] ?? '');
        $this->keepDomains   = $lower(self::lines($settings['keep_domains'] ?? ''));
        $this->denyExt       = array_flip($lower(self::lines($settings['dotted_deny'] ?? '')));
        $this->codeDeny      = array_flip($lower(self::lines($settings['code_deny'] ?? '')));
        $this->fqdnDenyFirst = array_flip($lower(self::lines($settings['fqdn_deny_first'] ?? '')));
        $this->stopwords     = array_flip($lower(self::lines($settings['person_stopwords'] ?? '')));
        $this->tlds          = $lower(self::lines($settings['tlds'] ?? ''));
        $this->firstNames    = $lower(self::lines($settings['first_names'] ?? ''));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Turns a newline (and optionally comma) separated list into an array; lines starting with # are comments */
    public static function lines(string $s, bool $commas = true): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $s) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            foreach ($commas ? explode(',', $line) : [$line] as $l) {
                $l = trim($l);
                if ($l !== '') {
                    $out[$l] = true;
                }
            }
        }
        return array_keys($out);
    }

    public static function lower(string $s): string
    {
        return mb_strtolower(str_replace('İ', 'i', $s));
    }

    /** Is $v among the keys of $set, case-insensitively? */
    private static function inSetCi(array $set, string $v): bool
    {
        $lv = self::lower($v);
        foreach ($set as $k => $_) {
            if (self::lower((string)$k) === $lv) {
                return true;
            }
        }
        return false;
    }

    public static function scrub(string $s): string
    {
        if (str_starts_with($s, "\xEF\xBB\xBF")) {
            $s = substr($s, 3);
        }
        return mb_scrub($s, 'UTF-8');
    }

    private static function wrap(string $pattern, string $flags): string
    {
        return self::D . $pattern . self::D . $flags;
    }

    /** Checks whether a regex compiles; returns the error message or null */
    public static function regexError(string $re): ?string
    {
        $msg = null;
        set_error_handler(function (int $no, string $str) use (&$msg) {
            $msg = preg_replace('/^preg_match\(\): /', '', $str);
            return true;
        });
        $ok = preg_match($re, '');
        restore_error_handler();
        if ($ok === false) {
            return $msg ?? preg_last_error_msg();
        }
        return null;
    }

    private static function cleanFlags(string $flags): string
    {
        $f = count_chars(preg_replace('/[^imsxU]/', '', $flags), 3);
        return $f . 'u';
    }

    // ------------------------------------------------------------------
    // Derleme
    // ------------------------------------------------------------------

    /** Expands template placeholders ({{TLD}}, {{FIRSTNAME}}, {{FIRSTNAME_CAP}}) */
    private function expand(string $p): string
    {
        if (str_contains($p, '{{TLD}}')) {
            $t = $this->tlds ?: ['com', 'net', 'org', 'local'];
            usort($t, fn($a, $b) => strlen($b) <=> strlen($a));
            $p = str_replace('{{TLD}}', implode('|', array_map(fn($x) => preg_quote($x, self::D), $t)), $p);
        }
        if (str_contains($p, '{{FIRSTNAME_CAP}}')) {
            $p = str_replace('{{FIRSTNAME_CAP}}', $this->nameAlt(true), $p);
        }
        if (str_contains($p, '{{FIRSTNAME}}')) {
            $p = str_replace('{{FIRSTNAME}}', $this->nameAlt(false), $p);
        }
        return $p;
    }

    private function nameAlt(bool $cap): string
    {
        $fold = ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'â' => 'a', 'î' => 'i', 'û' => 'u'];
        $set = [];
        foreach ($this->firstNames as $n) {
            if (mb_strlen($n) < 3) {
                continue;
            }
            $variants = [$n, strtr($n, $fold)];
            foreach ($variants as $v) {
                if (!$cap) {
                    $set[$v] = true;
                    continue;
                }
                $first = mb_substr($v, 0, 1);
                $rest = mb_substr($v, 1);
                $set[mb_strtoupper($first) . $rest] = true;
                if ($first === 'i') {
                    $set['İ' . $rest] = true;
                }
                $set[mb_strtoupper($v)] = true;
                $set[mb_strtoupper(strtr($v, ['i' => 'İ', 'ı' => 'I']))] = true;
            }
        }
        if (!$set) {
            return '(?!)';
        }
        $list = array_keys($set);
        usort($list, fn($a, $b) => strlen($b) <=> strlen($a));
        return implode('|', array_map(fn($x) => preg_quote($x, self::D), $list));
    }

    /**
     * Compiles a rule: [[regex, [groupNo => category]], ...]
     * @throws InvalidArgumentException on a compile error
     */
    public function compile(array $r): array
    {
        $cacheKey = (string)($r['id'] ?? '') . ':' . md5(json_encode($r));
        if (isset($this->compiled[$cacheKey])) {
            return $this->compiled[$cacheKey];
        }
        $o = $r['options'] ?? [];
        $flags = self::cleanFlags((string)($r['flags'] ?? ''));
        $cat = (string)$r['category'];
        $out = [];

        switch ($r['type']) {
            case 'regex':
                $pattern = trim((string)($r['pattern'] ?? ''));
                if ($pattern === '') {
                    throw new InvalidArgumentException(t('The regex pattern is empty.'));
                }
                if (str_contains($pattern, self::D)) {
                    throw new InvalidArgumentException(t('The pattern contains an invalid character.'));
                }
                $groups = $r['groups'] ?? [];
                $out[] = [self::wrap($this->expand($pattern), $flags), $groups ?: [0 => $cat]];
                break;

            case 'keyvalue':
                $out = $this->compileKv($r, $o);
                break;

            case 'keyword':
                $terms = self::lines((string)($r['keys_text'] ?? ''), false);
                $alts = [];
                $map = [];
                foreach ($terms as $line) {
                    $termCat = $cat;
                    if (preg_match('/^(.+?)\s*\|\s*([A-Z][A-Z0-9_]{1,31})\s*$/u', $line, $mm)) {
                        $line = $mm[1];
                        $termCat = $mm[2];
                    }
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $map[self::lower($line)] = $termCat;
                    $q = preg_quote($line, self::D);
                    $pre = preg_match('/^[\p{L}\p{N}_]/u', $line) ? '(?<![\p{L}\p{N}_])' : '';
                    $suf = preg_match('/[\p{L}\p{N}_]$/u', $line) ? '(?![\p{L}\p{N}_])' : '';
                    $alts[] = $pre . $q . $suf;
                }
                if ($alts) {
                    usort($alts, fn($a, $b) => strlen($b) <=> strlen($a));
                    $kwFlags = empty($o['case_sensitive']) ? 'iu' : 'u';
                    $out[] = [self::wrap('(?:' . implode('|', $alts) . ')', $kwFlags), [0 => $cat]];
                    $this->termCats[(string)($r['id'] ?? 'x')] = $map;
                }
                break;

            default:
                throw new InvalidArgumentException(t('Unknown rule type: {type}', ['type' => $r['type']]));
        }

        foreach ($out as [$re]) {
            $err = self::regexError($re);
            if ($err !== null) {
                throw new InvalidArgumentException('Regex derlenemedi: ' . $err);
            }
        }
        if (!empty($o['skip_regex']) && ($err = self::regexError(self::wrap((string)$o['skip_regex'], 'iu'))) !== null) {
            throw new InvalidArgumentException(t('The exclude regex could not be compiled: {error}', ['error' => $err]));
        }
        return $this->compiled[$cacheKey] = $out;
    }

    private function compileKv(array $r, array $o): array
    {
        $cat = (string)$r['category'];
        $alts = [];
        foreach (self::lines((string)($r['keys_text'] ?? '')) as $k) {
            $parts = preg_split('/[\s_.\-]+/u', self::lower($k), -1, PREG_SPLIT_NO_EMPTY);
            if ($parts) {
                $alts[] = implode('[ _.\-]?', array_map(fn($x) => preg_quote($x, self::D), $parts));
            }
        }
        if (!$alts) {
            return [];
        }
        usort($alts, fn($a, $b) => strlen($b) <=> strlen($a));
        $key = '(?:' . implode('|', $alts) . ')';

        $affix = $o['affix'] ?? 'none';
        $pre = '(?<![\p{L}\p{N}_/\\\\])';
        if ($affix === 'prefix' || $affix === 'both') {
            $pre .= '(?:[\p{L}\p{N}]+[_.\-])*';
        }
        $suf = $affix === 'both' ? '(?:[_.\-][\p{L}\p{N}]+)*' : '';
        $sep = match ($o['seps'] ?? 'std') {
            'eq'    => '[ \t]*=[ \t]*',
            'space' => '(?:[ \t]*(?:=>|:=|={1,3}|:(?!:))[ \t]*|[ \t]+)',
            default => '[ \t]*(?:=>|:=|={1,3}|:(?!:))[ \t]*',
        };

        if (!empty($o['value_regex'])) {
            $val = '["\'`]?(?![A-Za-z_][\w.\-]*=[^=\s])(' . $this->expand((string)$o['value_regex']) . ')';
            $groups = [1 => $cat];
        } else {
            $val = self::KV_VALUE;
            $groups = [1 => $cat, 2 => $cat, 3 => $cat, 4 => $cat];
        }

        $out = [];
        $out[] = [self::wrap($pre . $key . $suf . '(?![\p{L}\p{N}])["\']?\]?' . $sep . $val, 'iu'), $groups];

        if (!empty($o['xml'])) {
            $xmlVal = !empty($o['value_regex']) ? '(' . $this->expand((string)$o['value_regex']) . ')' : '([^<\r\n]{1,512}?)';
            $out[] = [self::wrap(
                '<(?:[\p{L}\p{N}]+[_.\-:])*' . $key . $suf . '(?![\p{L}\p{N}])(?:\s[^<>]*)?>[ \t]*' . $xmlVal . '[ \t]*</',
                'iu'
            ), [1 => $cat]];
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Running
    // ------------------------------------------------------------------

    public static function detectMode(string $text): string
    {
        $sample = substr($text, 0, 200000);
        $script = preg_match_all(
            '/^\s*(?:#!|function\s+[\w-]+|def\s+\w+\s*\(|class\s+\w+|param\s*\(|import\s+[\w.]+|from\s+[\w.]+\s+import|\$[\w:]+\s*=|[\w.]+\s*=\s*["\'\[{(]|if\s*\[|if\s*\(|for\s+\w+\s+in\s|foreach\s*\(|while\s|try\s*[:{]|except\b|catch\b|echo\s|Write-(?:Host|Output|Verbose)|(?:Get|Set|New|Remove|Invoke|Import|Export)-[A-Z]\w+|export\s+\w+=|local\s+\w+=|\#\s*requires|Set-StrictMode|<\?php|\}\s*$|fi\s*$|done\s*$)/mi',
            $sample
        );
        $log = preg_match_all(
            '/^\s*(?:<\d{1,3}>|\d{4}-\d\d-\d\d[T ]\d\d:\d\d|[A-Z][a-z]{2}\s+\d{1,2}\s+\d\d:\d\d:\d\d|\d\d[\/.]\d\d[\/.]\d{4}|\[\d{4}-\d\d-\d\d|\*?[A-Z][a-z]{2}\s+\d{1,2}\s|\d+:\s+\*?[A-Z][a-z]{2}|%[A-Z_]+-\d-|\d{10}(?:\.\d+)?\s)/m',
            $sample
        );
        return ($script > $log) ? 'script' : 'log';
    }

    /**
     * Main entry point.
     * $opt: mode (auto|log|script), disabled_cats (string[]), propagate (bool), debug (bool),
     *       known (previous mapping), hints ([category, value] to mask manually), allow (string[] to leave uncovered manually)
     */
    public function run(string $text, array $opt = []): array
    {
        $t0 = hrtime(true);
        $text = self::scrub($text);
        $detected = self::detectMode($text);
        $mode = $opt['mode'] ?? 'auto';
        $this->mode = in_array($mode, ['log', 'script'], true) ? $mode : $detected;
        $this->offCats = array_flip((array)($opt['disabled_cats'] ?? []));
        $this->debug = !empty($opt['debug']);
        $this->cands = $this->rejects = $this->ruleHits = $this->wordCount = [];
        $this->text = $text;

        foreach ($this->rules as $r) {
            if (empty($r['enabled'])) {
                continue;
            }
            $modes = $r['modes'] ?? 'all';
            if ($modes !== 'all' && $modes !== $this->mode) {
                continue;
            }
            try {
                $compiled = $this->compile($r);
            } catch (InvalidArgumentException $e) {
                $this->errors[] = ['rule_id' => $r['id'] ?? null, 'rule' => t($r['name']), 'error' => $e->getMessage()];
                continue;
            }
            foreach ($compiled as [$re, $gmap]) {
                $n = @preg_match_all($re, $text, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
                if ($n === false) {
                    $this->errors[] = ['rule_id' => $r['id'] ?? null, 'rule' => t($r['name']), 'error' => t('Run error:') . ' ' . preg_last_error_msg()];
                    continue;
                }
                foreach ($mm as $m) {
                    foreach ($gmap as $gi => $cat) {
                        if (!isset($m[$gi]) || $m[$gi][1] < 0 || $m[$gi][0] === '') {
                            continue;
                        }
                        $this->consider($r, (string)$cat, $m[$gi][0], $m[$gi][1], $m[0][0]);
                    }
                }
            }
        }

        // --- non-overlapping selection: priority > length > position
        usort($this->cands, fn($a, $b) => [$b['p'], $b['e'] - $b['s'], $a['s']] <=> [$a['p'], $a['e'] - $a['s'], $b['s']]);
        $occ = str_repeat("\0", strlen($text));
        $sel = [];
        // values the user said not to mask (e.g. text wrongly covered in a screenshot)
        $allow = [];
        foreach ((array)($opt['allow'] ?? []) as $a) {
            if (is_string($a) && $a !== '') {
                $allow[self::lower($a)] = true;
            }
        }
        foreach ($this->cands as $c) {
            if ($allow && isset($allow[self::lower($c['v'])])) {
                $this->reject($c, 'The user asked not to mask it');
                continue;
            }
            $l = $c['e'] - $c['s'];
            if (strspn($occ, "\0", $c['s'], $l) !== $l) {
                $this->reject($c, 'Overlaps a higher-priority match');
                continue;
            }
            for ($i = $c['s']; $i < $c['e']; $i++) {
                $occ[$i] = "\1";
            }
            $sel[] = $c;
            $this->ruleHits[$c['rn']] = ($this->ruleHits[$c['rn']] ?? 0) + 1;
        }

        // --- previously known mapping (chat): the same value gets the same token in every message
        $knownTok = [];
        $knownVal = [];
        foreach ((array)($opt['known'] ?? []) as $k) {
            $code = (string)($k['category'] ?? '');
            $tok = (string)($k['token'] ?? '');
            if ($code === '' || $tok === '' || !isset($this->cats[$code])) {
                continue;
            }
            $ci = empty($this->cats[$code]['case_sensitive']);
            foreach (array_merge([(string)($k['original'] ?? '')], array_map('strval', (array)($k['variants'] ?? []))) as $v) {
                if ($v === '' || mb_strlen($v) < 2) {
                    continue;
                }
                $knownTok[$code . "\0" . ($ci ? self::lower($v) : $v)] = $tok;
                $knownVal[$code][$v] = true;
                // the first label of an FQDN from an earlier message may appear on its own in this one
                if ($code === 'FQDN' && !empty($this->cats['HOST']['propagate'])) {
                    $label = (string)strtok($v, '.');
                    $ll = self::lower($label);
                    if (mb_strlen($label) >= max(1, (int)($this->set['propagate_min_len'] ?? 4)) && !in_array($ll, $this->tlds, true)
                        && !isset($this->codeDeny[$ll]) && !isset($this->keepValues[$ll]) && !ctype_digit($label)) {
                        $knownVal['HOST'][$label] = true;
                    }
                }
            }
        }

        // --- values the user marked manually: masked with this category everywhere in the text
        $hintVal = [];
        foreach ((array)($opt['hints'] ?? []) as $h) {
            $code = (string)($h['category'] ?? '');
            $v = trim((string)($h['value'] ?? ''));
            if (isset($this->cats[$code]) && mb_strlen($v) >= 2 && !isset($allow[self::lower($v)])) {
                $hintVal[$code][$v] = true;
                $knownVal[$code][$v] = true;
            }
        }

        // --- propagation
        $propOn = ($opt['propagate'] ?? true) && !empty($this->set['propagate']);
        $byCat = $knownVal;
        if ($propOn) {
            $minLen = max(1, (int)($this->set['propagate_min_len'] ?? 4));
            foreach ($sel as $c) {
                $cat = $this->cats[$c['c']] ?? null;
                if (!$cat || empty($cat['propagate']) || !$c['prop'] || mb_strlen($c['v']) < $minLen) {
                    continue;
                }
                $byCat[$c['c']][$c['v']] = true;
                // the first label of an FQDN (db01.corp.local → db01) is masked as HOST when it appears alone
                if ($c['c'] === 'FQDN' && !empty($this->cats['HOST']['propagate'])) {
                    $label = strtok($c['v'], '.');
                    $ll = self::lower($label);
                    if (mb_strlen($label) >= $minLen && !in_array($ll, $this->tlds, true)
                        && !isset($this->codeDeny[$ll]) && !isset($this->keepValues[$ll]) && !ctype_digit($label)) {
                        $byCat['HOST'][$label] = true;
                    }
                }
            }
        }
        if ($byCat) {
            foreach ($byCat as $code => $vals) {
                $ci = empty($this->cats[$code]['case_sensitive']);
                $vals = array_keys($vals);
                usort($vals, fn($a, $b) => strlen((string)$b) <=> strlen((string)$a));
                foreach (array_chunk($vals, 250) as $chunk) {
                    $alts = [];
                    foreach ($chunk as $v) {
                        $v = (string)$v;
                        $alts[] = (preg_match('/^[\p{L}\p{N}_]/u', $v) ? '(?<![\p{L}\p{N}_])' : '')
                            . preg_quote($v, self::D)
                            . (preg_match('/[\p{L}\p{N}_]$/u', $v) ? '(?![\p{L}\p{N}_])' : '');
                    }
                    $re = self::wrap('(?:' . implode('|', $alts) . ')', $ci ? 'iu' : 'u');
                    if (!@preg_match_all($re, $text, $mm, PREG_OFFSET_CAPTURE)) {
                        continue;
                    }
                    foreach ($mm[0] as [$v, $s]) {
                        $l = strlen($v);
                        if (strspn($occ, "\0", $s, $l) !== $l || ($allow && isset($allow[self::lower($v)]))) {
                            continue;
                        }
                        // matches inside a path / URL segment (/backup/, \Temp\) are not masked by propagation
                        if ($s > 0 && ($text[$s - 1] === '/' || $text[$s - 1] === '\\')) {
                            continue;
                        }
                        for ($i = $s; $i < $s + $l; $i++) {
                            $occ[$i] = "\1";
                        }
                        $rn = isset($knownTok[$code . "\0" . ($ci ? self::lower($v) : $v)]) ? 'Chat mapping (earlier message)'
                            : (self::inSetCi($hintVal[$code] ?? [], $v) ? 'Marked manually' : 'Propagation (repeated value)');
                        $sel[] = ['s' => $s, 'e' => $s + $l, 'v' => $v, 'c' => $code, 'r' => 0, 'rn' => $rn, 'p' => 0, 'prop' => false];
                        $this->ruleHits[$rn] = ($this->ruleHits[$rn] ?? 0) + 1;
                    }
                }
            }
        }

        // --- token assignment and output
        usort($sel, fn($a, $b) => $a['s'] <=> $b['s']);
        $tokens = [];
        $counter = [];
        $items = [];
        $usedTok = array_fill_keys(array_values($knownTok), true);
        foreach ($sel as &$c) {
            $cat = $this->cats[$c['c']] ?? ['code' => $c['c'], 'token' => $c['c'], 'label' => $c['c'], 'case_sensitive' => 0, 'placeholder' => null];
            $key = $c['c'] . "\0" . (empty($cat['case_sensitive']) ? self::lower($c['v']) : $c['v']);
            if (!isset($tokens[$key]) && isset($knownTok[$key])) {
                $tokens[$key] = $knownTok[$key];
                $items[$key] = [
                    'token'    => $knownTok[$key],
                    'category' => $c['c'],
                    'label'    => t((string)($cat['label'] ?? $c['c'])),
                    'original' => $c['v'],
                    'variants' => [],
                    'count'    => 0,
                    'rules'    => [],
                ];
            }
            if (!isset($tokens[$key])) {
                $tpl = (string)(($cat['placeholder'] ?? '') !== '' ? $cat['placeholder'] : ($this->set['placeholder_template'] ?? '<{CAT}_{n}>'));
                $guard = 0;
                do {
                    $n = ($counter[$c['c']] ?? 0) + 1;
                    $counter[$c['c']] = $n;
                    $tok = $this->token($tpl, $cat, $n, $c['v']);
                    $clash = str_contains($tpl, '{n}') && (isset($usedTok[$tok]) || str_contains($text, $tok));
                } while ($clash && ++$guard < 10000);
                $usedTok[$tok] = true;
                $tokens[$key] = $tok;
                $items[$key] = [
                    'token'    => $tok,
                    'category' => $c['c'],
                    'label'    => t((string)($cat['label'] ?? $c['c'])),
                    'original' => $c['v'],
                    'variants' => [],
                    'count'    => 0,
                    'rules'    => [],
                ];
            }
            $c['tok'] = $tokens[$key];
            $it = &$items[$key];
            $it['count']++;
            $it['variants'][$c['v']] = true;
            $it['rules'][$c['rn']] = true;
            unset($it);
        }
        unset($c);

        $out = '';
        $segs = [];
        $pos = 0;
        $line = 1;
        $maskedLines = [];
        foreach ($sel as $c) {
            if ($c['s'] > $pos) {
                $plain = substr($text, $pos, $c['s'] - $pos);
                $out .= $plain;
                $segs[] = ['t' => $plain];
                $line += substr_count($plain, "\n");
            }
            $out .= $c['tok'];
            $segs[] = ['m' => $c['tok'], 'o' => $c['v'], 'c' => $c['c'], 'r' => t($c['rn'])];
            $maskedLines[$line] = true;
            $line += substr_count($c['v'], "\n");
            $pos = $c['e'];
        }
        if ($pos < strlen($text)) {
            $segs[] = ['t' => substr($text, $pos)];
            $out .= substr($text, $pos);
        }

        // --- remaining risk scan (suspicious keyword on unmasked lines)
        $warnings = [];
        $risk = trim((string)($this->set['risk_keywords'] ?? ''));
        if ($risk !== '') {
            $reRisk = self::wrap($risk, 'iu');
            foreach (preg_split('/\n/', $out) as $i => $ln) {
                if (count($warnings) >= 50) {
                    break;
                }
                if (!isset($maskedLines[$i + 1]) && @preg_match($reRisk, $ln, $km)) {
                    $warnings[] = ['line' => $i + 1, 'keyword' => $km[0], 'text' => mb_substr(trim($ln), 0, 220)];
                }
            }
        }

        $byCat = [];
        foreach ($items as $it) {
            $b = &$byCat[$it['category']];
            $b['unique'] = ($b['unique'] ?? 0) + 1;
            $b['count'] = ($b['count'] ?? 0) + $it['count'];
            unset($b);
        }
        $items = array_map(function ($it) {
            $it['variants'] = array_keys($it['variants']);
            $it['rules'] = array_map('t', array_map('strval', array_keys($it['rules'])));
            return $it;
        }, array_values($items));
        arsort($this->ruleHits);

        $res = [
            'masked'   => $out,
            'segments' => $segs,
            'items'    => $items,
            'warnings' => $warnings,
            'errors'   => $this->errors,
            'stats'    => [
                'mode'       => $this->mode,
                'detected'   => $detected,
                'ms'         => round((hrtime(true) - $t0) / 1e6, 1),
                'chars_in'   => mb_strlen($text),
                'chars_out'  => mb_strlen($out),
                'lines'      => substr_count($text, "\n") + 1,
                'matches'    => count($sel),
                'unique'     => count($items),
                'by_category' => $byCat,
                'by_rule'    => $this->ruleHits,
                'propagated' => $propOn,
            ],
        ];
        if ($this->debug) {
            $res['rejects'] = array_slice($this->rejects, 0, 500);
            $res['candidates'] = count($this->cands);
        }
        return $res;
    }

    private function token(string $tpl, array $cat, int $n, string $v): string
    {
        $prefix = (string)($cat['token'] ?? $cat['code']);
        return strtr($tpl, [
            '{CAT}'   => $prefix,
            '{cat}'   => strtolower($prefix),
            '{n}'     => (string)$n,
            '{len}'   => (string)mb_strlen($v),
            '{hash}'  => substr(hash_hmac('sha256', self::lower($v), $this->secret), 0, 8),
            '{stars}' => str_repeat('*', max(3, min(32, mb_strlen($v)))),
        ]);
    }

    private function reject(array $c, string $why): void
    {
        if ($this->debug) {
            $this->rejects[] = ['value' => $c['v'], 'category' => $c['c'], 'rule' => t($c['rn']), 'offset' => $c['s'], 'reason' => t($why)];
        }
    }

    /** Evaluates a capture group as a candidate */
    private function consider(array $r, string $cat, string $val, int $off, string $whole): void
    {
        $o = $r['options'] ?? [];
        $base = ['r' => $r['id'] ?? 0, 'rn' => $r['name'], 'p' => (int)$r['priority'], 'prop' => ($o['propagate'] ?? true) !== false];

        if ($r['type'] === 'keyword') {
            $cat = $this->termCats[(string)($r['id'] ?? 'x')][self::lower($val)] ?? $cat;
        }
        if (!in_array($cat, self::SECRET_CATS, true)) {
            [$val, $off] = self::trimValue($val, $off);
        }
        if ($val === '') {
            return;
        }

        $list = (!empty($o['reclassify']) || !empty($o['split_domain']))
            ? $this->reclassify($cat, $val, $off, $o)
            : [[$cat, $val, $off]];

        $prev = $off > 0 ? $this->text[$off - 1] : '';
        $quoted = $prev === '"' || $prev === "'" || $prev === '`';
        foreach ($list as [$c, $v, $s]) {
            $cand = $base + ['s' => $s, 'e' => $s + strlen($v), 'v' => $v, 'c' => $c];
            $why = $this->why($r, $o, $c, $v, $whole, $quoted);
            if ($why !== null) {
                $this->reject($cand, $why);
                continue;
            }
            $this->cands[] = $cand;
        }
    }

    private static function trimValue(string $v, int $off): array
    {
        $lead = strspn($v, "\"'`([<{ \t");
        if ($lead > 0) {
            $v = substr($v, $lead);
            $off += $lead;
        }
        return [rtrim($v, ".,:;!?\"'`)]>} \t"), $off];
    }

    /** Corrects / splits the category based on the shape of the value */
    private function reclassify(string $cat, string $v, int $off, array $o): array
    {
        if (self::isIPv4($v)) {
            return [['IP', $v, $off]];
        }
        if (str_contains($v, ':') && filter_var(trim($v, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return [['IP6', $v, $off]];
        }
        if (preg_match('/^(?:[0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$|^[0-9A-Fa-f]{4}\.[0-9A-Fa-f]{4}\.[0-9A-Fa-f]{4}$/', $v)) {
            return [['MAC', $v, $off]];
        }
        if (preg_match('/^S-1-\d+(?:-\d+)+$/', $v)) {
            return preg_match('/^S-1-(?:5-21|12-1)(?:-\d+){3,4}$/', $v) ? [['SID', $v, $off]] : [];
        }
        if (in_array($cat, ['USER', 'HOST', 'DOMAIN', 'PERSON'], true)
            && preg_match('/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/', $v)) {
            return []; // e-mail / UPN; the e-mail rule catches it separately
        }
        if ((!empty($o['split_domain']) || $cat === 'USER') && preg_match('/^([^\\\\\s]+)\\\\{1,2}([^\\\\\s]+)$/', $v, $m)) {
            $uOff = $off + strlen($v) - strlen($m[2]);
            $dCat = str_contains($m[1], '.') ? 'FQDN' : 'DOMAIN';
            return [[$dCat, $m[1], $off], ...$this->reclassify('USER', $m[2], $uOff, [])] ?: [[$dCat, $m[1], $off]];
        }
        if ($cat === 'USER' && preg_match('/^([A-Za-z0-9][\w.\-]*)\$$/', $v, $m)) {
            return [['HOST', $m[1], $off]]; // machine account (PC01$)
        }
        if ($cat === 'USER' && preg_match('/^\p{L}[\p{L}\'’\-]*(?:[ \t]+\p{L}[\p{L}\'’\-]*)+$/u', $v)) {
            return [['PERSON', $v, $off]]; // display name such as "Ayşe Kaya"
        }
        if (in_array($cat, ['HOST', 'DOMAIN'], true) && preg_match('/^[A-Za-z0-9\-_]+(?:\.[A-Za-z0-9\-_]+)+$/', $v)
            && preg_match('/[A-Za-z]/', substr(strrchr($v, '.'), 1))) {
            return [['FQDN', $v, $off]];
        }
        return [[$cat, $v, $off]];
    }

    /** Why a candidate is rejected (null = accepted) */
    private function why(array $r, array $o, string $cat, string $v, string $whole, bool $quoted = false): ?string
    {
        $catRow = $this->cats[$cat] ?? null;
        if ($catRow !== null && empty($catRow['enabled'])) {
            return t('Category disabled ({cat})', ['cat' => $cat]);
        }
        if (isset($this->offCats[$cat])) {
            return t('Category disabled for this run ({cat})', ['cat' => $cat]);
        }
        $lv = self::lower($v);
        if (isset($this->keepValues[$lv])) {
            return 'Tutma listesinde (keep_values)';
        }
        if (($o['skip_vars'] ?? true) && self::isVariableRef($v)) {
            return t('Variable / placeholder reference');
        }
        if (!empty($o['skip_numeric']) && ctype_digit($v)) {
            return t('Numbers only');
        }
        $len = mb_strlen($v);
        if (!empty($o['min_len']) && $len < (int)$o['min_len']) {
            return t('Below the minimum length ({n})', ['n' => (int)$o['min_len']]);
        }
        if (!empty($o['max_len']) && $len > (int)$o['max_len']) {
            return t('Above the maximum length ({n})', ['n' => (int)$o['max_len']]);
        }
        if (!empty($o['skip_regex']) && @preg_match(self::wrap((string)$o['skip_regex'], 'iu'), $v)) {
            return t('Matched the exclude regex');
        }
        if ($cat === 'PASSWORD' && preg_match('/^\d{1,3}$/', $v)) {
            return t('Short numeric value (may be a setting value)');
        }
        if ($this->mode === 'script' && !$quoted && $r['type'] === 'keyvalue') {
            if (preg_match('/^[A-Za-z_]\w*(?:\.[A-Za-z_]\w*)+$/', $v)) {
                $last = self::lower(substr(strrchr($v, '.'), 1));
                if (!in_array($last, $this->tlds, true)) {
                    return t('Code reference (object.property)');
                }
            }
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $v) && $this->wordOccurrences($v) >= 2) {
                return t('Script variable (also appears elsewhere in the text)');
            }
        }
        if ($this->mode === 'script' && $cat === 'FQDN' && substr_count($v, '.') === 1
            && isset($this->codeDeny[self::lower(strtok($v, '.'))])) {
            return t('Code reference ({v})', ['v' => $v]);
        }

        // category-specific validation
        switch ($cat) {
            case 'IP':
                if (!self::isIPv4($v)) {
                    return t('Invalid IPv4');
                }
                if (!empty($this->set['keep_netmasks']) && self::isNetmask($v)) {
                    return t('Subnet mask / wildcard');
                }
                foreach ($this->keepCidrs as $cidr) {
                    if (self::inCidr($v, $cidr)) {
                        return t('In a kept IP range ({cidr})', ['cidr' => $cidr]);
                    }
                }
                break;
            case 'IP6':
                if (!filter_var(trim($v, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    return t('Invalid IPv6');
                }
                break;
            case 'FQDN':
            case 'EMAIL':
                $dom = $cat === 'EMAIL' ? substr(strrchr($lv, '@') ?: '', 1) : $lv;
                foreach ($this->keepDomains as $kd) {
                    if ($dom === $kd || str_ends_with($dom, '.' . $kd)) {
                        return t('Kept domain ({d})', ['d' => $kd]);
                    }
                }
                if ($cat === 'FQDN') {
                    $first = strtok($lv, '.');
                    if (isset($this->fqdnDenyFirst[$first])) {
                        return t('Code namespace ({ns}.)', ['ns' => $first]);
                    }
                    if (preg_match('/^[\d.]+$/', $lv)) {
                        return t('Numeric');
                    }
                }
                break;
        }

        switch ($o['validate'] ?? '') {
            case 'luhn':
                if (!self::luhn($v)) {
                    return t('Luhn check failed');
                }
                break;
            case 'tckn':
                if (!self::tckn($v)) {
                    return t('TCKN checksum does not match');
                }
                break;
            case 'iban':
                if (!self::iban($v)) {
                    return t('IBAN mod-97 check failed');
                }
                break;
            case 'phone':
                $d = strlen(preg_replace('/\D/', '', $v));
                if ($d < 10 || $d > 13) {
                    return t('Wrong number of phone digits');
                }
                break;
            case 'entropy':
                $min = (float)($o['entropy_min'] ?? 4.0);
                $e = self::entropy($v);
                if ($e < $min) {
                    return t('Low entropy ({e} < {min})', ['e' => sprintf('%.2f', $e), 'min' => sprintf('%.2f', $min)]);
                }
                break;
            case 'dotted':
                $parts = explode('.', $lv);
                foreach ($parts as $i => $p) {
                    if (isset($this->denyExt[$p]) || isset($this->codeDeny[$p])) {
                        return t('File extension / code token ({p})', ['p' => $p]);
                    }
                }
                if (in_array(end($parts), $this->tlds, true)) {
                    return t('Looks like a domain name');
                }
                break;
            case 'person':
                $words = preg_split('/[ \t]+/u', $lv);
                array_shift($words);
                foreach ($words as $w) {
                    if (isset($this->stopwords[$w])) {
                        return t('The second word is a common word ({w})', ['w' => $w]);
                    }
                }
                break;
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Validators
    // ------------------------------------------------------------------

    private function wordOccurrences(string $w): int
    {
        return $this->wordCount[$w] ??= (int)preg_match_all(
            self::wrap('(?<![\p{L}\p{N}_$])' . preg_quote($w, self::D) . '(?![\p{L}\p{N}_])', 'u'),
            $this->text
        );
    }

    public static function isVariableRef(string $v): bool
    {
        return (bool)preg_match(
            '/^(?:\$\{[^}]*\}|\$\([^)]*\)|\$[A-Za-z_][\w:.]*|%[A-Za-z_]\w*%|\{\{[^}]*\}\}|<[^<>]*>|\[[^\[\]]*\]|@\w+|:\w+|\?|\*{2,}|x{3,}|X{3,}|#{3,}|•+|\.{3,}|-{2,})$/u',
            $v
        );
    }

    public static function isIPv4(string $v): bool
    {
        return filter_var($v, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    public static function isNetmask(string $ip): bool
    {
        $first = (int)$ip;
        if ($first !== 255 && $first !== 0) {
            return false;
        }
        $bin = '';
        foreach (explode('.', $ip) as $o) {
            $bin .= str_pad(decbin((int)$o), 8, '0', STR_PAD_LEFT);
        }
        return (bool)preg_match('/^(?:1*0*|0*1*)$/', $bin);
    }

    public static function inCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$net, $bits] = explode('/', $cidr, 2);
        $bits = (int)$bits;
        $ipL = ip2long($ip);
        $netL = ip2long($net);
        if ($ipL === false || $netL === false || $bits < 0 || $bits > 32) {
            return false;
        }
        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits)) & 0xFFFFFFFF;
        return ($ipL & $mask) === ($netL & $mask);
    }

    public static function luhn(string $v): bool
    {
        $d = preg_replace('/\D/', '', $v);
        $n = strlen($d);
        if ($n < 13 || $n > 19 || preg_match('/^(\d)\1+$/', $d)) {
            return false;
        }
        if (!preg_match('/^(?:4|5[1-5]|2[2-7]|3[47]|6(?:011|5)|9792|35)/', $d)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < $n; $i++) {
            $x = (int)$d[$n - 1 - $i];
            if ($i % 2 === 1) {
                $x *= 2;
                if ($x > 9) {
                    $x -= 9;
                }
            }
            $sum += $x;
        }
        return $sum % 10 === 0;
    }

    public static function tckn(string $v): bool
    {
        if (!preg_match('/^[1-9]\d{10}$/', $v)) {
            return false;
        }
        $d = array_map('intval', str_split($v));
        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];
        if (((($odd * 7) - $even) % 10 + 10) % 10 !== $d[9]) {
            return false;
        }
        return array_sum(array_slice($d, 0, 10)) % 10 === $d[10];
    }

    public static function iban(string $v): bool
    {
        $s = strtoupper(preg_replace('/\s+/', '', $v));
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $s)) {
            return false;
        }
        $s = substr($s, 4) . substr($s, 0, 4);
        $mod = 0;
        foreach (str_split($s) as $ch) {
            $num = ctype_alpha($ch) ? (string)(ord($ch) - 55) : $ch;
            foreach (str_split($num) as $dg) {
                $mod = ($mod * 10 + (int)$dg) % 97;
            }
        }
        return $mod === 1;
    }

    public static function entropy(string $v): float
    {
        $len = strlen($v);
        if ($len === 0) {
            return 0.0;
        }
        $h = 0.0;
        foreach (count_chars($v, 1) as $cnt) {
            $p = $cnt / $len;
            $h -= $p * log($p, 2);
        }
        return $h;
    }
}
