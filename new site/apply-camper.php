<?php
require_once __DIR__ . '/smtp-send.php';
require_once __DIR__ . '/form-security.php';

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

$data_dir = tmc_get_data_dir();
$log_path = $data_dir . '/apply-camper.log';
function log_debug($message) {
    $timestamp = date('Y-m-d H:i:s');
    $line = '[' . $timestamp . '] ' . $message . "\n";
    @file_put_contents($GLOBALS['log_path'], $line, FILE_APPEND);
}

$parent_name = tmc_trim_post('parent-name', 160);
$parent_email = tmc_trim_post('parent-email', 254);
$parent_phone = tmc_trim_post('parent-phone', 40);
$student_name = tmc_trim_post('student-name', 160);
$student_age = tmc_trim_post('student-age', 3);
$program_track = tmc_trim_post('program-track', 80);
$preferred_session = tmc_trim_post('preferred-session', 80);
$preferred_month = tmc_trim_post('preferred-month', 20);
$payment_method = tmc_trim_post('payment-method', 40);
$terms_agree = tmc_trim_post('terms-agree', 10);
$notes = tmc_trim_post('student-notes', 4000);

$return_to = tmc_resolve_return_target($_POST['return-to'] ?? 'reserve-a-spot.html', [
    'reserve-a-spot.html',
    'schedule-pricing.html',
    'how-it-works.html',
    'pricing.html',
    'open-book-hook.html',
    'index.html',
    'thank-you.html',
    'etransfer.html'
], 'reserve-a-spot.html');
$allowed_returns = [
    'reserve-a-spot.html',
    'schedule-pricing.html',
    'how-it-works.html',
    'pricing.html',
    'open-book-hook.html',
    'schedule-pricing.html',
    'index.html',
    'thank-you.html',
    'etransfer.html'
];
$error_return = tmc_resolve_return_target($_POST['return-error'] ?? $return_to, $allowed_returns, 'reserve-a-spot.html');

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

log_debug('apply-camper POST program=' . $program_track . ' session=' . $preferred_session . ' payment=' . $payment_method . ' terms=' . ($terms_agree !== '' ? 'yes' : 'no') . ' return=' . $return_to);

try {
    tmc_issue_csrf_cookie();
} catch (RuntimeException $e) {
    tmc_log_form_security_event('apply-camper', 'csrf_cookie_failed');
    redirect_with_error($error_return, 'form', 'Unable to validate this form securely. Please refresh and try again.');
}

if (tmc_honeypot_triggered()) {
    tmc_log_form_security_event('apply-camper', 'honeypot_tripped', ['return_to' => $return_to]);
    tmc_redirect_with_status($return_to, 'sent');
}

$rate_limit = tmc_rate_limit_check('apply-camper', 6, 900);
if (!$rate_limit['allowed']) {
    tmc_log_form_security_event('apply-camper', 'rate_limited', ['retry_after' => (string)$rate_limit['retry_after']]);
    redirect_with_error($error_return, 'form', 'Too many submissions. Please wait before trying again.');
}

$csrf_reason = '';
if (!tmc_verify_csrf_token(true, $csrf_reason)) {
    tmc_log_form_security_event('apply-camper', 'csrf_failed', ['reason' => $csrf_reason]);
    redirect_with_error($error_return, 'form', 'Your form session expired. Please refresh and try again.');
}

if ($parent_name === '') {
    redirect_with_error($error_return, 'parent-name', 'Parent/guardian name is required.');
}

if (!tmc_is_valid_email($parent_email)) {
    redirect_with_error($error_return, 'parent-email', 'Please provide a valid email.');
}

if ($parent_phone === '') {
    redirect_with_error($error_return, 'parent-phone', 'Phone number is required.');
}

$phone_digits = tmc_phone_digits($parent_phone);
if ($phone_digits === '' || strlen($phone_digits) < 10 || strlen($phone_digits) > 15) {
    redirect_with_error($error_return, 'parent-phone', 'Please provide a valid phone number.');
}

$age_value = filter_var($student_age, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 10, 'max_range' => 16]
]);
if ($age_value === false) {
    redirect_with_error($error_return, 'student-age', 'Child’s age must be between 10 and 16.');
}

if ($student_name === '') {
    redirect_with_error($error_return, 'student-name', 'Child’s name is required.');
}

$program_options = [
    'two-week-builder-sprint' => [
        'label' => '2-Week Builder Sprint',
        'tuition' => 750,
        'sessions' => [
            'session1' => 'July 6-17, 2026',
            'session2' => 'August 4-15, 2026'
        ]
    ],
    'four-week-full-program' => [
        'label' => '4-Week Full Program',
        'tuition' => 1100,
        'sessions' => [
            'session1' => 'July 6-31, 2026',
            'session2' => 'August 4-28, 2026'
        ]
    ]
];

if (!array_key_exists($program_track, $program_options)) {
    redirect_with_error($error_return, 'program-track', 'Please select a valid program.');
}

if ($preferred_session === '' && $preferred_month !== '') {
    $preferred_session = $preferred_month === 'July' ? 'session1' : 'session2';
}

if (!array_key_exists($preferred_session, $program_options[$program_track]['sessions'])) {
    redirect_with_error($error_return, 'preferred-session', 'Please select a session.');
}

$session_label = $program_options[$program_track]['sessions'][$preferred_session];
$program_label = $program_options[$program_track]['label'];
$program_tuition = (float)$program_options[$program_track]['tuition'];

if ($terms_agree === '') {
    redirect_with_error($error_return, 'terms-agree', 'Please agree to the Terms & Payment Policy.');
}

if ($payment_method === '' || $payment_method !== 'e-Transfer') {
    redirect_with_error($error_return, 'payment-method', 'Payment is currently available by e-Transfer only.');
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
$lines[] = 'Program: ' . $program_label;
$lines[] = 'Session: ' . $session_label;
$lines[] = 'Program tuition: $' . number_format($program_tuition, 2) . ' CAD (+HST)';
$lines[] = 'Session spots remaining: ' . (string)$reserve_result['remaining'];
$lines[] = 'Terms agreed: ' . ($terms_agree !== '' ? 'Yes' : 'No');
$lines[] = 'Payment method: e-Transfer (pending manual confirmation)';
$lines[] = 'Notes: ' . ($notes !== '' ? $notes : '(none)');

$message = implode("\n", $lines);

if (!smtp_send_mail($to, $subject, $message, $from, $parent_email)) {
    log_debug('smtp_send_mail failed for registration notification.');
} else {
    log_debug('smtp_send_mail success session=' . $preferred_session . ' payment=' . $payment_method);
}

$parent_subject = 'Registration received — complete e-Transfer payment';
$greeting = $parent_name !== '' ? 'Hi ' . $parent_name . ',' : 'Hi there,';
$parent_approval_link = base_url() . '/parent-approval.html';
$parent_lines = [];
$parent_lines[] = $greeting;
$parent_lines[] = '';
$parent_lines[] = 'We’ve received your registration details.';
$parent_lines[] = '';
$parent_lines[] = 'To secure your child’s seat, please complete the parent approval form and send your Interac e-Transfer payment.';
$parent_lines[] = '';
$parent_lines[] = '---';
$parent_lines[] = '';
$parent_lines[] = '🧭 One final step';
$parent_lines[] = '';
$parent_lines[] = 'Please complete the parent approval form first:';
$parent_lines[] = '';
$parent_lines[] = '👉 ' . $parent_approval_link;
$parent_lines[] = '';
$parent_lines[] = 'Then send payment using our e-Transfer instructions:';
$parent_lines[] = '';
$parent_lines[] = '👉 ' . base_url() . '/etransfer.html';
$parent_lines[] = '';
$parent_lines[] = 'Seats are confirmed only after e-Transfer is received and manually matched to your registration.';
$parent_lines[] = '';
$parent_lines[] = '---';
$parent_lines[] = '';
$parent_lines[] = '📍 Program details';
$parent_lines[] = '';
$parent_lines[] = 'UTSU Student Commons';
$parent_lines[] = 'University of Toronto (downtown)';
$parent_lines[] = '';
$parent_lines[] = 'Daily 9–5, with instruction from 9:30am to 3:30pm';
$parent_lines[] = '';
$parent_lines[] = 'Program selected: ' . $program_label;
$parent_lines[] = 'Session selected: ' . $session_label;
$parent_lines[] = 'Program fee: $' . number_format($program_tuition, 2) . ' CAD (+HST)';
$parent_lines[] = '';
$parent_lines[] = '---';
$parent_lines[] = '';
$parent_lines[] = 'Once form + payment are both received, we’ll send your confirmation email.';
$parent_lines[] = '';
$parent_lines[] = 'If you have any questions, just reply to this email.';
$parent_lines[] = '';
$parent_lines[] = '— The Money Club.Org';
$parent_message = implode("\n", $parent_lines);

if (!smtp_send_mail([$parent_email], $parent_subject, $parent_message, $from, $from)) {
    log_debug('post-registration parent email failed session=' . $preferred_session);
} else {
    log_debug('post-registration parent email sent session=' . $preferred_session);
}

$csv_path = $data_dir . '/apply-camper-submissions.csv';
$csv_headers = [
    'submitted_at',
    'parent_name',
    'parent_email',
    'parent_phone',
    'student_name',
    'student_age',
    'program_track',
    'program_tuition',
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
    $program_label,
    '$' . number_format($program_tuition, 2),
    $session_label,
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

log_debug('redirect success to etransfer.html');
header('Location: etransfer.html?status=sent');
exit;
?>
