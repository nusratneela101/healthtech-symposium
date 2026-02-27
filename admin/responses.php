<?php
require_once __DIR__ . '/../includes/layout.php';

$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Mark as read
if (isset($_GET['mark_read'])) {
    Database::query("UPDATE responses SET is_read=1 WHERE id=?", [(int)$_GET['mark_read']]);
    header('Location: ' . APP_URL . '/admin/responses.php');
    exit;
}

// Send reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_id'])) {
    require_once __DIR__ . '/../includes/email.php';
    $respId  = (int)$_POST['reply_id'];
    $resp    = Database::fetchOne("SELECT * FROM responses WHERE id=?", [$respId]);
    if ($resp) {
        $result = EmailService::sendReply(
            $resp['from_email'], $resp['from_name'],
            trim($_POST['reply_subject']),
            nl2br(htmlspecialchars(trim($_POST['reply_body']))),
            $resp['message_id'] ?? ''
        );
        if ($result['success']) {
            Database::query("UPDATE responses SET is_replied=1, is_read=1 WHERE id=?", [$respId]);
            Database::query(
                "INSERT INTO response_replies (response_id, replied_by, reply_subject, reply_body) VALUES(?,?,?,?)",
                [$respId, Auth::user()['id'], trim($_POST['reply_subject']), trim($_POST['reply_body'])]
            );
            flash('success', 'Reply sent successfully.');
        } else {
            flash('error', 'Failed to send reply: ' . ($result['error'] ?? 'Unknown error'));
        }
    }
    header('Location: ' . APP_URL . '/admin/responses.php');
    exit;
}

$filter = $_GET['filter'] ?? '';
$where  = '1=1';
$params = [];
if ($filter === 'unread') { $where .= ' AND is_read=0'; }
if ($filter === 'interested') { $where .= ' AND response_type="interested"'; }

$total     = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM responses WHERE $where", $params)['c'] ?? 0);
$responses = Database::fetchAll(
    "SELECT r.*, l.full_name AS lead_name, l.company
     FROM responses r LEFT JOIN leads l ON r.lead_id = l.id
     WHERE $where ORDER BY r.received_at DESC LIMIT $perPage OFFSET $offset",
    $params
);
$pagination = paginate($total, $page, $perPage, APP_URL . '/admin/responses.php?' . http_build_query(array_filter(['filter'=>$filter])));
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h2 style="font-size:20px">💬 Responses
        <span style="font-size:14px;color:#8a9ab5;font-weight:400">(<?php echo number_format($total); ?> total)</span>
    </h2>
    <div style="display:flex;gap:8px">
        <a href="?" class="btn-sec" style="text-decoration:none;font-size:12px">All</a>
        <a href="?filter=unread" class="btn-sec" style="text-decoration:none;font-size:12px">Unread</a>
        <a href="?filter=interested" class="btn-sec" style="text-decoration:none;font-size:12px">Interested</a>
        <a href="<?php echo APP_URL; ?>/api/poll_inbox.php?api_key=<?php echo N8N_API_KEY; ?>" class="btn-launch" style="text-decoration:none;font-size:12px">🔄 Poll Inbox</a>
    </div>
</div>

<div class="gc">
    <?php foreach ($responses as $r): ?>
    <div class="inbox-item <?php echo !$r['is_read'] ? 'unread' : ''; ?>">
        <div class="ia"><?php echo strtoupper(substr($r['from_name'] ?: $r['from_email'], 0, 1)); ?></div>
        <div style="flex:1;min-width:0">
            <div class="if">
                <strong><?php echo htmlspecialchars($r['from_name'] ?: $r['from_email']); ?></strong>
                <?php if ($r['lead_name']): ?>
                    <small style="color:#8a9ab5"> · <?php echo htmlspecialchars($r['company'] ?: ''); ?></small>
                <?php endif; ?>
                <span style="margin-left:8px"><?php echo pill($r['response_type']); ?></span>
                <?php if ($r['is_replied']): ?><span class="pill p-sent" style="font-size:10px">Replied</span><?php endif; ?>
            </div>
            <div class="is"><?php echo htmlspecialchars($r['subject']); ?></div>
            <div class="ip"><?php echo htmlspecialchars(substr(strip_tags($r['body_text'] ?: $r['body_html']), 0, 120)); ?>…</div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;white-space:nowrap">
            <div style="font-size:12px;color:#8a9ab5"><?php echo timeAgo($r['received_at']); ?></div>
            <div style="display:flex;gap:6px">
                <?php if (!$r['is_read']): ?>
                <a href="?mark_read=<?php echo $r['id']; ?>" style="font-size:11px;color:#10b981;text-decoration:none">✓ Read</a>
                <?php endif; ?>
                <button onclick="openReply(<?php echo $r['id']; ?>,'<?php echo addslashes(htmlspecialchars($r['from_email'])); ?>','<?php echo addslashes(htmlspecialchars($r['from_name'])); ?>','Re: <?php echo addslashes(htmlspecialchars($r['subject'])); ?>')"
                        style="background:#0d6efd;border:none;color:#fff;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:11px">Reply</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($responses)): ?>
    <div style="text-align:center;color:#8a9ab5;padding:48px">No responses yet. <a href="<?php echo APP_URL; ?>/api/poll_inbox.php?api_key=<?php echo N8N_API_KEY; ?>" style="color:#0d6efd">Poll inbox →</a></div>
    <?php endif; ?>
    <?php echo $pagination; ?>
</div>

<!-- Reply Modal -->
<div class="modal-ov" id="replyModal">
    <div class="modal-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h3 style="font-size:16px">↩️ Send Reply</h3>
            <button onclick="closeReply()" style="background:none;border:none;color:#8a9ab5;font-size:20px;cursor:pointer">×</button>
        </div>
        <form method="POST" id="replyForm">
            <input type="hidden" name="reply_id" id="reply_id">
            <div style="margin-bottom:12px">
                <label style="font-size:13px;color:#8a9ab5">To: <span id="replyTo"></span></label>
            </div>
            <div style="margin-bottom:12px">
                <input class="fi" name="reply_subject" id="reply_subject" placeholder="Subject" style="width:100%">
            </div>
            <div style="margin-bottom:16px">
                <textarea class="fi rt" name="reply_body" placeholder="Write your reply…" style="width:100%;min-height:160px;resize:vertical"></textarea>
            </div>
            <button type="submit" class="btn-launch">📤 Send Reply</button>
            <button type="button" onclick="closeReply()" class="btn-sec" style="margin-left:8px">Cancel</button>
        </form>
    </div>
</div>

<script>
function openReply(id, email, name, subject) {
    document.getElementById('reply_id').value = id;
    document.getElementById('replyTo').textContent = name + ' <' + email + '>';
    document.getElementById('reply_subject').value = subject;
    document.getElementById('replyModal').classList.add('open');
}
function closeReply() {
    document.getElementById('replyModal').classList.remove('open');
}
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
