<?php
// ============================================================================
// CONFIGURAÇÃO DO BACKEND - SISTEMA DE DOAÇÃO PIX BUCKPAY
// ============================================================================

// Helper simples para carregar arquivo .env se existir
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

// Configurações padronizadas da BuckPay API (Sem segredos hardcoded)
define('BUCKPAY_TOKEN', getenv('BUCKPAY_TOKEN') ?: '');
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

// Função para obter o próximo perfil de doador via rotação sequencial (round-robin)
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

// Configurações legadas do comprador (Fallback)
define('DONOR_NAME', getenv('DONOR_NAME') ?: 'Robson Jorge da Silva Marforri');
define('DONOR_EMAIL', getenv('DONOR_EMAIL') ?: 'raizo.marforri@gmail.com.com');
define('DONOR_PHONE', getenv('DONOR_PHONE') ?: '5511978826618');
define('DONOR_DOCUMENT', getenv('DONOR_DOCUMENT') ?: '32024955851');
define('DONOR_EMAIL_DOMAIN', getenv('DONOR_EMAIL_DOMAIN') ?: 'abrigobaltazar.com.br');

// URL do Webhook Postback
define('WEBHOOK_PUBLIC_URL', getenv('WEBHOOK_PUBLIC_URL') ?: '');

// Flag para Proxy IP (desligada por padrão -> usa REMOTE_ADDR)
define('USE_PROXY_IP', filter_var(getenv('USE_PROXY_IP') ?: false, FILTER_VALIDATE_BOOLEAN));

// Diretórios locais para Cache, Logs e Rate Limiting
define('BASE_DIR', __DIR__);
define('STORAGE_DIR', BASE_DIR . '/storage');
define('LOGS_DIR', BASE_DIR . '/logs');
define('DATA_DIR', BASE_DIR . '/data');

// Garantir que os diretórios existam
foreach ([STORAGE_DIR . '/rate_limits', STORAGE_DIR . '/pix_cache', LOGS_DIR, DATA_DIR] as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Função de utilidade para mascarar CPF nos logs (Item 9)
function maskCpf($cpf) {
    $clean = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($clean) === 11) {
        return substr($clean, 0, 3) . '.***.***-' . substr($clean, -2);
    }
    return '***.***.***-**';
}
