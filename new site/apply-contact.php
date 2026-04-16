<?php
require_once __DIR__ . '/smtp-send.php';
require_once __DIR__ . '/form-security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact-us.html');
    exit;
}

$allowed_returns = ['contact-us.html', 'thank-u-contact.html'];
$return_to = tmc_resolve_return_target($_POST['return-to'] ?? 'contact-us.html', $allowed_returns, 'contact-us.html');
$error_return = tmc_resolve_return_target($_POST['return-error'] ?? $return_to, $allowed_returns, 'contact-us.html');

try {
    tmc_issue_csrf_cookie();
} catch (RuntimeException $e) {
    tmc_log_form_security_event('apply-contact', 'csrf_cookie_failed');
    tmc_redirect_with_error($error_return, 'form', 'Unable to validate this form securely. Please refresh and try again.');
}

if (tmc_honeypot_triggered()) {
    tmc_log_form_security_event('apply-contact', 'honeypot_tripped', ['return_to' => $return_to]);
    tmc_redirect_with_status($return_to, 'sent');
}

$rate_limit = tmc_rate_limit_check('apply-contact', 6, 600);
if (!$rate_limit['allowed']) {
    tmc_log_form_security_event('apply-contact', 'rate_limited', ['retry_after' => (string)$rate_limit['retry_after']]);
    tmc_redirect_with_error($error_return, 'form', 'Too many submissions. Please wait a few minutes and try again.');
}

$csrf_reason = '';
if (!tmc_verify_csrf_token(true, $csrf_reason)) {
    tmc_log_form_security_event('apply-contact', 'csrf_failed', ['reason' => $csrf_reason]);
    tmc_redirect_with_error($error_return, 'form', 'Your form session expired. Please refresh and try again.');
}

$name = tmc_trim_post('applicant-name', 160);
$email = tmc_trim_post('applicant-email', 254);
$phone = tmc_trim_post('applicant-phone', 40);
$notes = tmc_trim_post('applicant-notes', 4000);

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

$to = 'me@mistergoldberg.com';
$subject = 'Contact Us: The Money Club.Org';
$from = 'info@the-money-club.org';

$lines = [];
$lines[] = 'Name: ' . $name;
$lines[] = 'Email: ' . $email;
$lines[] = 'Phone: ' . $phone;
$lines[] = 'Notes: ' . ($notes !== '' ? $notes : '(none)');

$message = implode("\n", $lines);

if (!smtp_send_mail($to, $subject, $message, $from, $email)) {
    tmc_log_form_security_event('apply-contact', 'internal_email_failed');
    tmc_redirect_with_error($error_return, 'form', 'Unable to submit right now. Please try again shortly.');
}

tmc_redirect_with_status($return_to, 'sent');
?>
