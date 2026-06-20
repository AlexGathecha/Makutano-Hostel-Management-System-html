<?php
// Safaricom calls this URL automatically after payment
require_once 'db.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$result = $data['Body']['stkCallback'];
$checkout_id    = $result['CheckoutRequestID'];
$result_code    = $result['ResultCode'];
$result_desc    = $result['ResultDesc'];

if ($result_code == 0) {
    // Payment successful
    $items = $result['CallbackMetadata']['Item'];
    $amount = $mpesa_ref = $transaction_date = $phone = null;

    foreach ($items as $item) {
        match($item['Name']) {
            'Amount'              => $amount           = $item['Value'],
            'MpesaReceiptNumber'  => $mpesa_ref        = $item['Value'],
            'TransactionDate'     => $transaction_date = $item['Value'],
            'PhoneNumber'         => $phone            = $item['Value'],
            default               => null,
        };
    }

    // Get invoice_id from transaction
    $stmt = $pdo->prepare("SELECT invoice_id, tenant_id FROM mpesa_transactions WHERE checkout_request_id=?");
    $stmt->execute([$checkout_id]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tx) {
        // Record payment
        $pdo->prepare("INSERT INTO payments (tenant_id, invoice_id, amount, payment_date, method, reference) VALUES (?,?,?,CURDATE(),'M-Pesa',?)")
            ->execute([$tx['tenant_id'], $tx['invoice_id'], $amount, $mpesa_ref]);

        // Update invoice status
        $pdo->prepare("UPDATE invoices SET status='paid' WHERE id=?")
            ->execute([$tx['invoice_id']]);

        // Update transaction status
        $pdo->prepare("UPDATE mpesa_transactions SET status='paid', mpesa_ref=? WHERE checkout_request_id=?")
            ->execute([$mpesa_ref, $checkout_id]);
    }
} else {
    // Payment failed or cancelled
    $pdo->prepare("UPDATE mpesa_transactions SET status='failed' WHERE checkout_request_id=?")
        ->execute([$checkout_id]);
}

http_response_code(200);
echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Accepted']);