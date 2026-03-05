<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
Auth::check();

$service = $_GET['service'] ?? '';

switch ($service) {

    case 'brevo':
        $key = getSetting('brevo_api_key') ?: BREVO_API_KEY;
        if (!$key) { echo json_encode(['success'=>false,'error'=>'No Brevo API key configured']); exit; }
        $ch = curl_init('https://api.brevo.com/v3/account');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['api-key: '.$key, 'Accept: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($body, true) ?? [];
        if ($code === 200 && !empty($data['email'])) {
            echo json_encode(['success'=>true,'message'=>'Connected — account: '.$data['email']]);
        } else {
            $err = $data['message'] ?? ('HTTP '.$code);
            echo json_encode(['success'=>false,'error'=>$err]);
        }
        break;

    case 'n8n':
        $key = getSetting('n8n_api_key') ?: N8N_API_KEY;
        $url = getSetting('n8n_url') ?: 'https://smnurnobi.app.n8n.cloud';
        if (!$key) { echo json_encode(['success'=>false,'error'=>'No n8n API key configured']); exit; }
        $ch = curl_init(rtrim($url,'/').'/api/v1/workflows?limit=1');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['X-N8N-API-KEY: '.$key, 'Accept: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200) {
            $data = json_decode($body, true) ?? [];
            $count = count($data['data'] ?? []);
            echo json_encode(['success'=>true,'message'=>'Connected — '.$count.' workflow(s) found']);
        } else {
            echo json_encode(['success'=>false,'error'=>'HTTP '.$code.' — Check URL and API key']);
        }
        break;

    case 'apollo':
        $key = getSetting('apollo_api_key') ?: APOLLO_API_KEY;
        if (!$key) { echo json_encode(['success'=>false,'error'=>'No Apollo API key configured']); exit; }
        $ch = curl_init('https://api.apollo.io/v1/auth/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['api_key'=>$key]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($body, true) ?? [];
        if ($code === 200 && ($data['is_logged_in'] ?? false)) {
            echo json_encode(['success'=>true,'message'=>'Connected — Apollo API valid']);
        } else {
            $err = $data['message'] ?? ('HTTP '.$code);
            echo json_encode(['success'=>false,'error'=>$err]);
        }
        break;

    case 'brevo_info':
        $key = getSetting('brevo_api_key') ?: BREVO_API_KEY;
        if (!$key) { echo json_encode(['success'=>false,'error'=>'No Brevo API key configured']); exit; }
        $ch = curl_init('https://api.brevo.com/v3/account');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['api-key: '.$key, 'Accept: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
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
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'Unknown service']);
}
