<?php
ob_start();
// Public endpoint for n8n to read Apollo search configuration
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

ob_clean();
header('Content-Type: application/json');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
if ($apiKey === '' || $apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

echo json_encode([
    'apollo_api_key' => getSetting('apollo_api_key', APOLLO_API_KEY),
    'search_location' => getSetting('apollo_search_location', 'Canada'),
    'search_industry' => getSetting('apollo_search_industry', 'Health Technology'),
    'search_titles'  => array_values(array_filter(array_map('trim', explode("\n", getSetting('apollo_search_titles', ''))))),
    'per_page'       => (int)getSetting('apollo_per_page', '100'),
    'max_pages'      => (int)getSetting('apollo_max_pages', '5'),
    'app_url'        => APP_URL,
    'n8n_api_key'    => N8N_API_KEY,
]);
