<?php
/**
 * Sliding-window rate limiter.
 * Stores request counts in the rate_limits DB table.
 */
class RateLimiter {
    /**
     * Check whether a request is allowed. Returns true if allowed, false if
     * the limit has been exceeded.
     *
     * @param string $identifier  IP address or API key
     * @param string $endpoint    E.g. 'api/save_lead'
     * @param int    $limit       Max requests per window
     * @param int    $windowSecs  Window size in seconds (default 60)
     */
    public static function check(string $identifier, string $endpoint, int $limit = 60, int $windowSecs = 60): bool {
        try {
            $windowStart = date('Y-m-d H:i:s', time() - $windowSecs);

            // Count requests within the current window
            $row = Database::fetchOne(
                "SELECT SUM(requests) AS total FROM rate_limits
                 WHERE identifier = ? AND endpoint = ? AND window_start >= ?",
                [$identifier, $endpoint, $windowStart]
            );
            $total = (int)($row['total'] ?? 0);

            if ($total >= $limit) {
                return false;
            }

            // Upsert — try to increment the current minute bucket
            $bucket = date('Y-m-d H:i:00');
            $existing = Database::fetchOne(
                "SELECT id, requests FROM rate_limits WHERE identifier = ? AND endpoint = ? AND window_start = ?",
                [$identifier, $endpoint, $bucket]
            );
            if ($existing) {
                Database::query(
                    "UPDATE rate_limits SET requests = requests + 1 WHERE id = ?",
                    [$existing['id']]
                );
            } else {
                Database::query(
                    "INSERT INTO rate_limits (identifier, endpoint, requests, window_start) VALUES (?, ?, 1, ?)",
                    [$identifier, $endpoint, $bucket]
                );
            }

            // Prune old records (keep last 2 windows)
            $pruneTime = date('Y-m-d H:i:s', time() - ($windowSecs * 2));
            Database::query(
                "DELETE FROM rate_limits WHERE endpoint = ? AND window_start < ?",
                [$endpoint, $pruneTime]
            );

            return true;
        } catch (Exception $e) {
            // If rate_limits table not yet created, allow request
            return true;
        }
    }

    /**
     * Enforce the rate limit, sending a 429 JSON response if exceeded.
     */
    public static function enforce(string $identifier, string $endpoint, int $limit = 60, int $windowSecs = 60): void {
        if (!self::check($identifier, $endpoint, $limit, $windowSecs)) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Too many requests. Please slow down.', 'retry_after' => $windowSecs]);
            exit;
        }
    }

    /**
     * Get the caller's identifier (IP address).
     * Uses only REMOTE_ADDR to prevent spoofing via X-Forwarded-For.
     * If behind a trusted reverse proxy, configure trusted IPs in your web server
     * and let it set REMOTE_ADDR to the real client IP.
     */
    public static function getIdentifier(): string {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
