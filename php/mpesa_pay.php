<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once 'db.php';
require_once 'jwt.php';

define('MPESA_CONSUMER_KEY',    'y6U5Am1Z2BKT1rDZSGN2ArduDSMUj7cgS5pLxwvQSZkTWxhu');
define('MPESA_CONSUMER_SECRET', 'GGbbXzHTTh77ntlxGuA0X0X8Z5oXku8oCtcJPuwRQtAeS8Pr9vRX8IF5LaZ24Uz1');
define('MPESA_SHORTCODE',       '174379');
define('MPESA_PASSKEY',         'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919');
define('MPESA_CALLBACK_URL',    'https://quarters-handbag-geiger.ngrok-free.dev/makutano/php/mpesa_callback.php');
define('MPESA_ENV',             'sandbox');

// ── AUTH CHECK ──
$headers    = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token      = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'No token provided']); exit;
}

$payload = verifyJWT($token);
if (!$payload) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}
$user_id = $payload['user_id'];

// ── READ INPUT ──
$data       = json_decode(file_get_contents('php://input'), true);
$phone      = $data['phone']      ?? '';
$amount     = (int)($data['amount']     ?? 0);
$invoice_id = $data['invoice_id'] ?? '';

if (!$phone || !$amount || !$invoice_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']); exit;
}

$base_url = 'https://sandbox.safaricom.co.ke';

// ── STEP 1: GET ACCESS TOKEN ──
$credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);

$ch = curl_init("$base_url/oauth/v1/generate?grant_type=client_credentials");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic $credentials"]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$curlResponse = curl_exec($ch);
$curlError    = curl_error($ch);
$httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'message' => 'Curl error: ' . $curlError]); exit;
}

$res          = json_decode($curlResponse, true);
$access_token = $res['access_token'] ?? null;

if (!$access_token) {
    echo json_encode([
        'success'       => false,
        'message'       => 'Failed to get M-Pesa token. HTTP ' . $httpCode,
        'response'      => $curlResponse,
    ]);
    exit;
}

// ── STEP 2: STK PUSH ──
$timestamp = date('YmdHis');
$password  = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

$stkPayload = [
    'BusinessShortCode' => MPESA_SHORTCODE,
    'Password'          => $password,
    'Timestamp'         => $timestamp,
    'TransactionType'   => 'CustomerPayBillOnline',
    'Amount'            => $amount,
    'PartyA'            => $phone,
    'PartyB'            => MPESA_SHORTCODE,
    'PhoneNumber'       => $phone,
    'CallBackURL'       => MPESA_CALLBACK_URL,
    'AccountReference'  => 'Makutano-' . $invoice_id,
    'TransactionDesc'   => 'Hostel accommodation fee',
];

$ch = curl_init("$base_url/mpesa/stkpush/v1/processrequest");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stkPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token",
    "Content-Type: application/json",
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$stkResponse = curl_exec($ch);
$stkError    = curl_error($ch);
$stkCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($stkError) {
    echo json_encode(['success' => false, 'message' => 'STK curl error: ' . $stkError]); exit;
}

$stkData = json_decode($stkResponse, true);

if (isset($stkData['CheckoutRequestID'])) {
    // Save pending transaction
    try {
        $pdo->prepare("INSERT INTO mpesa_transactions (invoice_id, tenant_id, phone, amount, checkout_request_id, status) VALUES (?,?,?,?,?,'pending')")
            ->execute([$invoice_id, $user_id, $phone, $amount, $stkData['CheckoutRequestID']]);
    } catch (Exception $e) {
        // Log but don't fail — STK push already sent
    }

    echo json_encode(['success' => true, 'checkout_id' => $stkData['CheckoutRequestID']]);
} else {
    echo json_encode([
        'success'  => false,
        'message'  => $stkData['errorMessage'] ?? $stkData['ResultDesc'] ?? 'STK push failed. HTTP ' . $stkCode,
        'response' => $stkData,
    ]);
}
