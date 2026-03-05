<?php
function pill(string $status): string {
    $map = [
        'new'            => 'p-new',
        'emailed'        => 'p-emailed',
        'responded'      => 'p-responded',
        'converted'      => 'p-converted',
        'unsubscribed'   => 'p-bounced',
        'bounced'        => 'p-bounced',
        'sent'           => 'p-sent',
        'failed'         => 'p-failed',
        'queued'         => 'p-queued',
        'running'        => 'p-running',
        'completed'      => 'p-completed',
        'draft'          => 'p-queued',
        'paused'         => 'p-queued',
        'interested'     => 'p-interested',
        'not_interested' => 'p-notint',
        'more_info'      => 'p-moreinfo',
        'auto_reply'     => 'p-auto',
        'bounce'         => 'p-bounced',
        'other'          => 'p-other',
        'Venture Capital / Investors'      => 'p-vc',
        'Healthcare Providers'             => 'p-hp',
        'Health IT & Digital Health'       => 'p-hi',
        'Pharmaceutical & Biotech'         => 'p-pb',
        'Medical Devices'                  => 'p-md',
        'Medical Devices & Equipment'      => 'p-md',
        'Fintech Startups'              => 'p-hs',
    ];
    $cls = $map[$status] ?? 'p-other';
    return '<span class="pill ' . $cls . '">' . htmlspecialchars($status) . '</span>';
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)          return 'Just now';
    if ($diff < 3600)        return floor($diff / 60) . 'm ago';
    if ($diff < 86400)       return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)      return floor($diff / 86400) . 'd ago';
    return floor($diff / 604800) . 'w ago';
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function paginate(int $total, int $page, int $perPage, string $baseUrl): string {
    $pages = (int) ceil($total / $perPage);
    if ($pages <= 1) return '';
    $html = '<div class="pager">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = ($i === $page) ? ' active' : '';
        $sep = strpos($baseUrl, '?') !== false ? '&' : '?';
        $html .= '<a href="' . $baseUrl . $sep . 'page=' . $i . '" class="pg-btn' . $active . '">' . $i . '</a>';
    }
    $html .= '</div>';
    return $html;
}

function detectSegment(string $jobTitle, string $company): string {
    $haystack = trim($jobTitle) . ' ' . trim($company);

    $rules = [
        'Healthcare Providers' => [
            'doctor','physician','hospital','clinic','nurse','surgeon',
            'medical director','health system','health centre','healthcare',
        ],
        'Health IT & Digital Health' => [
            'cto','software','digital health','it','platform','tech','data',
            'engineer','developer','health it','informatics',
        ],
        'Pharmaceutical & Biotech' => [
            'pharma','drug','biotech','biotechnology','clinical',
            'therapeutics','bioscience',
        ],
        'Medical Devices & Equipment' => [
            'device','medical equipment','implant','diagnostics','imaging','medtech',
        ],
        'Venture Capital / Investors' => [
            'vc','venture capital','investor','capital','fund','managing partner',
            'angel','investment','private equity',
        ],
        'Fintech Startups' => [
            'startup','fintech','founder','co-founder',
        ],
    ];

    foreach ($rules as $segment => $keywords) {
        foreach ($keywords as $kw) {
            if (stripos($haystack, $kw) !== false) {
                return $segment;
            }
        }
    }

    return 'Other';
}

function audit_log(string $action, string $entityType = '', ?int $entityId = null, string $details = ''): void {
    try {
        $userId = null;
        if (class_exists('Auth') && method_exists('Auth', 'user')) {
            $u = Auth::user();
            $userId = $u['id'] ?? null;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        Database::query(
            "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?,?,?,?,?,?)",
            [$userId, $action, $entityType, $entityId, $details, $ip]
        );
    } catch (Exception $e) {
        // Silently fail — audit log should not break the main request
    }
}
