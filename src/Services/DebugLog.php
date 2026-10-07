<?php

/**
 * Journal de debug commun (HelloAsso, Mistral, SMTP).
 *
 * - Le dossier logs/ est verrouillé (.htaccess + index.html) à chaque écriture,
 *   y compris quand l'application est déployée sans DocumentRoot sur public/.
 * - Les secrets (client_secret, jetons, mots de passe, clés API) sont masqués.
 * - Chaque fichier est plafonné : au-delà de MAX_BYTES il est remis à zéro.
 */
class DebugLog {
    const MAX_BYTES = 2 * 1024 * 1024;

    const SECRET_KEYS = [
        'client_secret', 'clientsecret', 'access_token', 'refresh_token', 'id_token',
        'password', 'pass', 'smtppass', 'adminpassword', 'apikey', 'api_key', 'mistralapikey',
        'authorization', 'token',
    ];

    public static function dir(): string {
        return __DIR__ . '/../../logs';
    }

    public static function write(string $file, string $message): void {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            error_log("DebugLog: impossible de créer le répertoire de logs $dir");
            return;
        }
        self::protect($dir);

        $path = $dir . '/' . basename($file);
        if (is_file($path) && filesize($path) > self::MAX_BYTES) {
            @file_put_contents($path, '');
        }
        @file_put_contents($path, $message, FILE_APPEND | LOCK_EX);
        @chmod($path, 0640);
    }

    /** Bloque tout accès HTTP au dossier, quelle que soit la version d'Apache. */
    public static function protect(string $dir): void {
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess,
                "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n" .
                "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n" .
                "Options -Indexes\n");
        }
        if (!is_file($dir . '/index.html')) {
            @file_put_contents($dir . '/index.html', '');
        }
    }

    /** Masque récursivement les valeurs dont la clé désigne un secret. */
    public static function redact($data) {
        if (!is_array($data)) {
            return is_string($data) ? self::redactString($data) : $data;
        }
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SECRET_KEYS, true)) {
                $data[$key] = '***';
            } else {
                $data[$key] = self::redact($value);
            }
        }
        return $data;
    }

    /** Masque les secrets restés dans une chaîne (corps brut, en-tête Bearer). */
    public static function redactString(string $text): string {
        $text = preg_replace('/(Bearer\s+)[A-Za-z0-9._~+\/=-]+/i', '$1***', $text);
        $keys = implode('|', array_map('preg_quote', self::SECRET_KEYS));
        $text = preg_replace('/("(?:' . $keys . ')"\s*:\s*)"[^"]*"/i', '$1"***"', $text);
        return preg_replace('/((?:' . $keys . ')=)[^&\s]+/i', '$1***', $text);
    }

    /** jean.dupont@gmail.com -> j***@gmail.com */
    public static function maskEmail(string $email): string {
        $at = strpos($email, '@');
        if ($at === false) return '***';
        return substr($email, 0, 1) . '***' . substr($email, $at);
    }
}
