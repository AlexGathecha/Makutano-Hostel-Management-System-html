<?php
header('Content-Type: application/json');
require_once 'db.php';

$data     = json_decode(file_get_contents('php://input'), true);
$username = trim($data['username'] ?? '');
$email    = trim($data['email']    ?? '');
$password = trim($data['password'] ?? '');

if (empty($username) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Check if email already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Email already registered.']);
    exit;
}

$hashed = password_hash($password, PASSWORD_BCRYPT);
$stmt   = $pdo->prepare(
    "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'tenant')"
);
$stmt->execute([$username, $email, $hashed]);

// Mirrors: response.data.message === "User registered successfully"
echo json_encode(['success' => true, 'message' => 'User registered successfully']);