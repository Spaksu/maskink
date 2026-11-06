<?php
declare(strict_types=1);

final class Settings
{
    private static ?array $cache = null;

    /** Defaults merged with database values */
    public static function all(): array
    {
        if (self::$cache === null) {
            $vals = Seeds::settings();
            foreach (Db::all('SELECT k, v FROM mk_settings') as $row) {
                $vals[$row['k']] = $row['v'];
            }
            self::$cache = $vals;
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(array $kv): void
    {
        $st = Db::pdo()->prepare(
            'INSERT INTO mk_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)'
        );
        foreach ($kv as $k => $v) {
            $st->execute([(string)$k, is_bool($v) ? ($v ? '1' : '0') : (string)$v]);
        }
        self::$cache = null;
    }

    /** Keys editable on the Settings screen, with their types */
    public static function editable(): array
    {
        return [
            'placeholder_template' => 'string',
            'propagate'            => 'bool',
            'propagate_min_len'    => 'int',
            'default_mode'         => 'enum:auto,log,script',
            'keep_netmasks'        => 'bool',
            'keep_values'          => 'text',
            'keep_cidrs'           => 'text',
            'keep_domains'         => 'text',
            'tlds'                 => 'text',
            'first_names'          => 'text',
            'dotted_deny'          => 'text',
            'code_deny'            => 'text',
            'fqdn_deny_first'      => 'text',
            'person_stopwords'     => 'text',
            'risk_keywords'        => 'string',
            'max_input_kb'         => 'int',
            'mask_requires_login'  => 'bool',
            'log_usage'            => 'bool',
            'session_minutes'      => 'int',
            'chat_default_model'   => 'string',
            'chat_temperature'     => 'string',
            'chat_system_prompt'   => 'text',
        ];
    }

    /** Validates and stores values coming from the settings form */
    public static function saveFromInput(array $in): array
    {
        $out = [];
        foreach (self::editable() as $k => $type) {
            if (!array_key_exists($k, $in)) {
                continue;
            }
            $v = $in[$k];
            if ($type === 'bool') {
                $out[$k] = filter_var($v, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            } elseif ($type === 'int') {
                $out[$k] = (string)max(0, (int)$v);
            } elseif (str_starts_with($type, 'enum:')) {
                $allowed = explode(',', substr($type, 5));
                if (!in_array($v, $allowed, true)) {
                    throw new InvalidArgumentException(t('Invalid value: {key}', ['key' => $k]));
                }
                $out[$k] = $v;
            } else {
                $out[$k] = str_replace("\r\n", "\n", (string)$v);
            }
        }
        if (isset($out['placeholder_template']) && trim($out['placeholder_template']) === '') {
            throw new InvalidArgumentException(t('The placeholder template cannot be empty.'));
        }
        if (isset($out['risk_keywords']) && $out['risk_keywords'] !== ''
            && @preg_match("\x01" . $out['risk_keywords'] . "\x01iu", '') === false) {
            throw new InvalidArgumentException(t('The risk keyword regex is invalid.'));
        }
        if (isset($out['chat_temperature'])) {
            $t = str_replace(',', '.', trim($out['chat_temperature']));
            if ($t !== '' && (!is_numeric($t) || (float)$t < 0 || (float)$t > 2)) {
                throw new InvalidArgumentException(t('Temperature must be between 0 and 2 (empty = model default).'));
            }
            $out['chat_temperature'] = $t;
        }
        self::set($out);
        return array_keys($out);
    }
}
