<?php
/**
 * POST /api/update_lead_status.php
 * Body (JSON): { "lead_id": 5, "status": "contacted", "csrf_token": "..." }
 * Allowed statuses: new, contacted, converted, closed
 * Requires: admin login + valid CSRF token
 */

require_once __DIR__ . '/../admin/auth.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$input = is_array($input) ? $input : [];

require_login_api_csrf($input);

require_once __DIR__ . '/../config/db.php';

$allowed = ['new', 'contacted', 'converted', 'closed'];

$leadId = (int) ($input['lead_id'] ?? 0);
$status = $input['status'] ?? '';

if ($leadId <= 0 || !in_array($status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid enquiry or status.']);
    exit;
}

$stmt = $pdo->prepare('UPDATE leads SET status = :status WHERE id = :id');
$stmt->execute(['status' => $status, 'id' => $leadId]);

echo json_encode(['success' => true, 'message' => 'Enquiry status updated.']);
