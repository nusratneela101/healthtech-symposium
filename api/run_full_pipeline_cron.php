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

// Read pipeline settings
$pipelineBatchSize    = max(1, (int)getSetting('pipeline_batch_size', '100'));
$delaySeconds         = max(0, (int)getSetting('cron_send_delay_seconds', '5'));
$autoCampaignEnabled  = getSetting('auto_campaign_enabled', '1') === '1';
$apolloPaginationMode = getSetting('apollo_pagination_mode', 'configured');

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

$collectionSaved      = 0;
$collectionSkipped    = 0;
$collectionDuplicates = 0;
$collectionFetched    = 0;
$collectionError      = null;
$collectionId         = null;

if (!empty($apolloApiKey)) {
    // Create a lead_collections record to track this run
    Database::query(
        "INSERT INTO lead_collections (source, total_fetched, status, search_params, started_at)
         VALUES (?, ?, 'running', ?, NOW())",
        ['Apollo Pipeline', 0, json_encode(['location' => $location, 'industry' => $industry])]
    );
    $collectionId = (int)Database::lastInsertId();

    $searchParams = [
        'q_organization_industry_tag_ids' => [],
        'person_titles'                   => !empty($titles) ? $titles : ['CEO'],
        'person_locations'                => [$location],
        'per_page'                        => $perPage,
        'reveal_personal_emails'          => true,
    ];

    // Determine how many pages to fetch
    $totalPages = ($apolloPaginationMode === 'all') ? $safetyMaxPages : $maxPages;

    for ($page = 1; $page <= $totalPages; $page++) {
        $pageParams = array_merge($searchParams, ['page' => $page]);

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

        if ($response === false || $httpCode !== 200) {
            $collectionError = 'Apollo HTTP ' . $httpCode . ' on page ' . $page;
            break;
        }

        $data   = json_decode($response, true);
        $people = $data['people'] ?? [];

        if (empty($people)) {
            break; // No more results — stop paginating
        }

        $collectionFetched += count($people);

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
// STEP 2: Auto-create campaign for new leads (if no running campaign exists)
// =============================================================================

$campaignId      = null;
$campaignCreated = false;

$existingCampaign = Database::fetchOne("SELECT id FROM campaigns WHERE status='running' LIMIT 1");

if ($existingCampaign) {
    $campaignId = (int)$existingCampaign['id'];
    $pipelineLog['step2_campaign'] = ['action' => 'reused', 'campaign_id' => $campaignId];
} elseif ($autoCampaignEnabled) {
    $defaultTpl = Database::fetchOne(
        "SELECT id FROM email_templates WHERE is_default=1 AND is_active=1 LIMIT 1"
    );
    if ($defaultTpl) {
        $newLeadsCount = (int)(Database::fetchOne(
            "SELECT COUNT(*) AS c FROM leads WHERE status='new'"
        )['c'] ?? 0);

        $campaignKey  = 'camp_' . time() . '_' . rand(1000, 9999);
        $campaignName = 'Auto Pipeline — ' . date('Y-m-d H:i');

        Database::query(
            "INSERT INTO campaigns
                 (campaign_key, name, template_id, filter_segment, filter_role,
                  filter_province, total_leads, status, test_mode, created_by)
             VALUES (?, ?, ?, NULL, NULL, NULL, ?, 'running', 0, 0)",
            [$campaignKey, $campaignName, (int)$defaultTpl['id'], $newLeadsCount]
        );
        $campaignId      = (int)Database::lastInsertId();
        $campaignCreated = true;

        $pipelineLog['step2_campaign'] = [
            'action'      => 'created',
            'campaign_id' => $campaignId,
            'total_leads' => $newLeadsCount,
        ];
    } else {
        $pipelineLog['step2_campaign'] = [
            'action' => 'skipped',
            'reason' => 'No default template found (set is_default=1 on a template)',
        ];
    }
} else {
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

    if ($campaign && in_array($campaign['status'], ['running', 'draft'])) {
        // Ensure the campaign is marked running before we start sending
        if ($campaign['status'] === 'draft') {
            Database::query(
                "UPDATE campaigns SET status='running', started_at=NOW() WHERE id=?",
                [$campaignId]
            );
            $campaign['status'] = 'running';
        }

        $tpl = Database::fetchOne("SELECT * FROM email_templates WHERE id=?", [$campaign['template_id']]);

        if ($tpl) {
            $followUpSeq = 1;

            for ($i = 0; $i < $pipelineBatchSize; $i++) {
                // Respect warm-up and daily/weekly/monthly limits before each send
                $limitCheck = checkSendingLimits($followUpSeq);
                if (!$limitCheck['allowed']) {
                    $limitHit    = true;
                    $limitReason = $limitCheck['reason'];
                    break;
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

try {
    Database::query(
        "INSERT INTO cron_log (job_name, status, message, duration_ms)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status=VALUES(status), message=VALUES(message),
                                 duration_ms=VALUES(duration_ms), last_run=NOW()",
        ['full_pipeline', 'ok', $logMessage, $durationMs]
    );
} catch (Exception $e) { /* ignore — table may not exist yet */ }

echo json_encode([
    'success'  => true,
    'duration' => $durationMs,
    'pipeline' => $pipelineLog,
]);
