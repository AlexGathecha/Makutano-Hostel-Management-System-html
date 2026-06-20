<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once 'db.php';
require_once 'jwt.php';

$headers = getallheaders();
$token   = substr($headers['Authorization'] ?? '', 7);
$payload = verifyJWT($token);
if (!$payload) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$user_id = $payload['user_id'];

$result = [];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE tenant_id=?");
$stmt->execute([$user_id]);
$result['total_invoiced'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id=?");
$stmt->execute([$user_id]);
$result['total_paid'] = $stmt->fetchColumn();

$result['balance'] = $result['total_invoiced'] - $result['total_paid'];

$stmt = $pdo->prepare("SELECT * FROM invoices WHERE tenant_id=? ORDER BY due_date DESC");
$stmt->execute([$user_id]);
$result['invoices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM payments WHERE tenant_id=? ORDER BY payment_date DESC");
$stmt->execute([$user_id]);
$result['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($result);