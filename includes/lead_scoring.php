<?php
/**
 * Lead scoring system.
 *
 * Score rules:
 *   +50  for an 'interested' response
 *   +20  for any other reply (responded status)
 *   +10  for an email opened (email_logs.status = 'opened')
 *   +30  for a clicked link (not tracked yet — placeholder)
 *   -10  for a bounce
 */
class LeadScoring {
    /**
     * Recalculate the score for a single lead from DB data and save it.
     */
    public static function recalculate(int $leadId): int {
        try {
            $score = 0;

            // Responses: interested = +50, other = +20
            $responses = Database::fetchAll(
                "SELECT response_type FROM responses WHERE lead_id = ?",
                [$leadId]
            );
            foreach ($responses as $r) {
                if ($r['response_type'] === 'interested') {
                    $score += 50;
                } elseif (in_array($r['response_type'], ['more_info','other','wrong_person'])) {
                    $score += 20;
                }
            }

            // Email opens: +10 each
            $opens = (int)(Database::fetchOne(
                "SELECT COUNT(*) AS c FROM email_logs WHERE lead_id = ? AND status = 'opened'",
                [$leadId]
            )['c'] ?? 0);
            $score += $opens * 10;

            // Bounces: -10 each
            $bounces = (int)(Database::fetchOne(
                "SELECT COUNT(*) AS c FROM email_logs WHERE lead_id = ? AND status = 'bounced'",
                [$leadId]
            )['c'] ?? 0);
            $score -= $bounces * 10;

            $score = max(0, $score);

            Database::query("UPDATE leads SET score = ? WHERE id = ?", [$score, $leadId]);
            return $score;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Increment a lead's score by $delta (can be negative).
     */
    public static function increment(int $leadId, int $delta): void {
        try {
            Database::query(
                "UPDATE leads SET score = GREATEST(0, COALESCE(score, 0) + ?) WHERE id = ?",
                [$delta, $leadId]
            );
        } catch (Exception $e) {
            // Silently fail
        }
    }

    /**
     * Apply a scoring event by event name.
     *
     * Events: 'email_opened', 'replied', 'interested', 'link_clicked', 'bounced'
     */
    public static function applyEvent(int $leadId, string $event): void {
        $deltas = [
            'email_opened' => 10,
            'replied'      => 20,
            'interested'   => 50,
            'link_clicked' => 30,
            'bounced'      => -10,
        ];
        if (isset($deltas[$event])) {
            self::increment($leadId, $deltas[$event]);
        }
    }

    /**
     * Get the top N scored leads.
     */
    public static function getTopLeads(int $limit = 10): array {
        try {
            return Database::fetchAll(
                "SELECT id, full_name, email, company, job_title, segment, status, score
                 FROM leads WHERE score > 0 ORDER BY score DESC LIMIT ?",
                [$limit]
            );
        } catch (Exception $e) {
            return [];
        }
    }
}
