<?php
require_once __DIR__ . '/../includes/layout.php';

// Self-heal: ensure required columns exist before any query
try {
    Database::query("ALTER TABLE `responses` ADD COLUMN IF NOT EXISTS `response_type` VARCHAR(50) NULL DEFAULT NULL");
    Database::query("ALTER TABLE `responses` ADD COLUMN IF NOT EXISTS `is_read` TINYINT(1) NOT NULL DEFAULT 0");
    Database::query("ALTER TABLE `responses` ADD COLUMN IF NOT EXISTS `is_replied` TINYINT(1) NOT NULL DEFAULT 0");
    Database::query("ALTER TABLE `responses` ADD COLUMN IF NOT EXISTS `sentiment` VARCHAR(20) NULL DEFAULT NULL");
} catch (Exception $e) {
    // Columns already exist or DB doesn't support IF NOT EXISTS — safe to ignore
}

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
            audit_log('reply_sent', 'responses', $respId);
            flash('success', 'Reply sent successfully.');
        } else {
            flash('error', 'Failed to send reply: ' . ($result['error'] ?? 'Unknown error'));
        }
    }
    header('Location: ' . APP_URL . '/admin/responses.php');
    exit;
}

// AJAX: update response type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tag') {
    header('Content-Type: application/json');
    $respId = (int)($_POST['response_id'] ?? 0);
    $type   = trim($_POST['response_type'] ?? '');
    $allowed = ['interested','not_interested','more_info','wrong_person','auto_reply','bounce','other'];
    if ($respId && in_array($type, $allowed, true)) {
        Database::query("UPDATE responses SET response_type=? WHERE id=?", [$type, $respId]);
        audit_log('response_tagged', 'responses', $respId, "type=$type");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Invalid input']);
    }
    exit;
}

// AJAX: bulk tag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_tag') {
    header('Content-Type: application/json');
    $ids     = array_map('intval', $_POST['ids'] ?? []);
    $type    = trim($_POST['response_type'] ?? '');
    $allowed = ['interested','not_interested','more_info','wrong_person','auto_reply','bounce','other'];
    if ($ids && in_array($type, $allowed, true)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        Database::query("UPDATE responses SET response_type=? WHERE id IN ($placeholders)", array_merge([$type], $ids));
        audit_log('bulk_tag', 'responses', null, "ids=" . implode(',', $ids) . " type=$type");
        echo json_encode(['success' => true, 'count' => count($ids)]);
    } else {
        echo json_encode(['error' => 'Invalid input']);
    }
    exit;
}

$filter = $_GET['filter'] ?? '';
$where  = '1=1';
$params = [];
$filterTypes = ['unread', 'interested', 'not_interested', 'more_info', 'auto_reply', 'wrong_person', 'other'];
if ($filter === 'unread') {
    $where .= ' AND is_read=0';
} elseif (in_array($filter, $filterTypes, true) && $filter !== 'unread') {
    $where .= ' AND response_type=?';
    $params[] = $filter;
}

$total     = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM responses WHERE $where", $params)['c'] ?? 0);
$responses = Database::fetchAll(
    "SELECT r.*, l.full_name AS lead_name, l.company
     FROM responses r LEFT JOIN leads l ON r.lead_id = l.id
     WHERE $where ORDER BY r.received_at DESC LIMIT $perPage OFFSET $offset",
    $params
);
$pagination = paginate($total, $page, $perPage, APP_URL . '/admin/responses.php?' . http_build_query(array_filter(['filter'=>$filter])));

// Thread view
$threadEmail = $_GET['thread'] ?? '';
$thread = [];
if ($threadEmail) {
    $thread = Database::fetchAll(
        "SELECT r.*, l.full_name AS lead_name, l.company FROM responses r
         LEFT JOIN leads l ON r.lead_id = l.id
         WHERE r.from_email=? ORDER BY r.received_at ASC",
        [$threadEmail]
    );
}

$responseTypes = ['interested','not_interested','more_info','wrong_person','auto_reply','bounce','other'];
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
    <h2 style="font-size:20px">💬 Responses
        <span style="font-size:14px;color:#8a9ab5;font-weight:400">(<?php echo number_format($total); ?> total)</span>
    </h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="?" class="btn-sec<?php echo !$filter?' active':''; ?>" style="text-decoration:none;font-size:12px">All</a>
        <a href="?filter=unread" class="btn-sec<?php echo $filter==='unread'?' active':''; ?>" style="text-decoration:none;font-size:12px">Unread</a>
        <a href="?filter=interested" class="btn-sec<?php echo $filter==='interested'?' active':''; ?>" style="text-decoration:none;font-size:12px">Interested</a>
        <a href="?filter=not_interested" class="btn-sec<?php echo $filter==='not_interested'?' active':''; ?>" style="text-decoration:none;font-size:12px">Not Interested</a>
        <a href="?filter=more_info" class="btn-sec<?php echo $filter==='more_info'?' active':''; ?>" style="text-decoration:none;font-size:12px">More Info</a>
        <a href="?filter=auto_reply" class="btn-sec<?php echo $filter==='auto_reply'?' active':''; ?>" style="text-decoration:none;font-size:12px">Auto Reply</a>
        <a href="?filter=other" class="btn-sec<?php echo $filter==='other'?' active':''; ?>" style="text-decoration:none;font-size:12px">Other</a>
        <a href="<?php echo APP_URL; ?>/api/poll_inbox.php?api_key=<?php echo N8N_API_KEY; ?>" class="btn-launch" style="text-decoration:none;font-size:12px">🔄 Poll Inbox</a>
    </div>
</div>

<!-- Bulk tag bar -->
<div id="bulkBar" style="display:none;background:#13213a;border:1px solid #1e3355;border-radius:8px;padding:12px 16px;margin-bottom:12px;align-items:center;gap:12px">
    <span style="font-size:13px;color:#8a9ab5"><span id="selectedCount">0</span> selected</span>
    <select id="bulkType" class="fi" style="width:180px;font-size:13px">
        <?php foreach ($responseTypes as $rt): ?>
        <option value="<?php echo $rt; ?>"><?php echo ucfirst(str_replace('_',' ',$rt)); ?></option>
        <?php endforeach; ?>
    </select>
    <button onclick="applyBulkTag()" class="btn-launch" style="font-size:12px">Apply</button>
    <button onclick="clearSelection()" class="btn-sec" style="font-size:12px">Clear</button>
</div>

<div class="gc">
    <?php foreach ($responses as $r): ?>
    <div class="inbox-item <?php echo !$r['is_read'] ? 'unread' : ''; ?>" id="resp-<?php echo $r['id']; ?>">
        <input type="checkbox" class="resp-chk" value="<?php echo $r['id']; ?>"
               onchange="updateBulkBar()" style="margin-right:8px;cursor:pointer;width:16px;height:16px;flex-shrink:0">
        <div class="ia" style="cursor:pointer" onclick="openThread('<?php echo addslashes($r['from_email']); ?>')">
            <?php echo strtoupper(substr($r['from_name'] ?: $r['from_email'], 0, 1)); ?>
        </div>
        <div style="flex:1;min-width:0">
            <div class="if">
                <strong style="cursor:pointer" onclick="openThread('<?php echo addslashes($r['from_email']); ?>')"><?php echo htmlspecialchars($r['from_name'] ?: $r['from_email']); ?></strong>
                <?php if ($r['lead_name']): ?>
                    <small style="color:#8a9ab5"> · <?php echo htmlspecialchars($r['company'] ?: ''); ?></small>
                <?php endif; ?>
                <span style="margin-left:8px" id="type-badge-<?php echo $r['id']; ?>"><?php echo pill($r['response_type']); ?></span>
                <?php if ($r['is_replied']): ?><span class="pill p-sent" style="font-size:10px">Replied</span><?php endif; ?>
            </div>
            <div class="is"><?php echo htmlspecialchars($r['subject']); ?></div>
            <div class="ip"><?php echo htmlspecialchars(substr(strip_tags($r['body_text'] ?: $r['body_html']), 0, 120)); ?>…</div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;white-space:nowrap">
            <div style="font-size:12px;color:#8a9ab5"><?php echo timeAgo($r['received_at']); ?></div>
            <div style="display:flex;gap:6px;align-items:center">
                <select onchange="tagResponse(<?php echo $r['id']; ?>, this.value, this)" class="fi" style="width:130px;font-size:11px;padding:3px 6px">
                    <?php foreach ($responseTypes as $rt): ?>
                    <option value="<?php echo $rt; ?>"<?php echo $rt === $r['response_type'] ? ' selected' : ''; ?>><?php echo ucfirst(str_replace('_',' ',$rt)); ?></option>
                    <?php endforeach; ?>
                </select>
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

<!-- Thread Modal -->
<div class="modal-ov" id="threadModal">
    <div class="modal-box" style="max-width:700px;max-height:80vh;overflow-y:auto">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;position:sticky;top:0;background:#13213a;padding-bottom:8px">
            <h3 style="font-size:16px">🧵 Conversation Thread</h3>
            <button onclick="closeThread()" style="background:none;border:none;color:#8a9ab5;font-size:20px;cursor:pointer">×</button>
        </div>
        <div id="threadContent"></div>
    </div>
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
// Reply modal
function openReply(id, email, name, subject) {
    document.getElementById('reply_id').value = id;
    document.getElementById('replyTo').textContent = name + ' <' + email + '>';
    document.getElementById('reply_subject').value = subject;
    document.getElementById('replyModal').classList.add('open');
}
function closeReply() {
    document.getElementById('replyModal').classList.remove('open');
}

// Inline tag update
function tagResponse(id, type, sel) {
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=tag&response_id=' + id + '&response_type=' + encodeURIComponent(type)
    }).then(r => r.json()).then(d => {
        if (d.success) {
            const badge = document.getElementById('type-badge-' + id);
            if (badge) badge.innerHTML = renderPill(type);
        } else {
            sel.value = sel.dataset.prev || sel.value;
        }
    });
    sel.dataset.prev = type;
}

function renderPill(type) {
    const map = {
        interested:'p-interested', not_interested:'p-notint', more_info:'p-moreinfo',
        wrong_person:'p-other', auto_reply:'p-auto', bounce:'p-bounced', other:'p-other'
    };
    const cls = map[type] || 'p-other';
    return '<span class="pill ' + cls + '">' + type.replace('_',' ') + '</span>';
}

// Bulk tag
function updateBulkBar() {
    const checked = document.querySelectorAll('.resp-chk:checked');
    const bar = document.getElementById('bulkBar');
    document.getElementById('selectedCount').textContent = checked.length;
    bar.style.display = checked.length > 0 ? 'flex' : 'none';
}

function applyBulkTag() {
    const ids = Array.from(document.querySelectorAll('.resp-chk:checked')).map(c => c.value);
    const type = document.getElementById('bulkType').value;
    if (!ids.length) return;
    const body = 'action=bulk_tag&response_type=' + encodeURIComponent(type) + '&' + ids.map(i => 'ids[]=' + i).join('&');
    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
        .then(r => r.json()).then(d => {
            if (d.success) {
                ids.forEach(id => {
                    const badge = document.getElementById('type-badge-' + id);
                    if (badge) badge.innerHTML = renderPill(type);
                    const sel = document.querySelector('#resp-' + id + ' select');
                    if (sel) sel.value = type;
                });
                clearSelection();
            }
        });
}

function clearSelection() {
    document.querySelectorAll('.resp-chk').forEach(c => c.checked = false);
    document.getElementById('bulkBar').style.display = 'none';
}

// Thread modal
const threadData = <?php echo json_encode(
    array_map(fn($r) => [
        'from_name'     => $r['from_name'] ?: $r['from_email'],
        'from_email'    => $r['from_email'],
        'subject'       => $r['subject'],
        'body'          => substr(strip_tags($r['body_text'] ?: ($r['body_html'] ?? '')), 0, 500),
        'received_at'   => $r['received_at'],
        'response_type' => $r['response_type'],
        'is_replied'    => $r['is_replied'],
    ], $responses)
); ?>;

function openThread(email) {
    const msgs = threadData.filter(m => m.from_email === email);
    let html = '';
    msgs.forEach(m => {
        html += '<div style="background:#0d1f38;border-radius:8px;padding:14px;margin-bottom:12px">';
        html += '<div style="font-size:13px;font-weight:600">' + escHtml(m.from_name) + '</div>';
        html += '<div style="font-size:12px;color:#8a9ab5;margin-bottom:8px">' + escHtml(m.subject) + ' · ' + escHtml(m.received_at) + '</div>';
        html += '<div style="font-size:13px;color:#c8d6f0;line-height:1.6">' + escHtml(m.body) + '</div>';
        html += '</div>';
    });
    document.getElementById('threadContent').innerHTML = html || '<p style="color:#8a9ab5">No messages found.</p>';
    document.getElementById('threadModal').classList.add('open');
}

function closeThread() {
    document.getElementById('threadModal').classList.remove('open');
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
