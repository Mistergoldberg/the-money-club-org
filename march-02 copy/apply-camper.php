<?php
require_once __DIR__ . '/smtp-send.php';

function get_data_dir() {
    $outside = dirname(__DIR__) . '/data';
    if (is_dir($outside) && is_writable($outside)) {
        return $outside;
    }
    $inside = __DIR__ . '/data';
    if (is_dir($inside) && is_writable($inside)) {
        return $inside;
    }
    return $inside;
}

function base_url() {
    $scheme = 'https';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'the-money-club.org';
    return $scheme . '://' . $host;
}

// Simple form handler for camper reservations.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reserve-a-spot.html');
    exit;
}

$data_dir = get_data_dir();
$log_path = $data_dir . '/apply-camper.log';
function log_debug($message) {
    $timestamp = date('Y-m-d H:i:s');
    $line = '[' . $timestamp . '] ' . $message . "\n";
    @file_put_contents($GLOBALS['log_path'], $line, FILE_APPEND);
}

$parent_name = isset($_POST['parent-name']) ? trim($_POST['parent-name']) : '';
$parent_email = isset($_POST['parent-email']) ? trim($_POST['parent-email']) : '';
$parent_phone = isset($_POST['parent-phone']) ? trim($_POST['parent-phone']) : '';
$student_name = isset($_POST['student-name']) ? trim($_POST['student-name']) : '';
$student_age = isset($_POST['student-age']) ? trim($_POST['student-age']) : '';
$preferred_session = isset($_POST['preferred-session']) ? trim($_POST['preferred-session']) : '';
$preferred_month = isset($_POST['preferred-month']) ? trim($_POST['preferred-month']) : '';
$payment_method = isset($_POST['payment-method']) ? trim($_POST['payment-method']) : '';
$terms_agree = isset($_POST['terms-agree']) ? trim($_POST['terms-agree']) : '';
$notes = isset($_POST['student-notes']) ? trim($_POST['student-notes']) : '';

$return_to = isset($_POST['return-to']) ? trim($_POST['return-to']) : 'reserve-a-spot.html';
$allowed_returns = [
    'reserve-a-spot.html',
    'schedule-pricing.html',
    'how-it-works.html',
    'open-book-hook.html',
    'curriculum.html',
    'index.html',
    'thank-you.html',
    'etransfer.html'
];
if (!in_array($return_to, $allowed_returns, true)) {
    $return_to = 'reserve-a-spot.html';
}

$error_return = isset($_POST['return-error']) ? trim($_POST['return-error']) : $return_to;
if (!in_array($error_return, $allowed_returns, true)) {
    $error_return = 'reserve-a-spot.html';
}

function redirect_with_error($return_to, $field, $message) {
    log_debug('redirect_with_error field=' . $field . ' message=' . $message . ' return=' . $return_to);
    $params = [
        'status' => 'error',
        'field' => $field,
        'message' => $message
    ];
    $separator = (strpos($return_to, '?') === false) ? '?' : '&';
    header('Location: ' . $return_to . $separator . http_build_query($params));
    exit;
}

log_debug('apply-camper POST email=' . $parent_email . ' phone=' . $parent_phone . ' age=' . $student_age . ' session=' . $preferred_session . ' payment=' . $payment_method . ' terms=' . ($terms_agree !== '' ? 'yes' : 'no') . ' return=' . $return_to);

if ($parent_name === '') {
    redirect_with_error($error_return, 'parent-name', 'Parent/guardian name is required.');
}

if ($parent_email === '' || !filter_var($parent_email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_error($error_return, 'parent-email', 'Please provide a valid email.');
}

if ($parent_phone === '') {
    redirect_with_error($error_return, 'parent-phone', 'Phone number is required.');
}

$phone_digits = preg_replace('/\D+/', '', $parent_phone);
if ($phone_digits === '' || strlen($phone_digits) < 10 || strlen($phone_digits) > 15) {
    redirect_with_error($error_return, 'parent-phone', 'Please provide a valid phone number.');
}

$age_value = filter_var($student_age, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 11, 'max_range' => 17]
]);
if ($age_value === false) {
    redirect_with_error($error_return, 'student-age', 'Child’s age must be between 11 and 17.');
}

if ($student_name === '') {
    redirect_with_error($error_return, 'student-name', 'Child’s name is required.');
}

$session_map = [
    'session1' => 'Session 1: July 6, 2026 → July 31, 2026',
    'session2' => 'Session 2: August 4, 2026 → August 28, 2026'
];

if ($preferred_session === '' && $preferred_month !== '') {
    $preferred_session = $preferred_month === 'July' ? 'session1' : 'session2';
}

if (!array_key_exists($preferred_session, $session_map)) {
    redirect_with_error($error_return, 'preferred-session', 'Please select a session.');
}

if ($terms_agree === '') {
    redirect_with_error($error_return, 'terms-agree', 'Please agree to the program terms and privacy policy.');
}

if ($payment_method === '' || !in_array($payment_method, ['Credit Card', 'e-Transfer'], true)) {
    redirect_with_error($error_return, 'payment-method', 'Please choose a payment method.');
}

$availability_path = $data_dir . '/availability.json';
$availability_defaults = [
    'session1' => 30,
    'session2' => 30
];

function update_availability($path, $defaults, $callback) {
    $fp = fopen($path, 'c+');
    if (!$fp) {
        return ['ok' => false, 'error' => 'Unable to access availability.'];
    }
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $defaults;
    }
    foreach ($defaults as $key => $value) {
        if (!isset($data[$key]) || !is_numeric($data[$key])) {
            $data[$key] = $value;
        }
    }

    $result = $callback($data);

    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $result;
}

$reserve_result = update_availability($availability_path, $availability_defaults, function (&$data) use ($preferred_session) {
    if ((int)$data[$preferred_session] <= 0) {
        return ['ok' => false, 'remaining' => (int)$data[$preferred_session]];
    }
    $data[$preferred_session] = (int)$data[$preferred_session] - 1;
    return ['ok' => true, 'remaining' => (int)$data[$preferred_session]];
});

if (!$reserve_result['ok']) {
    if (isset($reserve_result['error'])) {
        log_debug('availability error: ' . $reserve_result['error']);
        $reserve_result = ['ok' => true, 'remaining' => 'unknown'];
    } else {
        log_debug('session full: ' . $preferred_session);
        redirect_with_error($error_return, 'preferred-session', 'Selected session is full. Please choose another session or contact us.');
    }
}

$to = ['info@the-money-club.org', 'alex@the-money-club.org', 'sarah@the-money-club.org'];
$subject = 'Reserve a Spot: The Money Club.Org';
$from = 'info@the-money-club.org';

$lines = [];
$lines[] = 'Parent/Guardian Name: ' . ($parent_name !== '' ? $parent_name : '(not provided)');
$lines[] = 'Email: ' . $parent_email;
$lines[] = 'Phone: ' . ($parent_phone !== '' ? $parent_phone : '(not provided)');
$lines[] = 'Child Name: ' . ($student_name !== '' ? $student_name : '(not provided)');
$lines[] = 'Child Age: ' . ($student_age !== '' ? $student_age : '(not provided)');
$lines[] = 'Session: ' . $session_map[$preferred_session];
$lines[] = 'Session spots remaining: ' . (string)$reserve_result['remaining'];
$lines[] = 'Terms agreed: ' . ($terms_agree !== '' ? 'Yes' : 'No');
$lines[] = 'Payment method: ' . ($payment_method !== '' ? $payment_method : '(not specified)');
$lines[] = 'Notes: ' . ($notes !== '' ? $notes : '(none)');

$message = implode("\n", $lines);

if (!smtp_send_mail($to, $subject, $message, $from, $parent_email)) {
    log_debug('smtp_send_mail failed for ' . $parent_email);
    update_availability($availability_path, $availability_defaults, function (&$data) use ($preferred_session) {
        $data[$preferred_session] = (int)$data[$preferred_session] + 1;
        return ['ok' => true];
    });
    redirect_with_error($error_return, 'payment-method', 'Unable to send confirmation email. Please try again.');
}

log_debug('smtp_send_mail success for ' . $parent_email . ' session=' . $preferred_session . ' payment=' . $payment_method);

$parent_subject = 'The Money Club.Org reservation — Payment Instructions';
$credit_card_link = 'https://checkout.stripe.com/c/pay/cs_live_b1RVNM06xWT2MiNRIovexSQB0sDZVqNTSr7LPxKKQUuN4s45FGBTW3Q1Zl#fidnandhYHdWcXxpYCc%2FJ2FgY2RwaXEnKSdkdWxOYHwnPyd1blppbHNgWjA0VnxNN0c2TGJvdzQwYn1MYW5TPXw1PV12VGNMMlI2VUN1UHRcaG9Gck09UXRSdEJ0amw9RDNUdU5XTnB9YGFRM3w3S3NIN2hDYHx9b1dyNklzVFF8a001NTVsaG5LdV9RbCcpJ2N3amhWYHdzYHcnP3F3cGApJ2dkZm5id2pwa2FGamlqdyc%2FJyY1Nz1mPD0nKSdpZHxqcHFRfHVgJz8naHBpcWxabHFgaCcpJ2BrZGdpYFVpZGZgbWppYWB3dic%2FcXdwYHgl';
$safe_parent_name = htmlspecialchars($parent_name !== '' ? $parent_name : 'Parent', ENT_QUOTES, 'UTF-8');
$safe_student_name = htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8');
$safe_student_age = htmlspecialchars((string) $student_age, ENT_QUOTES, 'UTF-8');
$safe_session_label = htmlspecialchars($session_map[$preferred_session], ENT_QUOTES, 'UTF-8');
$parent_message = 'Hi ' . $safe_parent_name . ',<br><br>'
    . '<strong>Thanks</strong> — we’ve received your reservation details for The Money Club.<br><br>'
    . '<strong>Student:</strong> ' . $safe_student_name . ' (Age ' . $safe_student_age . ')<br>'
    . '<strong>Session:</strong> ' . $safe_session_label . '<br><br>'
    . "If you've already made payment - thank you for investing in the local economy.<br><br>"
    . 'To confirm your seat, please complete your payment using one of the options below:<br><br>'
    . '<strong>Option 1: Credit Card (instant confirmation)</strong><br>'
    . '<a href="' . $credit_card_link . '">' . $credit_card_link . '</a><br><br>'
    . 'Total by credit card: $1,735.68 CAD (includes HST + 2.4% surcharge)<br>'
    . 'Note: The 2.4% credit card processing surcharge is non-refundable (refunds available until June 1, 2026).<br><br>'
    . '<strong>Option 2: Interac e-Transfer (no processing fee)</strong><br>'
    . 'To avoid the 2.4% credit card processing surcharge, you can pay by e-Transfer instead:<br><br>'
    . '<strong>Send to:</strong> <a href="mailto:info@the-money-club.org">info@the-money-club.org</a><br>'
    . '<strong>Amount:</strong> $1,695.00 CAD (includes HST)<br>'
    . '<strong>Message / Note (required):</strong> ' . $safe_student_name . '<br><br>'
    . 'We’ll email confirmation within 24 hours of receiving your transfer.<br><br>'
    . 'After payment, you’ll receive a receipt/confirmation and we’ll send the Student Registration & Consent Form.<br><br>'
    . '<strong>Questions?</strong> Reply to this email or contact us at <a href="mailto:info@the-money-club.org">info@the-money-club.org</a> / 437-239-8602.<br><br>'
    . '— The Money Club Team';
if (!smtp_send_mail([$parent_email], $parent_subject, $parent_message, $from, $from, 'The Money Club.Org', true)) {
    log_debug('parent payment instructions email failed for ' . $parent_email);
} else {
    log_debug('parent payment instructions email sent to ' . $parent_email);
}

$csv_path = $data_dir . '/apply-camper-submissions.csv';
$csv_headers = [
    'submitted_at',
    'parent_name',
    'parent_email',
    'parent_phone',
    'student_name',
    'student_age',
    'session',
    'terms_agreed',
    'payment_method',
    'spots_remaining'
];
$csv_row = [
    date('Y-m-d H:i:s'),
    $parent_name,
    $parent_email,
    $parent_phone,
    $student_name,
    $student_age,
    $session_map[$preferred_session],
    ($terms_agree !== '' ? 'Yes' : 'No'),
    $payment_method,
    (string)$reserve_result['remaining']
];

$csv_fp = fopen($csv_path, 'a+');
if ($csv_fp) {
    if (flock($csv_fp, LOCK_EX)) {
        $is_empty = (filesize($csv_path) === 0);
        if ($is_empty) {
            fputcsv($csv_fp, $csv_headers);
        }
        fputcsv($csv_fp, $csv_row);
        fflush($csv_fp);
        flock($csv_fp, LOCK_UN);
    }
    fclose($csv_fp);
}

if ($return_to === 'thank-you.html') {
    log_debug('redirect success to thank-you.html');
    header('Location: ' . $return_to);
} else {
    log_debug('redirect success to return_to=' . $return_to);
    header('Location: ' . $return_to . '?status=sent');
}
exit;
?>
