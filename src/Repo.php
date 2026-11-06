<?php
declare(strict_types=1);

final class Repo
{
    public const TYPES = ['regex', 'keyvalue', 'keyword'];
    public const MODES = ['all', 'log', 'script'];
    public const VALIDATORS = ['', 'luhn', 'tckn', 'iban', 'phone', 'entropy', 'dotted', 'person'];

    // ------------------------------------------------------------------
    // Schema / seeding
    // ------------------------------------------------------------------

    public static function schema(): array
    {
        $tail = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS mk_settings (
                k VARCHAR(64) NOT NULL PRIMARY KEY,
                v MEDIUMTEXT NOT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) $tail",
            "CREATE TABLE IF NOT EXISTS mk_categories (
                code VARCHAR(32) NOT NULL PRIMARY KEY,
                label VARCHAR(100) NOT NULL,
                token VARCHAR(32) NOT NULL,
                color VARCHAR(16) NOT NULL DEFAULT '#64748b',
                placeholder VARCHAR(120) NULL,
                description VARCHAR(255) NOT NULL DEFAULT '',
                propagate TINYINT(1) NOT NULL DEFAULT 0,
                case_sensitive TINYINT(1) NOT NULL DEFAULT 0,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                builtin TINYINT(1) NOT NULL DEFAULT 0,
                sort INT NOT NULL DEFAULT 0
            ) $tail",
            "CREATE TABLE IF NOT EXISTS mk_rules (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                bkey VARCHAR(64) NULL,
                name VARCHAR(150) NOT NULL,
                description TEXT NULL,
                category VARCHAR(32) NOT NULL,
                type VARCHAR(16) NOT NULL,
                pattern MEDIUMTEXT NULL,
                flags VARCHAR(16) NOT NULL DEFAULT '',
                keys_text MEDIUMTEXT NULL,
                groups_json TEXT NULL,
                options_json TEXT NULL,
                priority INT NOT NULL DEFAULT 50,
                modes VARCHAR(16) NOT NULL DEFAULT 'all',
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                builtin TINYINT(1) NOT NULL DEFAULT 0,
                sort INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_bkey (bkey),
                KEY ix_cat (category)
            ) $tail",
            "CREATE TABLE IF NOT EXISTS mk_audit (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                at DATETIME NOT NULL,
                action VARCHAR(64) NOT NULL,
                detail TEXT NULL,
                ip VARCHAR(45) NOT NULL DEFAULT '',
                KEY ix_at (at)
            ) $tail",
            "CREATE TABLE IF NOT EXISTS mk_usage (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                at DATETIME NOT NULL,
                mode VARCHAR(16) NOT NULL,
                chars_in INT UNSIGNED NOT NULL DEFAULT 0,
                matches INT UNSIGNED NOT NULL DEFAULT 0,
                uniq INT UNSIGNED NOT NULL DEFAULT 0,
                ms DECIMAL(10,1) NOT NULL DEFAULT 0,
                KEY ix_at (at)
            ) $tail",
        ];
    }

    public static function createSchema(PDO $pdo): void
    {
        foreach (self::schema() as $sql) {
            $pdo->exec($sql);
        }
    }

    /** Loads the defaults. With $overwrite=true the built-in rules/categories are reset. */
    public static function seed(bool $overwrite): array
    {
        $now = date('Y-m-d H:i:s');
        $pdo = Db::pdo();
        $nCat = 0;
        $nRule = 0;

        $catSql = $overwrite
            ? 'REPLACE INTO mk_categories (code,label,token,color,placeholder,description,propagate,case_sensitive,enabled,builtin,sort) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            : 'INSERT IGNORE INTO mk_categories (code,label,token,color,placeholder,description,propagate,case_sensitive,enabled,builtin,sort) VALUES (?,?,?,?,?,?,?,?,?,?,?)';
        $st = $pdo->prepare($catSql);
        foreach (Seeds::categories() as $c) {
            $st->execute([$c['code'], $c['label'], $c['token'], $c['color'], $c['placeholder'], $c['description'],
                $c['propagate'], $c['case_sensitive'], $c['enabled'], 1, $c['sort']]);
            $nCat += $st->rowCount() > 0 ? 1 : 0;
        }

        foreach (Seeds::rules() as $r) {
            $exists = Db::one('SELECT id FROM mk_rules WHERE bkey = ?', [$r['bkey']]);
            if ($exists && !$overwrite) {
                continue;
            }
            $vals = [
                $r['name'], $r['description'], $r['category'], $r['type'], $r['pattern'], $r['flags'],
                $r['keys_text'], self::encodeGroups($r['groups']), json_encode((object)$r['options'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $r['priority'], $r['modes'], $r['enabled'], $r['sort'], $now,
            ];
            if ($exists) {
                Db::exec('UPDATE mk_rules SET name=?, description=?, category=?, type=?, pattern=?, flags=?, keys_text=?, groups_json=?, options_json=?, priority=?, modes=?, enabled=?, sort=?, updated_at=?, builtin=1 WHERE id=?',
                    [...$vals, (int)$exists['id']]);
            } else {
                Db::exec('INSERT INTO mk_rules (name, description, category, type, pattern, flags, keys_text, groups_json, options_json, priority, modes, enabled, sort, updated_at, created_at, bkey, builtin) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)',
                    [...$vals, $now, $r['bkey']]);
            }
            $nRule++;
        }

        $st = $pdo->prepare('INSERT IGNORE INTO mk_settings (k, v) VALUES (?, ?)');
        foreach (Seeds::settings() as $k => $v) {
            if ($k === 'admin_hash') {
                continue;
            }
            $st->execute([$k, $v]);
        }
        return ['categories' => $nCat, 'rules' => $nRule];
    }

    // ------------------------------------------------------------------
    // Kurallar
    // ------------------------------------------------------------------

    public static function encodeGroups(array $g): string
    {
        $o = [];
        foreach ($g as $k => $v) {
            $o[(string)(int)$k] = (string)$v;
        }
        return json_encode((object)$o);
    }

    public static function decodeGroups(?string $json): array
    {
        $out = [];
        foreach ((array)json_decode((string)$json, true) as $k => $v) {
            $out[(int)$k] = (string)$v;
        }
        ksort($out);
        return $out;
    }

    public static function normRule(array $r): array
    {
        return [
            'id'          => (int)$r['id'],
            'bkey'        => $r['bkey'],
            'name'        => $r['name'],
            'description' => (string)$r['description'],
            'category'    => $r['category'],
            'type'        => $r['type'],
            'pattern'     => (string)$r['pattern'],
            'flags'       => (string)$r['flags'],
            'keys_text'   => (string)$r['keys_text'],
            'groups'      => self::decodeGroups($r['groups_json']),
            'options'     => (array)(json_decode((string)$r['options_json'], true) ?: []),
            'priority'    => (int)$r['priority'],
            'modes'       => $r['modes'],
            'enabled'     => (int)$r['enabled'],
            'builtin'     => (int)$r['builtin'],
            'sort'        => (int)$r['sort'],
            'updated_at'  => $r['updated_at'],
        ];
    }

    public static function rules(): array
    {
        return array_map([self::class, 'normRule'], Db::all('SELECT * FROM mk_rules ORDER BY priority DESC, sort, id'));
    }

    public static function rule(int $id): ?array
    {
        $r = Db::one('SELECT * FROM mk_rules WHERE id = ?', [$id]);
        return $r ? self::normRule($r) : null;
    }

    /** Cleans/validates a rule coming from the form (without saving) */
    public static function sanitizeRule(array $in): array
    {
        $cats = array_column(self::categories(), 'code');
        $name = trim((string)($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) {
            throw new InvalidArgumentException(t('The rule name must be 1-150 characters.'));
        }
        $cat = (string)($in['category'] ?? '');
        if (!in_array($cat, $cats, true)) {
            throw new InvalidArgumentException(t('Invalid category.'));
        }
        $type = (string)($in['type'] ?? 'regex');
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(t('Invalid rule type.'));
        }
        $modes = (string)($in['modes'] ?? 'all');
        if (!in_array($modes, self::MODES, true)) {
            throw new InvalidArgumentException(t('Invalid mode.'));
        }
        $groups = [];
        foreach ((array)($in['groups'] ?? []) as $k => $v) {
            if (is_array($v)) { // [{group:1, category:'USER'}] form
                $k = $v['group'] ?? null;
                $v = $v['category'] ?? null;
            }
            if ($k === null || $k === '' || !is_numeric($k) || (int)$k < 0 || (int)$k > 99) {
                continue;
            }
            if (!in_array($v, $cats, true)) {
                throw new InvalidArgumentException(t('Invalid category for group {n}.', ['n' => $k]));
            }
            $groups[(int)$k] = (string)$v;
        }
        $o = (array)($in['options'] ?? []);
        $opt = [];
        $affix = (string)($o['affix'] ?? 'none');
        if (in_array($affix, ['prefix', 'both'], true)) {
            $opt['affix'] = $affix;
        }
        $seps = (string)($o['seps'] ?? 'std');
        if (in_array($seps, ['eq', 'space'], true)) {
            $opt['seps'] = $seps;
        }
        foreach (['value_regex', 'skip_regex'] as $k) {
            if (isset($o[$k]) && trim((string)$o[$k]) !== '') {
                $opt[$k] = trim((string)$o[$k]);
            }
        }
        foreach (['min_len', 'max_len'] as $k) {
            if (!empty($o[$k]) && (int)$o[$k] > 0) {
                $opt[$k] = (int)$o[$k];
            }
        }
        foreach (['skip_numeric', 'reclassify', 'split_domain', 'xml', 'case_sensitive'] as $k) {
            if (!empty($o[$k]) && filter_var($o[$k], FILTER_VALIDATE_BOOLEAN)) {
                $opt[$k] = true;
            }
        }
        foreach (['skip_vars', 'propagate'] as $k) { // options that default to on
            if (array_key_exists($k, $o) && !filter_var($o[$k], FILTER_VALIDATE_BOOLEAN)) {
                $opt[$k] = false;
            }
        }
        $val = (string)($o['validate'] ?? '');
        if (!in_array($val, self::VALIDATORS, true)) {
            throw new InvalidArgumentException(t('Invalid validator.'));
        }
        if ($val !== '') {
            $opt['validate'] = $val;
            if ($val === 'entropy') {
                $opt['entropy_min'] = max(1.0, min(8.0, (float)($o['entropy_min'] ?? 4.0)));
            }
        }

        $rule = [
            'id'          => (int)($in['id'] ?? 0),
            'name'        => $name,
            'description' => trim((string)($in['description'] ?? '')),
            'category'    => $cat,
            'type'        => $type,
            'pattern'     => $type === 'regex' ? trim((string)($in['pattern'] ?? '')) : '',
            'flags'       => preg_replace('/[^imsxU]/', '', (string)($in['flags'] ?? '')),
            'keys_text'   => $type === 'regex' ? '' : str_replace("\r\n", "\n", (string)($in['keys_text'] ?? '')),
            'groups'      => $type === 'regex' ? $groups : [],
            'options'     => $opt,
            'priority'    => max(0, min(1000, (int)($in['priority'] ?? 50))),
            'modes'       => $modes,
            'enabled'     => !empty($in['enabled']) ? 1 : 0,
        ];
        if ($type === 'regex' && $rule['pattern'] === '') {
            throw new InvalidArgumentException(t('The regex pattern cannot be empty.'));
        }
        if ($type !== 'regex' && !Engine::lines($rule['keys_text'], $type === 'keyvalue')) {
            if ($type === 'keyvalue') {
                throw new InvalidArgumentException('En az bir anahtar girin.');
            }
        }
        $engine = self::engine([$rule]);
        $engine->compile($rule); // hata varsa InvalidArgumentException
        return $rule;
    }

    public static function saveRule(array $in): int
    {
        $r = self::sanitizeRule($in);
        $now = date('Y-m-d H:i:s');
        $vals = [$r['name'], $r['description'], $r['category'], $r['type'], $r['pattern'], $r['flags'], $r['keys_text'],
            self::encodeGroups($r['groups']), json_encode((object)$r['options'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $r['priority'], $r['modes'], $r['enabled'], $now];
        if ($r['id'] > 0) {
            if (!self::rule($r['id'])) {
                throw new InvalidArgumentException(t('Rule not found.'));
            }
            Db::exec('UPDATE mk_rules SET name=?, description=?, category=?, type=?, pattern=?, flags=?, keys_text=?, groups_json=?, options_json=?, priority=?, modes=?, enabled=?, updated_at=? WHERE id=?', [...$vals, $r['id']]);
            self::audit('rule.update', "#{$r['id']} {$r['name']}");
            return $r['id'];
        }
        $sort = (int)(Db::one('SELECT COALESCE(MAX(sort),0)+10 AS s FROM mk_rules')['s'] ?? 0);
        Db::exec('INSERT INTO mk_rules (name, description, category, type, pattern, flags, keys_text, groups_json, options_json, priority, modes, enabled, updated_at, created_at, sort, builtin) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)', [...$vals, $now, $sort]);
        $id = Db::lastId();
        self::audit('rule.create', "#$id {$r['name']}");
        return $id;
    }

    public static function deleteRule(int $id): void
    {
        $r = self::rule($id) ?? throw new InvalidArgumentException(t('Rule not found.'));
        if ($r['builtin']) {
            throw new InvalidArgumentException(t('Built-in rules cannot be deleted; you can disable them.'));
        }
        Db::exec('DELETE FROM mk_rules WHERE id = ?', [$id]);
        self::audit('rule.delete', "#$id {$r['name']}");
    }

    public static function toggleRule(int $id, bool $on): void
    {
        $r = self::rule($id) ?? throw new InvalidArgumentException(t('Rule not found.'));
        Db::exec('UPDATE mk_rules SET enabled = ?, updated_at = ? WHERE id = ?', [$on ? 1 : 0, date('Y-m-d H:i:s'), $id]);
        self::audit($on ? 'rule.enable' : 'rule.disable', "#$id {$r['name']}");
    }

    public static function resetRule(int $id): void
    {
        $r = self::rule($id) ?? throw new InvalidArgumentException(t('Rule not found.'));
        foreach (Seeds::rules() as $s) {
            if ($s['bkey'] === $r['bkey']) {
                Db::exec('UPDATE mk_rules SET name=?, description=?, category=?, type=?, pattern=?, flags=?, keys_text=?, groups_json=?, options_json=?, priority=?, modes=?, enabled=?, updated_at=? WHERE id=?', [
                    $s['name'], $s['description'], $s['category'], $s['type'], $s['pattern'], $s['flags'], $s['keys_text'],
                    self::encodeGroups($s['groups']), json_encode((object)$s['options'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $s['priority'], $s['modes'], $s['enabled'], date('Y-m-d H:i:s'), $id,
                ]);
                self::audit('rule.reset', "#$id {$s['name']}");
                return;
            }
        }
        throw new InvalidArgumentException(t('This rule is not built in; it has no default.'));
    }

    public static function duplicateRule(int $id): int
    {
        $r = self::rule($id) ?? throw new InvalidArgumentException(t('Rule not found.'));
        $r['id'] = 0;
        $r['name'] = mb_substr($r['name'] . ' (kopya)', 0, 150);
        $r['enabled'] = 0;
        return self::saveRule($r);
    }

    // ------------------------------------------------------------------
    // Kategoriler
    // ------------------------------------------------------------------

    public static function categories(): array
    {
        return array_map(function ($c) {
            foreach (['propagate', 'case_sensitive', 'enabled', 'builtin', 'sort'] as $k) {
                $c[$k] = (int)$c[$k];
            }
            return $c;
        }, Db::all('SELECT * FROM mk_categories ORDER BY sort, code'));
    }

    public static function saveCategory(array $in, bool $isNew): void
    {
        $code = strtoupper(trim((string)($in['code'] ?? '')));
        if (!preg_match('/^[A-Z][A-Z0-9_]{1,31}$/', $code)) {
            throw new InvalidArgumentException(t('The code must be 2-32 characters of uppercase letters, digits and underscores.'));
        }
        $label = trim((string)($in['label'] ?? ''));
        $token = trim((string)($in['token'] ?? $code));
        $color = trim((string)($in['color'] ?? '#64748b'));
        $ph = trim((string)($in['placeholder'] ?? ''));
        if ($label === '' || mb_strlen($label) > 100) {
            throw new InvalidArgumentException(t('The label must be 1-100 characters.'));
        }
        if (!preg_match('/^[A-Za-z0-9_\-]{1,32}$/', $token)) {
            throw new InvalidArgumentException(t('The token prefix may contain letters, digits, _ and -.'));
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            throw new InvalidArgumentException(t('The color must be in #RRGGBB format.'));
        }
        $exists = Db::one('SELECT * FROM mk_categories WHERE code = ?', [$code]);
        if ($isNew && $exists) {
            throw new InvalidArgumentException('Bu kod zaten var.');
        }
        $vals = [$label, $token, $color, $ph === '' ? null : mb_substr($ph, 0, 120), mb_substr(trim((string)($in['description'] ?? '')), 0, 255),
            !empty($in['propagate']) ? 1 : 0, !empty($in['case_sensitive']) ? 1 : 0, !empty($in['enabled']) ? 1 : 0, (int)($in['sort'] ?? 500)];
        if ($exists) {
            Db::exec('UPDATE mk_categories SET label=?, token=?, color=?, placeholder=?, description=?, propagate=?, case_sensitive=?, enabled=?, sort=? WHERE code=?', [...$vals, $code]);
            self::audit('category.update', $code);
        } else {
            Db::exec('INSERT INTO mk_categories (label, token, color, placeholder, description, propagate, case_sensitive, enabled, sort, code, builtin) VALUES (?,?,?,?,?,?,?,?,?,?,0)', [...$vals, $code]);
            self::audit('category.create', $code);
        }
    }

    public static function deleteCategory(string $code): void
    {
        $c = Db::one('SELECT * FROM mk_categories WHERE code = ?', [$code]) ?? throw new InvalidArgumentException(t('Category not found.'));
        if ((int)$c['builtin']) {
            throw new InvalidArgumentException(t('Built-in categories cannot be deleted.'));
        }
        $n = (int)Db::one('SELECT COUNT(*) AS n FROM mk_rules WHERE category = ?', [$code])['n'];
        if ($n > 0) {
            throw new InvalidArgumentException("Bu kategoriyi kullanan $n kural var.");
        }
        Db::exec('DELETE FROM mk_categories WHERE code = ?', [$code]);
        self::audit('category.delete', $code);
    }

    // ------------------------------------------------------------------
    // Engine factory
    // ------------------------------------------------------------------

    public static function engine(?array $rules = null): Engine
    {
        return new Engine($rules ?? self::rules(), self::categories(), Settings::all(), (string)Settings::get('app_secret', ''));
    }

    /** Compiles all rules and returns the broken ones */
    public static function validateAll(): array
    {
        $engine = self::engine();
        $bad = [];
        foreach (self::rules() as $r) {
            try {
                $engine->compile($r);
            } catch (InvalidArgumentException $e) {
                $bad[] = ['id' => $r['id'], 'name' => $r['name'], 'error' => $e->getMessage()];
            }
        }
        return $bad;
    }

    // ------------------------------------------------------------------
    // Audit / usage
    // ------------------------------------------------------------------

    public static function audit(string $action, string $detail = ''): void
    {
        try {
            Db::exec('INSERT INTO mk_audit (at, action, detail, ip) VALUES (?,?,?,?)', [date('Y-m-d H:i:s'), $action, mb_substr($detail, 0, 2000), mk_client_ip()]);
        } catch (Throwable) {
        }
    }

    public static function auditList(int $limit = 200): array
    {
        return Db::all('SELECT * FROM mk_audit ORDER BY id DESC LIMIT ' . max(1, min(1000, $limit)));
    }

    public static function logUsage(array $stats): void
    {
        if (!(int)Settings::get('log_usage', '1')) {
            return;
        }
        try {
            Db::exec('INSERT INTO mk_usage (at, mode, chars_in, matches, uniq, ms) VALUES (?,?,?,?,?,?)',
                [date('Y-m-d H:i:s'), $stats['mode'], $stats['chars_in'], $stats['matches'], $stats['unique'], $stats['ms']]);
        } catch (Throwable) {
        }
    }

    public static function usageStats(): array
    {
        $tot = Db::one('SELECT COUNT(*) AS runs, COALESCE(SUM(chars_in),0) AS chars, COALESCE(SUM(matches),0) AS matches, COALESCE(AVG(ms),0) AS avg_ms FROM mk_usage');
        $days = Db::all("SELECT DATE(at) AS d, COUNT(*) AS runs, SUM(matches) AS matches FROM mk_usage WHERE at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(at) ORDER BY d");
        $rules = Db::one('SELECT COUNT(*) AS total, SUM(enabled) AS enabled, SUM(builtin=0) AS custom FROM mk_rules');
        return ['total' => $tot, 'days' => $days, 'rules' => $rules];
    }

    // ------------------------------------------------------------------
    // Export / import
    // ------------------------------------------------------------------

    public static function export(): array
    {
        $settings = array_intersect_key(Settings::all(), Settings::editable());
        return [
            'app'         => 'maskink',
            'version'     => MK_VERSION,
            'exported_at' => date('c'),
            'settings'    => $settings,
            'categories'  => self::categories(),
            'rules'       => array_map(function ($r) {
                unset($r['id'], $r['updated_at']);
                return $r;
            }, self::rules()),
        ];
    }

    public static function import(array $data, bool $replaceCustom): array
    {
        if (!in_array($data['app'] ?? '', ['maskink', 'maskara'], true)) {
            throw new InvalidArgumentException(t('This file is not a Maskink export.'));
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            foreach ((array)($data['categories'] ?? []) as $c) {
                self::saveCategory($c, false);
            }
            if (isset($data['settings']) && is_array($data['settings'])) {
                Settings::saveFromInput($data['settings']);
            }
            if ($replaceCustom) {
                Db::exec('DELETE FROM mk_rules WHERE builtin = 0');
            }
            $n = 0;
            foreach ((array)($data['rules'] ?? []) as $r) {
                $existing = !empty($r['bkey'])
                    ? Db::one('SELECT id FROM mk_rules WHERE bkey = ?', [$r['bkey']])
                    : Db::one('SELECT id FROM mk_rules WHERE builtin = 0 AND name = ? AND type = ? AND category = ?', [(string)($r['name'] ?? ''), (string)($r['type'] ?? ''), (string)($r['category'] ?? '')]);
                $r['id'] = $existing ? (int)$existing['id'] : 0;
                self::saveRule($r);
                $n++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        self::audit('import', "$n rules imported");
        return ['rules' => $n];
    }
}
