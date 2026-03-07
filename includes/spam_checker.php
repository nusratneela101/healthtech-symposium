<?php
/**
 * SpamChecker — Analyzes email subject/body for spam triggers.
 * Returns a risk score (0–100) with specific warnings and suggestions.
 */
class SpamChecker {

    // Spam trigger words / phrases with individual weight
    private static array $subjectTriggers = [
        'free'         => 8,  'winner'        => 10, 'won'           => 8,
        'cash'         => 8,  'prize'         => 9,  'click here'    => 10,
        'urgent'       => 6,  'act now'       => 9,  'limited time'  => 7,
        'guaranteed'   => 7,  'no risk'       => 8,  'risk free'     => 8,
        'make money'   => 10, 'earn money'    => 9,  'extra income'  => 8,
        'this is not spam' => 12, 'not spam'  => 10, 'opportunity'   => 5,
        'congratulations'  => 6,  'you have been selected' => 12,
        '100%'         => 5,  'as seen on'    => 7,  'buy now'       => 8,
        'order now'    => 7,  'offer expires' => 7,  'special offer' => 6,
        'unbelievable' => 8,  'incredible'    => 6,
    ];

    private static array $bodyTriggers = [
        'click here'      => 9,  'buy now'         => 8, 'order now'    => 7,
        'free offer'      => 8,  'free gift'        => 8, 'free money'   => 10,
        'this is not spam'=> 12, 'remove from list' => 6, 'to be removed'=> 5,
        'earn per week'   => 10, 'earn extra cash'  => 10,'work from home'=> 7,
        'extra cash'      => 8,  'make money fast'  => 12,'get paid'     => 7,
        'no credit check' => 10, 'lowest price'     => 6, 'best price'   => 5,
        'amazing stuff'   => 6,  'dear friend'      => 7, 'dear valued'  => 5,
        'as seen on'      => 7,  'increase sales'   => 5, 'increase your sales' => 6,
        'instant access'  => 6,  'call free'        => 7, 'call now'     => 6,
        'cancel at any time' => 5,'satisfaction guaranteed' => 6,
        'you have been chosen' => 10, 'you\'ve been selected' => 10,
    ];

    /**
     * Analyse a subject line and HTML body, return an assessment array:
     *  [
     *    'score'       => int (0-100),
     *    'risk_level'  => 'low'|'medium'|'high'|'critical',
     *    'warnings'    => string[],
     *    'suggestions' => string[],
     *  ]
     */
    public static function analyze(string $subject, string $htmlBody): array {
        $warnings    = [];
        $suggestions = [];
        $score       = 0;

        $subjectLower = strtolower($subject);
        $bodyText     = strtolower(strip_tags($htmlBody));

        // ── Subject checks ──────────────────────────────────────────────────

        // All-caps subject (require at least 4 letters that are all uppercase)
        $subjectLetters = preg_replace('/[^a-zA-Z]/', '', $subject);
        if ($subject !== '' && strlen($subjectLetters) > 3 && $subjectLetters === strtoupper($subjectLetters)) {
            $score += 12;
            $warnings[]    = 'Subject is in ALL CAPS — major spam signal.';
            $suggestions[] = 'Use sentence case or title case in your subject line.';
        }

        // Excessive exclamation / question marks
        $exclamCount = substr_count($subject, '!');
        $questCount  = substr_count($subject, '?');
        if ($exclamCount >= 2) {
            $score += min($exclamCount * 4, 16);
            $warnings[]    = "Subject contains {$exclamCount} exclamation mark(s).";
            $suggestions[] = 'Remove excessive punctuation from subject line.';
        }
        if ($questCount >= 2) {
            $score += min($questCount * 3, 12);
            $warnings[]    = "Subject contains {$questCount} question mark(s).";
            $suggestions[] = 'Limit punctuation to a single mark in your subject.';
        }

        // Spam words in subject
        $hitSubject = [];
        foreach (self::$subjectTriggers as $word => $weight) {
            if (strpos($subjectLower, $word) !== false) {
                $score     += $weight;
                $hitSubject[] = $word;
            }
        }
        if ($hitSubject) {
            $warnings[]    = 'Spam trigger words in subject: "' . implode('", "', $hitSubject) . '".';
            $suggestions[] = 'Replace or remove spam-trigger words from the subject line.';
        }

        // Subject too short or too long
        $subjectLen = mb_strlen($subject);
        if ($subjectLen < 10) {
            $score += 5;
            $warnings[]    = 'Subject line is very short (' . $subjectLen . ' chars).';
            $suggestions[] = 'Write a descriptive subject of 30–60 characters.';
        } elseif ($subjectLen > 70) {
            $score += 4;
            $warnings[]    = 'Subject line is long (' . $subjectLen . ' chars).';
            $suggestions[] = 'Keep subject under 70 characters for best deliverability.';
        }

        // ── Body checks ─────────────────────────────────────────────────────

        // No plain-text alt body
        if (strip_tags($htmlBody) === $htmlBody) {
            // Treat as plain text — no HTML images to check
        } else {
            // Check image-to-text ratio (very rough: count <img> vs word count)
            $imgCount  = substr_count(strtolower($htmlBody), '<img');
            $wordCount = str_word_count($bodyText);
            if ($imgCount > 0 && $wordCount < 20) {
                $score += 10;
                $warnings[]    = 'Email is mostly images with little text.';
                $suggestions[] = 'Add meaningful plain text content alongside images.';
            }
        }

        // Spam words in body
        $hitBody = [];
        foreach (self::$bodyTriggers as $word => $weight) {
            if (strpos($bodyText, $word) !== false) {
                $score  += $weight;
                $hitBody[] = $word;
            }
        }
        if ($hitBody) {
            $warnings[]    = 'Spam trigger phrases in body: "' . implode('", "', array_slice($hitBody, 0, 5)) . '"'
                             . (count($hitBody) > 5 ? ' (+' . (count($hitBody)-5) . ' more).' : '.');
            $suggestions[] = 'Rewrite sections containing spam-trigger phrases.';
        }

        // Missing unsubscribe link
        if (stripos($htmlBody, 'unsubscribe') === false) {
            $score += 8;
            $warnings[]    = 'No unsubscribe link found in email body.';
            $suggestions[] = 'Include an unsubscribe link — required by CAN-SPAM / CASL.';
        }

        // Excessive links
        $linkCount = substr_count(strtolower($htmlBody), '<a ');
        if ($linkCount > 5) {
            $score += min(($linkCount - 5) * 2, 10);
            $warnings[]    = "Email contains {$linkCount} links.";
            $suggestions[] = 'Reduce the number of links to 3–5 per email.';
        }

        // Suspicious URL shorteners
        foreach (['bit.ly','tinyurl','goo.gl','ow.ly','t.co','rebrand.ly'] as $shortener) {
            if (stripos($htmlBody, $shortener) !== false) {
                $score += 8;
                $warnings[]    = "URL shortener detected ({$shortener}) — flagged by many spam filters.";
                $suggestions[] = 'Use full, branded URLs instead of link shorteners.';
                break;
            }
        }

        // Body too short
        $textLen = mb_strlen(trim($bodyText));
        if ($textLen < 50) {
            $score += 6;
            $warnings[]    = 'Email body is very short (' . $textLen . ' chars of text).';
            $suggestions[] = 'Write a more complete email with at least 100 words.';
        }

        // Caps ratio in body
        $alphaChars = preg_replace('/[^a-zA-Z]/', '', substr($htmlBody, 0, 500));
        if (strlen($alphaChars) > 20) {
            $upperCount = strlen(preg_replace('/[^A-Z]/', '', $alphaChars));
            if ($upperCount / strlen($alphaChars) > 0.4) {
                $score += 8;
                $warnings[]    = 'High proportion of UPPERCASE letters in body text.';
                $suggestions[] = 'Use normal sentence case throughout the email body.';
            }
        }

        // Clamp to 100
        $score = min($score, 100);

        if ($score <= 20) {
            $riskLevel = 'low';
        } elseif ($score <= 45) {
            $riskLevel = 'medium';
        } elseif ($score <= 70) {
            $riskLevel = 'high';
        } else {
            $riskLevel = 'critical';
        }

        return [
            'score'       => $score,
            'risk_level'  => $riskLevel,
            'warnings'    => $warnings,
            'suggestions' => $suggestions,
        ];
    }
}
