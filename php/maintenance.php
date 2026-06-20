<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once 'db.php';
require_once 'jwt.php';

$headers = getallheaders();
$token   = substr($headers['Authorization'] ?? '', 7);
$payload = verifyJWT($token);
if (!$payload) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$user_id = $payload['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $data = json_decode(file_get_contents('php://input'), true);
  $pdo->prepare("INSERT INTO maintenance (tenant_id, category, description, priority, status, date_reported) VALUES (?,?,?,?,'open', CURDATE())")
      ->execute([$user_id, $data['category'], $data['description'], $data['priority']]);
  echo json_encode(['success'=>true,'message'=>'Request submitted.']); exit;
}

$stmt = $pdo->prepare("SELECT * FROM maintenance WHERE tenant_id=? ORDER BY date_reported DESC");
$stmt->execute([$user_id]);
echo json_encode(['requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);