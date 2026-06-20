<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once 'db.php';
require_once 'jwt.php';

$headers = getallheaders();
$token   = substr($headers['Authorization'] ?? '', 7);
$payload = verifyJWT($token);
if (!$payload) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$user_id = $payload['user_id'];

$data = json_decode(file_get_contents('php://input'), true);

// Check for existing pending booking
$stmt = $pdo->prepare("SELECT id FROM bookings WHERE tenant_id=? AND status IN ('pending','approved')");
$stmt->execute([$user_id]);
if ($stmt->fetch()) {
  echo json_encode(['success'=>false,'message'=>'You already have an active booking application.']); exit;
}

$pdo->prepare("INSERT INTO bookings (tenant_id, room_type_requested, block_requested, academic_year, notes, status, date_applied) VALUES (?,?,?,?,?,'pending', CURDATE())")
    ->execute([$user_id, $data['room_type'], $data['block'], $data['academic_year'], $data['notes']]);

echo json_encode(['success'=>true,'message'=>'Application submitted successfully.']);