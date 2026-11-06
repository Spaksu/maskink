<?php
// Builds the static GitHub Pages demo into _site/ (php demo/build.php [outdir]).
// The real pages are rendered from a temporary copy of the app in which the database and
// sign-in are replaced by stand-ins, so the demo always shows the current interface.
declare(strict_types=1);
$root = dirname(__DIR__);
$out = $argv[1] ?? "$root/_site";
$tmp = sys_get_temp_dir() . '/maskink-demo-' . getmypid();
$php = PHP_BINARY;

function rrmdir(string $d): void
{
    if (!is_dir($d)) { return; }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($d);
}
function rcopy(string $from, string $to): void
{
    @mkdir($to, 0777, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $f) {
        $dest = $to . '/' . substr($f->getPathname(), strlen($from) + 1);
        $f->isDir() ? @mkdir($dest, 0777, true) : copy($f->getPathname(), $dest);
    }
}

rrmdir($out);
rrmdir($tmp);
mkdir($tmp, 0777, true);
foreach (['src', 'partials', 'guides', 'lang'] as $d) { rcopy("$root/$d", "$tmp/$d"); }
foreach (['index', 'chat', 'settings', 'guide', 'admin-guide'] as $p) { copy("$root/$p.php", "$tmp/$p.php"); }
mkdir("$tmp/assets");
rcopy("$root/assets/css", "$tmp/assets/css");
rcopy("$root/assets/js", "$tmp/assets/js");

file_put_contents("$tmp/config.php", "<?php return ['db' => []];\n");
file_put_contents("$tmp/src/Db.php", <<<'PHP'
<?php
final class DemoStatement { public function fetchColumn() { return MK_SCHEMA_VERSION; } }
final class DemoPdo { public function query(string $q) { return new DemoStatement(); } public function exec(string $q) { return 0; } }
final class Db
{
    public static function pdo() { return new DemoPdo(); }
    public static function connect(array $c, bool $db = true) { return new DemoPdo(); }
    public static function exec(string $q, array $p = []): int { return 0; }
    public static function one(string $q, array $p = []): ?array { return null; }
    public static function all(string $q, array $p = []): array
    {
        if (str_contains($q, 'mk_categories')) {
            $c = Seeds::categories();
            usort($c, fn($a, $b) => [$a['sort'], $a['code']] <=> [$b['sort'], $b['code']]);
            return $c;
        }
        return [];
    }
}
PHP);
file_put_contents("$tmp/src/Auth.php", <<<'PHP'
<?php
final class Auth
{
    public static function check(): bool { return true; }
    public static function csrf(): string { return 'demo'; }
}
PHP);
$prepend = "$tmp/prepend.php";
file_put_contents($prepend, "<?php \$_COOKIE['mki_lang'] = getenv('MK_LANG'); ob_start();\n");

$pages = ['index', 'chat', 'settings', 'guide', 'admin-guide'];
$scripts = '<script src="assets/demo/seed.js"></script><script src="assets/demo/pcre.js"></script><script src="assets/demo/engine.js"></script><script src="assets/demo/runtime.js"></script>' . "\n";
mkdir($out, 0777, true);
foreach (['en' => '', 'tr' => '.tr'] as $lang => $suffix) {
    foreach ($pages as $p) {
        $cmd = 'MK_LANG=' . $lang . ' ' . escapeshellarg($php) . ' -d display_errors=stderr -d auto_prepend_file=' . escapeshellarg($prepend)
            . ' -d session.save_path=' . escapeshellarg($tmp) . ' ' . escapeshellarg("$tmp/$p.php");
        $code = 0;
        $o = [];
        exec($cmd, $o, $code);
        $html = implode("\n", $o);
        if ($code !== 0 || !str_contains($html, '</html>')) {
            fwrite(STDERR, "render failed: $p ($lang)\n" . substr($html, 0, 400) . "\n");
            exit(1);
        }
        $html = preg_replace_callback('/href="(index|chat|settings|guide|admin-guide)\.php(#[^"]*)?"/', fn($m) => 'href="' . $m[1] . $suffix . '.html' . ($m[2] ?? '') . '"', $html);
        if (substr_count($html, '<script src="assets/js/common.js') !== 1) {
            fwrite(STDERR, "no common.js in $p\n");
            exit(1);
        }
        $html = str_replace('<script src="assets/js/common.js', $scripts . '<script src="assets/js/common.js', $html);
        $html = preg_replace('/\?v=\d+/', '?v=' . substr(sha1((string)time()), 0, 8), $html);
        $html = str_replace('<title>', '<meta name="description" content="Try Maskink in your browser: redact logs, scripts and chat messages before they reach an AI. Runs entirely on this page."><title>', $html);
        file_put_contents("$out/$p$suffix.html", $html);
    }
}
rrmdir($tmp);

rcopy("$root/assets", "$out/assets");
mkdir("$out/assets/demo", 0777, true);
foreach (['pcre.js', 'engine.js', 'runtime.js'] as $f) { copy(__DIR__ . "/src/$f", "$out/assets/demo/$f"); }
file_put_contents("$out/.nojekyll", '');
file_put_contents("$out/robots.txt", "User-agent: *\nAllow: /\n");

// data the browser starts from: generated from Seeds.php, never copied by hand
$code = <<<'PHP'
mb_internal_encoding('UTF-8');
function t($s, $a = []) { return $s; }
require $argv[1] . '/src/Seeds.php';
require $argv[1] . '/src/Db.php';
require $argv[1] . '/src/Settings.php';
$rules = [];
foreach (Seeds::rules() as $i => $r) {
    ksort($r['groups']);
    $r['id'] = $i + 1;
    $r['groups'] = (object)$r['groups'];
    $r['options'] = (object)$r['options'];
    $r['updated_at'] = '';
    $rules[] = $r;
}
$cats = Seeds::categories();
$settings = Seeds::settings();
unset($settings['admin_hash']);
echo json_encode(['version' => $argv[2], 'rules' => $rules, 'categories' => $cats, 'settings' => $settings, 'editable' => Settings::editable()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
PHP;
$boot = (string)file_get_contents("$root/src/bootstrap.php");
preg_match("/define\\('MK_VERSION', '([^']+)'\\)/", $boot, $mv);
$script = sys_get_temp_dir() . '/maskink-seed-' . getmypid() . '.php';
file_put_contents($script, "<?php\n" . $code);
$json = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($root) . ' ' . escapeshellarg($mv[1]));
unlink($script);
json_decode((string)$json, false, 512, JSON_THROW_ON_ERROR);
file_put_contents("$out/assets/demo/seed.js", 'window.MK_DEMO_SEED=' . $json . ";\n");

$size = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS)) as $f) { $size += $f->getSize(); }
printf("built %s (%.1f MB)\n", $out, $size / 1e6);
