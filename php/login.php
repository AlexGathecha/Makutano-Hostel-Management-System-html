<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'jwt.php';

$data     = json_decode(file_get_contents('php://input'), true);
$email    = trim($data['email']    ?? '');
$password = trim($data['password'] ?? '');

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    exit;
}

// Generate tokens — mirrors DRF's TokenObtainPairView returning access + refresh
$access  = generateJWT(['user_id' => $user['id'], 'type' => 'access'],  3600);
$refresh = generateJWT(['user_id' => $user['id'], 'type' => 'refresh'], 604800);

echo json_encode([
    'success' => true,
    'access'  => $access,
    'refresh' => $refresh,
]);