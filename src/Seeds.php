<?php
declare(strict_types=1);

/**
 * Default (built-in) categories, rules, settings and sample data.
 * Built-in rules are identified by "bkey"; "reset to default" on the settings screen
 * restores the definition given here. Names and descriptions are English; see lang/tr/seeds.php.
 */
final class Seeds
{
    public static function categories(): array
    {
        // code, label, token, color, propagate, case_sensitive, description
        $rows = [
            ['PASSWORD', 'Password',               'PASS',   '#ef4444', 1, 1, 'Passwords, communities, PSKs, password hashes'],
            ['SECRET',   'Key / Token',      'KEY',    '#f97316', 1, 1, 'API keys, SSH/PEM keys, JWTs, bearer tokens, cookies'],
            ['IP',       'IPv4',                 'IP',     '#3b82f6', 0, 0, 'IPv4 addresses (excluding masks and kept ranges)'],
            ['IP6',      'IPv6',                 'IPV6',   '#6366f1', 0, 0, 'IPv6 addresses'],
            ['MAC',      'MAC address',           'MAC',    '#14b8a6', 0, 0, 'MAC addresses (: - . forms)'],
            ['HOST',     'Hostname',             'HOST',   '#f59e0b', 1, 0, 'Computer / server / device names'],
            ['FQDN',     'FQDN / Domain name',      'FQDN',   '#ca8a04', 1, 0, 'Fully qualified domain names, internal domain names'],
            ['DOMAIN',   'AD / NetBIOS domain',  'DOMAIN', '#65a30d', 1, 0, 'Domain names in the CORP\\user form'],
            ['USER',     'Username',        'USER',   '#16a34a', 1, 0, 'Usernames, first.last, account names'],
            ['PERSON',   'Person (full name)',      'PERSON', '#db2777', 1, 0, 'Full names, display names'],
            ['EMAIL',    'E-mail',              'EMAIL',  '#9333ea', 0, 0, 'E-mail (when masked as a whole)'],
            ['SID',      'Windows SID',          'SID',    '#64748b', 0, 1, 'Domain / Azure AD security identifiers'],
            ['PHONE',    'Phone',              'PHONE',  '#0891b2', 0, 1, 'Phone numbers'],
            ['TCKN',     'Turkish ID number (TCKN)',       'TCKN',   '#0284c7', 0, 1, 'TCKN with a verified checksum'],
            ['IBAN',     'IBAN',                 'IBAN',   '#059669', 0, 1, 'IBAN verified with mod-97'],
            ['CARD',     'Card number',        'CARD',   '#b91c1c', 0, 1, 'Card number verified with Luhn'],
            ['CUSTOM',   'Custom term',           'CUSTOM', '#78716c', 0, 0, 'Organization terms from the custom dictionary'],
            ['LOCATION', 'Location',             'LOC',    '#0d9488', 1, 0, 'Physical location details such as building / floor / room / rack'],
            ['NETLABEL', 'Network label (VLAN / port)', 'NETLBL', '#7c3aed', 0, 0, 'VLAN names and VLAN / port descriptions'],
        ];
        $out = [];
        foreach ($rows as $i => $r) {
            $out[] = [
                'code' => $r[0], 'label' => $r[1], 'token' => $r[2], 'color' => $r[3],
                'propagate' => $r[4], 'case_sensitive' => $r[5], 'description' => $r[6],
                'placeholder' => null, 'enabled' => 1, 'sort' => ($i + 1) * 10, 'builtin' => 1,
            ];
        }
        return $out;
    }

    /** Default AI connections (can be changed under Settings → AI connections) */
    public static function llmConnections(): array
    {
        // Add your AI endpoints under Settings → AI connections (vLLM gateway, Ollama or any OpenAI-compatible API).
        return [];
    }

    public static function settings(): array
    {
        return [
            'placeholder_template' => '<{CAT}_{n}>',
            'propagate'            => '1',
            'propagate_min_len'    => '4',
            'default_mode'         => 'auto',
            'keep_netmasks'        => '1',
            'max_input_kb'         => '4096',
            'mask_requires_login'  => '0',
            'log_usage'            => '1',
            'session_minutes'      => '60',
            'chat_default_model'   => '',
            'chat_temperature'     => '',
            'chat_system_prompt'   => implode("\n", [
                'You are an assistant that helps with systems, networking and software. Reply in the user\'s language.',
                '',
                'Sensitive information in the user\'s messages has been replaced with placeholders for privacy (example: {EXAMPLE}). Categories: {CATEGORIES}.',
                'Rules:',
                '- The same placeholder always stands for the same real value; different placeholders are different values.',
                '- Write placeholders exactly as they are (including their brackets) in your reply, commands, scripts and configuration examples; do not change, translate, split or renumber them.',
                '- Do not try to guess real values, do not invent new placeholders and do not point out that placeholders are masked. The user will see your reply with the real values.',
            ]),
            'admin_hash'           => '',
            'risk_keywords'        => '\b(?:pass(?:word|wd|phrase)?|pwd|secret|token|api[_-]?key|apikey|credential|private[_ -]?key|bearer|authorization|community|şifre|sifre|parola)\b',
            'keep_values'          => implode("\n", [
                '# These values are never masked in any category (case-insensitive)',
                '-', '--', 'n/a', 'na', 'null', 'none', 'nil', 'undefined', 'unknown', 'empty',
                'true', 'false', 'yes', 'no', 'on', 'off', '0', '*', 'any', 'all',
                'required', 'optional', 'enabled', 'disabled', 'redacted', 'masked', 'hidden',
                'localhost', 'localhost.localdomain', 'ip6-localhost',
                'root', 'system', 'nobody', 'daemon', 'anonymous', 'guest', 'sshd', 'www-data',
                'local service', 'network service', 'nt authority', 'nt service', 'builtin',
                'workgroup', 'administrators', 'users', 'everyone', 'authenticated users',
                'default', 'public', 'shared', 'all users', 'default user', 'defaultuser0',
                'font driver host', 'window manager',
                '0.0.0.0', '127.0.0.1', '255.255.255.255', '::', '::1',
                '00:00:00:00:00:00', 'ff:ff:ff:ff:ff:ff', '00-00-00-00-00-00', 'ff-ff-ff-ff-ff-ff',
            ]),
            'keep_cidrs'           => implode("\n", [
                '# IPv4 addresses in these ranges are not masked. A single IP or a CIDR can be given.',
                '127.0.0.0/8',
                '0.0.0.0/32',
                '255.255.255.255/32',
                '224.0.0.0/4',
                '# 169.254.0.0/16',
                '# 8.8.8.8',
                '# To leave the internal network visible: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16',
            ]),
            'keep_domains'         => implode("\n", [
                '# These domains and their subdomains are not masked (general/public services)',
                'microsoft.com', 'windows.com', 'windowsupdate.com', 'office.com', 'office365.com',
                'microsoftonline.com', 'live.com', 'azure.com', 'azureedge.net', 'msftncsi.com',
                'google.com', 'googleapis.com', 'gstatic.com', 'gmail.com',
                'github.com', 'githubusercontent.com', 'gitlab.com', 'docker.com', 'docker.io',
                'ubuntu.com', 'debian.org', 'redhat.com', 'centos.org', 'fedoraproject.org', 'kernel.org',
                'python.org', 'pypi.org', 'npmjs.org', 'npmjs.com', 'apache.org', 'nginx.org', 'php.net',
                'mozilla.org', 'w3.org', 'schema.org', 'example.com', 'example.org', 'example.net',
                'cloudflare.com', 'amazonaws.com', 'letsencrypt.org', 'digicert.com', 'verisign.com',
            ]),
            'tlds'                 => implode(', ', [
                'com', 'net', 'org', 'edu', 'gov', 'mil', 'int', 'info', 'biz', 'io', 'co', 'me',
                'tr', 'de', 'uk', 'us', 'eu', 'ru', 'cn', 'nl', 'fr', 'it', 'es', 'az', 'ch', 'at',
                'be', 'se', 'no', 'dk', 'fi', 'pl', 'ro', 'gr', 'jp', 'kr', 'ca', 'au', 'br', 'ir',
                'cloud', 'app', 'dev', 'online', 'site', 'tech', 'xyz', 'top', 'shop', 'store',
                'local', 'lan', 'corp', 'internal', 'intranet', 'intra', 'home', 'localdomain',
                'domain', 'ad', 'lab', 'test', 'example', 'invalid', 'priv', 'private', 'office',
            ]),
            'first_names'          => implode(', ', self::firstNames()),
            'dotted_deny'          => implode(', ', [
                'txt', 'log', 'logs', 'conf', 'cfg', 'config', 'ini', 'json', 'xml', 'yml', 'yaml', 'toml',
                'csv', 'tsv', 'sh', 'bash', 'zsh', 'py', 'pyc', 'ps', 'psm', 'bat', 'cmd', 'exe', 'dll',
                'sys', 'so', 'ko', 'php', 'js', 'mjs', 'ts', 'jsx', 'tsx', 'html', 'htm', 'css', 'scss',
                'md', 'rst', 'pl', 'pm', 'rb', 'java', 'class', 'jar', 'war', 'go', 'rs', 'cpp', 'hpp',
                'cs', 'vb', 'vbs', 'sql', 'db', 'sqlite', 'bak', 'old', 'orig', 'tmp', 'temp', 'swp', 'zip',
                'gz', 'tgz', 'tar', 'bz', 'xz', 'rar', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt',
                'pptx', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'bmp', 'webp', 'mp', 'avi', 'mov',
                'msi', 'cab', 'pem', 'crt', 'cer', 'der', 'key', 'pub', 'pfx', 'csr', 'jks', 'rpm',
                'deb', 'apk', 'dmg', 'iso', 'img', 'vmdk', 'vhd', 'vhdx', 'ova', 'lnk', 'evtx', 'etl',
                'dmp', 'pid', 'lock', 'sock', 'service', 'socket', 'target', 'timer', 'mount', 'scope',
                'slice', 'path', 'device', 'swap', 'automount', 'err', 'out', 'run', 'cache', 'list',
                'repo', 'rules', 'policy', 'pol', 'inf', 'cat', 'reg', 'nfo', 'dat', 'bin', 'hex',
                'local', 'socket', 'conf', 'default', 'disabled', 'enabled', 'd', 'new', 'dist',
            ]),
            'code_deny'            => implode(', ', [
                'self', 'this', 'super', 'cls', 'os', 'sys', 're', 'io', 'np', 'pd', 'plt', 'tf',
                'json', 'console', 'math', 'window', 'document', 'process', 'system', 'microsoft',
                'std', 'main', 'app', 'module', 'exports', 'require', 'string', 'int', 'list', 'dict',
                'array', 'object', 'date', 'datetime', 'time', 'logging', 'logger', 'log', 'args',
                'kwargs', 'params', 'request', 'response', 'req', 'res', 'err', 'error', 'event',
                'ctx', 'config', 'settings', 'env', 'environ', 'path', 'file', 'files', 'socket',
                'http', 'urllib', 'requests', 'subprocess', 'shutil', 'glob', 'random', 'collections',
                'typing', 'pathlib', 'argparse', 'paramiko', 'netmiko', 'boto', 'django', 'flask',
                'jquery', 'angular', 'react', 'vue', 'node', 'npm', 'git', 'docker', 'kubectl',
                'kernel', 'net', 'vm', 'fs', 'user', 'users', 'data', 'value', 'values', 'item',
                'items', 'key', 'keys', 'name', 'type', 'result', 'output', 'input', 'status',
            ]),
            'fqdn_deny_first'      => implode(', ', ['system', 'asp', 'ado', 'vb', 'dot', 'microsoft.powershell']),
            'person_stopwords'     => implode(', ', [
                'not', 'is', 'was', 'be', 'the', 'and', 'or', 'of', 'to', 'in', 'on', 'at', 'by',
                'for', 'with', 'from', 'has', 'have', 'had', 'do', 'does', 'did', 'can', 'could',
                'will', 'would', 'should', 'may', 'might', 'must', 'error', 'failed', 'success',
                'warning', 'info', 'user', 'server', 'service', 'connect', 'connection', 'access',
                've', 'ile', 'için', 'bir', 'bu', 'şu', 'değil', 'olarak', 'var', 'yok',
            ]),
        ];
    }

    public static function firstNames(): array
    {
        return [
            // Turkish male
            'ahmet', 'mehmet', 'mustafa', 'ali', 'hüseyin', 'hasan', 'ibrahim', 'ismail', 'osman',
            'yusuf', 'murat', 'ömer', 'ramazan', 'halil', 'süleyman', 'abdullah', 'mahmut', 'recep',
            'salih', 'fatih', 'kadir', 'emre', 'hakan', 'adem', 'kemal', 'yaşar', 'bayram', 'metin',
            'serkan', 'burak', 'orhan', 'cengiz', 'erkan', 'volkan', 'tolga', 'onur', 'oğuz', 'uğur',
            'gökhan', 'sinan', 'levent', 'selim', 'sercan', 'serdar', 'tuncay', 'tuncer', 'tarık',
            'taner', 'yavuz', 'yunus', 'zafer', 'ercan', 'erdem', 'erhan', 'ersin', 'ertan',
            'ertuğrul', 'eren', 'enes', 'emir', 'emin', 'engin', 'ferhat', 'furkan', 'gürkan',
            'ilker', 'ilhan', 'kaan', 'kerem', 'koray', 'mert', 'mesut', 'muhammed', 'muhammet',
            'nihat', 'nuri', 'okan', 'ozan', 'özgür', 'polat', 'rıza', 'sadık', 'safa', 'samet',
            'sedat', 'selçuk', 'semih', 'serhat', 'şükrü', 'tayfun', 'tamer', 'timur', 'turgut',
            'ufuk', 'umut', 'ünal', 'vedat', 'yasin', 'yiğit', 'zeki', 'alper', 'arda', 'atakan',
            'barış', 'batuhan', 'berk', 'berkay', 'can', 'cem', 'cihan', 'deniz', 'doğan', 'doruk',
            'egemen', 'ekrem', 'fikret', 'hamza', 'harun', 'kenan', 'kutay', 'necati', 'nevzat',
            'oktay', 'sami', 'sefa', 'şahin', 'şenol', 'tufan', 'utku', 'yalçın', 'yücel', 'erol',
            'cemal', 'cemil', 'celal', 'bülent', 'hilmi', 'ilyas', 'kazım', 'mithat', 'nazım',
            'orçun', 'rasim', 'rüştü', 'savaş', 'tahsin', 'tevfik', 'yılmaz', 'görkem', 'çağlar',
            'çağrı', 'efe', 'alp', 'anıl', 'baran', 'bora', 'emrah', 'evren', 'eyüp', 'gökay',
            'hüsnü', 'ilkay', 'mehmetcan', 'ozan', 'sarp', 'taylan', 'tuna', 'tunahan', 'volkan',
            // Turkish female
            'ayşe', 'fatma', 'emine', 'hatice', 'zeynep', 'elif', 'meryem', 'şerife', 'zehra',
            'sultan', 'hanife', 'merve', 'özlem', 'esra', 'büşra', 'ebru', 'gamze', 'gizem', 'hülya',
            'ilknur', 'kübra', 'leyla', 'melek', 'melike', 'nurcan', 'özge', 'pınar', 'rabia',
            'seda', 'selin', 'sevgi', 'sibel', 'songül', 'tuğba', 'tülay', 'yasemin', 'yeliz', 'aslı',
            'aylin', 'ayşegül', 'burcu', 'cansu', 'ceren', 'damla', 'derya', 'didem', 'dilek',
            'duygu', 'ece', 'ezgi', 'filiz', 'gülşen', 'hande', 'irem', 'nazlı', 'nesrin',
            'neslihan', 'nilay', 'nihan', 'oya', 'sema', 'serap', 'şule', 'tuba', 'yağmur', 'zerrin',
            'beyza', 'buse', 'ecem', 'eda', 'eylül', 'sude', 'defne', 'ela', 'azra', 'asya', 'ilayda',
            'şeyma', 'hilal', 'gökçe', 'ipek', 'işıl', 'aysel', 'esin', 'feride', 'gülay', 'hacer',
            'handan', 'nermin', 'nur', 'reyhan', 'sevda', 'sevim', 'şebnem', 'tülin', 'ümran', 'zuhal',
            'begüm', 'berna', 'betül', 'cemre', 'dilara', 'ebru', 'elçin', 'esma', 'gonca', 'gül',
            // International
            'john', 'james', 'robert', 'michael', 'william', 'david', 'richard', 'joseph', 'thomas',
            'charles', 'christopher', 'daniel', 'matthew', 'anthony', 'steven', 'paul', 'andrew',
            'joshua', 'kevin', 'brian', 'george', 'edward', 'ryan', 'jason', 'jacob', 'gary', 'eric',
            'stephen', 'jonathan', 'larry', 'justin', 'scott', 'brandon', 'benjamin', 'samuel',
            'gregory', 'alexander', 'patrick', 'peter', 'mary', 'patricia', 'jennifer', 'linda',
            'elizabeth', 'barbara', 'susan', 'jessica', 'sarah', 'karen', 'nancy', 'lisa', 'emily',
            'emma', 'olivia', 'sophia', 'anna', 'laura', 'maria', 'julia', 'alex', 'chris', 'mike',
            'tom', 'dave', 'nick', 'sam', 'ben', 'jane', 'kate', 'adam', 'mark', 'frank',
            'nathan', 'tyler', 'aaron', 'sean', 'oliver', 'henry', 'lucas', 'ethan', 'noah', 'liam',
            'megan', 'rachel', 'amanda', 'michelle', 'nicole', 'rebecca', 'stephanie', 'heather',
            'angela', 'melissa', 'kimberly', 'ashley', 'amy', 'claire', 'hannah', 'chloe',
            // German, French, Italian
            'hans', 'lukas', 'klaus', 'stefan', 'andreas', 'markus', 'matthias', 'wolfgang', 'jürgen',
            'pierre', 'jean', 'francois', 'françois', 'antoine', 'nicolas', 'marie', 'sophie', 'camille',
            'giovanni', 'marco', 'luca', 'giulia', 'francesca',
            // Spanish, Portuguese
            'carlos', 'juan', 'jose', 'josé', 'miguel', 'pedro', 'diego', 'javier', 'joao', 'joão',
            'carmen', 'lucia', 'isabella',
            // Slavic, Nordic
            'ivan', 'dmitri', 'sergei', 'andrei', 'vladimir', 'natalia', 'olga', 'svetlana', 'piotr',
            'katarzyna', 'lars', 'erik', 'sven', 'ingrid', 'astrid',
            // Arabic
            'ahmed', 'mohammed', 'mohamed', 'omar', 'khaled', 'youssef', 'fatima', 'aisha', 'layla',
            // Indian
            'rajesh', 'amit', 'rahul', 'sanjay', 'vikram', 'deepak', 'priya', 'anjali', 'sunita',
            // East Asian
            'hiroshi', 'kenji', 'takashi', 'akira', 'yuki', 'sakura', 'minho', 'xiaoming', 'liang',
        ];
    }

    /**
     * Rule definition shortcut.
     * $o: options (affix, seps, value_regex, min_len, max_len, skip_numeric, skip_vars,
     *     skip_regex, reclassify, split_domain, validate, entropy_min, propagate, xml, case_sensitive)
     */
    private static function r(
        string $bkey, string $name, string $cat, string $type, int $prio, string $desc,
        string $pattern = '', string $flags = '', string $keys = '', array $groups = [],
        array $o = [], string $modes = 'all', int $enabled = 1
    ): array {
        return [
            'bkey' => $bkey, 'name' => $name, 'category' => $cat, 'type' => $type,
            'priority' => $prio, 'description' => $desc, 'pattern' => $pattern, 'flags' => $flags,
            'keys_text' => $keys, 'groups' => $groups, 'options' => $o, 'modes' => $modes,
            'enabled' => $enabled, 'builtin' => 1,
        ];
    }

    public static function rules(): array
    {
        $R = [];
        $P = 'PASSWORD'; $S = 'SECRET'; $U = 'USER'; $H = 'HOST'; $F = 'FQDN'; $D = 'DOMAIN';
        $HOST_VALUE = <<<'RE'
[A-Za-z0-9_](?:[A-Za-z0-9_.\-]*[A-Za-z0-9_])?\$?
RE;
        $PATH_SKIP = <<<'RE'
^(?:/[\w.\-]+){2,}/?$|^[A-Za-z]:[\\/]|^~?/[\w.\-]+/
RE;

        // ================================================================
        // KEYS / TOKENS
        // ================================================================
        $R[] = self::r('pem_private_key', 'PEM / PuTTY private key block', $S, 'regex', 100,
            'BEGIN ... PRIVATE KEY blocks (RSA, EC, OPENSSH, PGP) and PuTTY .ppk contents are masked entirely.',
            <<<'RE'
-----BEGIN (?:[A-Z0-9]+ )*PRIVATE KEY(?: BLOCK)?-----[\s\S]*?-----END (?:[A-Z0-9]+ )*PRIVATE KEY(?: BLOCK)?-----|PuTTY-User-Key-File-\d:[\s\S]*?Private-MAC:\s*\S+
RE);

        $R[] = self::r('ssh_public_key', 'SSH public key body', $S, 'regex', 92,
            'Base64 body of ssh-rsa / ssh-ed25519 / ecdsa keys. The trailing user@host comment is caught by a separate rule.',
            <<<'RE'
(?:ssh-(?:rsa|dss|ed25519)|ecdsa-sha2-nistp(?:256|384|521)|sk-(?:ssh-ed25519|ecdsa-sha2-nistp256)@openssh\.com)\s+(AAAA[0-9A-Za-z+/]{20,}={0,3})
RE, '', '', [1 => $S]);

        $R[] = self::r('jwt', 'JWT', $S, 'regex', 96,
            'Three-part JSON Web Token starting with eyJ...',
            <<<'RE'
(?<![\w-])eyJ[A-Za-z0-9_-]{8,}\.eyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]*
RE);

        $R[] = self::r('vendor_tokens', 'Known service keys', $S, 'regex', 96,
            'AWS (AKIA/ASIA), GitHub (ghp_/github_pat_), GitLab (glpat-), Slack (xox?-), Google (AIza, ya29.), Stripe, OpenAI/Anthropic (sk-), SendGrid, npm, DigitalOcean, HuggingFace, Shopify.',
            <<<'RE'
(?<![A-Za-z0-9])(?:(?:AKIA|ASIA|AROA|AIDA|ANPA)[0-9A-Z]{16}|gh[pousr]_[A-Za-z0-9]{36,}|github_pat_[A-Za-z0-9_]{60,}|glpat-[A-Za-z0-9_\-]{20,}|xox[abposr]-[A-Za-z0-9\-]{10,}|xapp-\d-[A-Z0-9]+-\d+-[a-z0-9]+|AIza[0-9A-Za-z_\-]{35}|ya29\.[0-9A-Za-z_\-]{20,}|(?:sk|rk|pk)_(?:live|test)_[0-9A-Za-z]{16,}|sk-(?:proj-|ant-(?:api\d+-)?)?[A-Za-z0-9_\-]{20,}|SG\.[A-Za-z0-9_\-]{16,}\.[A-Za-z0-9_\-]{16,}|npm_[A-Za-z0-9]{36}|dop_v1_[a-f0-9]{64}|hf_[A-Za-z0-9]{30,}|shp(?:at|ss|ca|pa)_[a-fA-F0-9]{32})(?![A-Za-z0-9])
RE);

        $R[] = self::r('auth_header', 'Authorization header', $S, 'regex', 95,
            'Value of Authorization / Proxy-Authorization: Basic|Bearer|Digest|NTLM|Negotiate ...',
            <<<'RE'
\b(?:Proxy-)?Authorization["']?\s*[:=]\s*["']?(?:Basic|Bearer|Digest|NTLM|Negotiate|Token|ApiKey|SharedKey|AWS4-HMAC-SHA256)\s+([^\s"']{6,})
RE, 'i', '', [1 => $S]);

        $R[] = self::r('bearer_token', 'Bearer token (free text)', $S, 'regex', 94,
            '"Bearer xxxxx" expressions without a header.',
            <<<'RE'
\bBearer\s+([A-Za-z0-9\-._~+/]{16,}=*)
RE, 'i', '', [1 => $S]);

        $R[] = self::r('cookie_header', 'Cookie / Set-Cookie header', $S, 'regex', 86,
            'Value of HTTP Cookie and Set-Cookie lines (contains session ids).',
            <<<'RE'
^[ \t]*(?:Set-)?Cookie:[ \t]*(.+?)[ \t]*$
RE, 'im', '', [1 => $S]);

        $R[] = self::r('url_credentials', 'user:password in a URL', $P, 'regex', 97,
            'The scheme://user:password@host form (ftp, http, mysql, mongodb, smb ...).',
            <<<'RE'
\b[a-z][a-z0-9+.\-]{1,15}://([^\s:/@"'<>]{1,64}):([^\s@/"'<>]{1,128})@
RE, 'i', '', [1 => $U, 2 => $P], ['reclassify' => false]);

        // ================================================================
        // KEY = VALUE
        // ================================================================
        $R[] = self::r('kv_password', 'Password keys (key=value)', $P, 'keyvalue', 90,
            'All key=value uses such as password=, pwd:, "pass": , $Password = , DB_PASS=, <password>...</password>. Prefixes/suffixes are supported (db_password, smtp.auth.pass).',
            '', '', implode("\n", [
                'password', 'passwd', 'pass', 'pwd', 'passphrase', 'passcode', 'parola', 'şifre', 'sifre',
                'secret', 'psk', 'pre shared key', 'preshared key', 'shared secret', 'community',
                'snmp community', 'credential', 'credentials', 'auth pass', 'login password', 'enable password',
                'kullanıcı şifresi', 'admin pass', 'root pass', 'master password', 'unlock code',
            ]), [], ['affix' => 'both', 'xml' => true, 'skip_regex' => $PATH_SKIP]);

        $R[] = self::r('kv_secret', 'API key / token keys', $S, 'keyvalue', 91,
            'api_key=, access_token:, client_secret, AccountKey=, x-api-key, sessionid, csrf token ...',
            '', '', implode("\n", [
                'api key', 'apikey', 'access key', 'secret key', 'private key', 'auth token', 'access token',
                'refresh token', 'id token', 'token', 'bearer', 'client secret', 'x api key', 'license key',
                'ssh key', 'encryption key', 'signing key', 'session id', 'session token', 'sessid',
                'jsessionid', 'phpsessid', 'csrf token', 'xsrf token', 'sas token', 'sig', 'account key',
                'shared access key', 'primary key', 'secondary key', 'subscription key',
                'ocp apim subscription key', 'hmac', 'otp secret', 'mfa secret', 'totp secret', 'webhook secret',
                'app secret', 'consumer secret', 'aws secret access key', 'master key', 'auth key',
            ]), [], ['affix' => 'both', 'xml' => true, 'min_len' => 6, 'skip_regex' => $PATH_SKIP]);

        $R[] = self::r('kv_user', 'User keys (key=value)', $U, 'keyvalue', 74,
            'user=, username:, Account Name:, TargetUserName, User-Name =, login, acct=, kullanıcı adı ... DOMAIN\\user values are split in two; numeric uids are skipped.',
            '', '', implode("\n", [
                'user', 'user name', 'user id', 'userid', 'login', 'login name', 'login id', 'logon name',
                'logon account', 'account', 'account name', 'acct', 'usr', 'uname', 'sam account name',
                'samaccountname', 'user principal name', 'upn', 'owner', 'created by', 'modified by',
                'author', 'operator', 'initiator', 'requester', 'requested by', 'approved by',
                'target user name', 'subject user name', 'target user', 'subject user', 'target account',
                'client user', 'remote user', 'ruser', 'suser', 'duser', 'src user', 'dst user',
                'source user', 'destination user', 'logname', 'auid', 'uid', 'euid', 'identity',
                'authenticated user', 'security id', 'member name', 'caller user name', 'run as',
                'kullanıcı', 'kullanici', 'kullanıcı adı', 'kullanici adi', 'hesap', 'hesap adı',
            ]), [], ['affix' => 'prefix', 'xml' => true, 'reclassify' => true, 'split_domain' => true, 'skip_numeric' => true]);

        $R[] = self::r('kv_host', 'Hostname keys (key=value)', $H, 'keyvalue', 72,
            'hostname=, host:, ComputerName, Workstation Name:, NAS-Identifier, NetworkDeviceName, rhost=, shost ... IP/FQDN values are moved to the right category automatically.',
            '', '', implode("\n", [
                'hostname', 'host name', 'host', 'computer name', 'computer', 'workstation', 'workstation name',
                'machine name', 'machine', 'server name', 'srv name', 'node name', 'device name',
                'network device name', 'nas identifier', 'endpoint host name', 'sysname', 'source host',
                'src host', 'destination host', 'dst host', 'dest host', 'shost', 'dhost', 'rhost',
                'remote host', 'client name', 'client host name', 'caller computer name', 'target server name',
                'logon server', 'domain controller', 'dc name', 'netbios name', 'agent hostname', 'beat hostname',
                'devname', 'device host name', 'dvchost', 'dvc host', 'origin host', 'reporting host',
                'sunucu', 'sunucu adı', 'bilgisayar adı', 'makine adı', 'cihaz adı',
            ]), [], ['affix' => 'prefix', 'xml' => true, 'reclassify' => true, 'value_regex' => $HOST_VALUE, 'min_len' => 2]);

        $R[] = self::r('kv_host_eq', 'Connection string server (Server=)', $H, 'keyvalue', 71,
            'Server keys used only with "=", such as Server= and Data Source= (so that the HTTP "Server: nginx" header is not caught).',
            '', '', "server\ndata source\ndatasource", [],
            ['affix' => 'none', 'seps' => 'eq', 'reclassify' => true, 'value_regex' => $HOST_VALUE, 'min_len' => 2]);

        $R[] = self::r('kv_fqdn', 'FQDN keys (key=value)', $F, 'keyvalue', 73,
            'fqdn=, DnsHostName, dns name, ad-host-dns-name, realm, search domain ...',
            '', '', implode("\n", [
                'fqdn', 'dns name', 'dns host name', 'host fqdn', 'computer fqdn', 'ad host dns name',
                'dns domain name', 'dns domain', 'dns suffix', 'search domain', 'realm', 'kerberos realm',
            ]), [], ['affix' => 'prefix', 'xml' => true, 'reclassify' => true,
                'value_regex' => '[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?)+']);

        $R[] = self::r('kv_domain', 'Domain keys (key=value)', $D, 'keyvalue', 72,
            'Account Domain:, TargetDomainName, domain=, workgroup, logon domain ... Dotted values are marked as FQDNs.',
            '', '', implode("\n", [
                'domain', 'account domain', 'user domain', 'target domain name', 'subject domain name',
                'domain name', 'logon domain', 'workgroup', 'netbios domain', 'nt domain', 'caller domain',
                'etki alanı',
            ]), [], ['affix' => 'prefix', 'xml' => true, 'reclassify' => true,
                'value_regex' => 'NT AUTHORITY|NT SERVICE|[A-Za-z0-9][A-Za-z0-9._\-]*']);

        $R[] = self::r('kv_mac', 'MAC keys (key=value)', 'MAC', 'keyvalue', 70,
            'Calling-Station-ID, mac=, EndPointMACAddress, hwaddr ... (including 12 digits without separators).',
            '', '', implode("\n", [
                'mac', 'mac address', 'macaddress', 'hw addr', 'hwaddress', 'calling station id',
                'called station id', 'endpoint mac address', 'client mac', 'src mac', 'dst mac', 'ether',
            ]), [], ['affix' => 'prefix', 'xml' => true,
                'value_regex' => '[0-9A-Fa-f]{2}(?:[:\-]?[0-9A-Fa-f]{2}){5}|[0-9A-Fa-f]{4}\.[0-9A-Fa-f]{4}\.[0-9A-Fa-f]{4}']);

        $R[] = self::r('kv_person', 'Person name keys (key=value)', 'PERSON', 'keyvalue', 71,
            'displayName, full name, ad soyad, manager, contact ... The whole quoted value or 1-4 capitalized words.',
            '', '', implode("\n", [
                'display name', 'full name', 'fullname', 'ad soyad', 'adı soyadı', 'isim', 'isim soyisim',
                'name surname', 'first name', 'last name', 'surname', 'given name', 'soyad', 'soyadı',
                'contact', 'contact name', 'manager', 'employee name', 'personel', 'gecos',
            ]), [], ['affix' => 'prefix', 'xml' => true,
                'value_regex' => <<<'RE'
(?<=")[^"\r\n]{2,80}(?=")|(?<=')[^'\r\n]{2,80}(?=')|\p{Lu}[\p{L}'’\-]*(?:[ \t]+\p{Lu}[\p{L}'’\-]*){0,3}
RE]);

        // ================================================================
        // COMMAND LINE / SCRIPT PATTERNS
        // ================================================================
        $R[] = self::r('cli_flag_password', 'Password parameter (--password x, -Password "x")', $P, 'regex', 91,
            '--password, -Password, --passwd, -pwd parameters of Bash/PowerShell/CLI tools.',
            <<<'RE'
(?<![\w-])--?(?:password|passwd|pwd|pass|passphrase|secret|new-?password|old-?password|db-?password|admin-?password|root-?password|bind-?password|auth-?password)(?:=|[ \t]+)(?!-|\()(?:"((?:[^"\\\r\n]|\\.)*)"|'([^'\r\n]*)'|([^\s"'|;&()]+))
RE, 'i', '', [1 => $P, 2 => $P, 3 => $P]);

        $R[] = self::r('cli_flag_secret', 'Token parameter (--token x, -ApiKey x)', $S, 'regex', 89,
            '--token, --api-key, -AccessToken, --client-secret, --private-key (excluding path values).',
            <<<'RE'
(?<![\w-])--?(?:token|api-?key|apikey|access-?token|auth-?token|client-?secret|secret-?key|access-?key|private-?key|bearer|sas-?token)(?:=|[ \t]+)(?!-|\()(?:"([^"\r\n]*)"|'([^'\r\n]*)'|([^\s"'|;&()]+))
RE, 'i', '', [1 => $S, 2 => $S, 3 => $S], ['skip_regex' => $PATH_SKIP, 'min_len' => 6]);

        $R[] = self::r('cli_flag_user', 'User parameter (--username x, -User x)', $U, 'regex', 80,
            '--username, -UserName, --user=, -User (PowerShell), find -user, --admin-user. Excludes the value-less "--user" flag (pip, systemctl).',
            <<<'RE'
(?<![\w-])(?:--?user-?name|--user(?==)|-User(?=[ \t])|-user(?=[ \t])|--?(?:admin|db|runas|bind|login)-?user|--?login-?name)(?:=|[ \t]+)(?!-|\()(?:"([^"\r\n]*)"|'([^'\r\n]*)'|([^\s"'|;&()]+))
RE, 'i', '', [1 => $U, 2 => $U, 3 => $U], ['reclassify' => true, 'split_domain' => true]);

        $R[] = self::r('cli_flag_host', 'Server parameter (-ComputerName x, --host x)', $H, 'regex', 78,
            '-ComputerName, -Server, --host, --hostname, -Target, -DomainController parameters.',
            <<<'RE'
(?<![\w-])--?(?:computer-?name|computer|host-?name|host|server-?name|server|target-?host|remote-?host|domain-?controller|dc)(?:=|[ \t]+)(?!-|\$|\()["']?([A-Za-z0-9][\w.\-]*)
RE, 'i', '', [1 => $H], ['reclassify' => true]);

        $R[] = self::r('ps_securestring', 'ConvertTo-SecureString plain text', $P, 'regex', 92,
            'PowerShell ConvertTo-SecureString "password" -AsPlainText usage.',
            <<<'RE'
\bConvertTo-SecureString\s+(?:-String\s+)?(?:"([^"\r\n]*)"|'([^'\r\n]*)')
RE, 'i', '', [1 => $P, 2 => $P]);

        $R[] = self::r('ps_credential_user', 'PSCredential username', $U, 'regex', 86,
            'New-Object PSCredential("user", ...) / -ArgumentList "user", ...',
            <<<'RE'
\bPSCredential\s*(?:\(\s*|-ArgumentList\s+\(?\s*)["']([^"'\r\n]+)["']
RE, 'i', '', [1 => $U], ['reclassify' => true, 'split_domain' => true]);

        $R[] = self::r('net_user', 'net user <user> <password>', $U, 'regex', 88,
            'Windows "net user user Password /add" commands.',
            <<<'RE'
\bnet(?:\.exe)?\s+user\s+(?!/)(?:"([^"\r\n]+)"|([^\s"/]+))(?:\s+(?!/)(?:"([^"\r\n]*)"|([^\s"/*]+)))?
RE, 'i', '', [1 => $U, 2 => $U, 3 => $P, 4 => $P]);

        $R[] = self::r('net_use', 'net use \\\\server <password>', $P, 'regex', 88,
            'Positional password in "net use Z: \\\\srv\\share Password /user:...".',
            <<<'RE'
\bnet(?:\.exe)?\s+use\s+(?:[A-Za-z]:\s+|\*\s+)?\\\\\S+\s+(?!/)(?:"([^"\r\n]+)"|([^\s"/*]+))
RE, 'i', '', [1 => $P, 2 => $P]);

        $R[] = self::r('slash_user', '/user: /u: /ru switches', $U, 'regex', 84,
            'runas /user:, net use /user:, cmdkey /user:, schtasks /ru usage.',
            <<<'RE'
(?<![\w])(?:/(?:user|username|u):|/ru\s+)\s*(?:"([^"\r\n]+)"|([^\s"/]+))
RE, 'i', '', [1 => $U, 2 => $U], ['reclassify' => true, 'split_domain' => true]);

        $R[] = self::r('slash_pass', '/pass: /password: /rp switches', $P, 'regex', 90,
            'cmdkey /pass:, schtasks /rp, /password: usage.',
            <<<'RE'
(?<![\w])(?:/(?:pass|password|pwd):|/rp\s+)\s*(?:"([^"\r\n]*)"|([^\s"/]+))
RE, 'i', '', [1 => $P, 2 => $P]);

        $R[] = self::r('psexec', 'PsExec -u / -p', $U, 'regex', 88,
            'psexec \\\\host -u user -p password',
            <<<'RE'
\bpsexec(?:64)?(?:\.exe)?\b[^\r\n]*?\s-u\s+(?:"([^"\r\n]+)"|(\S+))(?:\s+-p\s+(?:"([^"\r\n]*)"|(\S+)))?
RE, 'i', '', [1 => $U, 2 => $U, 3 => $P, 4 => $P], ['reclassify' => true, 'split_domain' => true]);

        $R[] = self::r('db_cli_user', 'Database CLI -u / -U', $U, 'regex', 84,
            'mysql -u, psql -U, sqlcmd -U, mongo -u usage.',
            <<<'RE'
\b(?:mysql|mysqldump|mysqladmin|mariadb(?:-dump)?|mysqlimport|psql|pg_dump|pg_restore|mongo(?:sh|dump|restore)?|sqlcmd|bcp)\b[^\r\n]*?\s-[uU][ \t]*(?:"([^"\r\n]+)"|'([^'\r\n]+)'|([^\s"'\-][^\s"']*))
RE, '', '', [1 => $U, 2 => $U, 3 => $U], ['reclassify' => true]);

        $R[] = self::r('db_cli_pass', 'Database CLI password (-pXXX, -P x)', $P, 'regex', 91,
            'mysql -pPassword (attached), sqlcmd -P, mongo -p, redis-cli -a.',
            <<<'RE'
(?:\b(?:mysql|mysqldump|mysqladmin|mariadb(?:-dump)?|mysqlimport)\b[^\r\n]*?\s-p|\b(?:sqlcmd|bcp|mongo(?:sh|dump|restore)?|redis-cli)\b[^\r\n]*?\s-[pPa][ \t]+)(?:"([^"\r\n]*)"|'([^'\r\n]*)'|([^\s"'\-][^\s"']*))
RE, '', '', [1 => $P, 2 => $P, 3 => $P]);

        $R[] = self::r('db_cli_host', 'Database CLI server (-h, -S)', $H, 'regex', 80,
            'mysql -h, psql -h, redis-cli -h, sqlcmd -S.',
            <<<'RE'
(?:\b(?:mysql|mysqldump|mysqladmin|mariadb(?:-dump)?|psql|pg_dump|pg_restore|mongo(?:sh|dump|restore)?|redis-cli)\b[^\r\n]*?\s-h|\b(?:sqlcmd|bcp)\b[^\r\n]*?\s-S)[ \t]*(?:"([^"\r\n]+)"|([A-Za-z0-9][\w.\-]*))
RE, '', '', [1 => $H, 2 => $H], ['reclassify' => true]);

        $R[] = self::r('sshpass', 'sshpass -p', $P, 'regex', 92, 'sshpass -p password ssh ...',
            <<<'RE'
\bsshpass\s+-p\s*(?:"([^"\r\n]*)"|'([^'\r\n]*)'|(\S+))
RE, 'i', '', [1 => $P, 2 => $P, 3 => $P]);

        $R[] = self::r('curl_user', 'curl/wget -u user:password', $U, 'regex', 90,
            'curl -u user:pass, --user, --proxy-user usage.',
            <<<'RE'
\b(?:curl|wget)\b[^\r\n]*?\s(?:-u|--user|--http-user|--proxy-user|-U)(?:=|[ \t]+)(?:"([^":\r\n]+):([^"\r\n]*)"|'([^':\r\n]+):([^'\r\n]*)'|([^\s:"']+):([^\s"']+)|"([^"\r\n:]+)"|([^\s:"'\-][^\s:"']*))
RE, 'i', '', [1 => $U, 2 => $P, 3 => $U, 4 => $P, 5 => $U, 6 => $P, 7 => $U, 8 => $U]);

        $R[] = self::r('auth_tuple', 'auth=("user","password") tuple', $U, 'regex', 90,
            'Python requests auth=(...), HTTPBasicAuth(...), basic_auth=[...] usage.',
            <<<'RE'
\b(?:auth|HTTPBasicAuth|HTTPDigestAuth|HttpNtlmAuth|NtlmAuth|basic_auth|http_auth)\s*(?:=\s*)?[\(\[]\s*["']([^"'\r\n]+)["']\s*,\s*["']([^"'\r\n]*)["']
RE, 'i', '', [1 => $U, 2 => $P], ['reclassify' => true, 'split_domain' => true]);

        $R[] = self::r('php_define_secret', 'define(\'..PASS/KEY..\', \'x\')', $P, 'regex', 91,
            'Password, key, salt and token constants defined with PHP define().',
            <<<'RE'
\bdefine\s*\(\s*["']\w*(?:PASS(?:WORD|WD)?|PWD|SECRET|TOKEN|API_?KEY|PRIVATE_?KEY|SALT|AUTH_KEY|NONCE)\w*["']\s*,\s*["']([^"'\r\n]*)["']
RE, 'i', '', [1 => $P]);

        $R[] = self::r('php_define_user', 'define(\'..USER..\', \'x\')', $U, 'regex', 84,
            'User constants defined with PHP define() (DB_USER ...).',
            <<<'RE'
\bdefine\s*\(\s*["']\w*(?:USER(?:NAME)?|LOGIN)\w*["']\s*,\s*["']([^"'\r\n]+)["']
RE, 'i', '', [1 => $U], ['reclassify' => true]);

        $R[] = self::r('php_define_host', 'define(\'..HOST..\', \'x\')', $H, 'regex', 82,
            'Server constants defined with PHP define() (DB_HOST ...).',
            <<<'RE'
\bdefine\s*\(\s*["']\w*(?:HOST(?:NAME)?|SERVER)\w*["']\s*,\s*["']([^"'\r\n:]+)
RE, 'i', '', [1 => $H], ['reclassify' => true]);

        $R[] = self::r('expect_send_pass', 'expect "Password:" → send "..."', $P, 'regex', 90,
            'The value sent after the password prompt in Expect scripts.',
            <<<'RE'
expect\s+(?:-\S+\s+)*"[^"\r\n]*(?:assword|asscode|assphrase|PIN|parola|şifre)[^"\r\n]*"\s*\{?\s*(?:\r?\n\s*)?send\s+(?:-\S+\s+)*(?:--\s+)?"([^"\r\n]*?)(?:\\r|\\n)*"
RE, 'i', '', [1 => $P]);

        $R[] = self::r('expect_send_user', 'expect "Username:" → send "..."', $U, 'regex', 84,
            'The value sent after the username prompt in Expect scripts.',
            <<<'RE'
expect\s+(?:-\S+\s+)*"[^"\r\n]*(?:sername|ogin|kullanıcı)[^"\r\n]*"\s*\{?\s*(?:\r?\n\s*)?send\s+(?:-\S+\s+)*(?:--\s+)?"([^"\r\n]*?)(?:\\r|\\n)*"
RE, 'i', '', [1 => $U]);

        // ================================================================
        // NETWORK DEVICE CONFIGURATIONS (Cisco, Fortinet, Juniper, Huawei, HP/Aruba)
        // ================================================================
        $R[] = self::r('netcfg_typed_secret', 'Typed password/key (password 7 xxx, secret 5 xxx)', $P, 'regex', 93,
            'Cisco/Aruba "enable secret 5 ...", "password 7 ...", "key-string 7 ...", "pre-shared-key 0 ...".',
            <<<'RE'
(?<![\w\-])(?:enable[ \t]+)?(?:secret|password|key-string|key|pre-shared-key|psk|wpa-psk[ \t]+ascii|auth-password|priv-password)[ \t]+[0-9]{1,2}[ \t]+(\S+)
RE, 'i', '', [1 => $P]);

        $R[] = self::r('netcfg_line_password', 'password/secret at line start', $P, 'regex', 92,
            '"password xxx" / "enable secret xxx" appearing on its own as a configuration line.',
            <<<'RE'
^[ \t]*(?:enable[ \t]+(?:secret|password)|password|secret)[ \t]+(?![0-9]{1,2}[ \t])(\S+)[ \t]*$
RE, 'im', '', [1 => $P]);

        $R[] = self::r('netcfg_username', 'username X [privilege N] secret/password Y', $U, 'regex', 93,
            'Cisco/Arista/Aruba local user definitions.',
            <<<'RE'
^[ \t]*username[ \t]+(\S+)(?:[ \t]+privilege[ \t]+\d+)?(?:[ \t]+(?:role[ \t]+\S+[ \t]+)?(?:secret|password)(?:[ \t]+[0-9]{1,2})?[ \t]+(\S+))?
RE, 'im', '', [1 => $U, 2 => $P]);

        $R[] = self::r('netcfg_set_secret', 'Fortinet/Juniper "set password ..."', $P, 'regex', 93,
            'FortiGate "set password ENC ...", "set psksecret ...", Juniper set ... authentication encrypted-password.',
            <<<'RE'
^[ \t]*set[ \t]+(?:\S+[ \t]+)*?(?:password|passwd|psksecret|secret|passphrase|sso-password|ldap-password|auth-password|priv-password|community|encrypted-password|plain-text-password-value|pre-shared-key)[ \t]+(?:ENC[ \t]+)?(?:"([^"\r\n]*)"|(\S+))
RE, 'im', '', [1 => $P, 2 => $P]);

        $R[] = self::r('netcfg_juniper_user', 'Juniper "set system login user X"', $U, 'regex', 85,
            'Juniper user definitions.',
            <<<'RE'
\bset[ \t]+system[ \t]+login[ \t]+user[ \t]+(\S+)
RE, 'i', '', [1 => $U]);

        $R[] = self::r('netcfg_huawei_user', 'Huawei "local-user X password ..."', $U, 'regex', 88,
            'Huawei / H3C local-user definitions and passwords.',
            <<<'RE'
\blocal-user[ \t]+(\S+)(?:[ \t]+password[ \t]+(?:cipher|simple|irreversible-cipher)[ \t]+(\S+))?
RE, 'i', '', [1 => $U, 2 => $P]);

        $R[] = self::r('netcfg_snmp', 'SNMP community / v3 auth-priv key', $P, 'regex', 93,
            'snmp-server community X, snmp-server host IP [version 2c] X, auth sha X priv aes 128 Y.',
            <<<'RE'
\bsnmp-server[ \t]+community[ \t]+(\S+)|\bsnmp-server[ \t]+host[ \t]+\S+(?:[ \t]+(?:informs|traps))?(?:[ \t]+version[ \t]+(?:1|2c|3[ \t]+(?:auth|noauth|priv)))?[ \t]+(?!version\b|informs\b|traps\b)(\S+)|\b(?:auth[ \t]+(?:md5|sha\d*)|priv[ \t]+(?:des|3des|aes(?:[ \t]+\d+)?))[ \t]+(\S+)
RE, 'i', '', [1 => $P, 2 => $P, 3 => $P]);

        $R[] = self::r('netcfg_aaa_key', 'TACACS/RADIUS/NTP/OSPF keys', $P, 'regex', 92,
            'tacacs-server key, radius-server host ... key, ntp authentication-key N md5 X, message-digest-key N md5 X.',
            <<<'RE'
\b(?:tacacs-server|radius-server|tacacs|radius)\b[^\r\n]*?[ \t]key[ \t]+(?:[0-9][ \t]+)?(\S+)|\b(?:authentication-key|message-digest-key)[ \t]+\d+[ \t]+(?:md5|sha1|sha256|hmac-sha\S*)[ \t]+(?:[0-9][ \t]+)?(\S+)
RE, 'i', '', [1 => $P, 2 => $P]);

        $R[] = self::r('netcfg_hostname', 'Device hostname definition', $H, 'regex', 75,
            'hostname X, host-name X; (Juniper), sysname X (Huawei), set hostname "X" (Fortinet), switchname X (NX-OS).',
            <<<'RE'
^[ \t]*(?:set[ \t]+(?:system[ \t]+)?)?(?:hostname|host-name|sysname|switchname|system-name)[ \t]+"?([A-Za-z0-9][\w.\-]*)"?[ \t]*;?[ \t]*$
RE, 'im', '', [1 => $H], ['reclassify' => true]);

        $R[] = self::r('netcfg_domain', 'Device domain-name definition', $F, 'regex', 75,
            'ip domain-name X, ip domain name X, domain-name X, set system domain-name X.',
            <<<'RE'
^[ \t]*(?:ip[ \t]+domain[ \t\-]name|ip[ \t]+domain-list|domain-name|set[ \t]+system[ \t]+domain-name|dns-domain)[ \t]+(?:vrf[ \t]+\S+[ \t]+)?"?([A-Za-z0-9][\w.\-]*)"?[ \t]*;?[ \t]*$
RE, 'im', '', [1 => $F]);

        $R[] = self::r('netcfg_prompt', 'CLI prompt (SW01#, FW01(config)#)', $H, 'regex', 70,
            'The prompt name at the start of lines in network device CLI output.',
            <<<'RE'
^([A-Za-z][\w.\-]{1,62})(?:\([\w\-./]+\))?[#>](?=\s|$|[a-z])
RE, 'm', '', [1 => $H], ['reclassify' => true]);

        $R[] = self::r('netcfg_snmp_contact', 'snmp-server contact', 'PERSON', 'regex', 70,
            'SNMP contact information (person / e-mail / phone).',
            <<<'RE'
^[ \t]*snmp-server[ \t]+contact[ \t]+(.+?)[ \t]*$
RE, 'im', '', [1 => 'PERSON']);

        $R[] = self::r('netcfg_local_user', 'user X group Y password ciphertext Z (Aruba CX / Comware)', $P, 'regex', 94,
            'Local user definition: username as USER, password / hash as PASSWORD.',
            <<<'RE'
^[ \t]*user[ \t]+(\S+)[ \t]+group[ \t]+\S+[ \t]+password[ \t]+(?:ciphertext|plaintext|cipher|simple|hash|sha1|sha256|sha512)[ \t]+(\S+)
RE, 'im', '', [1 => $U, 2 => $P], ['propagate' => true]);

        $R[] = self::r('netcfg_snmp_location', 'SNMP system location (system-location / location)', 'LOCATION', 'regex', 72,
            'snmp-server system-location X, snmp-server location X, snmp-agent sys-info location X, set snmp location X.',
            <<<'RE'
^[ \t]*(?:snmp-server[ \t]+(?:system-)?location|snmp-agent[ \t]+sys-info[ \t]+location|set[ \t]+snmp[ \t]+location|snmp[ \t]+location)[ \t]+"?([^"\r\n]+?)"?[ \t]*;?[ \t]*$
RE, 'im', '', [1 => 'LOCATION']);

        $R[] = self::r('netcfg_snmp_syscontact', 'SNMP system contact (system-contact / sys-info contact)', 'PERSON', 'regex', 70,
            'snmp-server system-contact X, snmp-agent sys-info contact X, set snmp contact X.',
            <<<'RE'
^[ \t]*(?:snmp-server[ \t]+system-contact|snmp-agent[ \t]+sys-info[ \t]+contact|set[ \t]+snmp[ \t]+contact)[ \t]+"?([^"\r\n]+?)"?[ \t]*;?[ \t]*$
RE, 'im', '', [1 => 'PERSON']);

        $R[] = self::r('netcfg_vlan_label', 'VLAN block: name / description', 'NETLABEL', 'regex', 71,
            'The name (VLAN name) and description values on the indented lines of a "vlan N" block.',
            <<<'RE'
^[ \t]*vlan[ \t]+\d+[ \t]*\R(?:[ \t]+(?:name[ \t]+"?([^"\r\n]+?)"?[ \t]*;?[ \t]*|description[ \t]+"?([^"\r\n]+?)"?[ \t]*;?[ \t]*|(?!name\b|description\b)[^\r\n]*?)[ \t]*(?:\R|$))+
RE, 'im', '', [1 => 'NETLABEL', 2 => 'NETLABEL']);

        $R[] = self::r('netcfg_if_description', 'Interface / port description', 'NETLABEL', 'regex', 69,
            'Indented "description X" lines (interface, port-channel etc.). Off by default; descriptions give the AI useful context.',
            <<<'RE'
^[ \t]+description[ \t]+"?([^"\r\n]+?)"?[ \t]*;?[ \t]*$
RE, 'im', '', [1 => 'NETLABEL'], [], 'all', 0);

        // ================================================================
        // PASSWORD HASHES / LINUX ACCOUNT FILES
        // ================================================================
        $R[] = self::r('crypt_hash', 'crypt / bcrypt password hash', $P, 'regex', 85,
            'Unix password hashes starting with $1$, $5$, $6$, $2y$, $y$, $apr1$.',
            <<<'RE'
(?<![\w$])\$(?:1|2[abxy]?|5|6|7|8|9|y|gy|sha1|md5|apr1)\$[./A-Za-z0-9$=,\-]{8,}
RE);

        $R[] = self::r('ntlm_hash_pair', 'LM:NTLM hash pair', $S, 'regex', 80,
            'LM/NT hash pairs in the 32 hex : 32 hex form.',
            <<<'RE'
(?<![A-Fa-f0-9])[A-Fa-f0-9]{32}:[A-Fa-f0-9]{32}(?![A-Fa-f0-9])
RE);

        $R[] = self::r('secretsdump_user', 'secretsdump / pwdump line user', $U, 'regex', 85,
            'Lines in the DOMAIN\\user:RID:LM:NT::: form.',
            <<<'RE'
^(?:([^\\:\s]+)\\)?([^:\s\\]+):\d+:[A-Fa-f0-9]{32}:[A-Fa-f0-9]{32}:::
RE, 'm', '', [1 => $D, 2 => $U]);

        $R[] = self::r('shadow_line', '/etc/shadow line', $U, 'regex', 82,
            'Username of user:$6$...:19000:0:99999:7::: lines.',
            <<<'RE'
^([a-z_][a-z0-9_.\-]{0,31}\$?):(?:\$[^:\r\n]+|[!*]{1,2}[^:\r\n]*):\d*:\d*:\d*:
RE, 'm', '', [1 => $U]);

        $R[] = self::r('passwd_line', '/etc/passwd line', $U, 'regex', 82,
            'User and GECOS fields of user:x:1000:1000:Full Name,,,:/home/user:/bin/bash lines.',
            <<<'RE'
^([a-z_][a-z0-9_.\-]{0,31}\$?):[x*!]?:\d+:\d+:([^:,\r\n]*)[^:\r\n]*:[^:\r\n]*:
RE, 'm', '', [1 => $U, 2 => 'PERSON']);

        // ================================================================
        // NETWORK ADDRESSES
        // ================================================================
        $R[] = self::r('ipv4', 'IPv4 address', 'IP', 'regex', 60,
            'IPv4 with octet validation. Version numbers (10.0.19045.1) and subnet masks are excluded; kept ranges come from the settings.',
            <<<'RE'
(?<![\p{L}\p{N}_.])(?:(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)\.){3}(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?![\p{L}\p{N}_]|\.\d)
RE);

        $R[] = self::r('ipv6', 'IPv6 address', 'IP6', 'regex', 60,
            'Full and compressed (::) IPv6 forms, including zone ids (%eth0). Validated with filter_var.',
            <<<'RE'
(?<![\p{L}\p{N}_:.])(?:(?:[0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4}|(?:[0-9A-Fa-f]{1,4}:){1,7}:|(?:[0-9A-Fa-f]{1,4}:){1,6}:[0-9A-Fa-f]{1,4}|(?:[0-9A-Fa-f]{1,4}:){1,5}(?::[0-9A-Fa-f]{1,4}){1,2}|(?:[0-9A-Fa-f]{1,4}:){1,4}(?::[0-9A-Fa-f]{1,4}){1,3}|(?:[0-9A-Fa-f]{1,4}:){1,3}(?::[0-9A-Fa-f]{1,4}){1,4}|(?:[0-9A-Fa-f]{1,4}:){1,2}(?::[0-9A-Fa-f]{1,4}){1,5}|[0-9A-Fa-f]{1,4}:(?::[0-9A-Fa-f]{1,4}){1,6}|:(?:(?::[0-9A-Fa-f]{1,4}){1,7}|:))(?:%[\w.]+)?(?![\p{L}\p{N}_:]|\.\d)
RE);

        $R[] = self::r('mac', 'MAC address', 'MAC', 'regex', 65,
            'aa:bb:cc:dd:ee:ff, AA-BB-CC-DD-EE-FF and Cisco aabb.ccdd.eeff forms.',
            <<<'RE'
(?<![\p{L}\p{N}_:.\-])(?:[0-9A-Fa-f]{2}([:\-])(?:[0-9A-Fa-f]{2}\1){4}[0-9A-Fa-f]{2}|[0-9A-Fa-f]{4}\.[0-9A-Fa-f]{4}\.[0-9A-Fa-f]{4})(?![\p{L}\p{N}_:\-]|\.[0-9A-Fa-f])
RE, '', '', [0 => 'MAC']);

        $R[] = self::r('fqdn', 'FQDN / domain name', $F, 'regex', 55,
            'Domain names ending in a known TLD (from the settings): srv01.corp.local, portal.acme.local. File names (config.json) are skipped because they are not in the TLD list.',
            <<<'RE'
(?<![\p{L}\p{N}_.@\-])((?:[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?\.)+(?:{{TLD}}))(?![\p{L}\p{N}_\-(]|\.[\p{L}\p{N}])
RE, 'i', '', [1 => $F]);

        $R[] = self::r('url_host', 'Server name in a URL', $H, 'regex', 62,
            'Host part of URLs, including dotless names such as http://intranet/, smb://nas01/, ldap://dc01:389.',
            <<<'RE'
\b(?:https?|ftps?|sftp|ssh|ldaps?|smb|cifs|nfs|rdp|vnc|telnet|mysql|mariadb|postgres(?:ql)?|mssql|sqlserver|jdbc:[a-z]+|redis|mongodb(?:\+srv)?|amqps?|mqtts?|kafka|smtps?|imaps?|pop3s?|wss?|tcp|udp|git|svn)://(?:[^\s/@:"'<>]+(?::[^\s/@"'<>]*)?@)?(?:\[([0-9A-Fa-f:.]+)\]|([A-Za-z0-9_](?:[A-Za-z0-9_\-]*[A-Za-z0-9_])?(?:\.[A-Za-z0-9_](?:[A-Za-z0-9_\-]*[A-Za-z0-9_])?)*))
RE, 'i', '', [1 => $H, 2 => $H], ['reclassify' => true]);

        $R[] = self::r('unc_host', 'UNC path server (\\\\srv\\share)', $H, 'regex', 70,
            '\\\\server\\share and JSON-escaped \\\\\\\\server forms.',
            <<<'RE'
(?<![\p{L}\p{N}_:\\/.])\\{2,4}([A-Za-z0-9][A-Za-z0-9.\-_]{0,62})(?=[\\/\s"'`,;]|$)
RE, '', '', [1 => $H], ['reclassify' => true]);

        $R[] = self::r('syslog_host', 'Hostname in the syslog header', $H, 'regex', 66,
            'The field after the timestamp in "Nov  2 10:00:01 srv01 ..." (BSD) lines; in lines with ISO timestamps only if followed by "program[pid]:".',
            <<<'RE'
^(?:<\d{1,3}>)?(?:[A-Z][a-z]{2}[ \t]+\d{1,2}[ \t]+\d{2}:\d{2}:\d{2}(?:\.\d+)?[ \t]+(?!(?:TRACE|DEBUG|INFO|NOTICE|WARN(?:ING)?|ERROR|ERR|CRIT(?:ICAL)?|ALERT|EMERG|FATAL|SEVERE|last|message)\b)([A-Za-z0-9][A-Za-z0-9._\-]{0,62})(?=[ \t])|\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:[.,]\d+)?(?:Z|[+\-]\d{2}:?\d{2})?[ \t]+(?!(?:TRACE|DEBUG|INFO|NOTICE|WARN(?:ING)?|ERROR|ERR|CRIT(?:ICAL)?|ALERT|EMERG|FATAL|SEVERE)\b)([A-Za-z0-9][A-Za-z0-9._\-]{0,62})[ \t]+(?=[\w\-/.]+(?:\[\d+\])?:))
RE, 'm', '', [1 => $H, 2 => $H], ['reclassify' => true], 'log');

        $R[] = self::r('syslog5424_host', 'RFC5424 syslog hostname', $H, 'regex', 66,
            'The "<34>1 2024-01-01T10:00:00Z host app ..." form.',
            <<<'RE'
^<\d{1,3}>\d[ \t]+\S+[ \t]+([A-Za-z0-9][A-Za-z0-9._\-]{0,62})[ \t]
RE, 'm', '', [1 => $H], ['reclassify' => true], 'log');

        $R[] = self::r('win_default_host', 'Windows default computer name', $H, 'regex', 58,
            'Automatic names in the DESKTOP-XXXXXXX, LAPTOP-XXXXXXX, WIN-XXXXXXXX form.',
            <<<'RE'
\b(?:DESKTOP|LAPTOP|WIN|WS|PC|NB)-[A-Z0-9]{6,15}\b
RE);

        $R[] = self::r('host_naming_example', 'Organization naming pattern (example – edit it)', $H, 'regex', 57,
            'Edit it to match your organization\'s server/device naming standard and enable it. E.g. SRV-DC01, SW-CORE-01, FW_EDGE1.',
            <<<'RE'
\b(?:SRV|SW|FW|RTR|AP|DC|NAC|ESX|VM|PRN)[\-_][A-Z0-9][A-Z0-9\-_]{1,30}\b
RE, '', '', [], [], 'all', 0);

        $R[] = self::r('user_at_host', 'user@host (ssh, prompt)', $U, 'regex', 70,
            'ssh root@srv01, [jdoe@web01 ~]$, scp x jdoe@10.0.0.5: forms.',
            <<<'RE'
(?<![\p{L}\p{N}_.%+\-/@])([A-Za-z_][A-Za-z0-9_.\-]{0,31})@((?:\d{1,3}\.){3}\d{1,3}|[A-Za-z0-9][A-Za-z0-9\-]{0,62})(?![\p{L}\p{N}_@\-]|\.[A-Za-z0-9])
RE, '', '', [1 => $U, 2 => $H], ['reclassify' => true]);

        $R[] = self::r('email_split', 'E-mail (user + domain separately)', $U, 'regex', 80,
            'john.smith@acme.local → <USER_1>@<FQDN_1>. Public domains (gmail.com etc.) stay visible according to the list in the settings.',
            <<<'RE'
(?<![\w.%+\-])([A-Za-z0-9][A-Za-z0-9._%+\-]*)@((?:[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,})(?![\w\-])
RE, '', '', [1 => $U, 2 => $F], ['propagate' => false]);

        $R[] = self::r('email_whole', 'E-mail (whole)', 'EMAIL', 'regex', 81,
            'To mask an e-mail as a single token, enable this rule and disable "E-mail (user + domain separately)".',
            <<<'RE'
(?<![\w.%+\-])[A-Za-z0-9][A-Za-z0-9._%+\-]*@(?:[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,}(?![\w\-])
RE, '', '', [], [], 'all', 0);

        // ================================================================
        // USER CONTEXTS
        // ================================================================
        $R[] = self::r('auth_log_user', 'Authentication log patterns', $U, 'regex', 76,
            'sshd "Failed password for (invalid user) X", "Invalid user X", "session opened for user X", "Login failed for user", "user X logged in", "Disconnected from user X" ...',
            <<<'RE'
\b(?:(?:Failed|Accepted|Invalid|Postponed)[ \t]+(?:password|publickey|keyboard-interactive(?:/pam)?|none|gssapi-\S+|hostbased)[ \t]+for(?:[ \t]+invalid[ \t]+user)?|(?:invalid|illegal|unknown)[ \t]+user|for[ \t]+(?:invalid[ \t]+)?user|by[ \t]+user|user[ \t]+(?=\S+[ \t]+(?:from|logged|login|authenticated|failed|succeeded|session|connected|disconnected|locked|unlocked))|authenticating[ \t]+user|log(?:in|on)[ \t]+(?:failed[ \t]+)?(?:for|by|of)(?:[ \t]+user)?|(?:Disconnected[ \t]+from|Connection[ \t]+closed[ \t]+by|Received[ \t]+disconnect[ \t]+from)(?:[ \t]+(?:invalid|authenticating))?[ \t]+user|session[ \t]+(?:opened|closed)[ \t]+for[ \t]+user)[ \t]+(?!(?:invalid|user|from|for|to|the|a|an|is|was|has|not|unknown)\b)["'\[<]?([A-Za-z0-9_$][A-Za-z0-9_.@$\\\-]{0,63})
RE, 'i', '', [1 => $U], ['reclassify' => true, 'split_domain' => true]);

        $R[] = self::r('quoted_user', 'User \'x\' / user "x" / user [x]', $U, 'regex', 77,
            'Usernames given in quotes/square brackets in network device and application logs.',
            <<<'RE'
\buser(?:name)?[ \t]+(?:'([^'\r\n]{1,64})'|"([^"\r\n]{1,64})"|\[([^\]\r\n]{1,64})\]|<([^>\r\n]{1,64})>)
RE, 'i', '', [1 => $U, 2 => $U, 3 => $U, 4 => $U], ['reclassify' => true, 'split_domain' => true]);

        $R[] = self::r('sudo_su_user', 'sudo / su log user', $U, 'regex', 78,
            '"sudo:   jdoe : TTY=pts/0 ; ..." ve "su: (to root) jdoe on pts/0".',
            <<<'RE'
\bsudo(?:\[\d+\])?:[ \t]+([A-Za-z0-9_][A-Za-z0-9_.\-]*)[ \t]+:|\bsu(?:\[\d+\])?:[ \t]+\(to[ \t]+(\S+)\)[ \t]+(\S+)
RE, '', '', [1 => $U, 2 => $U, 3 => $U]);

        $R[] = self::r('win_domain_user', 'DOMAIN\\user', $U, 'regex', 75,
            'Uppercase NetBIOS domain + backslash + user (CORP\\john). Registry roots (HKLM\\...) are excluded.',
            <<<'RE'
(?<![\p{L}\p{N}_.\\:\-/])(?!(?:HKLM|HKCU|HKCR|HKU|HKCC|HKEY_[A-Z_]+|BUILTIN|WINDOWS|SYSTEM32|SYSWOW64|PROGRA~\d|NT)\\)([A-Z][A-Z0-9_\-]{1,14})\\{1,2}([A-Za-z0-9][A-Za-z0-9._$\-]{0,63})
RE, '', '', [1 => $D, 2 => $U], ['reclassify' => true]);

        $R[] = self::r('win_profile_path', 'Windows profile path (C:\\Users\\X)', $U, 'regex', 79,
            'C:\\Users\\john.smith\\..., C:\\Documents and Settings\\X, JSON-escaped paths.',
            <<<'RE'
\b[A-Za-z]:[\\/]+(?:Users|Documents and Settings)[\\/]+([^\\/\s:*?"<>|%$]+(?: [^\\/\s:*?"<>|%$]+(?=[\\/]))?)
RE, 'i', '', [1 => $U]);

        $R[] = self::r('unix_home_path', 'Linux/macOS home directory (/home/X, /Users/X)', $U, 'regex', 79,
            'Username in /home/jdoe/..., /Users/jdoe/... paths.',
            <<<'RE'
(?<![\p{L}\p{N}_.\-])/(?:home|Users|var/mail|var/spool/mail)/([A-Za-z0-9_][A-Za-z0-9_.\-]{0,63})(?=[/\s"':;,)]|$)
RE, '', '', [1 => $U]);

        $R[] = self::r('evtx_xml_user', 'Windows event XML: user fields', $U, 'regex', 80,
            '<Data Name="TargetUserName">X</Data>, SubjectUserName, SamAccountName ...',
            <<<'RE'
<Data\s+Name\s*=\s*["'](?:Target|Subject|Caller|Member|Old|New)?(?:UserName|User|AccountName|SamAccountName|UserPrincipalName)["']\s*>([^<]{1,256})</Data>
RE, 'i', '', [1 => $U], ['reclassify' => true, 'split_domain' => true]);

        $R[] = self::r('evtx_xml_domain', 'Windows event XML: domain fields', $D, 'regex', 80,
            '<Data Name="TargetDomainName">CORP</Data> ...',
            <<<'RE'
<Data\s+Name\s*=\s*["'](?:Target|Subject|Caller)?DomainName["']\s*>([^<]{1,256})</Data>
RE, 'i', '', [1 => $D], ['reclassify' => true]);

        $R[] = self::r('evtx_xml_host', 'Windows event XML: computer fields', $H, 'regex', 80,
            '<Computer>X</Computer>, <Data Name="WorkstationName">X</Data> ...',
            <<<'RE'
<Computer>([^<]{1,256})</Computer>|<Data\s+Name\s*=\s*["'](?:WorkstationName|Workstation|ComputerName|TargetServerName|Hostname|ClientName)["']\s*>([^<]{1,256})</Data>
RE, 'i', '', [1 => $H, 2 => $H], ['reclassify' => true]);

        $R[] = self::r('ldap_dn', 'LDAP DN (CN=..., DC=...)', $U, 'regex', 74,
            'CN=John Smith,OU=Users,DC=corp,DC=local → the CN value is masked as a person/user and the DC chain as a single FQDN token.',
            <<<'RE'
\b(?:CN|UID)=([^,=\r\n"]+?)(?=,\s*(?:OU|DC|CN|O|L)=)|\bDC=([A-Za-z0-9\-]+(?:,[ \t]*DC=[A-Za-z0-9\-]+)*)
RE, 'i', '', [1 => $U, 2 => $F], ['reclassify' => true]);

        $R[] = self::r('ldap_ou', 'LDAP OU names', 'CUSTOM', 'regex', 73,
            'Organizational unit names (OU=Accounting). Enable it to hide your organization\'s structure.',
            <<<'RE'
\bOU=([^,=\r\n"]+)
RE, 'i', '', [1 => 'CUSTOM'], [], 'all', 0);

        $R[] = self::r('user_dotted_known', 'first.last (with a known name)', $U, 'regex', 68,
            'first.last / first.last.x users whose first part is in the first-name dictionary (settings): ahmet.yilmaz, john.smith. Runs in all modes.',
            <<<'RE'
(?<![\p{L}\p{N}_.@/\\\-])((?:{{FIRSTNAME}})\.\p{L}{2,30}(?:\.\p{L}{2,30})?)(?![\p{L}\p{N}_@(\-]|\.[\p{L}\p{N}])
RE, 'i', '', [1 => $U], ['validate' => 'dotted']);

        $R[] = self::r('user_dotted_generic', 'first.last (generic pattern)', $U, 'regex', 52,
            'Lowercase first.last pattern even if the name is not in the dictionary. Code tokens and file extensions are excluded. Log mode only.',
            <<<'RE'
(?<![\p{L}\p{N}_.@/\\:\-])([a-zçğıöşü]{2,20}\.[a-zçğıöşü]{2,25})(?![\p{L}\p{N}_@(\-]|\.[\p{L}\p{N}])
RE, '', '', [1 => $U], ['validate' => 'dotted'], 'log');

        $R[] = self::r('person_name_space', 'First Last (with a known name)', 'PERSON', 'regex', 54,
            'Full names starting with a name from the dictionary whose second word is capitalized: "John Smith", "AYŞE KAYA".',
            <<<'RE'
(?<![\p{L}])((?:{{FIRSTNAME_CAP}})[ \t]+\p{Lu}[\p{L}'’\-]{1,30}(?:[ \t]+\p{Lu}[\p{L}'’\-]{1,30})?)(?![\p{L}])
RE, '', '', [1 => 'PERSON'], ['validate' => 'person']);

        $R[] = self::r('sid', 'Windows / Azure AD SID', 'SID', 'regex', 70,
            'S-1-5-21-x-y-z-RID and S-1-12-1-... forms (excluding well-known short SIDs).',
            <<<'RE'
\bS-1-(?:5-21|12-1)(?:-\d+){3,4}\b
RE);

        // ================================================================
        // PERSONAL DATA (KVKK / GDPR)
        // ================================================================
        $R[] = self::r('phone_tr', 'Phone (TR)', 'PHONE', 'regex', 45,
            '+90 5xx xxx xx xx, 0 (212) xxx xx xx forms.',
            <<<'RE'
(?<![\w+.])(?:(?:\+|00)90[\s\-.]?|0)\(?[2-5]\d{2}\)?[\s\-.]?\d{3}[\s\-.]?\d{2}[\s\-.]?\d{2}(?![\w])
RE, '', '', [], ['validate' => 'phone']);

        $R[] = self::r('tckn', 'Turkish ID number (TCKN)', 'TCKN', 'regex', 46,
            '11-digit TCKN with a verified checksum.',
            <<<'RE'
(?<![\w.\-])[1-9]\d{10}(?![\w.\-])
RE, '', '', [], ['validate' => 'tckn']);

        $R[] = self::r('iban', 'IBAN', 'IBAN', 'regex', 47,
            'TR and international IBAN (mod-97 verified).',
            <<<'RE'
\b[A-Z]{2}\d{2}(?:[ ]?[A-Z0-9]{4}){2,7}(?:[ ]?[A-Z0-9]{1,4})?\b
RE, '', '', [], ['validate' => 'iban']);

        $R[] = self::r('card', 'Card number', 'CARD', 'regex', 44,
            '13-19 digit card numbers verified with Luhn and the BIN prefix.',
            <<<'RE'
(?<![\w\-])(?:\d[ \-]?){12,18}\d(?![\w\-])
RE, '', '', [], ['validate' => 'luhn']);

        // ================================================================
        // GENERIC / CUSTOM
        // ================================================================
        $R[] = self::r('generic_high_entropy', 'High-entropy secret value', $S, 'regex', 40,
            'Strings of 32+ characters with letters and digits whose Shannon entropy is above the threshold (default 4.0). Hex hashes and GUIDs stay below the threshold.',
            <<<'RE'
(?<![A-Za-z0-9+/=_\-.])(?=[A-Za-z0-9+_\-]*\d)(?=[A-Za-z0-9+_\-]*[A-Za-z])[A-Za-z0-9+_\-]{32,}={0,2}(?![A-Za-z0-9+/=_\-])
RE, '', '', [], ['validate' => 'entropy', 'entropy_min' => 4.0]);

        $R[] = self::r('custom_terms', 'Custom dictionary (organization terms)', 'CUSTOM', 'keyword', 99,
            'One term per line. Write "term | CATEGORY" to choose a category. E.g. "ACME-DC01 | HOST", "Project Falcon".',
            '', '', implode("\n", [
                '# Write one term per line. Optional: term | CATEGORY',
                '# EXAMPLE-SERVER01 | HOST',
                '# Project Falcon',
            ]));

        foreach ($R as $i => &$r) {
            $r['sort'] = ($i + 1) * 10;
        }
        return $R;
    }

    // ------------------------------------------------------------------
    // Sample data (entirely made up)
    // ------------------------------------------------------------------
    public static function samples(): array
    {
        $log = <<<'TXT'
Nov  2 09:14:07 srv-web01 sshd[2231]: Failed password for invalid user admin from 203.0.113.9 port 52144 ssh2
Nov  2 09:14:11 srv-web01 sshd[2231]: Accepted publickey for ahmet.yilmaz from 10.20.30.41 port 51822 ssh2: RSA SHA256:Qk2l8pK3n1yX0
Nov  2 09:15:02 srv-web01 sudo:   ahmet.yilmaz : TTY=pts/0 ; PWD=/home/ahmet.yilmaz ; USER=root ; COMMAND=/usr/bin/systemctl restart nginx
Nov  2 09:16:40 fw-edge01 date=2026-11-02 time=09:16:40 devname="FW-EDGE01" logid="0100032001" type="event" subtype="system" user="mehmet.kaya" ui="https(10.20.30.55)" action="login" status="success" msg="Administrator mehmet.kaya logged in successfully from https(10.20.30.55)"
Nov  2 09:17:12 sw-core01 %SEC_LOGIN-5-LOGIN_SUCCESS: Login Success [user: netadmin] [Source: 10.20.1.5] [localport: 22] at 09:17:12 UTC Mon Nov 2 2026
Nov  2 09:18:30 ise01 CISE_Passed_Authentications 0000123 1 0 2026-11-02 09:18:30.112 +03:00 0000044 5200 NOTICE Passed-Authentication: Authentication succeeded, User-Name=CORP\zeynep.demir, NAS-IP-Address=10.20.1.11, NAS-Identifier=SW-ACC-03, Calling-Station-ID=3C-52-82-1A-BE-07, Framed-IP-Address=10.20.44.120, EndpointMACAddress=3C:52:82:1A:BE:07
2026-11-02 09:19:01,442 ERROR [db-pool] Connection failed: jdbc:mysql://db01.corp.local:3306/crm?user=crm_app&password=Cr4zyP@ss!2026
2026-11-02 09:20:15 INFO  api-gw request GET https://portal.acme.local/api/v1/users Authorization: Bearer eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJhaG1ldCIsInJvbGUiOiJhZG1pbiJ9.dGhpcy1pcy1ub3QtYS1yZWFsLXNpZw
2026-11-02 09:21:44 WARN  ldap bind as CN=Ayşe Kaya,OU=IT,DC=corp,DC=local failed (data 52e) client 2001:db8:85a3::8a2e:370:7334
2026-11-02 09:22:03 INFO  vpn-gw session opened for maria.garcia from 10.20.44.131, approved by Lukas Schneider
2026-11-02 09:22:48 WARN  mail-gw blocked attachment from priya.sharma@acme.local to Pierre Dubois
2026-11-02 09:23:15 INFO  helpdesk ticket 48213 assigned to kenji.tanaka, requested by Fatima Al-Sayed
An account failed to log on.
Subject:
	Security ID:		S-1-5-21-3623811015-3361044348-30300820-1013
	Account Name:		DESKTOP-7KQ2M9X$
	Account Domain:		CORP
Account For Which Logon Failed:
	Account Name:		can.ozturk
	Account Domain:		CORP
Network Information:
	Workstation Name:	LAPTOP-AB12CD3
	Source Network Address:	192.168.10.77
	Source Port:		50211
Profile path: C:\Users\can.ozturk\AppData\Local\Temp ; share \\nas01\finans ; mail: can.ozturk@acme.local
TXT;
        $script = <<<'TXT'
#!/usr/bin/env bash
# Backup script - copies from db01 to the NAS
DB_HOST="db01.corp.local"
DB_USER="backup_svc"
DB_PASS='B@ckup#2026!'
export AWS_SECRET_ACCESS_KEY="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY99"
mysqldump -h 10.20.30.15 -u root -pR00tP4ss! crm > /backup/crm.sql
sshpass -p 'S3cretSsh' scp /backup/crm.sql backup@nas01:/volume1/db/
curl -u ahmet.yilmaz:Qwerty123 https://jenkins.corp.local/job/backup/build
TXT;
        $ps = <<<'TXT'
$Server   = "SRV-DC01.corp.local"
$User     = "CORP\svc_deploy"
$Password = ConvertTo-SecureString "Depl0y!2026" -AsPlainText -Force
$Cred     = New-Object System.Management.Automation.PSCredential("CORP\svc_deploy", $Password)
Invoke-Command -ComputerName SRV-APP02 -Credential $Cred -ScriptBlock { Get-Service w3svc }
net use Z: \\fs01\paylasim Pa55w0rd /user:CORP\zeynep.demir
$apiKey = "sk-proj-AbCdEfGhIjKlMnOpQrStUvWxYz0123456789"
Write-Host "Connection: https://admin:Adm1nPass@10.20.30.40:8443/console"
TXT;
        $cisco = <<<'TXT'
hostname SW-CORE-01
ip domain-name corp.local
enable secret 5 $1$mERr$hx5rVt7rPNoS4wqbXKX7m0
username netadmin privilege 15 secret 9 $9$nhEmQVczB7dqsO$X.HsgL6x1il0RxkOSSvyQYwucySCt7qFm4v4f/rpEvk
!
interface Vlan10
 ip address 10.20.10.1 255.255.255.0
!
snmp-server community Kurum2026RO RO
snmp-server host 10.20.1.50 version 2c Kurum2026RO
tacacs-server host 10.20.1.60 key 7 0822455D0A16
ntp authentication-key 1 md5 NtpK3y!
line vty 0 4
 password 7 094F471A1A0A
SW-CORE-01#show run | include username
TXT;
        return ['log' => $log, 'bash' => $script, 'powershell' => $ps, 'network' => $cisco];
    }
}
