<?php
// ============================================================================
// ENDPOINT BACKEND: CRIAÇÃO DE PIX (BUCKPAY API INTEGRATION)
// ============================================================================

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// 1. Receber e decodificar o JSON recebido
$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    $rawInput = file_get_contents('php://stdin');
}
$input = json_decode($rawInput, true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Corpo da requisição inválido']);
    exit;
}

// 2. Validação contra a WHITELIST no Servidor
$amount = (int)($input['amount'] ?? 0);
if (!in_array($amount, ALLOWED_AMOUNTS, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Valor não permitido. Selecione um dos valores pré-definidos.']);
    exit;
}

// Sanitização de Session ID e Tentativa
$rawSessionId = (string)($input['sessionId'] ?? '');
$sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $rawSessionId);
if (empty($sessionId)) {
    $sessionId = 'sess_' . bin2hex(random_bytes(8));
}

$tentativa = max(1, (int)($input['tentativa'] ?? 1));

// 3. Rate Limit por IP (REMOTE_ADDR por padrão; proxy se USE_PROXY_IP for true)
if (USE_PROXY_IP && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $clientIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
} else {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

$ipHash = md5($clientIp);
$rateLimitFile = STORAGE_DIR . '/rate_limits/' . $ipHash . '.json';

$now = time();
$timeWindow = 600; // 10 minutos
$maxRequests = 10;

$timestamps = [];
if (file_exists($rateLimitFile)) {
    $data = json_decode(file_get_contents($rateLimitFile), true);
    if (is_array($data)) {
        $timestamps = array_filter($data, function ($ts) use ($now, $timeWindow) {
            return ($now - $ts) < $timeWindow;
        });
    }
}

if (count($timestamps) >= $maxRequests) {
    http_response_code(429);
    echo json_encode(['error' => 'Limite de requisições excedido. Por favor, aguarde alguns minutos para tentar novamente.']);
    exit;
}

$timestamps[] = $now;
@file_put_contents($rateLimitFile, json_encode(array_values($timestamps)));

// 4. Idenpotência: external_id = <sessionId>_<amountCentavos>_<tentativa>
$external_id = "{$sessionId}_{$amount}_{$tentativa}";
$cacheFile = STORAGE_DIR . '/pix_cache/' . md5($external_id) . '.json';

if (file_exists($cacheFile)) {
    $cacheAge = time() - filemtime($cacheFile);
    if ($cacheAge < 1800) {
        $cachedData = json_decode(file_get_contents($cacheFile), true);
        if ($cachedData) {
            $cachedData['cached'] = true;
            http_response_code(200);
            echo json_encode($cachedData);
            exit;
        }
    }
}

// 5. Obter perfil do doador via rotação sequencial (round-robin)
$donor = getNextDonorProfile();

$payload = [
    'payment_method' => 'pix',
    'amount' => $amount,
    'external_id' => $external_id,
    'buyer' => [
        'name' => $donor['name'],
        'email' => $donor['email'],
        'phone' => $donor['phone'],
        'document' => $donor['document']
    ]
];

if (!empty($input['utmParams']) && is_array($input['utmParams'])) {
    $u = $input['utmParams'];
    $payload['tracking'] = [
        'ref' => (string)($u['ref'] ?? $u['utm_source'] ?? ''),
        'src' => (string)($u['src'] ?? $u['utm_source'] ?? ''),
        'sck' => (string)($u['sck'] ?? $u['xcod'] ?? ''),
        'utm_source' => (string)($u['utm_source'] ?? ''),
        'utm_medium' => (string)($u['utm_medium'] ?? ''),
        'utm_campaign' => (string)($u['utm_campaign'] ?? ''),
        'utm_id' => (string)($u['utm_id'] ?? ''),
        'utm_term' => (string)($u['utm_term'] ?? ''),
        'utm_content' => (string)($u['utm_content'] ?? '')
    ];
    $payload['metadata'] = ['tracking' => array_filter($u)];
}

if (!empty(WEBHOOK_PUBLIC_URL) && filter_var(WEBHOOK_PUBLIC_URL, FILTER_VALIDATE_URL)) {
    $payload['postbackUrl'] = WEBHOOK_PUBLIC_URL;
}

// 6. Chamada à API BuckPay via cURL (Com BUCKPAY_USER_AGENT e BUCKPAY_TOKEN)
$apiUrl = BUCKPAY_BASE_URL . '/v1/transactions';

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . BUCKPAY_TOKEN,
        'User-Agent: ' . BUCKPAY_USER_AGENT
    ],
    CURLOPT_TIMEOUT => 20
]);

$responseBody = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 7. Tratar Erro ou Sucesso
if ($curlError || $httpCode < 200 || $httpCode >= 300) {
    // Log sem expor token nem CPF completo (Item 9)
    $sanitizedPayload = $payload;
    if (isset($sanitizedPayload['buyer']['document'])) {
        $sanitizedPayload['buyer']['document'] = maskCpf($sanitizedPayload['buyer']['document']);
    }

    $logEntry = sprintf(
        "[%s] IP: %s | HTTP: %d | ExternalID: %s\nPayload: %s\nResponse: %s\ncURL Error: %s\n----------------------------------------\n",
        date('Y-m-d H:i:s'),
        $clientIp,
        $httpCode,
        $external_id,
        json_encode($sanitizedPayload, JSON_UNESCAPED_UNICODE),
        $responseBody ?: 'SEM_RESPOSTA',
        $curlError ?: 'NENHUM'
    );
    @file_put_contents(LOGS_DIR . '/pix_errors.log', $logEntry, FILE_APPEND);

    http_response_code($httpCode >= 400 && $httpCode < 600 ? $httpCode : 500);
    echo json_encode([
        'error' => 'Falha na comunicação com a API de pagamento BuckPay',
        'status_code' => $httpCode,
        'api_response' => json_decode($responseBody, true) ?: $responseBody,
        'external_id' => $external_id
    ]);
    exit;
}

$apiResponse = json_decode($responseBody, true);

$qrCodeData = $apiResponse['data']['pix']['qrcode_base64'] ?? $apiResponse['qrcode'] ?? $apiResponse['qr_code'] ?? $apiResponse['qrcode_url'] ?? '';
$copiaColaData = $apiResponse['data']['pix']['code'] ?? $apiResponse['copia_cola'] ?? $apiResponse['emv'] ?? $apiResponse['pix_code'] ?? '';

$outputData = [
    'success' => true,
    'ok' => true,
    'status' => 'pending',
    'paid' => false,
    'external_id' => $external_id,
    'amount' => $amount,
    'amount_formatted' => 'R$ ' . number_format($amount / 100, 2, ',', '.'),
    'qrcode' => $qrCodeData,
    'copia_cola' => $copiaColaData,
    'expires_at' => $apiResponse['expires_at'] ?? date('Y-m-d H:i:s', time() + 300),
    'created_at' => date('Y-m-d H:i:s'),
    'raw' => $apiResponse
];

// Salvar no Cache
@file_put_contents($cacheFile, json_encode($outputData));

http_response_code(200);
echo json_encode($outputData);
