<?php
/**
 * POST /api/update_order_status.php
 * Body (JSON): { "order_id": 5, "status": "confirmed", "csrf_token": "..." }
 * Allowed statuses: pending, confirmed, preparing, out_for_delivery, delivered, cancelled
 * Requires: admin login + valid CSRF token
 */

require_once __DIR__ . '/../admin/auth.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$input = is_array($input) ? $input : [];

require_login_api_csrf($input);

require_once __DIR__ . '/../config/db.php';

$allowed = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

$orderId = (int) ($input['order_id'] ?? 0);
$status  = $input['status'] ?? '';

if ($orderId <= 0 || !in_array($status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid order or status.']);
    exit;
}

$stmt = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
$stmt->execute(['status' => $status, 'id' => $orderId]);

if ($stmt->rowCount() === 0) {
    $exists = $pdo->prepare('SELECT id FROM orders WHERE id = :id');
    $exists->execute(['id' => $orderId]);
    if (!$exists->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found.']);
        exit;
    }
}

echo json_encode(['success' => true, 'message' => 'Order status updated.']);
