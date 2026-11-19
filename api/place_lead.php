<?php
/**
 * POST /api/place_lead.php
 *
 * Saves a custom-cake enquiry from the "Place Your Order" contact form
 * (the free-text design form - separate from the catalog checkout in
 * place_order.php). The site still opens WhatsApp either way; this keeps
 * a permanent, searchable record so no enquiry is lost.
 *
 * Body (JSON):
 * {
 *   "order_ref": "BB-12345",
 *   "name": "Ayesha Khan",
 *   "phone": "03001234567",
 *   "area": "DHA Phase 5, Lahore",
 *   "occasion": "Birthday",
 *   "required_by": "2026-08-20",
 *   "flavour": "Chocolate",
 *   "size": "Medium (Serves 12-15)",
 *   "budget": "Rs. 3,500 - 5,000",
 *   "cake_message": "Happy Birthday Zara!",
 *   "design_details": "Pink and gold theme, 2 tiers, fondant finish...",
 *   "referral": "Instagram"
 * }
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_error('Method not allowed.', 405);
}

// ---- Rate limit: 4 enquiries per 10 minutes per IP ----
enforce_rate_limit(
    'place_lead',
    4,
    600,
    'You have sent several enquiries already. Please wait a few minutes, or message us directly on WhatsApp: 0313-2483458'
);

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    json_error('Invalid request data. Please refresh the page and try again.');
}

// ---- Bot honeypot: silently accept and discard ----
if (is_honeypot_tripped($input)) {
    echo json_encode(['success' => true, 'message' => 'Enquiry saved!', 'data' => ['lead_id' => 0]]);
    exit;
}

$name    = trim($input['name'] ?? '');
$phone   = trim($input['phone'] ?? '');
$area    = trim($input['area'] ?? '');
$details = trim($input['design_details'] ?? '');

// ---- Validation (mirrors the frontend rules) ----
if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
    json_error('Please enter your full name (3-100 characters).');
}

$cleanPhone = preg_replace('/[\s\-()]/', '', $phone);
if (!preg_match('/^(\+92|0092|92|0)?3[0-9]{9}$/', $cleanPhone)) {
    json_error('Please enter a valid Pakistani WhatsApp number (e.g. 03001234567).');
}
$normalisedPhone = '0' . substr(preg_replace('/\D/', '', $cleanPhone), -10);

if (mb_strlen($area) < 3 || mb_strlen($area) > 150) {
    json_error('Please enter your delivery area or city.');
}
if (empty($input['occasion'])) {
    json_error('Please select the occasion for your cake.');
}
if (empty($input['required_by'])) {
    json_error('Please tell us when you need the cake by.');
}
if (mb_strlen($details) < 5) {
    json_error('Please describe your cake design so we can help you better.');
}
if (mb_strlen($details) > 2000) {
    json_error('Design details are too long (max 2000 characters).');
}
if (looks_like_spam($details)) {
    json_error('Your message could not be accepted. Please remove any links and try again.');
}

$requiredByTs = strtotime($input['required_by']);
if ($requiredByTs === false) {
    json_error('Please enter a valid date for when you need the cake.');
}
if ($requiredByTs < strtotime('today')) {
    json_error('The date you need the cake by cannot be in the past.');
}
$requiredBy = date('Y-m-d', $requiredByTs);

// ---- Duplicate guard: same phone + same day = same enquiry ----
$dup = $pdo->prepare('SELECT id FROM leads WHERE phone = :phone AND created_at > (NOW() - INTERVAL 5 MINUTE) LIMIT 1');
$dup->execute(['phone' => $normalisedPhone]);
if ($dup->fetch()) {
    echo json_encode([
        'success' => true,
        'message' => 'We already have your enquiry - our team will WhatsApp you shortly.',
        'data'    => ['lead_id' => 0, 'duplicate' => true],
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare('
        INSERT INTO leads
          (order_ref, name, phone, area, occasion, required_by, flavour, size, budget, cake_message, design_details, referral, status)
        VALUES
          (:order_ref, :name, :phone, :area, :occasion, :required_by, :flavour, :size, :budget, :cake_message, :design_details, :referral, "new")
    ');
    $stmt->execute([
        'order_ref'      => mb_substr(trim($input['order_ref'] ?? ''), 0, 20) ?: null,
        'name'           => $name,
        'phone'          => $normalisedPhone,
        'area'           => $area,
        'occasion'       => mb_substr(trim($input['occasion'] ?? ''), 0, 100) ?: null,
        'required_by'    => $requiredBy,
        'flavour'        => mb_substr(trim($input['flavour'] ?? ''), 0, 100) ?: null,
        'size'           => mb_substr(trim($input['size'] ?? ''), 0, 100) ?: null,
        'budget'         => mb_substr(trim($input['budget'] ?? ''), 0, 100) ?: null,
        'cake_message'   => mb_substr(trim($input['cake_message'] ?? ''), 0, 255) ?: null,
        'design_details' => $details,
        'referral'       => mb_substr(trim($input['referral'] ?? ''), 0, 100) ?: null,
    ]);

    $leadId = (int) $pdo->lastInsertId();

    // ---- Notify the bakery (best-effort) ----
    @mail(
        'info@bobobakers.com',
        "New Custom Cake Enquiry - Bobo Baker's",
        "Name: $name\nWhatsApp: $normalisedPhone\nArea: $area\n"
        . 'Occasion: ' . ($input['occasion'] ?? '-') . "\n"
        . "Needed by: $requiredBy\n\nDesign details:\n$details\n\nOpen the admin panel to manage this enquiry.",
        "From: Bobo Baker's Website <orders@bobobakers.com>\r\nReply-To: info@bobobakers.com\r\nContent-Type: text/plain; charset=UTF-8"
    );

    echo json_encode([
        'success' => true,
        'message' => 'Enquiry saved!',
        'data'    => ['lead_id' => $leadId],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Lead save failed: ' . $e->getMessage());
    // Non-fatal from the frontend's point of view - WhatsApp still opens.
    json_error('Could not save your enquiry record, but your WhatsApp message will still go through.', 500);
}
