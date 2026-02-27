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
        'Financial Institutions'           => 'p-fi',
        'Venture Capital / Investors'      => 'p-vc',
        'FinTech Startups'                 => 'p-ft',
        'Technology & Solution Providers'  => 'p-tp',
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
