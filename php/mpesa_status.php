<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'jwt.php';

$headers = getallheaders();
$token   = substr($headers['Authorization'] ?? '', 7);
if (!verifyJWT($token)) { echo json_encode(['paid'=>false]); exit; }

$invoice_id = $_GET['invoice_id'] ?? 0;
$stmt = $pdo->prepare("SELECT status FROM invoices WHERE id=?");
$stmt->execute([$invoice_id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['paid' => ($inv['status'] ?? '') === 'paid']);