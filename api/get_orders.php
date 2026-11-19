<?php
/**
 * GET /api/get_orders.php
 * Optional: ?status=pending
 * Returns: list of orders with customer + product + toppings info (for admin panel)
 * Requires: Admin login (session)
 */

require_once __DIR__ . "/../admin/auth.php";
require_login_api();

header("Content-Type: application/json");
require_once __DIR__ . "/../config/db.php";

$status = $_GET['status'] ?? null;

$sql = "SELECT o.id, o.total_price, o.base_price, o.addons_total, o.delivery_datetime,
               o.note, o.status, o.created_at,
               c.name AS customer_name, c.phone, c.address,
               p.name AS product_name
        FROM orders o
        JOIN customers c ON c.id = o.customer_id
        JOIN products  p ON p.id = o.product_id";

$params = [];
if ($status) {
    $sql .= " WHERE o.status = :status";
    $params['status'] = $status;
}
$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// attach toppings for each order
foreach ($orders as &$order) {
    $stmt2 = $pdo->prepare("
        SELECT t.name, t.emoji, ot.price
        FROM order_toppings ot
        JOIN toppings t ON t.id = ot.topping_id
        WHERE ot.order_id = :order_id
    ");
    $stmt2->execute(['order_id' => $order['id']]);
    $order['toppings'] = $stmt2->fetchAll();
}

echo json_encode([
    "success" => true,
    "count"   => count($orders),
    "data"    => $orders
]);
