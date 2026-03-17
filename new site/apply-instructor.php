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
$link = isset($_POST['applicant-link']) ? trim($_POST['applicant-link']) : '';
$interest = isset($_POST['applicant-interest']) ? trim($_POST['applicant-interest']) : '';
$background_check = isset($_POST['background-check']) ? 'Yes' : 'No';

if ($name === '' || $phone === '') {
    http_response_code(400);
    exit('Please complete all required fields.');
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('Please provide a valid email.');
}

if ($link === '' || !filter_var($link, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    exit('Please provide a valid LinkedIn or resume link.');
}

if ($interest === '') {
    http_response_code(400);
    exit('Please share why you are interested in leading the program.');
}

if ($background_check !== 'Yes') {
    http_response_code(400);
    exit('Please acknowledge the background check requirement.');
}

$to = ['alex@the-money-club.org', 'info@the-money-club.org'];
$subject = 'Instructor Application: The Money Club.Org';
$from = 'info@the-money-club.org';

$lines = [];
$lines[] = 'Name: ' . ($name !== '' ? $name : '(not provided)');
$lines[] = 'Email: ' . $email;
$lines[] = 'Phone: ' . ($phone !== '' ? $phone : '(not provided)');
$lines[] = 'Background check acknowledged: ' . $background_check;
$lines[] = 'LinkedIn/Resume: ' . ($link !== '' ? $link : '(none)');
$lines[] = 'Why interested: ' . ($interest !== '' ? $interest : '(not provided)');

$message = implode("\n", $lines);

if (!smtp_send_mail($to, $subject, $message, $from, $email)) {
    http_response_code(500);
    exit('Unable to send email.');
}

$first_name = trim((string) strtok($name, ' '));
if ($first_name === '') {
    $first_name = 'there';
}
$safe_first_name = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
$applicant_subject = 'Thank you for applying — The Money Club.Org';
$applicant_message = 'Hi ' . $safe_first_name . ',<br><br>'
    . 'Thanks for applying to join The Money Club as a university instructor/mentor. We really appreciate you putting your hand up.<br><br>'
    . 'Our program is built around mentorship in the real world — not just teaching concepts, but helping young people build judgment, confidence, and communication by working through real constraints and real decisions. We’re looking for educators who can lead with clarity, curiosity, and care — and help shape the next generation of leaders.<br><br>'
    . 'We’re reviewing applications now and will be in touch shortly with next steps (interview details + timing).<br><br>'
    . 'Thanks again,<br>'
    . 'The Money Club Team';
smtp_send_mail([$email], $applicant_subject, $applicant_message, $from, $from, 'The Money Club.Org', true);

header('Location: instructor-apply.html?status=sent');
exit;
?>
