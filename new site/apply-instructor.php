<?php
require_once __DIR__ . '/smtp-send.php';

// Simple form handler for instructor applications.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: instructor-apply.html');
    exit;
}

$name = isset($_POST['applicant-name']) ? trim($_POST['applicant-name']) : '';
$email = isset($_POST['applicant-email']) ? trim($_POST['applicant-email']) : '';
$phone = isset($_POST['applicant-phone']) ? trim($_POST['applicant-phone']) : '';
$notes = isset($_POST['applicant-notes']) ? trim($_POST['applicant-notes']) : '';
$background_check = isset($_POST['background-check']) ? 'Yes' : 'No';

if ($name === '' || $phone === '') {
    http_response_code(400);
    exit('Please complete all required fields.');
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('Please provide a valid email.');
}

if ($background_check !== 'Yes') {
    http_response_code(400);
    exit('Please acknowledge the background check requirement.');
}

$to = 'me@mistergoldberg.com';
$subject = 'Instructor Application: The Money Club';
$from = 'info@the-money-club.org';

$lines = [];
$lines[] = 'Name: ' . ($name !== '' ? $name : '(not provided)');
$lines[] = 'Email: ' . $email;
$lines[] = 'Phone: ' . ($phone !== '' ? $phone : '(not provided)');
$lines[] = 'Background check acknowledged: ' . $background_check;
$lines[] = 'Notes: ' . ($notes !== '' ? $notes : '(none)');

$message = implode("\n", $lines);

if (!smtp_send_mail($to, $subject, $message, $from, $email)) {
    http_response_code(500);
    exit('Unable to send email.');
}

header('Location: instructor-apply.html?status=sent');
exit;
?>
