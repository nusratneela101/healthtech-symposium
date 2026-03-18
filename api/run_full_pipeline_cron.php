<?php
/**
 * Full Automated Pipeline Cron
 *
 * Step 1 — Collect leads from Apollo API (with configurable pagination mode)
 * Step 2 — Auto-create a campaign for new leads (if no running campaign exists)
 * Step 3 — Send emails respecting warm-up and daily/weekly/monthly limits
 *
 * Schedule suggestion: every 30 minutes  (* /30 * * * *)
 * Auth: ?api_key=<N8N_API_KEY>
 */

ob_start();
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/check_sending_limits.php';
require_once __DIR__ . '/../includes/campaign_sender.php';

ob_clean();
header('Content-Type: application/json');

$automationMode = getSetting('automation_mode', 'cron');
if ($automationMode !== 'cron') {
    echo json_encode(['success' => false, 'error' => 'Cron mode is disabled. automation_mode = ' . $automationMode]);
    exit;
}

$apiKey = $_GET['api_key'] ?? (json_decode(file_get_contents('php://input'), true)['api_key'] ?? '');
if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$startTime = microtime(true);
$maxRunSeconds = 1680; // 28 minutes max — prevents overlap with next 30-min cron run

// ── Ensure cron_log table exists before any logging ──────────────────────────
try {
    Database::query(
        "CREATE TABLE IF NOT EXISTS `cron_log` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `job_name` varchar(100) NOT NULL,
          `status` varchar(20) DEFAULT 'ok',
          `message` text,
          `duration_ms` int DEFAULT 0,
          `last_run` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `job_name` (`job_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
} catch (Exception $e) { /* ignore */ }

// ── Acquire a MySQL advisory lock to prevent overlapping pipeline runs ────────
// Uses the same lock name as run_campaign_cron.php so both scripts cannot run
// at the same time, preventing TOCTOU race conditions on sending limits.
try {
    $lockRow      = Database::fetchOne("SELECT GET_LOCK('healthtech_email_sender', 0) AS got_lock");
    $lockAcquired = isset($lockRow['got_lock']) && (int)$lockRow['got_lock'] === 1;
} catch (Exception $e) {
    $lockAcquired = true; // if advisory lock unavailable, proceed anyway
}

if (!$lockAcquired) {
    try {
        Database::query(
            "INSERT INTO cron_log (job_name, status, message, duration_ms)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status=VALUES(status), message=VALUES(message),
                                     duration_ms=VALUES(duration_ms), last_run=NOW()",
            ['full_pipeline', 'skipped', 'Skipped: another instance is running (locked)', 0]
        );
    } catch (Exception $e) { /* ignore */ }
    echo json_encode([
        'success' => false,
        'skipped' => true,
        'reason'  => 'Another sender is already running',
    ]);
    exit;
}

// Read pipeline settings
$pipelineBatchSize    = max(1, (int)getSetting('pipeline_batch_size', '100'));
$delaySeconds         = max(0, (int)getSetting('cron_send_delay_seconds', '5'));
$autoCampaignEnabled  = getSetting('auto_campaign_enabled', '1') === '1';
$apolloPaginationMode = getSetting('apollo_pagination_mode', 'configured');
$pipelineTargetMode   = getSetting('pipeline_target_mode', 'all') === 'fixed' ? 'fixed' : 'all';
$pipelineTargetCount  = max(0, (int)getSetting('pipeline_target_count', '0'));

$pipelineLog = [
    'step1_collection' => null,
    'step2_campaign'   => null,
    'step3_sending'    => null,
];

// =============================================================================
// STEP 1: Collect leads from Apollo
// =============================================================================

$apolloApiKey  = getSetting('apollo_api_key', defined('APOLLO_API_KEY') ? APOLLO_API_KEY : '');
$location      = getSetting('apollo_search_location', 'Canada');
$industry      = getSetting('apollo_search_industry', 'Health Technology');
$titlesRaw     = getSetting('apollo_search_titles', '');
$perPage       = min(25, max(1, (int)getSetting('apollo_per_page', '25')));
$maxPages      = max(1, (int)getSetting('apollo_max_pages', '5'));
$safetyMaxPages = 100; // hard cap when apollo_pagination_mode = 'all'

$enrichmentApolloActive        = getSetting('enrichment_apollo_active', '0') === '1';
$enrichmentHunterActive        = getSetting('enrichment_hunter_active', '0') === '1';
$enrichmentAnymailfinderActive = getSetting('enrichment_anymailfinder_active', '0') === '1';
$hunterApiKey                  = getSetting('hunter_api_key', '');
$anymailfinderApiKey           = getSetting('anymailfinder_api_key', '');

$titles = array_values(array_filter(array_map('trim', explode("\n", $titlesRaw))));

/**
 * Map Apollo industry string to a lead segment label.
 */
function pipelineMapSegment(string $ind): string
{
    $i = strtolower($ind);
    if (strpos($i, 'health it') !== false || strpos($i, 'digital health') !== false) return 'Health IT & Digital Health';
    if (strpos($i, 'pharma') !== false || strpos($i, 'biotech') !== false)            return 'Pharmaceutical & Biotech';
    if (strpos($i, 'medical device') !== false)                                        return 'Medical Devices';
    if (strpos($i, 'venture') !== false || strpos($i, 'invest') !== false)             return 'Venture Capital / Investors';
    if (strpos($i, 'hospital') !== false || strpos($i, 'healthcare provider') !== false) return 'Healthcare Providers';
    return 'Health IT & Digital Health';
}

/**
 * Enrich email via Apollo /people/match endpoint.
 */
function pipelineApolloEnrich(string $apolloApiKey, array $person): string
{
    $linkedinUrl = trim($person['linkedin_url'] ?? '');
    $firstName   = trim($person['first_name'] ?? '');
    $lastName    = trim($person['last_name'] ?? '');
    $orgName     = trim($person['organization']['name'] ?? '');

    $payload = ['reveal_personal_emails' => true];
    if ($linkedinUrl) {
        $payload['linkedin_url'] = $linkedinUrl;
    } elseif ($firstName && $orgName) {
        $payload['first_name']        = $firstName;
        $payload['last_name']         = $lastName;
        $payload['organization_name'] = $orgName;
    } else {
        return '';
    }

    $ch = curl_init('https://api.apollo.io/api/v1/people/match');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Cache-Control: no-cache',
            'X-Api-Key: ' . $apolloApiKey,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) return '';
    $data  = json_decode($response, true);
    $email = strtolower(trim(($data['person'] ?? [])['email'] ?? ''));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

/**
 * Enrich email via Hunter.io /v2/email-finder endpoint.
 */
function pipelineHunterEnrich(string $hunterApiKey, array $person): string
{
    $firstName = trim($person['first_name'] ?? '');
    $lastName  = trim($person['last_name'] ?? '');
    $orgDomain = trim($person['organization']['website_url'] ?? '');

    if ($orgDomain) {
        $parsed    = parse_url($orgDomain);
        $host      = $parsed['host'] ?? '';
        $orgDomain = $host ? preg_replace('/^www\./', '', $host) : '';
    }

    if (empty($firstName) || empty($orgDomain)) return '';

    $url = 'https://api.hunter.io/v2/email-finder?'
        . 'domain=' . urlencode($orgDomain)
        . '&first_name=' . urlencode($firstName)
        . '&last_name=' . urlencode($lastName)
        . '&api_key=' . urlencode($hunterApiKey);

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) return '';
    $data  = json_decode($response, true);
    $email = strtolower(trim($data['data']['email'] ?? ''));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

/**
 * Enrich email via Anymailfinder /v5.0/search/person.json endpoint.
 */
function pipelineAnymailfinderEnrich(string $anymailfinderApiKey, array $person): string
{
    $firstName = trim($person['first_name'] ?? '');
    $lastName  = trim($person['last_name'] ?? '');
    $orgDomain = trim($person['organization']['website_url'] ?? '');

    if ($orgDomain) {
        $parsed    = parse_url($orgDomain);
        $host      = $parsed['host'] ?? '';
        $orgDomain = $host ? preg_replace('/^www\./', '', $host) : '';
    }

    if (empty($firstName) || empty($orgDomain)) return '';

    $payload = [
        'full_name'   => $firstName . ' ' . $lastName,
        'domain_name' => $orgDomain,
    ];

    $ch = curl_init('https://api.anymailfinder.com/v5.0/search/person.json');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $anymailfinderApiKey,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) return '';
    $data  = json_decode($response, true);
    $email = strtolower(trim($data['email'] ?? ''));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

// ── pipelineLog helper: upsert step status into cron_log and optionally notify ─
/**
 * Helper: upsert a step status into cron_log and optionally create a notification.
 */
function pipelineLog(string $jobName, string $status, string $message, int $durationMs = 0, bool $notify = false): void
{
    try {
        Database::query(
            "INSERT INTO cron_log (job_name, status, message, duration_ms)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status=VALUES(status), message=VALUES(message),
                                     duration_ms=VALUES(duration_ms), last_run=NOW()",
            [$jobName, $status, $message, $durationMs]
        );
    } catch (Exception $e) { /* ignore */ }

    if ($notify) {
        try {
            Database::query(
                "INSERT INTO notifications (user_id, title, message, type) VALUES (0, ?, ?, ?)",
                ['Pipeline: ' . $jobName, $message, $status === 'ok' ? 'info' : 'warning']
            );
        } catch (Exception $e) { /* ignore */ }
    }
}

$collectionSaved      = 0;
$collectionSkipped    = 0;
$collectionDuplicates = 0;
$collectionFetched    = 0;
$collectionError      = null;
$collectionId         = null;

if (!empty($apolloApiKey)) {
    // Determine the start page — resume from last run if page tracking is enabled
    $lastPageFetched = (int)getSetting('last_apollo_page_fetched', '0');
    $startPage       = ($lastPageFetched > 0) ? $lastPageFetched + 1 : 1;

    // Determine how many pages to fetch
    $totalPages = ($apolloPaginationMode === 'all') ? $safetyMaxPages : $maxPages;
    $endPage    = ($apolloPaginationMode === 'all') ? ($startPage + $safetyMaxPages - 1) : ($startPage + $maxPages - 1);

    // Create a lead_collections record to track this run
    Database::query(
        "INSERT INTO lead_collections (source, total_fetched, status, search_params, started_at)
         VALUES (?, ?, 'running', ?, NOW())",
        ['Apollo Pipeline', 0, json_encode(['location' => $location, 'industry' => $industry, 'start_page' => $startPage])]
    );
    $collectionId = (int)Database::lastInsertId();

    pipelineLog('full_pipeline_step1', 'ok', "Starting lead collection from page $startPage (mode: $apolloPaginationMode)");

    $searchParams = [
        'q_organization_industry_tag_ids' => [],
        'person_titles'                   => !empty($titles) ? $titles : ['CEO'],
        'person_locations'                => [$location],
        'per_page'                        => $perPage,
        'reveal_personal_emails'          => true,
    ];

    $lastSuccessfulPage = $lastPageFetched;
    $allPagesExhausted  = false;

    for ($page = $startPage; $page <= $endPage; $page++) {
        $pageParams = array_merge($searchParams, ['page' => $page]);

        // Apollo rate-limit retry: up to 3 attempts with exponential backoff
        $maxRetries  = 3;
        $response    = false;
        $httpCode    = 0;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init('https://api.apollo.io/api/v1/mixed_people/api_search');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($pageParams),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Cache-Control: no-cache',
                    'X-Api-Key: ' . $apolloApiKey,
                ],
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 429) {
                // Rate limited — wait with exponential backoff then retry
                $waitSeconds = (int)pow(2, $attempt); // 2s, 4s, 8s
                pipelineLog('full_pipeline_step1', 'ok', "Apollo rate limit (429) on page $page, attempt $attempt — waiting {$waitSeconds}s");
                sleep($waitSeconds);
                $response = false;
                continue;
            }
            break; // Success or non-429 error — stop retrying
        }

        if ($response === false || $httpCode !== 200) {
            $collectionError = 'Apollo HTTP ' . $httpCode . ' on page ' . $page . ' (after retries)';
            break;
        }

        $data   = json_decode($response, true);
        $people = $data['people'] ?? [];

        if (empty($people)) {
            // No more results — all pages exhausted; reset page tracker for next run
            $allPagesExhausted = true;
            break; // No more results — stop paginating
        }

        $collectionFetched  += count($people);
        $lastSuccessfulPage  = $page;

        foreach ($people as $person) {
            $email       = strtolower(trim($person['email'] ?? ''));
            $fullName    = trim($person['name'] ?? trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? '')));
            $linkedinUrl = trim($person['linkedin_url'] ?? '');

            if (empty($fullName) && empty($linkedinUrl)) {
                $collectionSkipped++;
                continue;
            }

            // Try enrichment services in priority order
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) && $enrichmentApolloActive) {
                $email = pipelineApolloEnrich($apolloApiKey, $person);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) && $enrichmentHunterActive && !empty($hunterApiKey)) {
                $email = pipelineHunterEnrich($hunterApiKey, $person);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) && $enrichmentAnymailfinderActive && !empty($anymailfinderApiKey)) {
                $email = pipelineAnymailfinderEnrich($anymailfinderApiKey, $person);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $collectionSkipped++;
                continue;
            }

            $existing = Database::fetchOne("SELECT id FROM leads WHERE email=?", [$email]);
            if ($existing) {
                $collectionDuplicates++;
                Database::query(
                    "INSERT INTO lead_collection_items (collection_id, lead_id, action) VALUES (?, ?, 'duplicate')",
                    [$collectionId, (int)$existing['id']]
                );
                continue;
            }

            $fn          = trim($person['first_name'] ?? '');
            $ln          = trim($person['last_name'] ?? '');
            $orgName     = trim($person['organization']['name'] ?? '');
            $orgIndustry = trim($person['organization']['industry'] ?? '');
            $segment     = pipelineMapSegment($orgIndustry ?: $industry);

            try {
                Database::query(
                    "INSERT INTO leads
                         (first_name, last_name, full_name, email, company, job_title,
                          role, segment, country, province, city, source, linkedin_url)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $fn, $ln, $fullName ?: "$fn $ln", $email,
                        $orgName, trim($person['title'] ?? ''),
                        trim($person['title'] ?? ''), $segment,
                        trim($person['country'] ?? 'Canada'), trim($person['state'] ?? ''),
                        trim($person['city'] ?? ''), 'Apollo Pipeline',
                        $linkedinUrl,
                    ]
                );
                $leadId = (int)Database::lastInsertId();
                $collectionSaved++;
                Database::query(
                    "INSERT INTO lead_collection_items (collection_id, lead_id, action) VALUES (?, ?, 'created')",
                    [$collectionId, $leadId]
                );
            } catch (Exception $e) {
                $collectionSkipped++;
            }
        }
    }

    Database::query(
        "UPDATE lead_collections
         SET total_fetched=?, total_saved=?, total_skipped=?, total_duplicates=?,
             status='completed', completed_at=NOW()
         WHERE id=?",
        [$collectionFetched, $collectionSaved, $collectionSkipped, $collectionDuplicates, $collectionId]
    );

    // Save page tracking: reset to 0 when all pages exhausted so next run starts from page 1
    try {
        if ($allPagesExhausted) {
            Database::query(
                "INSERT INTO site_settings (setting_key, setting_value) VALUES ('last_apollo_page_fetched', '0')
                 ON DUPLICATE KEY UPDATE setting_value='0'"
            );
        } elseif ($lastSuccessfulPage > $lastPageFetched) {
            Database::query(
                "INSERT INTO site_settings (setting_key, setting_value) VALUES ('last_apollo_page_fetched', ?)
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
                [(string)$lastSuccessfulPage]
            );
        }
    } catch (Exception $e) { /* ignore */ }

    $step1Msg = "Fetched: $collectionFetched | Saved: $collectionSaved | Dupes: $collectionDuplicates | Skipped: $collectionSkipped | Last page: $lastSuccessfulPage"
        . ($collectionError ? ' | Error: ' . $collectionError : '')
        . ($allPagesExhausted ? ' | All pages exhausted — reset' : '');
    pipelineLog('full_pipeline_step1', $collectionError ? 'error' : 'ok', $step1Msg, 0, $collectionSaved > 0);
}

$pipelineLog['step1_collection'] = [
    'collection_id' => $collectionId,
    'fetched'       => $collectionFetched,
    'saved'         => $collectionSaved,
    'duplicates'    => $collectionDuplicates,
    'skipped'       => $collectionSkipped,
    'error'         => $collectionError,
];

// =============================================================================
// STEP 2: Auto-create campaign for new leads (if no active campaign exists)
// =============================================================================

$campaignId      = null;
$campaignCreated = false;

// Check for any active campaign: running, draft, or scheduled
$existingCampaign = Database::fetchOne(
    "SELECT id FROM campaigns WHERE status IN ('running', 'draft', 'scheduled') ORDER BY id DESC LIMIT 1"
);

if ($existingCampaign) {
    $campaignId = (int)$existingCampaign['id'];

    // Update total_leads to reflect current eligible lead count
    $eligibleCount = (int)(Database::fetchOne(
        "SELECT COUNT(*) AS c FROM leads
         WHERE status='new' AND email NOT LIKE '%@noemail.placeholder' AND email != ''"
    )['c'] ?? 0);
    try {
        Database::query(
            "UPDATE campaigns SET total_leads=? WHERE id=?",
            [$eligibleCount, $campaignId]
        );
    } catch (Exception $e) { /* ignore */ }

    pipelineLog('full_pipeline_step2', 'ok', "Reused existing campaign #$campaignId (eligible leads: $eligibleCount)");
    $pipelineLog['step2_campaign'] = ['action' => 'reused', 'campaign_id' => $campaignId, 'total_leads' => $eligibleCount];
} elseif ($autoCampaignEnabled) {
    // Check if there are eligible leads (new status OR any leads if collection just ran)
    $newLeadsCount = (int)(Database::fetchOne(
        "SELECT COUNT(*) AS c FROM leads
         WHERE status='new' AND email NOT LIKE '%@noemail.placeholder' AND email != ''"
    )['c'] ?? 0);

    if ($newLeadsCount === 0 && $collectionSaved === 0) {
        pipelineLog('full_pipeline_step2', 'ok', 'Skipped campaign creation — no eligible leads found');
        $pipelineLog['step2_campaign'] = ['action' => 'skipped', 'reason' => 'No eligible leads'];
    } else {
        $defaultTpl = Database::fetchOne(
            "SELECT id FROM email_templates WHERE is_default=1 AND is_active=1 LIMIT 1"
        );
        if ($defaultTpl) {
            $campaignKey  = 'camp_' . time() . '_' . rand(1000, 9999);
            $campaignName = 'Auto Pipeline — ' . date('Y-m-d H:i');

            Database::query(
                "INSERT INTO campaigns
                     (campaign_key, name, template_id, filter_segment, filter_role,
                      filter_province, total_leads, status, test_mode, created_by,
                      target_mode, target_count)
                 VALUES (?, ?, ?, NULL, NULL, NULL, ?, 'running', 0, 0, ?, ?)",
                [$campaignKey, $campaignName, (int)$defaultTpl['id'], $newLeadsCount,
                 $pipelineTargetMode, $pipelineTargetCount]
            );
            $campaignId      = (int)Database::lastInsertId();
            $campaignCreated = true;

            pipelineLog('full_pipeline_step2', 'ok', "Created new campaign #$campaignId with $newLeadsCount leads", 0, true);
            $pipelineLog['step2_campaign'] = [
                'action'      => 'created',
                'campaign_id' => $campaignId,
                'total_leads' => $newLeadsCount,
            ];
        } else {
            pipelineLog('full_pipeline_step2', 'error', 'No default template found (set is_default=1 on a template)');
            $pipelineLog['step2_campaign'] = [
                'action' => 'skipped',
                'reason' => 'No default template found (set is_default=1 on a template)',
            ];
        }
    }
} else {
    pipelineLog('full_pipeline_step2', 'ok', 'Skipped — auto_campaign_enabled is off');
    $pipelineLog['step2_campaign'] = [
        'action' => 'skipped',
        'reason' => 'auto_campaign_enabled is off',
    ];
}

// =============================================================================
// STEP 3: Send emails for the active campaign
// =============================================================================

$totalSent   = 0;
$totalFailed = 0;
$limitHit    = false;
$limitReason = '';

if ($campaignId) {
    $campaign = Database::fetchOne("SELECT * FROM campaigns WHERE id=?", [$campaignId]);

    if ($campaign && in_array($campaign['status'], ['running', 'draft', 'scheduled'])) {
        // Ensure the campaign is marked running before we start sending
        if (in_array($campaign['status'], ['draft', 'scheduled'])) {
            Database::query(
                "UPDATE campaigns SET status='running', started_at=NOW() WHERE id=?",
                [$campaignId]
            );
            $campaign['status'] = 'running';
        }

        pipelineLog('full_pipeline_step3', 'ok', "Starting sends for campaign #$campaignId (batch size: $pipelineBatchSize)");

        $tpl = Database::fetchOne("SELECT * FROM email_templates WHERE id=?", [$campaign['template_id']]);

        if ($tpl) {
            $followUpSeq = 1;

            for ($i = 0; $i < $pipelineBatchSize; $i++) {
                // Re-check campaign status before each send (user may have paused/stopped)
                $freshStatus = Database::fetchOne("SELECT status FROM campaigns WHERE id=?", [$campaignId]);
                if (!$freshStatus || $freshStatus['status'] !== 'running') {
                    break; // Campaign was paused/stopped/completed — stop sending immediately
                }

                // Cap execution time to prevent overlap with next cron run
                if ((microtime(true) - $startTime) > $maxRunSeconds) {
                    $limitHit    = true;
                    $limitReason = 'Max run time reached (' . $maxRunSeconds . 's)';
                    break;
                }

                // Respect warm-up and daily/weekly/monthly limits before each send
                $limitCheck = checkSendingLimits($followUpSeq);
                if (!$limitCheck['allowed']) {
                    $limitHit    = true;
                    $limitReason = $limitCheck['reason'];
                    break;
                }

                // Check fixed target limit
                if (($campaign['target_mode'] ?? 'all') === 'fixed' && (int)($campaign['target_count'] ?? 0) > 0) {
                    if (((int)$campaign['sent_count'] + $totalSent) >= (int)$campaign['target_count']) {
                        Database::query(
                            "UPDATE campaigns SET status='completed', completed_at=NOW() WHERE id=?",
                            [$campaignId]
                        );
                        break;
                    }
                }

                // Build lead query respecting any campaign filters
                $where  = "l.status NOT IN ('unsubscribed','bounced','emailed') AND l.email NOT LIKE '%@noemail.placeholder'";
                $params = [];
                if (!empty($campaign['filter_segment'])) {
                    $where   .= ' AND LOWER(TRIM(l.segment))=LOWER(TRIM(?))';
                    $params[] = $campaign['filter_segment'];
                }
                if (!empty($campaign['filter_role'])) {
                    $where   .= ' AND l.role LIKE ?';
                    $params[] = '%' . $campaign['filter_role'] . '%';
                }
                if (!empty($campaign['filter_province'])) {
                    $where   .= ' AND LOWER(TRIM(l.province))=LOWER(TRIM(?))';
                    $params[] = $campaign['filter_province'];
                }

                $lead = Database::fetchOne(
                    "SELECT l.* FROM leads l
                     LEFT JOIN email_logs el
                            ON el.lead_id = l.id AND el.campaign_id = ? AND el.follow_up_sequence = ?
                     WHERE $where AND el.id IS NULL LIMIT 1",
                    array_merge([$campaignId, $followUpSeq], $params)
                );

                if (!$lead) {
                    // No more eligible leads — campaign is complete
                    Database::query(
                        "UPDATE campaigns SET status='completed', completed_at=NOW() WHERE id=?",
                        [$campaignId]
                    );
                    break;
                }

                $result = sendCampaignEmail($campaign, $tpl, $lead, $followUpSeq);

                if ($result['status'] === 'sent') {
                    $totalSent++;
                } elseif ($result['status'] === 'skipped') {
                    break; // Campaign was paused/stopped mid-send — stop the loop
                } else {
                    $totalFailed++;
                }

                if ($delaySeconds > 0) {
                    sleep($delaySeconds);
                }
            }
        }
    }

    $pipelineLog['step3_sending'] = [
        'campaign_id'  => $campaignId,
        'sent'         => $totalSent,
        'failed'       => $totalFailed,
        'limit_hit'    => $limitHit,
        'limit_reason' => $limitReason ?: null,
    ];

    $step3Msg = "Campaign #$campaignId | Sent: $totalSent | Failed: $totalFailed"
        . ($limitHit ? ' | Stopped: ' . $limitReason : '');
    pipelineLog('full_pipeline_step3', ($totalFailed > 0 && $totalSent === 0) ? 'error' : 'ok', $step3Msg, 0, $totalSent > 0);
} else {
    $pipelineLog['step3_sending'] = ['skipped' => true, 'reason' => 'No campaign available'];
}

// =============================================================================
// Log to cron_log and return response
// =============================================================================

$durationMs = (int)round((microtime(true) - $startTime) * 1000);
$logMessage = sprintf(
    'Leads: +%d saved | Campaign: %s | Sent: %d',
    $collectionSaved,
    $campaignCreated
        ? 'created #' . $campaignId
        : ($campaignId ? 'reused #' . $campaignId : 'none'),
    $totalSent
);
if ($limitHit) {
    $logMessage .= ' | Limit: ' . $limitReason;
}

// ── Release the advisory lock ─────────────────────────────────────────────────
try {
    Database::fetchOne("SELECT RELEASE_LOCK('healthtech_email_sender')");
} catch (Exception $e) { /* ignore */ }

pipelineLog('full_pipeline', 'ok', $logMessage, $durationMs, false);

echo json_encode([
    'success'  => true,
    'duration' => $durationMs,
    'pipeline' => $pipelineLog,
]);
