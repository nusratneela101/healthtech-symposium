<?php
// Start output buffering BEFORE any output
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Clear any accidental output
ob_clean();

header('Content-Type: application/json');

try {
    Auth::check();
} catch (Exception $e) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: ' . $e->getMessage()]);
    exit;
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
        $url = safeGet('n8n_url') ?: 'https://smnurnobi.app.n8n.cloud';
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
        $ch = curl_init('https://api.apollo.io/v1/auth/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['api_key'=>$key]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
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
