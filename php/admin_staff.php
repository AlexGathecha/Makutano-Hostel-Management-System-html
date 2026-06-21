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
if ($role !== 'admin') { echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!empty($data['delete'])) {
        $pdo->prepare("DELETE FROM users WHERE id=? AND role='staff'")
            ->execute([$data['staff_id']]);
        echo json_encode(['success'=>true]); exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        echo json_encode(['success'=>false,'message'=>'Email already registered.']); exit;
    }

    $hashed = password_hash($data['password'], PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?,?,?,'staff')")
        ->execute([$data['username'], $data['email'], $hashed]);

    echo json_encode(['success'=>true, 'message'=>'Staff account created.']); exit;
}

if (isset($_GET['summary'])) {
    $total = $pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn();
    echo json_encode(['total'=>$total]); exit;
}

$stmt = $pdo->query("SELECT id, username, email, created_at FROM users WHERE role='staff' ORDER BY created_at DESC");
echo json_encode(['staff' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);