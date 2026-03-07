<?php
/**
 * Check whether sending another email is permitted under the configured limits.
 *
 * @param  int  $followUpSeq  1 = normal campaign email, >1 = follow-up
 * @return array  ['allowed' => bool, 'reason' => string]
 */
function checkSendingLimits(int $followUpSeq = 1): array {
    $isFollowup = ($followUpSeq > 1);

    // ── Warm-up check (applies to all sequences) ─────────────────────────
    require_once __DIR__ . '/../includes/warmup.php';
    $warmupCheck = WarmupManager::checkLimit();
    if (!$warmupCheck['allowed']) {
        return ['allowed' => false, 'reason' => $warmupCheck['reason']];
    }

    // Read limits from site_settings / config (0 = unlimited)
    $dailyLimit   = (int)getSetting($isFollowup ? 'followup_daily_limit'   : 'email_daily_limit',   '0');
    $weeklyLimit  = (int)getSetting($isFollowup ? 'followup_weekly_limit'  : 'email_weekly_limit',  '0');
    $monthlyLimit = (int)getSetting($isFollowup ? 'followup_monthly_limit' : 'email_monthly_limit', '0');

    $type = $isFollowup ? 'follow-up' : 'campaign';

    // Count emails sent today (by follow_up_sequence type)
    $seqCond = $isFollowup ? '> 1' : '= 1';

    if ($dailyLimit > 0) {
        $sentToday = (int)(Database::fetchOne(
            "SELECT COUNT(*) AS c FROM email_logs
             WHERE status='sent' AND follow_up_sequence $seqCond AND DATE(sent_at) = CURDATE()"
        )['c'] ?? 0);
        if ($sentToday >= $dailyLimit) {
            return ['allowed' => false, 'reason' => "Daily $type limit reached ($sentToday / $dailyLimit)"];
        }
    }

    if ($weeklyLimit > 0) {
        $sentWeek = (int)(Database::fetchOne(
            "SELECT COUNT(*) AS c FROM email_logs
             WHERE status='sent' AND follow_up_sequence $seqCond
               AND YEARWEEK(sent_at, 1) = YEARWEEK(NOW(), 1)"
        )['c'] ?? 0);
        if ($sentWeek >= $weeklyLimit) {
            return ['allowed' => false, 'reason' => "Weekly $type limit reached ($sentWeek / $weeklyLimit)"];
        }
    }

    if ($monthlyLimit > 0) {
        $sentMonth = (int)(Database::fetchOne(
            "SELECT COUNT(*) AS c FROM email_logs
             WHERE status='sent' AND follow_up_sequence $seqCond
               AND YEAR(sent_at) = YEAR(NOW()) AND MONTH(sent_at) = MONTH(NOW())"
        )['c'] ?? 0);
        if ($sentMonth >= $monthlyLimit) {
            return ['allowed' => false, 'reason' => "Monthly $type limit reached ($sentMonth / $monthlyLimit)"];
        }
    }

    return ['allowed' => true, 'reason' => ''];
}
