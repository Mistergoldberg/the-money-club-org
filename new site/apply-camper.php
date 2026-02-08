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
$preferred_session = isset($_POST['preferred-session']) ? trim($_POST['preferred-session']) : '';
$preferred_month = isset($_POST['preferred-month']) ? trim($_POST['preferred-month']) : '';
$payment_method = isset($_POST['payment-method']) ? trim($_POST['payment-method']) : '';
$notes = isset($_POST['student-notes']) ? trim($_POST['student-notes']) : '';

$return_to = isset($_POST['return-to']) ? trim($_POST['return-to']) : 'reserve-a-spot.html';
$allowed_returns = ['reserve-a-spot.html', 'schedule-pricing.html', 'thank-you.html'];
if (!in_array($return_to, $allowed_returns, true)) {
    $return_to = 'reserve-a-spot.html';
}

$error_return = isset($_POST['return-error']) ? trim($_POST['return-error']) : $return_to;
if (!in_array($error_return, $allowed_returns, true)) {
    $error_return = 'reserve-a-spot.html';
}

function redirect_with_error($return_to, $field, $message) {
    $params = [
        'status' => 'error',
        'field' => $field,
        'message' => $message
    ];
    $separator = (strpos($return_to, '?') === false) ? '?' : '&';
    header('Location: ' . $return_to . $separator . http_build_query($params));
    exit;
}

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

if ($payment_method === '' || !in_array($payment_method, ['Credit Card', 'e-Transfer'], true)) {
    redirect_with_error($error_return, 'payment-method', 'Please choose a payment method.');
}

$availability_path = __DIR__ . '/data/availability.json';
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
    redirect_with_error($error_return, 'preferred-session', 'Selected session is full. Please choose another session or contact us.');
}

$to = ['jared@the-money-club.org', 'sarah@the-money-club.org'];
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
$lines[] = 'Payment method: ' . ($payment_method !== '' ? $payment_method : '(not specified)');
$lines[] = 'Notes: ' . ($notes !== '' ? $notes : '(none)');

$message = implode("\n", $lines);

if (!smtp_send_mail($to, $subject, $message, $from, $parent_email)) {
    update_availability($availability_path, $availability_defaults, function (&$data) use ($preferred_session) {
        $data[$preferred_session] = (int)$data[$preferred_session] + 1;
        return ['ok' => true];
    });
    redirect_with_error($error_return, 'payment-method', 'Unable to send confirmation email. Please try again.');
}

if ($return_to === 'thank-you.html') {
    header('Location: ' . $return_to);
} else {
    header('Location: ' . $return_to . '?status=sent');
}
exit;
?>
