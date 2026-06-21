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
if ($role !== 'admin') { echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

$totalRooms    = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$occupiedRooms = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status='occupied'")->fetchColumn();
$maintRooms    = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status='maintenance'")->fetchColumn();

$occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;

$totalInvoiced  = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM invoices")->fetchColumn();
$totalCollected = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
$totalArrears   = $totalInvoiced - $totalCollected;
$collectionRate = $totalInvoiced > 0 ? round(($totalCollected / $totalInvoiced) * 100, 1) : 0;

$stmt = $pdo->query("
    SELECT block,
           COUNT(*) as total,
           SUM(CASE WHEN status='occupied' THEN 1 ELSE 0 END) as occupied,
           SUM(CASE WHEN status='available' THEN 1 ELSE 0 END) as available
    FROM rooms
    GROUP BY block
    ORDER BY block
");
$byBlock = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'occupancy_rate'           => $occupancyRate,
    'collection_rate'          => $collectionRate,
    'rooms_under_maintenance'  => $maintRooms,
    'total_invoiced'           => $totalInvoiced,
    'total_collected'          => $totalCollected,
    'total_arrears'            => $totalArrears,
    'by_block'                 => $byBlock,
]);