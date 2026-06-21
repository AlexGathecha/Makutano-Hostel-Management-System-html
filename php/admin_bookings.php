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

    // Update maintenance status
    if (!empty($data['update_maintenance'])) {
        $resolvedDate = $data['status'] === 'resolved' ? ", date_resolved=CURDATE()" : "";
        $pdo->prepare("UPDATE maintenance SET status=?$resolvedDate WHERE id=?")
            ->execute([$data['status'], $data['request_id']]);
        echo json_encode(['success'=>true]); exit;
    }

    // Approve/Reject booking
    $booking_id = $data['booking_id'];
    $action     = $data['action']; // 'approved' or 'rejected'

    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id=?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) { echo json_encode(['success'=>false,'message'=>'Booking not found']); exit; }

    if ($action === 'approved') {
        // Find an available room matching the requested type/block
        $stmt = $pdo->prepare("SELECT id FROM rooms WHERE status='available' AND room_type=? AND block=? LIMIT 1");
        $stmt->execute([$booking['room_type_requested'], $booking['block_requested']]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            echo json_encode(['success'=>false,'message'=>'No available room matches this request.']); exit;
        }

        $pdo->prepare("UPDATE bookings SET status='approved', room_id=? WHERE id=?")
            ->execute([$room['id'], $booking_id]);
        $pdo->prepare("UPDATE rooms SET status='occupied' WHERE id=?")
            ->execute([$room['id']]);
    } else {
        $pdo->prepare("UPDATE bookings SET status='rejected' WHERE id=?")
            ->execute([$booking_id]);
    }

    echo json_encode(['success'=>true]); exit;
}

// Maintenance summary
if (isset($_GET['maintenance_summary'])) {
    $open = $pdo->query("SELECT COUNT(*) FROM maintenance WHERE status='open'")->fetchColumn();
    echo json_encode(['open_count'=>$open]); exit;
}

// Recent maintenance
if (isset($_GET['recent_maintenance'])) {
    $stmt = $pdo->query("
        SELECT m.*, u.username as tenant_name FROM maintenance m
        JOIN users u ON m.tenant_id = u.id
        ORDER BY m.date_reported DESC LIMIT 5
    ");
    echo json_encode(['requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
}

// All maintenance (filtered)
if (isset($_GET['maintenance'])) {
    $status = $_GET['status'] ?? '';
    $sql = "SELECT m.*, u.username as tenant_name FROM maintenance m JOIN users u ON m.tenant_id = u.id";
    if ($status) {
        $sql .= " WHERE m.status = :status";
        $stmt = $pdo->prepare($sql . " ORDER BY m.date_reported DESC");
        $stmt->execute(['status' => $status]);
    } else {
        $stmt = $pdo->query($sql . " ORDER BY m.date_reported DESC");
    }
    echo json_encode(['requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
}

// Booking summary
if (isset($_GET['summary'])) {
    $pending = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
    echo json_encode(['pending_count'=>$pending]); exit;
}

// Recent bookings
if (isset($_GET['recent'])) {
    $stmt = $pdo->query("
        SELECT b.*, u.username as tenant_name FROM bookings b
        JOIN users u ON b.tenant_id = u.id
        ORDER BY b.date_applied DESC LIMIT 5
    ");
    echo json_encode(['bookings' => $stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
}

// All bookings (filtered)
$status = $_GET['status'] ?? '';
$sql = "SELECT b.*, u.username as tenant_name FROM bookings b JOIN users u ON b.tenant_id = u.id";
if ($status) {
    $sql .= " WHERE b.status = :status";
    $stmt = $pdo->prepare($sql . " ORDER BY b.date_applied DESC");
    $stmt->execute(['status' => $status]);
} else {
    $stmt = $pdo->query($sql . " ORDER BY b.date_applied DESC");
}
echo json_encode(['bookings' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);