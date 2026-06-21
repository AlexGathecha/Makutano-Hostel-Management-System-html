<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once 'db.php';
require_once 'jwt.php';

$headers    = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token      = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';
$payload    = verifyJWT($token);
if (!$payload) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

$stmt = $pdo->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$payload['user_id']]);
$role = $stmt->fetchColumn();
if (!in_array($role, ['admin','staff'])) { echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $invoiceRef = 'INV-' . strtoupper(substr(md5(time()), 0, 6));
    $pdo->prepare("INSERT INTO invoices (tenant_id, invoice_id, description, total_amount, due_date, status) VALUES (?,?,?,?,?,'unpaid')")
        ->execute([$data['tenant_id'], $invoiceRef, $data['description'], $data['amount'], $data['due_date']]);

    echo json_encode(['success'=>true, 'message'=>'Invoice generated.']); exit;
}

if (isset($_GET['summary'])) {
    $collected = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
    $invoiced  = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM invoices")->fetchColumn();
    echo json_encode(['total_collected'=>$collected, 'total_arrears'=>($invoiced - $collected)]); exit;
}

$stmt = $pdo->query("
    SELECT i.*, u.username as tenant_name FROM invoices i
    JOIN users u ON i.tenant_id = u.id
    ORDER BY i.due_date DESC
");
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $pdo->query("
    SELECT p.*, u.username as tenant_name FROM payments p
    JOIN users u ON p.tenant_id = u.id
    ORDER BY p.payment_date DESC
");
$payments = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['invoices' => $invoices, 'payments' => $payments]);