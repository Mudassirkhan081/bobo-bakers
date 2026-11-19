<?php
/**
 * GET /api/get_products.php
 * Optional: ?category=wedding  (filters by category slug)
 * Returns: all active cakes with category name + image
 */

header("Content-Type: application/json");
require_once __DIR__ . "/../config/db.php";

$categorySlug = $_GET['category'] ?? null;

$sql = "SELECT p.id, p.name, p.description, p.price, p.image_url,
               c.slug AS category_slug, c.name AS category_name
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE p.is_active = 1";

$params = [];
if ($categorySlug && $categorySlug !== 'all') {
    $sql .= " AND c.slug = :slug";
    $params['slug'] = $categorySlug;
}
$sql .= " ORDER BY p.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

echo json_encode([
    "success" => true,
    "count"   => count($products),
    "data"    => $products
]);
