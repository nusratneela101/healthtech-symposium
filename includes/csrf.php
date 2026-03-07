<?php
/**
 * CSRF Protection class.
 * Generates and validates CSRF tokens to prevent Cross-Site Request Forgery attacks.
 */
class CSRF {
    private const TOKEN_KEY    = '_csrf_token';
    private const EXPIRY_KEY   = '_csrf_expiry';
    private const TOKEN_TTL    = 3600; // 1 hour

    /**
     * Generate (or reuse a valid) CSRF token and store it in the session.
     */
    public static function generateToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Reuse existing token if it has not expired
        if (!empty($_SESSION[self::TOKEN_KEY]) && !empty($_SESSION[self::EXPIRY_KEY])
            && $_SESSION[self::EXPIRY_KEY] > time()) {
            return $_SESSION[self::TOKEN_KEY];
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_KEY]  = $token;
        $_SESSION[self::EXPIRY_KEY] = time() + self::TOKEN_TTL;
        return $token;
    }

    /**
     * Return the current token, generating one if necessary.
     */
    public static function getToken(): string {
        return self::generateToken();
    }

    /**
     * Validate the supplied token using a timing-safe comparison.
     */
    public static function validateToken(string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $stored  = $_SESSION[self::TOKEN_KEY]  ?? '';
        $expiry  = $_SESSION[self::EXPIRY_KEY] ?? 0;

        if (empty($stored) || $expiry < time()) {
            return false;
        }

        return hash_equals($stored, $token);
    }

    /**
     * Render a hidden HTML input containing the CSRF token.
     */
    public static function field(): string {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }

    /**
     * Render a <meta> tag for use by AJAX requests.
     */
    public static function metaTag(): string {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<meta name="csrf-token" content="' . $token . '">';
    }

    /**
     * Validate the CSRF token from the request and exit with a 403 if invalid.
     * Checks POST body first, then the X-CSRF-Token header (AJAX).
     */
    public static function check(): void {
        $token = $_POST['_csrf_token']
            ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!self::validateToken($token)) {
            http_response_code(403);
            if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid or expired CSRF token.']);
            } else {
                echo 'Invalid or expired CSRF token.';
            }
            exit;
        }

        // Token remains valid until it expires (TTL-based reuse is intentional
        // to support multi-tab and concurrent form submissions).
    }

    /**
     * Force generation of a fresh token.
     */
    public static function regenerate(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_KEY]  = $token;
        $_SESSION[self::EXPIRY_KEY] = time() + self::TOKEN_TTL;
        return $token;
    }
}
