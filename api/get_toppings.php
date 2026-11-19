<?php
/**
 * GET /api/get_toppings.php
 * Returns: all active add-ons (candles, balloons, etc.)
 */

header("Content-Type: application/json");
require_once __DIR__ . "/../config/db.php";

$stmt = $pdo->query("SELECT id, name, emoji, price FROM toppings WHERE is_active = 1 ORDER BY id ASC");
$toppings = $stmt->fetchAll();

echo json_encode([
    "success" => true,
    "count"   => count($toppings),
    "data"    => $toppings
]);
