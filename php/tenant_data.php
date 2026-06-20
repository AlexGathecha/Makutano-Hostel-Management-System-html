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

// ── POST: update profile or change password ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $data = json_decode(file_get_contents('php://input'), true);

  if (!empty($data['change_password'])) {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!password_verify($data['current_password'], $user['password'])) {
      echo json_encode(['success'=>false,'message'=>'Current password is incorrect.']); exit;
    }
    $hashed = password_hash($data['new_password'], PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $user_id]);
    echo json_encode(['success'=>true,'message'=>'Password changed.']); exit;
  }

  // Update/insert tenant profile
  $stmt = $pdo->prepare("SELECT id FROM tenants WHERE user_id=?");
  $stmt->execute([$user_id]);
  if ($stmt->fetch()) {
    $pdo->prepare("UPDATE tenants SET full_name=?,phone=?,course=?,year_of_study=?,emergency_contact_name=?,emergency_contact_phone=? WHERE user_id=?")
        ->execute([$data['full_name'],$data['phone'],$data['course'],$data['year_of_study'],$data['emergency_contact_name'],$data['emergency_contact_phone'],$user_id]);
  } else {
    $pdo->prepare("INSERT INTO tenants (user_id,full_name,phone,course,year_of_study,emergency_contact_name,emergency_contact_phone) VALUES (?,?,?,?,?,?,?)")
        ->execute([$user_id,$data['full_name'],$data['phone'],$data['course'],$data['year_of_study'],$data['emergency_contact_name'],$data['emergency_contact_phone']]);
  }
  echo json_encode(['success'=>true,'message'=>'Profile updated.']); exit;
}

// ── GET: fetch all tenant data ──
$result = [];

// Profile
$stmt = $pdo->prepare("SELECT * FROM tenants WHERE user_id=?");
$stmt->execute([$user_id]);
$result['profile'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

// Booking
$stmt = $pdo->prepare("
  SELECT b.*, r.room_number, r.block, r.room_type
  FROM bookings b
  LEFT JOIN rooms r ON b.room_id = r.id
  WHERE b.tenant_id=? ORDER BY b.id DESC LIMIT 1
");
$stmt->execute([$user_id]);
$result['booking']        = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
$result['booking_status'] = $result['booking']['status'] ?? 'None';
$result['room']           = $result['booking']['room_number'] ?? 'Not Assigned';

// Balance
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as total FROM invoices WHERE tenant_id=? AND status!='paid'");
$stmt->execute([$user_id]);
$result['balance'] = $stmt->fetchColumn();

// Open maintenance requests
$stmt = $pdo->prepare("SELECT COUNT(*) FROM maintenance WHERE tenant_id=? AND status='open'");
$stmt->execute([$user_id]);
$result['open_requests'] = $stmt->fetchColumn();

// Recent payments
$stmt = $pdo->prepare("SELECT * FROM payments WHERE tenant_id=? ORDER BY payment_date DESC LIMIT 5");
$stmt->execute([$user_id]);
$result['recent_payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent maintenance
$stmt = $pdo->prepare("SELECT * FROM maintenance WHERE tenant_id=? ORDER BY date_reported DESC LIMIT 5");
$stmt->execute([$user_id]);
$result['recent_maintenance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($result);