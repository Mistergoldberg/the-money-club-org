<?php
require_once __DIR__ . '/smtp-send.php';

// Simple form handler for mission support inquiries.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: support-our-mission.html');
    exit;
}

$name = isset($_POST['applicant-name']) ? trim($_POST['applicant-name']) : '';
$email = isset($_POST['applicant-email']) ? trim($_POST['applicant-email']) : '';
$phone = isset($_POST['applicant-phone']) ? trim($_POST['applicant-phone']) : '';
$notes = isset($_POST['applicant-notes']) ? trim($_POST['applicant-notes']) : '';

if ($name === '' || $phone === '') {
    http_response_code(400);
    exit('Please complete all required fields.');
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('Please provide a valid email.');
}

$to = 'me@mistergoldberg.com';
$subject = 'Support Our Mission: The Money Club';
$from = 'info@the-money-club.org';

$lines = [];
$lines[] = 'Name: ' . ($name !== '' ? $name : '(not provided)');
$lines[] = 'Email: ' . $email;
$lines[] = 'Phone: ' . ($phone !== '' ? $phone : '(not provided)');
$lines[] = 'Notes: ' . ($notes !== '' ? $notes : '(none)');

$message = implode("\n", $lines);

if (!smtp_send_mail($to, $subject, $message, $from, $email)) {
    http_response_code(500);
    exit('Unable to send email.');
}

header('Location: support-our-mission.html?status=sent');
exit;
?>
