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
$program_track = isset($_POST['program-track']) ? trim($_POST['program-track']) : '';
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
    'pricing.html',
    'open-book-hook.html',
    'schedule-pricing.html',
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

log_debug('apply-camper POST email=' . $parent_email . ' phone=' . $parent_phone . ' age=' . $student_age . ' program=' . $program_track . ' session=' . $preferred_session . ' payment=' . $payment_method . ' terms=' . ($terms_agree !== '' ? 'yes' : 'no') . ' return=' . $return_to);

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
$lines[] = 'Program: ' . $program_label;
$lines[] = 'Session: ' . $session_label;
$lines[] = 'Program tuition: $' . number_format($program_tuition, 2) . ' CAD (+HST)';
$lines[] = 'Session spots remaining: ' . (string)$reserve_result['remaining'];
$lines[] = 'Terms agreed: ' . ($terms_agree !== '' ? 'Yes' : 'No');
$lines[] = 'Payment method: ' . ($payment_method !== '' ? $payment_method : '(not specified)');
$lines[] = 'Notes: ' . ($notes !== '' ? $notes : '(none)');

$message = implode("\n", $lines);

if (!smtp_send_mail($to, $subject, $message, $from, $parent_email)) {
    log_debug('smtp_send_mail failed for ' . $parent_email);
} else {
    log_debug('smtp_send_mail success for ' . $parent_email . ' session=' . $preferred_session . ' payment=' . $payment_method);
}

$parent_subject = 'You’re in — one final step';
$greeting = $parent_name !== '' ? 'Hi ' . $parent_name . ',' : 'Hi there,';
$parent_approval_link = base_url() . '/parent-approval.html';
$parent_lines = [];
$parent_lines[] = $greeting;
$parent_lines[] = '';
$parent_lines[] = 'Your child’s spot in The Money Club.Org is confirmed.';
$parent_lines[] = '';
$parent_lines[] = 'Thanks for registering.';
$parent_lines[] = '';
$parent_lines[] = '---';
$parent_lines[] = '';
$parent_lines[] = '🧭 One final step';
$parent_lines[] = '';
$parent_lines[] = 'Please complete the parent approval form:';
$parent_lines[] = '';
$parent_lines[] = '👉 ' . $parent_approval_link;
$parent_lines[] = '';
$parent_lines[] = 'This takes 2–3 minutes and helps us confirm safety and contact details.';
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
$parent_lines[] = 'Once the form is submitted, you’re fully set.';
$parent_lines[] = '';
$parent_lines[] = 'If you have any questions, just reply to this email.';
$parent_lines[] = '';
$parent_lines[] = '— The Money Club.Org';
$parent_message = implode("\n", $parent_lines);

if (!smtp_send_mail([$parent_email], $parent_subject, $parent_message, $from, $from)) {
    log_debug('post-registration parent email failed for ' . $parent_email);
} else {
    log_debug('post-registration parent email sent to ' . $parent_email);
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

if ($return_to === 'thank-you.html') {
    log_debug('redirect success to thank-you.html');
    header('Location: ' . $return_to);
} else {
    log_debug('redirect success to return_to=' . $return_to);
    header('Location: ' . $return_to . '?status=sent');
}
exit;
?>
