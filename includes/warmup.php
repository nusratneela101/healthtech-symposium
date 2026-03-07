<?php
/**
 * WarmupManager — Email warm-up system.
 *
 * Calculates daily sending limits that gradually increase over a configurable
 * number of days (default 21), allowing a new sender to build reputation.
 *
 * Settings stored in site_settings:
 *   warmup_enabled      — 1 / 0
 *   warmup_start_date   — YYYY-MM-DD when warm-up began
 *   warmup_days         — total days of warm-up period (default 21)
 *   warmup_start_volume — emails on day 1 (default 20)
 *   warmup_max_volume   — cap reached at end of period (default 500)
 */
class WarmupManager {

    // ── Public API ─────────────────────────────────────────────────────────

    /**
     * Returns the daily sending cap for today, or null when warm-up is off.
     */
    public static function getDailyLimit(): ?int {
        if (!self::isEnabled()) {
            return null;
        }

        $startDate  = getSetting('warmup_start_date', '');
        if (!$startDate) {
            return (int)getSetting('warmup_start_volume', 20);
        }

        $days       = max(1, (int)getSetting('warmup_days', 21));
        $startVol   = max(1, (int)getSetting('warmup_start_volume', 20));
        $maxVol     = max($startVol, (int)getSetting('warmup_max_volume', 500));

        $dayNumber  = self::currentDay($startDate);

        if ($dayNumber > $days) {
            // Warm-up complete — return max volume (caller may also disable warm-up)
            return $maxVol;
        }

        // Linear ramp from $startVol to $maxVol over $days days
        $limit = (int)round($startVol + ($maxVol - $startVol) * (($dayNumber - 1) / max(1, $days - 1)));
        return max($startVol, min($limit, $maxVol));
    }

    /**
     * Returns progress information for display.
     */
    public static function getProgress(): array {
        $enabled    = self::isEnabled();
        $startDate  = getSetting('warmup_start_date', '');
        $days       = max(1, (int)getSetting('warmup_days', 21));
        $startVol   = max(1, (int)getSetting('warmup_start_volume', 20));
        $maxVol     = max($startVol, (int)getSetting('warmup_max_volume', 500));

        $dayNumber  = $startDate ? self::currentDay($startDate) : 0;
        $completed  = $dayNumber > $days;
        $dailyLimit = self::getDailyLimit();

        // Build schedule for display (one row per day)
        $schedule = [];
        for ($d = 1; $d <= $days; $d++) {
            $vol = (int)round($startVol + ($maxVol - $startVol) * (($d - 1) / max(1, $days - 1)));
            $schedule[] = [
                'day'    => $d,
                'volume' => max($startVol, min($vol, $maxVol)),
                'done'   => $d < $dayNumber,
                'today'  => $d === $dayNumber,
            ];
        }

        return [
            'enabled'     => $enabled,
            'start_date'  => $startDate,
            'days'        => $days,
            'start_vol'   => $startVol,
            'max_vol'     => $maxVol,
            'current_day' => $dayNumber,
            'completed'   => $completed,
            'daily_limit' => $dailyLimit,
            'schedule'    => $schedule,
        ];
    }

    /**
     * Checks whether the daily warm-up limit has been reached today.
     * Returns ['allowed' => bool, 'reason' => string, 'limit' => int, 'sent' => int]
     */
    public static function checkLimit(): array {
        $limit = self::getDailyLimit();
        if ($limit === null) {
            return ['allowed' => true, 'reason' => '', 'limit' => 0, 'sent' => 0];
        }

        $sent = self::sentToday();

        if ($sent >= $limit) {
            return [
                'allowed' => false,
                'reason'  => "Warm-up daily limit of {$limit} reached ({$sent} sent today).",
                'limit'   => $limit,
                'sent'    => $sent,
            ];
        }

        return ['allowed' => true, 'reason' => '', 'limit' => $limit, 'sent' => $sent];
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private static function isEnabled(): bool {
        return (bool)(int)getSetting('warmup_enabled', '0');
    }

    /** Day-number since warm-up start (1-based). */
    private static function currentDay(string $startDate): int {
        try {
            $start = new DateTime($startDate);
            $now   = new DateTime('today');
            $diff  = $start->diff($now);
            return max(1, (int)$diff->days + 1);
        } catch (Exception $e) {
            return 1;
        }
    }

    /** Emails sent today (from email_logs). */
    private static function sentToday(): int {
        try {
            $row = Database::fetchOne(
                "SELECT COUNT(*) AS c FROM email_logs WHERE status='sent' AND DATE(sent_at)=CURDATE()"
            );
            return (int)($row['c'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
}
