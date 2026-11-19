<?php
/**
 * POST /api/place_order.php
 *
 * Body (JSON):
 * {
 *   "name": "Sara Ahmed",
 *   "phone": "03001234567",
 *   "address": "Block 5, Gulberg, Lahore",
 *   "product_id": 1,
 *   "toppings": [1, 4],                       // optional array of topping IDs
 *   "delivery_datetime": "2026-06-20 18:00:00",
 *   "note": "Write 'Happy Anniversary' on cake"
 * }
 *
 * SECURITY NOTE - prices are read from the database, never from the request.
 * The previous version trusted a "base_price" field sent by the browser, which
 * meant anyone could order a Rs. 3,500 cake for Rs. 1 by editing the request.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/db.php';

// ---- Only POST is accepted ----
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_error('Method not allowed.', 405);
}

// ---- Rate limit: 5 orders per 10 minutes per IP ----
enforce_rate_limit(
    'place_order',
    5,
    600,
    'You have placed several orders in a row. Please wait a few minutes, or send your order on WhatsApp: 0313-2483458'
);

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    json_error('Invalid request data. Please refresh the page and try again.');
}

// ---- Bot honeypot ----
if (is_honeypot_tripped($input)) {
    // Look successful to the bot so it stops retrying, but save nothing.
    echo json_encode(['success' => true, 'message' => 'Order placed successfully!', 'data' => ['order_id' => 0, 'total_price' => 0]]);
    exit;
}

// ---- Required fields ----
foreach (['name', 'phone', 'address', 'product_id', 'delivery_datetime'] as $field) {
    if (empty($input[$field])) {
        json_error("Please fill in the '$field' field.");
    }
}

$name    = trim($input['name']);
$phone   = trim($input['phone']);
$address = trim($input['address']);
$note    = trim($input['note'] ?? '');
$flavour = trim($input['flavour'] ?? '');

// ---- Field validation ----
if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
    json_error('Please enter your full name (3-100 characters).');
}

// Pakistani mobile formats: 03001234567, +923001234567, 923001234567
$cleanPhone = preg_replace('/[\s\-()]/', '', $phone);
if (!preg_match('/^(\+92|0092|92|0)?3[0-9]{9}$/', $cleanPhone)) {
    json_error('Please enter a valid Pakistani phone number (e.g. 03001234567).');
}
// Normalise so the same customer is never stored twice under two formats
$normalisedPhone = '0' . substr(preg_replace('/\D/', '', $cleanPhone), -10);

if (mb_strlen($address) < 10) {
    json_error('Please enter a complete delivery address including area and landmark.');
}
if (mb_strlen($address) > 255) {
    json_error('Address is too long (max 255 characters).');
}
if (mb_strlen($note) > 500) {
    json_error('Note is too long (max 500 characters).');
}
if ($note !== '' && looks_like_spam($note)) {
    json_error('Your note could not be accepted. Please remove any links and try again.');
}

$productId = (int) $input['product_id'];
if ($productId <= 0) {
    json_error('Invalid cake selection. Please refresh the page and try again.');
}

// ---- Delivery window: must be in the future ----
$deliveryTimestamp = strtotime($input['delivery_datetime']);
if ($deliveryTimestamp === false) {
    json_error('Invalid delivery date/time.');
}
if ($deliveryTimestamp < time() + 3600) {
    json_error('Please choose a delivery slot at least a few hours from now. For same-day orders, WhatsApp us on 0313-2483458.');
}
if ($deliveryTimestamp > time() + (180 * 86400)) {
    json_error('Delivery date is too far in the future (max 6 months).');
}

// ---- Look up the real price from the database ----
$check = $pdo->prepare('SELECT id, name, price FROM products WHERE id = :id AND is_active = 1');
$check->execute(['id' => $productId]);
$product = $check->fetch();

if (!$product) {
    json_error('Selected cake is not available.', 404);
}

$basePrice = (float) $product['price'];   // authoritative - from DB, not the browser
$cakeName  = $product['name'];

$toppings = $input['toppings'] ?? [];
$toppings = is_array($toppings) ? array_values(array_unique(array_map('intval', $toppings))) : [];
$toppings = array_values(array_filter($toppings, static fn($t) => $t > 0));
$delivery = date('Y-m-d H:i:s', $deliveryTimestamp);

try {
    $pdo->beginTransaction();

    // ---- 1. Insert or update customer (matched on phone) ----
    $stmt = $pdo->prepare('SELECT id FROM customers WHERE phone = :phone');
    $stmt->execute(['phone' => $normalisedPhone]);
    $customer = $stmt->fetch();

    if ($customer) {
        $customerId = $customer['id'];
        $pdo->prepare('UPDATE customers SET name = :name, address = :address WHERE id = :id')
            ->execute(['name' => $name, 'address' => $address, 'id' => $customerId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO customers (name, phone, address) VALUES (:name, :phone, :address)');
        $stmt->execute(['name' => $name, 'phone' => $normalisedPhone, 'address' => $address]);
        $customerId = $pdo->lastInsertId();
    }

    // ---- 2. Add-on prices, also read from the database ----
    $addonsTotal    = 0.0;
    $toppingDetails = [];

    if (!empty($toppings)) {
        $placeholders = implode(',', array_fill(0, count($toppings), '?'));
        $stmt = $pdo->prepare("SELECT id, name, price FROM toppings WHERE id IN ($placeholders) AND is_active = 1");
        $stmt->execute($toppings);
        $toppingDetails = $stmt->fetchAll();
        foreach ($toppingDetails as $t) {
            $addonsTotal += (float) $t['price'];
        }
    }

    $totalPrice = $basePrice + $addonsTotal;

    // ---- 3. Insert the order ----
    $noteFull = $flavour !== '' ? trim("Flavour: $flavour. $note") : $note;
    $stmt = $pdo->prepare('
        INSERT INTO orders (customer_id, product_id, base_price, addons_total, total_price, delivery_datetime, note, status)
        VALUES (:customer_id, :product_id, :base_price, :addons_total, :total_price, :delivery_datetime, :note, "pending")
    ');
    $stmt->execute([
        'customer_id'       => $customerId,
        'product_id'        => $productId,
        'base_price'        => $basePrice,
        'addons_total'      => $addonsTotal,
        'total_price'       => $totalPrice,
        'delivery_datetime' => $delivery,
        'note'              => $noteFull !== '' ? mb_substr($noteFull, 0, 255) : null,
    ]);
    $orderId = $pdo->lastInsertId();

    // ---- 4. Link the add-ons ----
    if ($toppingDetails) {
        $ins = $pdo->prepare('INSERT INTO order_toppings (order_id, topping_id, price) VALUES (:order_id, :topping_id, :price)');
        foreach ($toppingDetails as $t) {
            $ins->execute([
                'order_id'   => $orderId,
                'topping_id' => $t['id'],
                'price'      => $t['price'],
            ]);
        }
    }

    $pdo->commit();

    // ---- 5. Notify the bakery (best-effort, never blocks the order) ----
    send_order_notification_email($orderId, $name, $normalisedPhone, $address, $cakeName, $totalPrice, $delivery, $noteFull);

    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully!',
        'data'    => [
            'order_id'    => (int) $orderId,
            'cake_name'   => $cakeName,
            'base_price'  => $basePrice,
            'addons'      => $addonsTotal,
            'total_price' => $totalPrice,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Order placement failed: ' . $e->getMessage());
    json_error('Something went wrong while placing your order. Please try again or contact us on WhatsApp.', 500);
}

/**
 * Emails the bakery when a new order arrives.
 * Uses PHP mail(), which works on most shared hosting.
 */
function send_order_notification_email(
    $orderId, $name, $phone, $address, $cakeName, $totalPrice, $delivery, $note
): void {
    // ---------------------------------------------------------------
    // CHANGE THIS to the real address that should receive orders.
    // Use an address on your own domain so it passes SPF/DKIM checks
    // and does not land in spam.
    // ---------------------------------------------------------------
    $adminEmail = 'info@bobobakers.com';
    $fromEmail  = 'orders@bobobakers.com';

    $subject = "New Order #$orderId - Bobo Baker's";
    $body = "A new order has been placed.\n\n"
          . "Order ID: #$orderId\n"
          . "Customer: $name\n"
          . "Phone: $phone\n"
          . "Address: $address\n"
          . "Cake: $cakeName\n"
          . 'Total: Rs. ' . number_format($totalPrice) . "\n"
          . 'Delivery: ' . date('d M Y, h:i A', strtotime($delivery)) . "\n"
          . ($note ? "Note: $note\n" : '')
          . "\nOpen the admin panel to view full details.";

    $headers = "From: Bobo Baker's Website <$fromEmail>\r\n"
             . "Reply-To: $adminEmail\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . 'X-Mailer: PHP/' . phpversion();

    @mail($adminEmail, $subject, $body, $headers, "-f$fromEmail");
}
