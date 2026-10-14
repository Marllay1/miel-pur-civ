<?php
declare(strict_types=1);

/**
 * Simulateur local des API de paiement (Wave, MTN MoMo, Orange Money WebPay, CinetPay), pour les tests d'intégration.
 *   php -S 127.0.0.1:8099 tests/mock-providers/server.php
 * Reproduit les contrats documentés (chemins, en-têtes, champs, statuts). Il ne remplace pas les tests en bac à sable
 * chez les prestataires : il prouve que notre code envoie et interprète correctement ces échanges.
 * Routes de contrôle (simulent l'action du client chez le prestataire) : POST /_control/{prestataire}/{id}/{statut}[?amount=]
 */

const STATE = __DIR__ . '/state.json';
const KEYS = [
    'wave' => 'wave_test_key',
    'mtn_sub' => 'mtn_sub_key', 'mtn_user' => 'mtn_api_user', 'mtn_key' => 'mtn_api_key',
    'om_id' => 'om_client', 'om_secret' => 'om_secret', 'om_merchant' => 'om_merchant_key',
    'cp_key' => 'cp_api_key', 'cp_site' => '123456',
];

function state(): array
{
    return is_file(STATE) ? (json_decode((string) file_get_contents(STATE), true) ?: []) : [];
}

function save(array $s): void
{
    file_put_contents(STATE, json_encode($s), LOCK_EX);
}

function reply(int $status, $data = null): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo $data === null ? '' : json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function header_value(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return (string) ($_SERVER[$key] ?? ($name === 'Authorization' ? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '') : ''));
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = (string) file_get_contents('php://input');
$json = json_decode($body, true) ?: [];
$s = state();

// ---------- Contrôle des scénarios
if ($method === 'POST' && preg_match('#^/_control/(wave|mtn|orange|cinetpay)/([^/]+)/([A-Za-z_]+)$#', $path, $m)) {
    [, $provider, $id, $status] = $m;
    if (!isset($s[$provider][$id])) {
        reply(404, ['error' => 'unknown']);
    }
    $s[$provider][$id]['status'] = $status;
    if (isset($_GET['amount'])) {
        $s[$provider][$id]['amount'] = (string) (int) $_GET['amount'];
    }
    save($s);
    reply(200, ['ok' => true]);
}
if ($method === 'POST' && $path === '/_control/reset') {
    save([]);
    reply(200, ['ok' => true]);
}

// ---------- Wave
if (str_starts_with($path, '/wave/')) {
    if (header_value('Authorization') !== 'Bearer ' . KEYS['wave']) {
        reply(401, ['code' => 'unauthorized']);
    }
    if ($method === 'POST' && $path === '/wave/v1/checkout/sessions') {
        if (!is_string($json['amount'] ?? null) || !preg_match('/^[1-9]\d*$/', $json['amount']) || ($json['currency'] ?? '') !== 'XOF'
            || !str_starts_with((string) ($json['success_url'] ?? ''), 'http') || !is_string($json['client_reference'] ?? null)) {
            reply(400, ['code' => 'request-validation-error']);
        }
        $id = 'cos-' . bin2hex(random_bytes(6));
        $s['wave'][$id] = ['id' => $id, 'amount' => $json['amount'], 'currency' => 'XOF', 'client_reference' => $json['client_reference'], 'status' => 'open'];
        save($s);
        reply(200, ['id' => $id, 'amount' => $json['amount'], 'currency' => 'XOF', 'checkout_status' => 'open', 'payment_status' => 'processing',
            'client_reference' => $json['client_reference'], 'wave_launch_url' => 'https://pay.wave.com/c/' . $id, 'when_expires' => gmdate('c', time() + 1800)]);
    }
    if ($method === 'GET' && preg_match('#^/wave/v1/checkout/sessions/(cos-[a-z0-9]+)$#', $path, $m)) {
        $x = $s['wave'][$m[1]] ?? reply(404, ['code' => 'not-found']);
        $complete = $x['status'] === 'succeeded';
        reply(200, ['id' => $x['id'], 'amount' => $x['amount'], 'currency' => 'XOF', 'client_reference' => $x['client_reference'],
            'checkout_status' => $complete ? 'complete' : ($x['status'] === 'expired' ? 'expired' : 'open'),
            'payment_status' => $complete ? 'succeeded' : 'processing', 'transaction_id' => $complete ? 'TCN' . strtoupper(substr($x['id'], 4)) : null]);
    }
    reply(404, ['code' => 'not-found']);
}

// ---------- MTN MoMo (Collections)
if (str_starts_with($path, '/mtn/')) {
    if (header_value('Ocp-Apim-Subscription-Key') !== KEYS['mtn_sub']) {
        reply(401, ['message' => 'Access denied due to invalid subscription key.']);
    }
    if ($method === 'POST' && $path === '/mtn/collection/token/') {
        if (header_value('Authorization') !== 'Basic ' . base64_encode(KEYS['mtn_user'] . ':' . KEYS['mtn_key'])) {
            reply(401, ['error' => 'login_failed']);
        }
        reply(200, ['access_token' => 'mtn-access-token', 'token_type' => 'access_token', 'expires_in' => 3600]);
    }
    if (header_value('Authorization') !== 'Bearer mtn-access-token' || header_value('X-Target-Environment') === '') {
        reply(401, ['code' => 'NOT_AUTHORIZED']);
    }
    if ($method === 'POST' && $path === '/mtn/collection/v1_0/requesttopay') {
        $ref = header_value('X-Reference-Id');
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $ref) || isset($s['mtn'][$ref])) {
            reply(409, ['code' => 'RESOURCE_ALREADY_EXIST']);
        }
        if (($json['payer']['partyIdType'] ?? '') !== 'MSISDN' || !preg_match('/^\d{11,13}$/', (string) ($json['payer']['partyId'] ?? '')) || !preg_match('/^\d+$/', (string) ($json['amount'] ?? ''))) {
            reply(400, ['code' => 'INVALID_PARAMETER']);
        }
        $s['mtn'][$ref] = ['amount' => $json['amount'], 'currency' => $json['currency'] ?? '', 'externalId' => $json['externalId'] ?? '', 'payer' => $json['payer'], 'status' => 'PENDING'];
        save($s);
        reply(202);
    }
    if ($method === 'GET' && preg_match('#^/mtn/collection/v1_0/requesttopay/([0-9a-f-]{36})$#', $path, $m)) {
        $x = $s['mtn'][$m[1]] ?? reply(404, ['code' => 'RESOURCE_NOT_FOUND']);
        reply(200, ['amount' => $x['amount'], 'currency' => $x['currency'], 'externalId' => $x['externalId'], 'payer' => $x['payer'],
            'status' => $x['status'], 'financialTransactionId' => $x['status'] === 'SUCCESSFUL' ? (string) random_int(100000000, 999999999) : null]);
    }
    reply(404, ['code' => 'RESOURCE_NOT_FOUND']);
}

// ---------- Orange Money WebPay
if (str_starts_with($path, '/orange/')) {
    if ($method === 'POST' && $path === '/orange/oauth/v3/token') {
        if (header_value('Authorization') !== 'Basic ' . base64_encode(KEYS['om_id'] . ':' . KEYS['om_secret']) || !str_contains($body, 'grant_type=client_credentials')) {
            reply(401, ['error' => 'invalid_client']);
        }
        reply(200, ['token_type' => 'Bearer', 'access_token' => 'om-access-token', 'expires_in' => 7776000]);
    }
    if (header_value('Authorization') !== 'Bearer om-access-token') {
        reply(401, ['code' => 42, 'message' => 'Expired credentials']);
    }
    if ($method === 'POST' && $path === '/orange/orange-money-webpay/dev/v1/webpayment') {
        if (($json['merchant_key'] ?? '') !== KEYS['om_merchant'] || !is_int($json['amount'] ?? null) || !isset($json['order_id'], $json['notif_url'], $json['return_url'], $json['cancel_url'])) {
            reply(400, ['code' => 1, 'message' => 'Bad request']);
        }
        $pay = bin2hex(random_bytes(16));
        $notif = bin2hex(random_bytes(16));
        $s['orange'][$json['order_id']] = ['pay_token' => $pay, 'notif_token' => $notif, 'amount' => (string) $json['amount'], 'status' => 'INITIATED'];
        save($s);
        reply(201, ['status' => 201, 'message' => 'OK', 'pay_token' => $pay, 'payment_url' => 'https://webpayment-qualif.orange-money.com/payment/pay_token/' . $pay, 'notif_token' => $notif]);
    }
    if ($method === 'POST' && $path === '/orange/orange-money-webpay/dev/v1/transactionstatus') {
        $x = $s['orange'][$json['order_id'] ?? ''] ?? null;
        if ($x === null || ($json['pay_token'] ?? '') !== $x['pay_token'] || (string) ($json['amount'] ?? '') !== $x['amount']) {
            reply(404, ['code' => 404, 'message' => 'Transaction not found']);
        }
        reply(201, ['status' => $x['status'], 'order_id' => $json['order_id'], 'txnid' => $x['status'] === 'SUCCESS' ? 'MP' . random_int(100000, 999999) : null]);
    }
    reply(404, ['message' => 'not found']);
}

// ---------- CinetPay
if (str_starts_with($path, '/cinetpay/')) {
    if (($json['apikey'] ?? '') !== KEYS['cp_key'] || (string) ($json['site_id'] ?? '') !== KEYS['cp_site']) {
        reply(200, ['code' => '608', 'message' => 'MINIMUM_REQUIRED_FIELDS']);
    }
    if ($method === 'POST' && $path === '/cinetpay/v2/payment') {
        if (!is_int($json['amount'] ?? null) || $json['amount'] % 5 !== 0 || ($json['currency'] ?? '') !== 'XOF' || empty($json['notify_url']) || empty($json['customer_email'])) {
            reply(200, ['code' => '608', 'message' => 'MINIMUM_REQUIRED_FIELDS']);
        }
        $tok = bin2hex(random_bytes(12));
        $s['cinetpay'][$json['transaction_id']] = ['amount' => (string) $json['amount'], 'status' => 'WAITING_FOR_CUSTOMER', 'token' => $tok];
        save($s);
        reply(200, ['code' => '201', 'message' => 'CREATED', 'data' => ['payment_token' => $tok, 'payment_url' => 'https://checkout.cinetpay.com/payment/' . $tok]]);
    }
    if ($method === 'POST' && $path === '/cinetpay/v2/payment/check') {
        $x = $s['cinetpay'][$json['transaction_id'] ?? ''] ?? null;
        if ($x === null) {
            reply(200, ['code' => '627', 'message' => 'TRANSACTION_NOT_FOUND']);
        }
        reply(200, ['code' => $x['status'] === 'ACCEPTED' ? '00' : '662', 'message' => $x['status'],
            'data' => ['amount' => $x['amount'], 'currency' => 'XOF', 'status' => $x['status'], 'payment_method' => 'VISA', 'operator_id' => 'CP' . substr($x['token'], 0, 8)]]);
    }
    reply(404, ['message' => 'not found']);
}

reply(404, ['error' => 'unknown route']);
