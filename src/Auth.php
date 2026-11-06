<?php
declare(strict_types=1);

final class Auth
{
    public static function check(): bool
    {
        mk_session();
        if (empty($_SESSION['mk_admin'])) {
            return false;
        }
        $ttl = max(5, (int)Settings::get('session_minutes', '60')) * 60;
        if (time() - (int)($_SESSION['mk_seen'] ?? 0) > $ttl) {
            self::logout();
            return false;
        }
        $_SESSION['mk_seen'] = time();
        return true;
    }

    public static function login(string $password): bool
    {
        mk_session();
        $fails = (int)($_SESSION['mk_fails'] ?? 0);
        $last = (int)($_SESSION['mk_fail_at'] ?? 0);
        if ($fails >= 5 && time() - $last < 300) {
            throw new InvalidArgumentException(t('Too many failed attempts. Try again in 5 minutes.'));
        }
        $hash = (string)Settings::get('admin_hash', '');
        if ($hash !== '' && password_verify($password, $hash)) {
            session_regenerate_id(true);
            $_SESSION['mk_admin'] = true;
            $_SESSION['mk_seen'] = time();
            $_SESSION['mk_fails'] = 0;
            Repo::audit('login', 'Successful login');
            return true;
        }
        usleep(400000);
        $_SESSION['mk_fails'] = $fails + 1;
        $_SESSION['mk_fail_at'] = time();
        Repo::audit('login.fail', 'Wrong password');
        return false;
    }

    public static function logout(): void
    {
        mk_session();
        unset($_SESSION['mk_admin'], $_SESSION['mk_seen']);
        session_regenerate_id(true);
    }

    public static function csrf(): string
    {
        mk_session();
        if (empty($_SESSION['mk_csrf'])) {
            $_SESSION['mk_csrf'] = bin2hex(random_bytes(24));
        }
        return $_SESSION['mk_csrf'];
    }

    public static function verifyCsrf(): bool
    {
        $sent = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return $sent !== '' && hash_equals(self::csrf(), $sent);
    }

    public static function changePassword(string $current, string $new): void
    {
        $hash = (string)Settings::get('admin_hash', '');
        if ($hash === '' || !password_verify($current, $hash)) {
            throw new InvalidArgumentException(t('Current password is incorrect.'));
        }
        if (mb_strlen($new) < 8) {
            throw new InvalidArgumentException(t('The new password must be at least 8 characters.'));
        }
        Settings::set(['admin_hash' => password_hash($new, PASSWORD_DEFAULT)]);
        Repo::audit('password.change', 'Admin password changed');
    }
}
