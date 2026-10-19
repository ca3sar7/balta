<?php
// ============================================================================
// ENDPOINT BACKEND: WEBHOOK POSTBACK (WEBHOOK.PHP)
// ============================================================================

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

// Responde HTTP 200 sempre (regra de resiliência de Webhook)
function respondOk($message = 'Webhook recebido com sucesso') {
    http_response_code(200);
    echo json_encode(['ok' => true, 'message' => $message]);
    exit;
}

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    $rawInput = file_get_contents('php://stdin');
}

$data = json_decode($rawInput, true) ?: [];

// Extrair external_id da notificação recebida (usa como gatilho)
$external_id = $data['external_id'] ?? $data['data']['external_id'] ?? $data['id'] ?? '';
$external_id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$external_id);

if (empty($external_id)) {
    // Retorna 200 mesmo para webhook vazio para não causar retentativas de bot
    respondOk('Webhook sem external_id recebido');
}

$cacheFile = STORAGE_DIR . '/pix_cache/' . md5($external_id) . '.json';

// Se a cobrança nem existe localmente, responde 200 sem alterar nada
if (!file_exists($cacheFile)) {
    respondOk('external_id não encontrado no cache local; nenhuma alteração realizada');
}

// REGRA CRÍTICA: Confirma o status REAL via GET na BuckPay API antes de marcar como PAID
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

if ($httpCode >= 200 && $httpCode < 300 && !empty($responseBody)) {
    $apiResp = json_decode($responseBody, true);
    $remoteStatus = strtolower($apiResp['status'] ?? $apiResp['data']['status'] ?? '');

    if (in_array($remoteStatus, ['paid', 'approved', 'completed', 'processed', 'success', 'pago'], true)) {
        $localData = json_decode(file_get_contents($cacheFile), true) ?: [];
        $localData['status'] = 'paid';
        $localData['paid'] = true;
        $localData['updated_at'] = date('Y-m-d H:i:s');
        @file_put_contents($cacheFile, json_encode($localData));
        respondOk('Transação confirmada e atualizada para PAID com sucesso');
    }
}

respondOk('Webhook processado. Status não confirmado como pago na consulta GET.');
