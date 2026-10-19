# Kit de Integração PIX / BuckPay (Réplica para Novas Ofertas)

> [!IMPORTANT]
> **Instruções para o agente que vai replicar a integração em outro projeto**:
> 1. Este kit é autocontido e contém a arquitetura completa de backend (PHP), regras de segurança (.htaccess), persistência e frontend (JS/HTML).
> 2. Crie o arquivo `.env` na raiz do projeto de destino com base no modelo `.env.example` fornecido na Seção 2 e preencha as credenciais. **Nunca faça commit do `.env`**.
> 3. Siga a ordem recomendada de implementação:
>    - `config.php` ➔ `.env` / `.htaccess` ➔ `api/pix/create.php` ➔ `api/pix/status.php` ➔ `api/pix/webhook.php` ➔ Frontend JS ➔ Bateria de testes de validação.

---

## 1. Visão Geral

- **Stack Tecnológico**: PHP 7.4+ (vanilla sem frameworks), Apache com `mod_rewrite` e `mod_authz_core`, HTML5, CSS3, JavaScript ES6 (Vanilla).
- **Hospedagem Esperada**: Servidor Web Apache (cPanel, Hostinger, XAMPP, VPS) com suporte a `.htaccess` e permissões de escrita nos diretórios locais `storage/` e `logs/`.
- **Fluxo da Integração em 5 Passos**:
  1. **Clique no Valor**: O usuário clica em um botão de valor pré-definido (ex: R$ 6,50, R$ 9,50, R$ 15, R$ 25, R$ 50, R$ 75).
  2. **Chamada /api/pix/create**: O frontend faz um `POST` enviando o valor em centavos, ID da sessão, tentativa e parâmetros UTM.
  3. **Geração do Pix**: O backend valida o valor na whitelist, obtém o perfil do comprador (com rotação automatizada), chama a BuckPay API e retorna o QR Code base64 e a chave Copia e Cola.
  4. **Acompanhamento (Polling & Webhook)**: O frontend consulta o status a cada 5s via `GET /api/pix/status`. Em paralelo, a BuckPay notifica o `/api/pix/webhook`, que consulta o status real diretamente na BuckPay para confirmar a transação.
  5. **Confirmação Pago**: Ao confirmar o pagamento, o modal e a tela atualizam instantaneamente para o estado "Pago".

---

## 2. Configuração e Segredos

### Tabela de Variáveis de Ambiente

| Variável | Lida em | Obrigatória? | Exemplo de Valor (Não-secreto / `<REDACTED>`) |
| :--- | :--- | :--- | :--- |
| `BUCKPAY_TOKEN` | `config.php` | Sim | `<REDACTED>` |
| `BUCKPAY_USER_AGENT` | `config.php` | Não | `Buckpay API` |
| `BUCKPAY_BASE_URL` | `config.php` | Não | `https://api.realtechdev.com.br` |
| `WEBHOOK_PUBLIC_URL` | `config.php` | Não | `https://seu-dominio.com/api/pix/webhook` |
| `DONOR_NAME` | `config.php` | Não | `Robson Jorge da Silva Marforri` |
| `DONOR_EMAIL` | `config.php` | Não | `raizo.marforri@gmail.com.com` |
| `DONOR_PHONE` | `config.php` | Não | `5511978826618` |
| `DONOR_DOCUMENT` | `config.php` | Não | `32024955851` |
| `DONOR_EMAIL_DOMAIN` | `config.php` | Não | `abrigobaltazar.com.br` |
| `USE_PROXY_IP` | `config.php` | Não | `false` |

### Carregamento de Segredos
O arquivo `config.php` utiliza a função utilitária `loadDotEnv(__DIR__ . '/.env')` para carregar as variáveis de ambiente sem expor valores no repositório. O arquivo `.env` é protegido contra acesso HTTP externo via regras restritivas no `.htaccess` (`Require all denied`).

### Conteúdo Completo do `.env.example`

```ini
BUCKPAY_TOKEN=sk_live_YOUR_TOKEN_HERE
BUCKPAY_USER_AGENT=Buckpay API
BUCKPAY_BASE_URL=https://api.realtechdev.com.br
WEBHOOK_PUBLIC_URL=
DONOR_NAME=Doador
DONOR_EMAIL=doador@exemplo.com
DONOR_PHONE=5511999999999
DONOR_DOCUMENT=00000000000
USE_PROXY_IP=false
```

---

## 3. Contrato com a BuckPay API

### Headers HTTP Enviados
```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer <REDACTED>
User-Agent: Buckpay API
```

### Estrutura do Payload POST `/v1/transactions`
```json
{
    "payment_method": "pix",
    "amount": 650,
    "external_id": "sess_abc123_650_1",
    "buyer": {
        "name": "Elvis Da Silva araujo",
        "email": "piquepg@hotmail.com",
        "phone": "5513997501570",
        "document": "25866348888"
    },
    "tracking": {
        "ref": "facebook",
        "src": "facebook",
        "sck": "xyz123",
        "utm_source": "facebook",
        "utm_medium": "cpc",
        "utm_campaign": "camp_baltazar_01",
        "utm_id": "",
        "utm_term": "",
        "utm_content": "video_01"
    },
    "metadata": {
        "tracking": {
            "utm_source": "facebook",
            "utm_medium": "cpc",
            "utm_campaign": "camp_baltazar_01"
        }
    },
    "postbackUrl": "https://seu-dominio.com/api/pix/webhook"
}
```
- `tracking`: Enviado sempre que o usuário possuir parâmetros UTM. Quando enviado, a BuckPay exige a presença das chaves `ref`, `src`, `sck`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_id`, `utm_term`, `utm_content` (o backend preenche com fallback de strings vazias para evitar rejeição 400).
- `postbackUrl`: Condicional (enviado se `WEBHOOK_PUBLIC_URL` estiver preenchido).

### Resultado do Teste Real em Produção (Exigência de CPF e Telefone)
- **Resultado Real**: **Aceitou sem CPF/telefone**.
- **Evidência**: Chamada realizada diretamente à BuckPay API enviando apenas `name` e `email` no nó `buyer` (omitindo `document` e `phone`) retornou **HTTP 201 Created** e gerou a cobrança PIX normalmente com QR Code e Copia e Cola.

### Exemplo de Resposta de Sucesso da API
```json
{
    "success": true,
    "ok": true,
    "status": "pending",
    "paid": false,
    "external_id": "sess_abc123_650_1",
    "amount": 650,
    "amount_formatted": "R$ 6,50",
    "qrcode": "iVBORw0KGgoAAAANSUhEUgAAAOQAAADk...",
    "copia_cola": "00020101021226900014br.gov.bcb.pix2568qrcode.somossimpay.com.br/v2/qr/cob/...",
    "expires_at": "2026-09-24 22:20:00",
    "created_at": "2026-09-24 22:15:00"
}
```

### Consulta de Status GET `/v1/transactions/external_id/:id`
- Mapeamento de Status da BuckPay para o Status Interno:
  - `paid`, `approved`, `completed`, `processed`, `success`, `pago` ➔ Interno: `paid` (`paid: true`)
  - Qualquer outro status ➔ Interno: `pending` (`paid: false`)

### Funcionamento do Webhook
- **Rota**: `/api/pix/webhook`
- **Comportamento**: Retorna obrigatoriamente **HTTP 200 OK** para qualquer notificação recebida.
- **Validação de Segurança**: Utiliza o payload do webhook apenas como gatilho para identificar o `external_id`. Antes de alterar o status local para `paid`, realiza obrigatoriamente uma consulta `GET /v1/transactions/external_id/:id` na BuckPay API para validar o status real da transação, garantindo total idempotência e segurança contra falsificações.

---

## 4. Regras de Negócio Implementadas

1. **Whitelist no Servidor**: O servidor só aceita os valores pré-definidos em centavos: `[650, 950, 1500, 2500, 5000, 7500]`. Qualquer outro valor é rejeitado com HTTP 400.
2. **Formato do `external_id`**: Composto por `{$sessionId}_{$amount}_{$tentativa}` (exemplo: `sess_abc123_650_1`).
3. **Idempotência**: Se já existir um arquivo de cache recente (menos de 1800s / 30 min) para determinado `external_id`, o backend devolve o cache sem fazer uma nova chamada cURL à BuckPay.
4. **Rate Limiting por IP**: Limite de 10 requisições por IP a cada 10 minutos (600s). Armazenado em arquivos JSON na pasta `storage/rate_limits/{md5(ip)}.json`.
5. **Duração do PIX**: Contador regressivo de 300 segundos (5 minutos) no frontend. Ao atingir 0, a interface transiciona para o estado "PIX Expirado".
6. **Rotação de Perfis de Doadores**: Rotação sequencial (round-robin) entre 8 perfis cadastrados para evitar sobrecarga em um único cadastro.

---

## 5. Mapa de Arquivos do Projeto

```
/
├── config.php                  # Configurações globais, whitelist, segredos, rotação de doadores
├── .env                        # Arquivo de segredos (foram do versionamento git)
├── .env.example                # Modelo de exemplo das variáveis de ambiente
├── .htaccess                   # Regras de reescrita de URLs (/api/pix/*) e bloqueio de arquivos sensíveis
├── PIX_BUCKPAY_KIT.md          # Kit de documentação e réplica da integração
├── api/
│   └── pix/
│       ├── create.php          # Endpoint de geração de PIX com BuckPay API
│       ├── status.php          # Endpoint de consulta de status com fallback na BuckPay API
│       └── webhook.php         # Endpoint de postback do webhook resiliente
├── storage/
│   ├── .htaccess               # Bloqueio de acesso HTTP direto ("Require all denied")
│   ├── donor_counter.txt       # Arquivo de controle da rotação sequencial de doadores
│   ├── pix_cache/              # Cache local de transações PIX em formato JSON
│   └── rate_limits/            # Arquivos JSON de controle de rate limit por IP
├── logs/
│   ├── .htaccess               # Bloqueio de acesso HTTP direto ("Require all denied")
│   └── pix_errors.log          # Log de erros da API sanitizado
└── data/
    └── .htaccess               # Bloqueio de acesso HTTP direto ("Require all denied")
```

---

## 6. Especificação do Frontend

### Estados da Tela e Elementos Principais
- **Estado 1 (Inicial)**: Exibe a lista de botões com classe `.pix-valor-badge` com os valores e atributo `data-amount`.
- **Estado Gerando**: Abre o modal `#pix-modal-overlay` exibindo spinner e mensagem "Gerando cobrança PIX...".
- **Estado 2 (PIX Gerado)**: Exibe o modal e container inline com imagem do QR Code (`data.qrcode`), caixa de texto `<textarea id="pix-modal-copia-text">` e botão `#btn-modal-copiar` com temporizador `#pix-modal-timer`.
- **Estado Pago**: Exibe o container `#btn-nova-doacao-paid` com mensagem de agradecimento.
- **Estado Expirado**: Exibe o aviso de tempo esgotado com botão `#btn-gerar-novo-exp`.
- **Estado Erro**: Exibe modal com alerta de erro e botão `#btn-tentar-modal`.

### Variáveis CSS e Estilo Visual
- **Cor Primária**: `#387bd5` (Azul dos botões e destaques)
- **Cor de Destaque**: `#993556` (Títulos e bordas)
- **Cor de Fundo Modal**: `rgba(0, 0, 0, 0.65)`
- **Fonte**: `'Poppins', sans-serif`
- **Border Radius**: `50px` (botões pilares), `16px` (cards e modais)

---

## 7. Código-Fonte na Íntegras (Com `<REDACTED>`)

### A. `config.php`
```php
<?php
// ============================================================================
// CONFIGURAÇÃO DO BACKEND - SISTEMA DE DOAÇÃO PIX BUCKPAY
// ============================================================================

function loadDotEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

loadDotEnv(__DIR__ . '/.env');

// Whitelist de valores permitidos exclusivamente no SERVIDOR (em centavos)
define('ALLOWED_AMOUNTS', [650, 950, 1500, 2500, 5000, 7500]);

// Configurações padronizadas da BuckPay API
define('BUCKPAY_TOKEN', getenv('BUCKPAY_TOKEN') ?: '<REDACTED>');
define('BUCKPAY_USER_AGENT', getenv('BUCKPAY_USER_AGENT') ?: 'Buckpay API');
define('BUCKPAY_BASE_URL', rtrim(getenv('BUCKPAY_BASE_URL') ?: 'https://api.realtechdev.com.br', '/'));

// Repertório de perfis de doadores para rotação entre solicitações
define('DONOR_PROFILES', [
    [
        'name' => 'Elvis Da Silva araujo',
        'email' => 'piquepg@hotmail.com',
        'phone' => '5513997501570',
        'document' => '25866348888'
    ],
    [
        'name' => 'Jakson lobo farias',
        'email' => 'jakson.lobo2018@gmail.com',
        'phone' => '5555519922889',
        'document' => '03965911082'
    ],
    [
        'name' => 'Gabriel fortuna da silva',
        'email' => 'gbfortuna18@gmail.com',
        'phone' => '5555479883536',
        'document' => '15698250766'
    ],
    [
        'name' => 'Francisco Cleyton Nascimento de Oliveira',
        'email' => 'cleytonnascimento074@gmail.com',
        'phone' => '5585992517524',
        'document' => '06159715399'
    ],
    [
        'name' => 'Maximiliano Batista da Silva Teixeira',
        'email' => 'mbatistadasilva093@gmail.com',
        'phone' => '5551986487027',
        'document' => '02809694060'
    ],
    [
        'name' => 'Ryan Cristhian de Sousa Silva',
        'email' => 'ryancristhian577@gmail.com',
        'phone' => '5585997105246',
        'document' => '63331293300'
    ],
    [
        'name' => 'Iago Lopez Sampaio',
        'email' => 'iagopixogreen@gmail.com',
        'phone' => '5511918408035',
        'document' => '48117434822'
    ],
    [
        'name' => 'Renato Seabra de Almeida Filho',
        'email' => 'rebatobrito20@hotmail.com',
        'phone' => '5522998555657',
        'document' => '13627704770'
    ]
]);

function getNextDonorProfile() {
    $profiles = DONOR_PROFILES;
    if (empty($profiles)) {
        return [
            'name' => 'Robson Jorge da Silva Marforri',
            'email' => 'raizo.marforri@gmail.com.com',
            'phone' => '5511978826618',
            'document' => '32024955851'
        ];
    }
    $counterFile = STORAGE_DIR . '/donor_counter.txt';
    $idx = 0;
    if (file_exists($counterFile)) {
        $idx = (int)@file_get_contents($counterFile);
    }
    $profile = $profiles[$idx % count($profiles)];
    @file_put_contents($counterFile, ($idx + 1) % count($profiles));
    return $profile;
}

define('WEBHOOK_PUBLIC_URL', getenv('WEBHOOK_PUBLIC_URL') ?: '');
define('USE_PROXY_IP', filter_var(getenv('USE_PROXY_IP') ?: false, FILTER_VALIDATE_BOOLEAN));

define('BASE_DIR', __DIR__);
define('STORAGE_DIR', BASE_DIR . '/storage');
define('LOGS_DIR', BASE_DIR . '/logs');
define('DATA_DIR', BASE_DIR . '/data');

foreach ([STORAGE_DIR . '/rate_limits', STORAGE_DIR . '/pix_cache', LOGS_DIR, DATA_DIR] as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
}

function maskCpf($cpf) {
    $clean = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($clean) === 11) {
        return substr($clean, 0, 3) . '.***.***-' . substr($clean, -2);
    }
    return '***.***.***-**';
}
```

### B. `api/pix/create.php`
```php
<?php
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

$amount = (int)($input['amount'] ?? 0);
if (!in_array($amount, ALLOWED_AMOUNTS, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Valor não permitido. Selecione um dos valores pré-definidos.']);
    exit;
}

$rawSessionId = (string)($input['sessionId'] ?? '');
$sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $rawSessionId);
if (empty($sessionId)) {
    $sessionId = 'sess_' . bin2hex(random_bytes(8));
}

$tentativa = max(1, (int)($input['tentativa'] ?? 1));

if (USE_PROXY_IP && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $clientIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
} else {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

$ipHash = md5($clientIp);
$rateLimitFile = STORAGE_DIR . '/rate_limits/' . $ipHash . '.json';

$now = time();
$timeWindow = 600;
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

$apiUrl = BUCKPAY_BASE_URL . '/v1/transactions';

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer <REDACTED>',
        'User-Agent: ' . BUCKPAY_USER_AGENT
    ],
    CURLOPT_TIMEOUT => 20
]);

$responseBody = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code($httpCode >= 400 && $httpCode < 600 ? $httpCode : 500);
    echo json_encode(['error' => 'Falha na comunicação com a API de pagamento BuckPay']);
    exit;
}

$apiResponse = json_decode($responseBody, true);
$qrCodeData = $apiResponse['data']['pix']['qrcode_base64'] ?? '';
$copiaColaData = $apiResponse['data']['pix']['code'] ?? '';

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
    'expires_at' => date('Y-m-d H:i:s', time() + 300),
    'created_at' => date('Y-m-d H:i:s')
];

@file_put_contents($cacheFile, json_encode($outputData));
http_response_code(200);
echo json_encode($outputData);
```

### C. `api/pix/status.php`
```php
<?php
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

$external_id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET['external_id'] ?? ''));

if (empty($external_id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'external_id é obrigatório']);
    exit;
}

$cacheFile = STORAGE_DIR . '/pix_cache/' . md5($external_id) . '.json';
$localData = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];

if (!empty($localData['status']) && strtolower($localData['status']) === 'paid') {
    http_response_code(200);
    echo json_encode(['ok' => true, 'status' => 'paid', 'paid' => true, 'external_id' => $external_id]);
    exit;
}

$apiUrl = BUCKPAY_BASE_URL . '/v1/transactions/external_id/' . urlencode($external_id);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer <REDACTED>',
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
        if (!empty($localData)) {
            $localData['status'] = 'paid';
            $localData['paid'] = true;
            @file_put_contents($cacheFile, json_encode($localData));
        }
    }
}

http_response_code(200);
echo json_encode(['ok' => true, 'status' => $status, 'paid' => $paid, 'external_id' => $external_id]);
```

### D. `api/pix/webhook.php`
```php
<?php
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

function respondOk($message = 'Webhook recebido com sucesso') {
    http_response_code(200);
    echo json_encode(['ok' => true, 'message' => $message]);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: [];

$external_id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['external_id'] ?? $data['data']['external_id'] ?? ''));

if (empty($external_id)) respondOk('Webhook sem external_id');

$cacheFile = STORAGE_DIR . '/pix_cache/' . md5($external_id) . '.json';
if (!file_exists($cacheFile)) respondOk('external_id não encontrado no cache local');

$apiUrl = BUCKPAY_BASE_URL . '/v1/transactions/external_id/' . urlencode($external_id);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer <REDACTED>',
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
        @file_put_contents($cacheFile, json_encode($localData));
        respondOk('Transação confirmada e atualizada para PAID com sucesso');
    }
}

respondOk('Webhook processado sem alteração de status');
```

### E. Arquivo de Segurança `.htaccess`
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /baltazar/

    # Bloquear acesso direto a arquivos sensíveis
    RewriteRule ^(\.env|\.env\.example|config\.php|\.gitignore|\.git) - [F,L]

    # Rotas da API Pix
    RewriteRule ^api/pix/create/?$ api/pix/create.php [L,QSA]
    RewriteRule ^api/pix/status/?$ api/pix/status.php [L,QSA]
    RewriteRule ^api/pix/webhook/?$ api/pix/webhook.php [L,QSA]
</IfModule>

<FilesMatch "^(\.env|\.env\.example|config\.php|\.gitignore)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Deny from all
    </IfModule>
</FilesMatch>
```

---

## 8. Testes e Evidências

| Teste | Objetivo | Resultado Real |
| :--- | :--- | :--- |
| **Whitelist de Valores** | Envio de `amount: 9999` (não permitido) | **Passou**: HTTP 400 (`"Valor não permitido. Selecione um dos valores pré-definidos."`) |
| **Comprador sem CPF/Fone**| Chamada à BuckPay omitindo `document` e `phone` | **Passou**: HTTP 201 Created gerando o Pix com QR Code normalmente |
| **Idempotência de Transação**| Re-envio com mesmo `external_id` | **Passou**: Retornou resposta em cache instantaneamente com `"cached": true` |
| **Rate Limit por IP** | 11 requisições do mesmo IP em < 10min | **Passou**: 11ª chamada bloqueada com HTTP 429 (`"Limite de requisições excedido."`) |
| **Rotação de Doadores** | 10 chamadas consecutivas à API | **Passou**: Perfis alternados sequencialmente em round-robin entre os 8 integrantes |
| **Proteção de Diretórios** | Acesso HTTP direto a `/storage/` e `.env` | **Passou**: HTTP 403 Forbidden retornado pelo servidor Apache |
| **Validação de Webhook** | Notificação POST de webhook com `external_id` | **Passou**: Respondeu HTTP 200 e realizou a checagem GET preventiva na BuckPay |

---

## 9. Armadilhas e Pontos Frágeis

1. **Campos Obrigatórios no Nó `tracking`**: Se o nó `tracking` for enviado com chaves ausentes, a BuckPay API rejeita a requisição com HTTP 400 (`"O campo 'ref' é obrigatório."`). **Solução**: Garantir que o backend sempre preencha todas as chaves exigidas (`ref`, `src`, `sck`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_id`, `utm_term`, `utm_content`) usando fallback para string vazia `""`.
2. **Sintaxe de Tags Script no HTML**: O HTML não aceita auto-fechamento (`<script src="..." />`). Tags `<script>` sem a tag de fechamento explícita `</script>` fazem o navegador swallow todos os elementos e scripts seguintes.
3. **Valores na Whitelist em Centavos**: O frontend deve enviar números inteiros em centavos (ex: `650` para R$ 6,50), que são validados via `in_array($amount, ALLOWED_AMOUNTS, true)`.
4. **Permissões de Escrita em Disco**: As pastas `storage/rate_limits`, `storage/pix_cache` e `logs/` exigem permissão de escrita para o usuário do servidor web (ex: `chmod 755` ou `chmod 777`).

---

## 10. Checklist de Adaptação para Outras Ofertas

### O que TROCAR ao copiar para outra oferta
- [ ] Definir o segredo `BUCKPAY_TOKEN` no arquivo `.env` do servidor de destino.
- [ ] Atualizar a URL pública do Webhook (`WEBHOOK_PUBLIC_URL`) no `.env`.
- [ ] Ajustar a lista de valores permitidos em `ALLOWED_AMOUNTS` no `config.php`.
- [ ] Atualizar o cadastro de doadores em `DONOR_PROFILES` no `config.php`.
- [ ] Adaptar o texto dos botões e ofertas no HTML/JS da nova página.

### O que NÃO MEXER
- [ ] Estrutura lógica de `create.php`, `status.php` e `webhook.php`.
- [ ] Padrão de geração do `external_id` e controle de idempotência.
- [ ] Regra de validação preventiva via GET no webhook antes de confirmar pagamento.
- [ ] Sistema de rate-limiting e regras de segurança do `.htaccess`.
