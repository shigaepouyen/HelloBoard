<?php

class SessionService {
    private static $sessionStarted = false;

    public static function start($rememberMe = false) {
        if (self::$sessionStarted) return;

        $sessionPath = __DIR__ . '/../../config/sessions';
        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0755, true);
            file_put_contents($sessionPath . '/.htaccess', "Deny from all");
        }

        // 30 days in seconds
        $lifetime = 30 * 24 * 60 * 60;

        // Force session storage in our local folder to avoid system GC
        ini_set('session.save_path', $sessionPath);

        // Increase GC lifetime to 30 days
        ini_set('session.gc_maxlifetime', $lifetime);

        // Ensure cookie is HTTP only for security
        ini_set('session.cookie_httponly', 1);

        // If remember me is checked OR we already have a long-lived session
        if ($rememberMe || (isset($_COOKIE[session_name()]) && strlen($_COOKIE[session_name()]) > 0)) {
            // We set cookie lifetime. Note: if $rememberMe is false but cookie exists,
            // we keep it as it is (browser will send it if not expired).
            // But to be proactive, if we are starting a session and want it to last:
            if ($rememberMe) {
                session_set_cookie_params($lifetime);
            }
        }

        session_start();
        self::$sessionStarted = true;
    }

    public static function destroy() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();

        // Clear cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
    }
}