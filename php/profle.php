<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'jwt.php';

// Get Bearer token from Authorization header — mirrors axios.get("/api/profile/", { headers: { Authorization: `Bearer ${access}` } })
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!str_starts_with($authHeader, 'Bearer ')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$token   = substr($authHeader, 7);
$payload = verifyJWT($token);

if (!$payload || $payload['type'] !== 'access') {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
$stmt->execute([$payload['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Returns same shape as DRF's /api/profile/ response
echo json_encode($user);