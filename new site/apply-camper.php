<?php
require_once __DIR__ . '/smtp-send.php';

// Simple form handler for camper reservations.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reserve-a-spot.html');
    exit;
}

$parent_name = isset($_POST['parent-name']) ? trim($_POST['parent-name']) : '';
$parent_email = isset($_POST['parent-email']) ? trim($_POST['parent-email']) : '';
$parent_phone = isset($_POST['parent-phone']) ? trim($_POST['parent-phone']) : '';
$student_name = isset($_POST['student-name']) ? trim($_POST['student-name']) : '';
$student_age = isset($_POST['student-age']) ? trim($_POST['student-age']) : '';
$preferred_month = isset($_POST['preferred-month']) ? trim($_POST['preferred-month']) : '';
$notes = isset($_POST['student-notes']) ? trim($_POST['student-notes']) : '';

if ($parent_name === '' || $parent_phone === '' || $student_name === '' || $student_age === '') {
    http_response_code(400);
    exit('Please complete all required fields.');
}

if ($parent_email === '' || !filter_var($parent_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('Please provide a valid email.');
}

if (!in_array($preferred_month, ['July', 'August'], true)) {
    http_response_code(400);
    exit('Please select a valid month.');
}

$return_to = isset($_POST['return-to']) ? trim($_POST['return-to']) : 'reserve-a-spot.html';
$allowed_returns = ['reserve-a-spot.html', 'schedule-pricing.html'];
if (!in_array($return_to, $allowed_returns, true)) {
    $return_to = 'reserve-a-spot.html';
}

$to = 'me@mistergoldberg.com';
$subject = 'Reserve a Spot: The Money Club';
$from = 'info@the-money-club.org';

$lines = [];
$lines[] = 'Parent/Guardian Name: ' . ($parent_name !== '' ? $parent_name : '(not provided)');
$lines[] = 'Email: ' . $parent_email;
$lines[] = 'Phone: ' . ($parent_phone !== '' ? $parent_phone : '(not provided)');
$lines[] = 'Child Name: ' . ($student_name !== '' ? $student_name : '(not provided)');
$lines[] = 'Child Age: ' . ($student_age !== '' ? $student_age : '(not provided)');
$lines[] = 'Preferred Month: ' . ($preferred_month !== '' ? $preferred_month : '(not selected)');
$lines[] = 'Notes: ' . ($notes !== '' ? $notes : '(none)');

$message = implode("\n", $lines);

if (!smtp_send_mail($to, $subject, $message, $from, $parent_email)) {
    http_response_code(500);
    exit('Unable to send email.');
}

header('Location: ' . $return_to . '?status=sent');
exit;
?>
