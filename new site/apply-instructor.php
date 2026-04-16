<?php
require_once __DIR__ . '/smtp-send.php';
require_once __DIR__ . '/form-security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: instructor-apply.html');
    exit;
}

$allowed_returns = ['instructor-apply.html'];
$return_to = tmc_resolve_return_target($_POST['return-to'] ?? 'instructor-apply.html', $allowed_returns, 'instructor-apply.html');
$error_return = tmc_resolve_return_target($_POST['return-error'] ?? $return_to, $allowed_returns, 'instructor-apply.html');

try {
    tmc_issue_csrf_cookie();
} catch (RuntimeException $e) {
    tmc_log_form_security_event('apply-instructor', 'csrf_cookie_failed');
    tmc_redirect_with_error($error_return, 'form', 'Unable to validate this form securely. Please refresh and try again.');
}

if (tmc_honeypot_triggered()) {
    tmc_log_form_security_event('apply-instructor', 'honeypot_tripped', ['return_to' => $return_to]);
    tmc_redirect_with_status($return_to, 'sent');
}

$rate_limit = tmc_rate_limit_check('apply-instructor', 5, 1800);
if (!$rate_limit['allowed']) {
    tmc_log_form_security_event('apply-instructor', 'rate_limited', ['retry_after' => (string)$rate_limit['retry_after']]);
    tmc_redirect_with_error($error_return, 'form', 'Too many submissions. Please wait before trying again.');
}

$csrf_reason = '';
if (!tmc_verify_csrf_token(true, $csrf_reason)) {
    tmc_log_form_security_event('apply-instructor', 'csrf_failed', ['reason' => $csrf_reason]);
    tmc_redirect_with_error($error_return, 'form', 'Your form session expired. Please refresh and try again.');
}

$name = tmc_trim_post('applicant-name', 160);
$email = tmc_trim_post('applicant-email', 254);
$phone = tmc_trim_post('applicant-phone', 40);
$link = tmc_trim_post('applicant-link', 2048);
$interest = tmc_trim_post('applicant-interest', 6000);
$background_check = isset($_POST['background-check']) ? 'Yes' : 'No';

if ($name === '') {
    tmc_redirect_with_error($error_return, 'applicant-name', 'Name is required.');
}

if (!tmc_is_valid_email($email)) {
    tmc_redirect_with_error($error_return, 'applicant-email', 'Please provide a valid email.');
}

$phone_digits = tmc_phone_digits($phone);
if ($phone_digits === '' || strlen($phone_digits) < 10 || strlen($phone_digits) > 15) {
    tmc_redirect_with_error($error_return, 'applicant-phone', 'Please provide a valid phone number.');
}

if ($link === '' || !filter_var($link, FILTER_VALIDATE_URL)) {
    tmc_redirect_with_error($error_return, 'applicant-link', 'Please provide a valid LinkedIn or resume link.');
}

if ($interest === '') {
    tmc_redirect_with_error($error_return, 'applicant-interest', 'Please share why you are interested in leading the program.');
}

if ($background_check !== 'Yes') {
    tmc_redirect_with_error($error_return, 'background-check', 'Please acknowledge the background check requirement.');
}

$to = ['alex@the-money-club.org', 'info@the-money-club.org'];
$subject = 'Instructor Application: The Money Club.Org';
$from = 'info@the-money-club.org';

$lines = [];
$lines[] = 'Name: ' . $name;
$lines[] = 'Email: ' . $email;
$lines[] = 'Phone: ' . $phone;
$lines[] = 'Background check acknowledged: ' . $background_check;
$lines[] = 'LinkedIn/Resume: ' . $link;
$lines[] = 'Why interested: ' . $interest;

$message = implode("\n", $lines);

if (!smtp_send_mail($to, $subject, $message, $from, $email)) {
    tmc_log_form_security_event('apply-instructor', 'internal_email_failed');
    tmc_redirect_with_error($error_return, 'form', 'Unable to submit right now. Please try again shortly.');
}

$first_name = trim((string)strtok($name, ' '));
if ($first_name === '') {
    $first_name = 'there';
}
$safe_first_name = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
$applicant_subject = 'Thank you for applying — The Money Club.Org';
$applicant_message = 'Hi ' . $safe_first_name . ',<br><br>'
    . 'Thanks for applying to join The Money Club.Org as a university instructor/mentor. We really appreciate you putting your hand up.<br><br>'
    . 'Our program is built around mentorship in the real world — not just teaching concepts, but helping young people build judgment, confidence, and communication by working through real constraints and real decisions. We’re looking for educators who can lead with clarity, curiosity, and care — and help shape the next generation of leaders.<br><br>'
    . 'We’re reviewing applications now and will be in touch shortly with next steps (interview details + timing).<br><br>'
    . 'Thanks again,<br>'
    . 'The Money Club.Org Team';
smtp_send_mail([$email], $applicant_subject, $applicant_message, $from, $from, 'The Money Club.Org', true);

tmc_redirect_with_status($return_to, 'sent');
?>
