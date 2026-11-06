<?php
declare(strict_types=1);

define('MK_ROOT', dirname(__DIR__));
define('MK_VERSION', '1.0.0');
define('MK_APP', 'Maskink');

mb_internal_encoding('UTF-8');
ini_set('pcre.backtrack_limit', '10000000');
ini_set('pcre.recursion_limit', '1000000');
date_default_timezone_set('Europe/Istanbul');

require_once __DIR__ . '/I18n.php';
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Seeds.php';
require_once __DIR__ . '/Engine.php';
require_once __DIR__ . '/Repo.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Llm.php';

/** Contents of config.php, or null when it is missing */
function mk_config(): ?array
{
    static $cfg = false;
    if ($cfg === false) {
        $file = MK_ROOT . '/config.php';
        $cfg = is_file($file) ? (require $file) : null;
    }
    return is_array($cfg) ? $cfg : null;
}

const MK_SCHEMA_VERSION = '2';

/**
 * Prepares the database: creates the database and tables when missing and loads
 * the default rules. No setup step is needed; costs a single query per request.
 * Returns an error message on failure, null on success.
 */
function mk_boot(): ?string
{
    static $err = false;
    if ($err !== false) {
        return $err;
    }
    $cfg = mk_config();
    if ($cfg === null) {
        return $err = t('config.php not found.');
    }
    try {
        try {
            $pdo = Db::pdo();
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1049 && !str_contains($e->getMessage(), '1049')) {
                throw $e;
            }
            $root = Db::connect($cfg['db'], false);
            $root->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $cfg['db']['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo = Db::pdo();
        }
        try {
            $ver = $pdo->query("SELECT v FROM mk_settings WHERE k = 'schema_version'")->fetchColumn();
        } catch (PDOException) {
            $ver = false;
        }
        if ($ver !== MK_SCHEMA_VERSION) {
            Repo::createSchema($pdo);
            Repo::seed(false);
            Settings::set(['schema_version' => MK_SCHEMA_VERSION]);
            if ((string)Settings::get('app_secret', '') === '') {
                Settings::set(['app_secret' => bin2hex(random_bytes(32))]);
            }
        }
        return $err = null;
    } catch (Throwable $e) {
        return $err = t('Could not connect to the database ({error}). Check the connection settings in config.php.', ['error' => $e->getMessage()]);
    }
}

/** Shows a plain error page when the database is not ready */
function mk_boot_or_die(): void
{
    $err = mk_boot();
    if ($err === null) {
        return;
    }
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>' . MK_APP . '</title>'
        . '<div style="font:14px system-ui;max-width:640px;margin:15vh auto;padding:24px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:12px">'
        . '<b>' . h(t('{app} could not start', ['app' => MK_APP])) . '</b><p>' . h($err) . '</p></div>';
    exit;
}

function h(mixed $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mk_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('MASKINKSESS');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

/** Client IP (used only for the audit log) */
function mk_client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45);
}
