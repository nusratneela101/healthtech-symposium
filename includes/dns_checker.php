<?php
/**
 * DnsChecker — Verifies SPF, DKIM, and DMARC DNS records for a domain.
 * Returns status and setup instructions for each record type.
 */
class DnsChecker {

    /**
     * Run all checks for the configured sender domain.
     * Returns an array keyed by record type with status, value, and instructions.
     */
    public static function checkAll(): array {
        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '';
        $domain    = self::extractDomain($fromEmail);

        if (!$domain) {
            return self::emptyResult('No sender domain configured.');
        }

        return [
            'domain' => $domain,
            'spf'    => self::checkSpf($domain),
            'dmarc'  => self::checkDmarc($domain),
            'dkim'   => self::checkDkim($domain),
        ];
    }

    // ── SPF ──────────────────────────────────────────────────────────────────

    public static function checkSpf(string $domain): array {
        $records = @dns_get_record($domain, DNS_TXT);
        if ($records === false) {
            $records = [];
        }

        $spfRecord = '';
        foreach ($records as $r) {
            // PHP dns_get_record() returns 'txt' for simple records and 'entries' (array)
            // for multi-string TXT records (common on some platforms/PHP versions).
            $txt = $r['txt'] ?? (is_array($r['entries'] ?? null) ? implode('', $r['entries']) : '');
            if (stripos($txt, 'v=spf1') === 0) {
                $spfRecord = $txt;
                break;
            }
        }

        if ($spfRecord === '') {
            return [
                'status'       => 'missing',
                'record'       => null,
                'instructions' => self::spfInstructions($domain),
                'description'  => 'SPF record not found. Emails may be marked as spam.',
            ];
        }

        // Basic validation: should end with ~all or -all
        if (strpos($spfRecord, '-all') !== false || strpos($spfRecord, '~all') !== false) {
            return [
                'status'       => 'valid',
                'record'       => $spfRecord,
                'instructions' => null,
                'description'  => 'SPF record found and correctly configured.',
            ];
        }

        return [
            'status'       => 'warning',
            'record'       => $spfRecord,
            'instructions' => 'Update your SPF record to end with "-all" (fail) or "~all" (softfail).',
            'description'  => 'SPF record exists but may not restrict unauthorized senders.',
        ];
    }

    // ── DMARC ─────────────────────────────────────────────────────────────────

    public static function checkDmarc(string $domain): array {
        $dmarcHost = '_dmarc.' . $domain;
        $records   = @dns_get_record($dmarcHost, DNS_TXT);
        if ($records === false) {
            $records = [];
        }

        $dmarcRecord = '';
        foreach ($records as $r) {
            // PHP dns_get_record() returns 'txt' for simple records and 'entries' (array)
            // for multi-string TXT records (common on some platforms/PHP versions).
            $txt = $r['txt'] ?? (is_array($r['entries'] ?? null) ? implode('', $r['entries']) : '');
            if (stripos($txt, 'v=DMARC1') === 0) {
                $dmarcRecord = $txt;
                break;
            }
        }

        if ($dmarcRecord === '') {
            return [
                'status'       => 'missing',
                'record'       => null,
                'instructions' => self::dmarcInstructions($domain),
                'description'  => 'DMARC record not found. Add one to improve deliverability and prevent spoofing.',
            ];
        }

        // Check policy strength
        if (preg_match('/p=(quarantine|reject)/i', $dmarcRecord)) {
            return [
                'status'       => 'valid',
                'record'       => $dmarcRecord,
                'instructions' => null,
                'description'  => 'DMARC record found with a strong policy (quarantine/reject).',
            ];
        }

        return [
            'status'       => 'warning',
            'record'       => $dmarcRecord,
            'instructions' => 'Consider upgrading DMARC policy from p=none to p=quarantine or p=reject.',
            'description'  => 'DMARC record found but policy is set to "none" (monitoring only).',
        ];
    }

    // ── DKIM ─────────────────────────────────────────────────────────────────

    public static function checkDkim(string $domain): array {
        // Common selectors used by popular providers
        $selectors = ['default', 'mail', 'google', 'selector1', 'selector2', 'k1', 'smtp', 'brevo', 'dkim'];
        $found     = null;
        $selector  = '';

        foreach ($selectors as $sel) {
            $host    = $sel . '._domainkey.' . $domain;
            $records = @dns_get_record($host, DNS_TXT);
            if (!$records) {
                continue;
            }
            foreach ($records as $r) {
                $txt = $r['txt'] ?? (is_array($r['entries'] ?? null) ? implode('', $r['entries']) : '');
                if (stripos($txt, 'v=DKIM1') !== false || stripos($txt, 'p=') !== false) {
                    $found    = $txt;
                    $selector = $sel;
                    break 2;
                }
            }
        }

        if (!$found) {
            return [
                'status'       => 'missing',
                'record'       => null,
                'instructions' => self::dkimInstructions($domain),
                'description'  => 'No DKIM record detected with common selectors. Configure DKIM through your email provider.',
            ];
        }

        return [
            'status'       => 'valid',
            'record'       => "Selector: {$selector} — " . substr($found, 0, 80) . '…',
            'instructions' => null,
            'description'  => "DKIM record found (selector: {$selector}).",
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function extractDomain(string $email): string {
        $parts = explode('@', $email);
        return count($parts) === 2 ? strtolower(trim($parts[1])) : '';
    }

    private static function emptyResult(string $reason): array {
        $empty = ['status' => 'unknown', 'record' => null, 'instructions' => $reason, 'description' => $reason];
        return ['domain' => '', 'spf' => $empty, 'dmarc' => $empty, 'dkim' => $empty];
    }

    private static function spfInstructions(string $domain): string {
        return "Add a TXT record to your DNS for {$domain}:\n"
             . "  Name: @  (or leave blank)\n"
             . '  Value: v=spf1 include:_spf.brevo.com ~all' . "\n"
             . 'Replace the include: value with your email provider\'s SPF include.';
    }

    private static function dmarcInstructions(string $domain): string {
        return "Add a TXT record to your DNS:\n"
             . "  Name: _dmarc.{$domain}\n"
             . "  Value: v=DMARC1; p=quarantine; rua=mailto:dmarc-reports@{$domain}; pct=100\n"
             . 'Start with p=none for monitoring, then move to p=quarantine or p=reject.';
    }

    private static function dkimInstructions(string $domain): string {
        return "Generate a DKIM key pair through your email provider (Brevo, Microsoft 365, etc.) "
             . "and add the provided TXT record to your DNS under the _domainkey subdomain.\n"
             . "Example record name: default._domainkey.{$domain}";
    }
}
