<?php
// Start output buffering BEFORE any output
ob_start();

// Bootstrap: set session name and start session BEFORE loading config
require_once __DIR__ . '/../includes/session_bootstrap.php';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Clear any accidental output
ob_clean();

header('Content-Type: application/json');

// Accept session auth (admin panel) OR API key (n8n / direct calls)
$apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
if (empty($_SESSION['user_id'])) {
    $validApiKey = !empty(N8N_API_KEY) ? N8N_API_KEY : getSetting('n8n_api_key');
    if ($apiKey === '' || $validApiKey === '' || !hash_equals($validApiKey, $apiKey)) {
        ob_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

function safeGet(string $key, string $default = ''): string {
    try {
        return getSetting($key) ?: $default;
    } catch (Exception $e) {
        return $default;
    }
}

$service = $_GET['service'] ?? '';

try {

switch ($service) {

    case 'brevo':
        $key = safeGet('brevo_api_key') ?: BREVO_API_KEY;
        if (!$key) {
            ob_clean();
            echo json_encode(['success'=>false,'error'=>'No Brevo API key configured']);
            exit;
        }
        $ch = curl_init('https://api.brevo.com/v3/account');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['api-key: '.$key, 'Accept: application/json'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            ob_clean();
            echo json_encode(['success'=>false,'error'=>'CURL Error: ' . $error]);
            exit;
        }

        $data = json_decode($body, true) ?? [];
        ob_clean();
        if ($code === 200 && !empty($data['email'])) {
            echo json_encode(['success'=>true,'message'=>'Connected — account: '.$data['email']]);
        } else {
            $err = $data['message'] ?? ('HTTP '.$code);
            echo json_encode(['success'=>false,'error'=>$err]);
        }
        break;

    case 'n8n':
        $key = safeGet('n8n_api_key') ?: N8N_API_KEY;
        $url = safeGet('n8n_url') ?: N8N_URL;
        if (!$key) {
            ob_clean();
            echo json_encode(['success'=>false,'error'=>'No n8n API key configured']);
            exit;
        }
        $ch = curl_init(rtrim($url,'/').'/api/v1/workflows?limit=1');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['X-N8N-API-KEY: '.$key, 'Accept: application/json'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            ob_clean();
            echo json_encode(['success'=>false,'error'=>'CURL Error: ' . $error]);
            exit;
        }

        ob_clean();
        if ($code === 200) {
            $data = json_decode($body, true) ?? [];
            $count = count($data['data'] ?? []);
            echo json_encode(['success'=>true,'message'=>'Connected — '.$count.' workflow(s) found']);
        } else {
            echo json_encode(['success'=>false,'error'=>'HTTP '.$code.' — Check URL and API key']);
        }
        break;

    case 'apollo':
        $key = safeGet('apollo_api_key') ?: APOLLO_API_KEY;
        if (!$key) {
            ob_clean();
            echo json_encode(['success'=>false,'error'=>'No Apollo API key configured']);
            exit;
        }
        $ch = curl_init('https://api.apollo.io/api/v1/auth/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Api-Key: ' . $key,
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            ob_clean();
            echo json_encode(['success'=>false,'error'=>'CURL Error: ' . $error]);
            exit;
        }

        $data = json_decode($body, true) ?? [];
        ob_clean();
        if ($code === 200 && ($data['is_logged_in'] ?? false)) {
            echo json_encode(['success'=>true,'message'=>'Connected — Apollo API valid']);
        } else {
            $err = $data['message'] ?? ('HTTP '.$code);
            echo json_encode(['success'=>false,'error'=>$err]);
        }
        break;

    case 'brevo_info':
        $key = safeGet('brevo_api_key') ?: BREVO_API_KEY;
        if (!$key) {
            ob_clean();
            echo json_encode(['success'=>false,'error'=>'No Brevo API key configured']);
            exit;
        }
        $ch = curl_init('https://api.brevo.com/v3/account');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['api-key: '.$key, 'Accept: application/json'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            ob_clean();
            echo json_encode(['success'=>false,'error'=>'CURL Error: ' . $error]);
            exit;
        }

        ob_clean();
        if ($code === 200) {
            $data = json_decode($body, true) ?? [];
            echo json_encode([
                'success'          => true,
                'plan'             => $data['plan'][0]['type'] ?? '—',
                'credits'          => $data['plan'][0]['credits'] ?? '—',
                'creditsRemaining' => $data['plan'][0]['creditsRemaining'] ?? '—',
                'email'            => $data['email'] ?? '',
            ]);
        } else {
            echo json_encode(['success'=>false,'error'=>'HTTP '.$code]);
        }
        break;

    case 'enrichment_apollo':
        $key = safeGet('apollo_api_key') ?: APOLLO_API_KEY;
        if (!$key) {
            ob_clean();
            echo json_encode(['success'=>false,'error'=>'Apollo API key not configured']);
            exit;
        }
        $ch = curl_init('https://api.apollo.io/api/v1/people/match');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['reveal_personal_emails' => true, 'first_name' => 'Test', 'last_name' => 'User', 'organization_name' => 'TestCo']),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Cache-Control: no-cache',
                'X-Api-Key: ' . $key,
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body  = curl_exec($ch);
        $error = curl_error($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($error) {
            ob_clean();
            echo json_encode(['success'=>false,'error'=>'CURL Error: '.$error]);
            exit;
        }
        ob_clean();
        if ($code === 200) {
            echo json_encode(['success'=>true,'message'=>'Connected — Apollo People Match endpoint accessible']);
        } else {
            $data = json_decode($body, true) ?? [];
            $err  = $data['message'] ?? ('HTTP '.$code.' — Connection failed');
            echo json_encode(['success'=>false,'error'=>$err]);
        }
        break;

    case 'enrichment_hunter':
        $key = safeGet('hunter_api_key', '');
        if (empty($key)) {
            ob_clean();
            echo json_encode(['success'=>false,'error'=>'Hunter.io API key not configured']);
            exit;
        }
        $ch = curl_init('https://api.hunter.io/v2/account?api_key=' . urlencode($key));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_SSL_VERIFYPEER=>true]);
        $body  = curl_exec($ch);
        $error = curl_error($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($error) {
            ob_clean();
            echo json_encode(['success'=>false,'error'=>'CURL Error: '.$error]);
            exit;
        }
        $data = json_decode($body, true) ?? [];
        ob_clean();
        if ($code === 200 && isset($data['data']['email'])) {
            $requests = $data['data']['requests']['searches']['available'] ?? 0;
            echo json_encode(['success'=>true,'message'=>'Connected! Account: '.$data['data']['email'].'. Searches available: '.$requests]);
        } else {
            echo json_encode(['success'=>false,'error'=>'Invalid API key or connection failed (HTTP '.$code.')']);
        }
        break;

    case 'enrichment_anymailfinder':
        $key = safeGet('anymailfinder_api_key', '');
        if (empty($key)) {
            ob_clean();
            echo json_encode(['success'=>false,'error'=>'Anymailfinder API key not configured']);
            exit;
        }
        $ch = curl_init('https://api.anymailfinder.com/v5.0/account.json');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$key],
        ]);
        $body  = curl_exec($ch);
        $error = curl_error($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($error) {
            ob_clean();
            echo json_encode(['success'=>false,'error'=>'CURL Error: '.$error]);
            exit;
        }
        $data = json_decode($body, true) ?? [];
        ob_clean();
        if ($code === 200) {
            $remaining = $data['requests_remaining'] ?? 'N/A';
            echo json_encode(['success'=>true,'message'=>'Connected! Requests remaining: '.$remaining]);
        } else {
            echo json_encode(['success'=>false,'error'=>'Invalid API key or connection failed (HTTP '.$code.')']);
        }
        break;

    default:
        ob_clean();
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'Unknown service: ' . htmlspecialchars($service, ENT_QUOTES, 'UTF-8')]);
}

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
