<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once 'db.php';
require_once 'jwt.php';

// ── CONFIG — replace with your real Daraja credentials ──
define('MPESA_CONSUMER_KEY',    'y6U5Am1Z2BKT1rDZSGN2ArduDSMUj7cgS5pLxwvQSZkTWxhu');
define('MPESA_CONSUMER_SECRET', 'GGbbXzHTTh77ntlxGuA0X0X8Z5oXku8oCtcJPuwRQtAeS8Pr9vRX8IF5LaZ24Uz1');
define('MPESA_SHORTCODE',       '174379');        // Sandbox shortcode
define('MPESA_PASSKEY',         'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919');  // From Daraja portal
define('MPESA_CALLBACK_URL',    'https://yourdomain.com/makutano/php/mpesa_callback.php');
define('MPESA_ENV',             'live');       // change to 'live' for production

// ── AUTH CHECK ──
$headers = getallheaders();
$token   = substr($headers['Authorization'] ?? '', 7);
$payload = verifyJWT($token);
if (!$payload) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

$data       = json_decode(file_get_contents('php://input'), true);
$phone      = $data['phone'];
$amount     = (int) $data['amount'];
$invoice_id = $data['invoice_id'];

// ── STEP 1: GET ACCESS TOKEN ──
$base_url = MPESA_ENV === 'live'
    ? 'https://api.safaricom.co.ke'
    : 'https://sandbox.safaricom.co.ke';

$credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);

$ch = curl_init("$base_url/oauth/v1/generate?grant_type=client_credentials");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic $credentials"]);
$res = json_decode(curl_exec($ch), true);
curl_close($ch);

$access_token = $res['access_token'] ?? null;
if (!$access_token) {
    echo json_encode(['success'=>false,'message'=>'Failed to get M-Pesa token']); exit;
}

// ── STEP 2: GENERATE PASSWORD ──
$timestamp = date('YmdHis');
$password  = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

// ── STEP 3: INITIATE STK PUSH ──
$payload = [
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
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token",
    "Content-Type: application/json",
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

if (isset($response['CheckoutRequestID'])) {
    // Save pending transaction
    $pdo->prepare("INSERT INTO mpesa_transactions (invoice_id, tenant_id, phone, amount, checkout_request_id, status) VALUES (?,?,?,?,'pending')")
        ->execute([$invoice_id, $payload['user_id'] ?? 0, $phone, $amount, $response['CheckoutRequestID']]);

    echo json_encode(['success'=>true,'checkout_id'=>$response['CheckoutRequestID']]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $response['errorMessage'] ?? 'STK push failed'
    ]);
}