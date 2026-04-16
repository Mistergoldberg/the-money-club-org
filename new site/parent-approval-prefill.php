<?php
session_start();
require_once __DIR__ . '/form-security.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$rate_limit = tmc_rate_limit_check('parent-approval-prefill', 30, 300);
if (!$rate_limit['allowed']) {
    tmc_log_form_security_event('parent-approval-prefill', 'rate_limited', ['retry_after' => (string)$rate_limit['retry_after']]);
    http_response_code(429);
    echo json_encode(['ok' => false]);
    exit;
}

if (!tmc_is_same_origin_request()) {
    tmc_log_form_security_event('parent-approval-prefill', 'same_origin_failed');
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$prefill = $_SESSION['tmc_parent_approval_prefill'] ?? null;
$prefill_set_at = $_SESSION['tmc_parent_approval_prefill_set_at'] ?? null;

if (!is_array($prefill)) {
    echo json_encode(['ok' => false]);
    exit;
}

if (!is_int($prefill_set_at) || ($prefill_set_at + 1800) < time()) {
    unset($_SESSION['tmc_parent_approval_prefill'], $_SESSION['tmc_parent_approval_prefill_set_at']);
    echo json_encode(['ok' => false]);
    exit;
}

unset($_SESSION['tmc_parent_approval_prefill'], $_SESSION['tmc_parent_approval_prefill_set_at']);
echo json_encode([
    'ok' => true,
    'prefill' => $prefill
]);
exit;
?>
