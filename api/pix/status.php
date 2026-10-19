<?php
// ============================================================================
// ENDPOINT BACKEND: CONSULTA DE STATUS DO PIX (STATUS.PHP)
// ============================================================================

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$external_id = $_GET['external_id'] ?? $_POST['external_id'] ?? '';
if (empty($external_id)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $data = json_decode($rawInput, true);
        $external_id = $data['external_id'] ?? '';
    }
}

$external_id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$external_id);

if (empty($external_id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'external_id é obrigatório']);
    exit;
}

$cacheFile = STORAGE_DIR . '/pix_cache/' . md5($external_id) . '.json';
$localData = [];
if (file_exists($cacheFile)) {
    $localData = json_decode(file_get_contents($cacheFile), true) ?: [];
}

// Se já constar como pago no cache local, retorna imediatamente
if (!empty($localData['status']) && strtolower($localData['status']) === 'paid') {
    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'status' => 'paid',
        'paid' => true,
        'external_id' => $external_id
    ]);
    exit;
}

// Consultar na BuckPay API para confirmar o status REAL via GET
$apiUrl = BUCKPAY_BASE_URL . '/v1/transactions/external_id/' . urlencode($external_id);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer ' . BUCKPAY_TOKEN,
        'User-Agent: ' . BUCKPAY_USER_AGENT
    ],
    CURLOPT_TIMEOUT => 10
]);

$responseBody = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$status = 'pending';
$paid = false;

if ($httpCode >= 200 && $httpCode < 300 && !empty($responseBody)) {
    $apiResp = json_decode($responseBody, true);
    $remoteStatus = strtolower($apiResp['status'] ?? $apiResp['data']['status'] ?? '');
    
    if (in_array($remoteStatus, ['paid', 'approved', 'completed', 'processed', 'success', 'pago'], true)) {
        $status = 'paid';
        $paid = true;

        // Atualizar no cache local se foi confirmado como pago
        if (!empty($localData)) {
            $localData['status'] = 'paid';
            $localData['paid'] = true;
            @file_put_contents($cacheFile, json_encode($localData));
        }
    }
}

http_response_code(200);
echo json_encode([
    'ok' => true,
    'status' => $status,
    'paid' => $paid,
    'external_id' => $external_id
]);
