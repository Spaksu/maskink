<?php
// Runs src/Engine.php over a deterministic corpus and writes the expected results (JSONL) for parity.mjs.
declare(strict_types=1);
mb_internal_encoding('UTF-8');
ini_set('memory_limit', '4G');
ini_set('pcre.backtrack_limit', '10000000');
ini_set('pcre.recursion_limit', '1000000');
function t(string $s, array $a = []): string { foreach ($a as $k => $v) { $s = str_replace('{' . $k . '}', (string)$v, $s); } return $s; }
$root = dirname(__DIR__, 2);
require "$root/src/Seeds.php";
require "$root/src/Engine.php";
$out = $argv[1] ?? __DIR__ . '/.cache/fixtures.jsonl';
$scale = (int)($argv[2] ?? 1);
@mkdir(dirname($out), 0777, true);
$fh = fopen($out, 'w');
$J = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$emit = function (array $row) use ($fh, $J) { fwrite($fh, json_encode($row, $J) . "\n"); };

// same order the app loads them in (Repo::rules / Repo::categories)
$rules = Seeds::rules();
foreach ($rules as $i => &$r) { $r['id'] = $i + 1; ksort($r['groups']); }
unset($r);
usort($rules, fn($a, $b) => [$b['priority'], $a['sort'], $a['id']] <=> [$a['priority'], $b['sort'], $b['id']]);
$cats = Seeds::categories();
foreach ($cats as &$c) { $c['builtin'] = 1; }
unset($c);
usort($cats, fn($a, $b) => [$a['sort'], $a['code']] <=> [$b['sort'], $b['code']]);
$settings = Seeds::settings();
$emit(['kind' => 'base', 'rules' => $rules, 'categories' => $cats, 'settings' => $settings]);

$frag = require __DIR__ . '/fragments.php';
$docs = array_values(Seeds::samples());
$docs[] = implode("\n", $frag);
foreach ($frag as $f) { $docs[] = $f; }
foreach (['README.md', 'Rules.md'] as $f) { $docs[] = file_get_contents("$root/$f"); }
$handDocs = count($docs);

// randomized documents: same seed, same corpus
mt_srand(20261106);
$noise = ["\r\n", "\n\n", ' ', "\t", "\u{00A0}", "\u{2028}", "\u{0085}", "\u{200B}", "\u{FEFF}", 'İ', 'ı', 'ß', 'ſ', 'K', "e\u{0301}", '٣', '🔑', '"', "'", '`', '(', ')', '[', ']', '<', '>', '=', ':', ',', ';', '/', '\\', '.', '-', '_', '$', '%', '#', '@'];
$rnd = fn(array $a) => $a[mt_rand(0, count($a) - 1)];
$mutate = function (string $s) use ($noise, $rnd): string {
    $chars = mb_str_split($s);
    $n = mt_rand(0, 6);
    for ($i = 0; $i < $n && $chars; $i++) {
        $p = mt_rand(0, count($chars) - 1);
        switch (mt_rand(0, 3)) {
            case 0: array_splice($chars, $p, 0, [$rnd($noise)]); break;
            case 1: array_splice($chars, $p, 1); break;
            case 2: $chars[$p] = mb_strtoupper($chars[$p]); break;
            case 3: $chars[$p] = $rnd($noise); break;
        }
    }
    return implode('', $chars);
};
$nFuzz = 1500 * $scale;
$fuzz = [];
for ($i = 0; $i < $nFuzz; $i++) {
    $lines = [];
    for ($j = mt_rand(1, 8); $j > 0; $j--) { $lines[] = $mutate($rnd($frag)); }
    $fuzz[] = implode($rnd(["\n", "\n", "\r\n", "\n\n", ' ']), $lines);
}

$strip = function (array $res) { unset($res['stats']['ms']); return $res; };
$run = function (string $name, array $rs, array $st, array $cs, string $text, array $opt) use ($emit, $strip) {
    $e = new Engine($rs, $cs, $st, 'parity-secret');
    $res = $e->run($text, $opt + ['debug' => true]);
    $emit(['kind' => 'run', 'name' => $name, 'text' => $text, 'opt' => $opt + ['debug' => true], 'result' => $strip($res)]);
};
// the JS side rebuilds rules/settings from these descriptors so the file stays small
$runV = function (string $name, array $variant, string $text, array $opt) use ($emit, $strip, $rules, $cats, $settings) {
    $rs = $rules;
    $cs = $cats;
    $st = array_merge($settings, $variant['settings'] ?? []);
    if (!empty($variant['all_rules'])) { foreach ($rs as &$r) { $r['enabled'] = 1; } unset($r); }
    if (isset($variant['only'])) { $rs = array_values(array_filter($rs, fn($r) => $r['id'] === $variant['only'])); foreach ($rs as &$r) { $r['enabled'] = 1; } unset($r); }
    if (isset($variant['extra_rules'])) { $rs = array_merge($variant['extra_rules'], $rs); }
    foreach ($variant['cats'] ?? [] as $code => $patch) { foreach ($cs as &$c) { if ($c['code'] === $code) { $c = array_merge($c, $patch); } } unset($c); }
    static $cache = [];
    $key = md5(json_encode([$variant]));
    $e = $cache[$key] ??= new Engine($rs, $cs, $st, 'parity-secret');
    if (count($cache) > 64) { $cache = []; }
    $e->errors = [];
    $res = $e->run($text, $opt + ['debug' => true]);
    $emit(['kind' => 'run', 'name' => $name, 'variant' => $variant, 'text' => $text, 'opt' => $opt + ['debug' => true], 'result' => $strip($res)]);
};

// 1. every document with default settings, in each mode
foreach (array_merge($docs, $fuzz) as $i => $d) {
    foreach (['auto', 'log', 'script'] as $m) {
        if ($i >= $handDocs && $m !== 'auto' && $i % 5) { continue; }
        $runV($i < $handDocs ? "doc$i/$m" : "fuzz$i/$m", [], $d, ['mode' => $m]);
    }
}
// 2. settings variants
$big = implode("\n", $frag);
$variants = [
    'all-rules'   => ['all_rules' => true],
    'tpl-brace'   => ['settings' => ['placeholder_template' => '[[{cat}-{n}]]']],
    'tpl-hash'    => ['settings' => ['placeholder_template' => '{CAT}#{hash}']],
    'tpl-stars'   => ['settings' => ['placeholder_template' => '{stars}']],
    'tpl-len'     => ['settings' => ['placeholder_template' => '<{CAT}:{len}:{n}>']],
    'no-prop'     => ['settings' => ['propagate' => '0']],
    'min-len'     => ['settings' => ['propagate_min_len' => '10']],
    'netmask-off' => ['settings' => ['keep_netmasks' => '0']],
    'keeps'       => ['settings' => ['keep_values' => "admin\nroot, 12345", 'keep_cidrs' => "10.0.0.0/8\n8.8.8.8\n192.168.0.0/33\n172.16.0.0/12\n0.0.0.0/0x", 'keep_domains' => "acme.local\nexample.com"]],
    'names-empty' => ['settings' => ['first_names' => '', 'tlds' => '']],
    'names-few'   => ['settings' => ['first_names' => "ali, işıl, ömer\nabc,xy", 'tlds' => 'local, co.uk']],
    'no-risk'     => ['settings' => ['risk_keywords' => '']],
    'risk-bad'    => ['settings' => ['risk_keywords' => '(unclosed']],
    'stop-empty'  => ['settings' => ['person_stopwords' => '', 'dotted_deny' => '', 'code_deny' => '', 'fqdn_deny_first' => '']],
    'cat-off'     => ['cats' => ['IP' => ['enabled' => 0], 'USER' => ['enabled' => 0]]],
    'cat-case'    => ['cats' => ['USER' => ['case_sensitive' => 1], 'HOST' => ['case_sensitive' => 1, 'propagate' => 0]]],
    'cat-ph'      => ['cats' => ['IP' => ['placeholder' => 'IP-{n}-{cat}'], 'PASSWORD' => ['placeholder' => '***']]],
];
$r0 = fn(array $x) => $x + ['id' => 9000, 'name' => 'Custom', 'description' => '', 'category' => 'SECRET', 'type' => 'regex', 'pattern' => '', 'flags' => '', 'keys_text' => '', 'groups' => [], 'options' => [], 'priority' => 900, 'modes' => 'all', 'enabled' => 1, 'builtin' => 0, 'sort' => 1];
$variants += [
    'kw-rule'  => ['extra_rules' => [$r0(['type' => 'keyword', 'keys_text' => "Acme Corp\nFinance-Floor3 | HOST\n# comment\nşifre\n+90\n(x)", 'category' => 'PERSON'])]],
    'kw-case'  => ['extra_rules' => [$r0(['type' => 'keyword', 'keys_text' => "acme\nProject", 'options' => ['case_sensitive' => true]])]],
    'kv-rule'  => ['extra_rules' => [$r0(['type' => 'keyvalue', 'keys_text' => "uid, db host\nowner", 'options' => ['affix' => 'both', 'seps' => 'space', 'xml' => true]])]],
    'kv-regex' => ['extra_rules' => [$r0(['type' => 'keyvalue', 'keys_text' => 'host', 'options' => ['seps' => 'eq', 'value_regex' => '[\w.-]+', 'xml' => true, 'reclassify' => true, 'split_domain' => true]])]],
    'rx-opts'  => ['extra_rules' => [$r0(['pattern' => '(\w+)=(\S+)', 'groups' => [1 => 'USER', 2 => 'SECRET'], 'options' => ['min_len' => 4, 'max_len' => 12, 'skip_numeric' => true, 'skip_regex' => '^x', 'skip_vars' => false, 'propagate' => false]])]],
    'rx-ent'   => ['extra_rules' => [$r0(['pattern' => '\b[A-Za-z0-9+/]{16,}\b', 'options' => ['validate' => 'entropy', 'entropy_min' => 3.5]])]],
    'rx-bad'   => ['extra_rules' => [$r0(['pattern' => '(unclosed'])]],
    'rx-flags' => ['extra_rules' => [$r0(['pattern' => "^ ip \\s+ address # comment\n .+? $", 'flags' => 'imxU'])]],
    'rx-script'=> ['extra_rules' => [$r0(['pattern' => 'Vlan\d+', 'modes' => 'script', 'category' => 'HOST'])]],
];
foreach ($variants as $name => $v) {
    foreach ($docs as $i => $d) {
        if ($i > 4 && $i < $handDocs - 2 && $i % 7) { continue; } // full set is covered through $big
        $runV("var:$name/doc$i", $v, $d, []);
    }
    foreach (array_slice($fuzz, 0, 40) as $i => $d) { $runV("var:$name/fuzz$i", $v, $d, []); }
}
// 3. run options
$first = (new Engine($rules, $cats, $settings, 'parity-secret'))->run($big);
$known = array_map(fn($it) => ['token' => $it['token'], 'category' => $it['category'], 'original' => $it['original'], 'variants' => $it['variants']], $first['items']);
$opts = [
    'disabled' => ['disabled_cats' => ['IP', 'PASSWORD', 'FQDN']],
    'noprop'   => ['propagate' => false],
    'known'    => ['known' => $known],
    'known-odd'=> ['known' => [['token' => '<IP_7>', 'category' => 'IP', 'original' => '10.20.30.40', 'variants' => ['10.20.30.40']], ['token' => '<FQDN_3>', 'category' => 'FQDN', 'original' => 'db01.corp.acme.local'], ['token' => '<X>', 'category' => 'NOPE', 'original' => 'admin'], ['category' => 'IP', 'original' => 'x']]],
    'hints'    => ['hints' => [['category' => 'SECRET', 'value' => 'finance'], ['category' => 'HOST', 'value' => ' Guest_WiFi '], ['category' => 'NOPE', 'value' => 'ticket'], ['category' => 'USER', 'value' => 'a']]],
    'allow'    => ['allow' => ['10.20.30.40', 'ADMIN', 'jdoe', '']],
    'both'     => ['hints' => [['category' => 'HOST', 'value' => 'Project']], 'allow' => ['project', 'hunter2'], 'known' => array_slice($known, 0, 5)],
];
foreach ($opts as $name => $o) {
    foreach (array_merge([$big], array_slice($docs, 0, 4), array_slice($fuzz, 0, 40)) as $i => $d) { $runV("opt:$name/$i", [], $d, $o); }
}
// 4. each rule alone, so a difference cannot hide behind a higher-priority rule
$solo = array_merge([$big], array_slice($docs, 0, 4), array_slice($fuzz, 0, 25 * $scale));
foreach ($rules as $r) {
    foreach ($solo as $i => $d) { $runV("solo:{$r['bkey']}/$i", ['only' => $r['id']], $d, ['mode' => $i % 2 ? 'script' : 'log']); }
}
// 5. validators against the PHP functions
$ips = ['::', '::1', '1::', '1:2:3:4:5:6:7:8', '1:2:3:4:5:6:7', '1:2:3:4:5:6:7:8:9', '1::8', ':1', '1:', ':::', '1:::2', '1::2::3', '12345::', 'g::', '::ffff:1.2.3.4', '1:2:3:4:5:6:1.2.3.4', '1:2:3:4:5:6:7:1.2.3.4', '1:2:3:4:5::1.2.3.4', '::1.2.3.4', '1.2.3.4', ':1.2.3.4', '::1.2.3', '::1.2.3.256', '1::1.2.3.4', 'fe80::1%eth0', '2001:db8::', '2001:DB8:0:0:8:800:200C:417A', '1:2:3:4:5:6:7::', '::2:3:4:5:6:7:8', '1:2:3:4:5:6::8', '1:2:3:4::5:6:7:8', '', ':', '1', 'a:b', '::a:b:c:d:e:f:1.2.3.4', '0:0:0:0:0:0:0:0', '1:2:3:4:5:6:7:8:', ':1:2:3:4:5:6:7:8', '::1:', '00000::1', '1:2:3:4:5:6:7::8', '1:2:3:4:5:6:7:8::', '::1:2:3:4:5:6:7:8', '1:2:3:4:5:6::1.2.3.4', '1:2:3:4:5::', '1::1.2.3.4:', 'a.b:c'];
foreach ($ips as $ip) { $emit(['kind' => 'val', 'fn' => 'isIPv6', 'args' => [$ip], 'out' => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false]); }
foreach (['1.2.3.4', '0.0.0.0', '255.255.255.255', '256.1.1.1', '01.2.3.4', '1.2.3', '1.2.3.4.5', '1.2.3.4 ', ' 1.2.3.4', '1.2.3.4' . "\n", '١.2.3.4', '00.0.0.0'] as $ip) { $emit(['kind' => 'val', 'fn' => 'isIPv4', 'args' => [$ip], 'out' => Engine::isIPv4($ip)]); }
foreach (['255.255.255.0', '0.0.0.255', '255.0.255.0', '0.0.0.0', '255.255.255.255', '10.0.0.0', '255.255.128.0', '0.0.127.255'] as $ip) { $emit(['kind' => 'val', 'fn' => 'isNetmask', 'args' => [$ip], 'out' => Engine::isNetmask($ip)]); }
foreach ([['10.1.2.3', '10.0.0.0/8'], ['11.1.2.3', '10.0.0.0/8'], ['1.2.3.4', '1.2.3.4'], ['1.2.3.4', '0.0.0.0/0'], ['1.2.3.4', '1.2.3.4/32'], ['1.2.3.5', '1.2.3.4/32'], ['200.1.1.1', '128.0.0.0/1'], ['1.2.3.4', 'x/8'], ['1.2.3.4', '1.2.3.0/33'], ['1.2.3.4', '1.2.3.0/-1']] as $a) { $emit(['kind' => 'val', 'fn' => 'inCidr', 'args' => $a, 'out' => Engine::inCidr(...$a)]); }
foreach (['4111 1111 1111 1111', '4111111111111112', '5500-0000-0000-0004', '340000000000009', '1111111111111111', '4444444444444444', '123', '6011000000000004', '9792000000000001'] as $v) { $emit(['kind' => 'val', 'fn' => 'luhn', 'args' => [$v], 'out' => Engine::luhn($v)]); }
foreach (['10000000146', '12345678901', '00000000000', '1000000014', '10000000147', '19191919190'] as $v) { $emit(['kind' => 'val', 'fn' => 'tckn', 'args' => [$v], 'out' => Engine::tckn($v)]); }
foreach (['TR33 0006 1005 1978 6457 8413 26', 'TR00 0006 1005 1978 6457 8413 26', 'GB82 WEST 1234 5698 7654 32', 'gb82west12345698765432', 'XX', 'DE89370400440532013000'] as $v) { $emit(['kind' => 'val', 'fn' => 'iban', 'args' => [$v], 'out' => Engine::iban($v)]); }
foreach (['aaaa', 'abcd', 'şifre', 'a8f5f167f44f4964e6c998dee827110c', '🔑🔑x', ''] as $v) { $emit(['kind' => 'val', 'fn' => 'entropy', 'args' => [$v], 'out' => round(Engine::entropy($v), 9)]); }
foreach (['what do ya want for nothing?', 'şifre', ''] as $v) { $emit(['kind' => 'val', 'fn' => 'hmacSha256Hex', 'args' => [$v, 'Jefe'], 'out' => hash_hmac('sha256', $v, 'Jefe')]); }
$emit(['kind' => 'val', 'fn' => 'hmacSha256Hex', 'args' => ['x', str_repeat('k', 100)], 'out' => hash_hmac('sha256', 'x', str_repeat('k', 100))]);
foreach (['$x', '${A:-b}', '$(cmd)', '%TEMP%', '{{ a }}', '<x>', '[y]', '@v', ':p', '?', '***', '*', 'xxx', 'XXXX', '###', '••', '...', '--', 'abc', "\$x\n"] as $v) { $emit(['kind' => 'val', 'fn' => 'isVariableRef', 'args' => [$v], 'out' => Engine::isVariableRef($v)]); }

// 6. the regex translator against PCRE, construct by construct
$subjects = ["xaybz xa xaz ybxa zzz xay\n", "foo bar_baz 123 Ağaç İSTANBUL ışık\nline two\r\nline three\n", "aaa aab ab b  \t x\u{00A0}y\u{2003}z\u{0085}w\u{2028}v\n\n", "é e\u{0301} ٣٤ Ⅷ ‿x_y-z KK ſs ß 🔑🔑 日本語\n", "a.b(c)[d]{e}|f^g\$h*i+j?k\\l/m-n#o \"q\" 'r' <s>", "", "\n", "abcABC\nabc"];
$patterns = [
    ['\w+', ''], ['\W+', ''], ['\d+', ''], ['\D+', ''], ['\s+', ''], ['\S+', ''], ['\bfoo\b', ''], ['\b\w', ''], ['\B\w', ''], ['\w\b', ''],
    ['[\w-]+', ''], ['[\w.-]+', ''], ['[^\w]+', ''], ['[\W]+', ''], ['[\S\n]+', ''], ['[^\S\n]+', ''], ['[\D]+', ''], ['[^\D]+', ''], ['[a-z\d]+', 'i'], ['[]a]+', ''], ['[^]a]+', ''], ['[a\-z]+', ''], ['[-a]+', ''], ['[a-]+', ''], ['[\[\]]+', ''], ['[\\\\/]+', ''],
    ['^', ''], ['^', 'm'], ['$', ''], ['$', 'm'], ['^\w+', 'm'], ['\w+$', 'm'], ['\w+$', ''], ['^.*$', 'm'], ['^.*$', 'ms'], ['.+', ''], ['.+', 's'], ['\A\w+', ''], ['\w+\z', ''], ['\w+\Z', ''], ['\N+', ''],
    ['\h+', ''], ['\H+', ''], ['\v+', ''], ['\V+', ''], ['\R', ''], ['[\h]+', ''], ['[\H]+', ''], ['[\v]+', ''], ['[^\v]+', ''],
    ['(?>a+)b', ''], ['(?>a|ab)c', ''], ['a++b', ''], ['a*+a', ''], ['a?+b', ''], ['a{1,2}+b', ''], ['(a)(?>(b))(c)?', ''], ['(a)\1', ''], ['(?<n>a)\k<n>', ''], ['(?P<n>a)(?P=n)', ''], ["(?'n'a)\k{n}", ''], ['(a)\g{1}', ''], ['(a)\g1', ''], ['(a)\g{-1}', ''],
    ['(?i)abc', ''], ['a(?i)bc', ''], ['(?i:a)b', ''], ['(?-i:a)b', 'i'], ['(a(?i)b)c', ''], ['a(?i)b|c', ''], ['(?s).+', ''], ['(?m)^\w', ''], ['(?x) a b # c' . "\n" . ' c', ''], ['(?s:.)+', ''], ['(?#comment)a', ''],
    [' a  b  # comment' . "\n" . ' c ', 'x'], ['a +', 'x'], ['[ a]+', 'x'], ['a+', 'U'], ['a+?', 'U'], ['\w{2,3}', 'U'], ['\w{2,3}?', ''], ['a{2}', ''], ['a{2,}', ''], ['a{,2}', ''], ['a{x}', ''], ['{', ''], ['}', ''], [']', ''], ['a{', ''],
    ['\p{L}+', ''], ['\P{L}+', ''], ['\pL+', ''], ['\p{Lu}+', ''], ['\p{Lu}+', 'i'], ['\p{Ll}+', ''], ['[\p{Lu}\d]+', ''], ['[\P{L}]+', ''], ['\p{Han}+', ''], ['\p{^L}+', ''], ['\p{L&}+', ''], ['\p{Nd}+', ''], ['\p{Greek}', ''],
    ['i', 'i'], ['I', 'i'], ['İ', 'i'], ['ı', 'i'], ['k', 'i'], ['s', 'i'], ['ß', 'i'], ['ş', 'i'], ['Ş', 'i'], ['Ağaç', 'i'], ['[a-c]+', 'i'], ['[^a-c]+', 'i'], ['\w+', 'i'],
    ['\x41', ''], ['\x{1F511}', ''], ['\x{e9}', ''], ['\101', ''], ['\0', ''], ['\t', ''], ['\n', ''], ['\r\n', ''], ['\e', ''], ['\f', ''], ['\cA', ''], ['[\x41-\x43]+', 'i'], ['[\t\n]+', ''], ['\Qa.b\E', ''], ['\Qa.b', ''], ['[\Qa.\E]+', ''], ['\-', ''], ['\=', ''], ['\ ', ''], ['\_', ''], ['\"', ''], ['\'', ''], ['\/', ''], ['\#', ''], ['\@', ''], ['\%', ''], ['\<', ''], ['\:', ''], ['\,', ''], ['\!', ''], ['\&', ''], ['\~', ''], ['\`', ''],
    ['(?=a)\w', ''], ['(?!a)\w', ''], ['(?<=a)\w', ''], ['(?<!a)\w', ''], ['(?<![\p{L}])a', ''], ['(a)|(b)', ''], ['(a)|b', ''], ['(a)?b', ''], ['(a)*b', ''], ['((a)|(b))+', ''], ['(?:x(a)|y(b)|z)+', ''], ['(?:x(a)|y(b)|z){2,}', ''], ['(?:x(a)|y(b)|z)+?', ''], ['(?:x(a)|y(b)|z)*z', ''], ['(z)(?:x(a)|\1)+', ''], ['((?:x(a)|y(b))+z)+', ''], ['(?:(a)|(b)|\s)+', 'i'], ['(?:(a)|b)+', ''], ['a|', ''], ['|a', ''], ['()', ''], ['(a*)', ''], ['a*', ''], ['a*?', ''], ['\b', ''], ['(?=b)', ''], ['x*', ''],
];
foreach ($patterns as [$p, $f]) {
    foreach ($subjects as $si => $s) {
        $re = "\x01$p\x01{$f}u";
        $n = @preg_match_all($re, $s, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        if ($n === false) { $emit(['kind' => 're', 'p' => $p, 'f' => $f . 'u', 's' => $s, 'error' => true]); continue; }
        $list = array_map(fn($m) => array_map(fn($g) => $g[1] < 0 ? null : [$g[0], $g[1]], array_values(array_filter($m, 'is_int', ARRAY_FILTER_USE_KEY))), $mm);
        $emit(['kind' => 're', 'p' => $p, 'f' => $f . 'u', 's' => $s, 'out' => $list]);
    }
}
fclose($fh);
fprintf(STDERR, "fixtures written: %s (%.1f MB)\n", $out, filesize($out) / 1e6);
