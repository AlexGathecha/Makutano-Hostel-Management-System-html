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

// Verify admin role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$payload['user_id']]);
$role = $stmt->fetchColumn();
if ($role !== 'admin') { echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!empty($data['update_status'])) {
        $pdo->prepare("UPDATE rooms SET status=? WHERE id=?")
            ->execute([$data['status'], $data['room_id']]);
        echo json_encode(['success'=>true]); exit;
    }

    // Add new room
    $pdo->prepare("INSERT INTO rooms (room_number, block, room_type, capacity, status) VALUES (?,?,?,?,'available')")
        ->execute([$data['room_number'], $data['block'], $data['room_type'], $data['capacity'] ?: 1]);
    echo json_encode(['success'=>true, 'message'=>'Room added successfully.']); exit;
}

// Summary stats
if (isset($_GET['summary'])) {
    $total    = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
    $occupied = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status='occupied'")->fetchColumn();
    echo json_encode(['total'=>$total, 'occupied'=>$occupied]); exit;
}

// List rooms (optionally filtered)
$status = $_GET['status'] ?? '';
if ($status) {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE status=? ORDER BY block, room_number");
    $stmt->execute([$status]);
} else {
    $stmt = $pdo->query("SELECT * FROM rooms ORDER BY block, room_number");
}
echo json_encode(['rooms' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);