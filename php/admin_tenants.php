<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

if (isset($_GET['summary'])) {
    $total = $pdo->query("SELECT COUNT(*) FROM users WHERE role='tenant'")->fetchColumn();
    echo json_encode(['total'=>$total]); exit;
}

$search = $_GET['search'] ?? '';
$sql = "
    SELECT u.id as user_id, u.username, u.email, t.full_name, t.phone, t.course, t.year_of_study,
           r.room_number
    FROM users u
    LEFT JOIN tenants t ON t.user_id = u.id
    LEFT JOIN bookings b ON b.tenant_id = u.id AND b.status = 'approved'
    LEFT JOIN rooms r ON r.id = b.room_id
    WHERE u.role = 'tenant'
";

if ($search) {
    $sql .= " AND (u.username LIKE :search OR u.email LIKE :search OR t.full_name LIKE :search)";
    $stmt = $pdo->prepare($sql . " ORDER BY u.username");
    $stmt->execute(['search' => "%$search%"]);
} else {
    $stmt = $pdo->query($sql . " ORDER BY u.username");
}

echo json_encode(['tenants' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);