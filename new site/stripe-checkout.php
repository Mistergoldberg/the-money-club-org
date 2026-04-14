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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reserve-a-spot.html');
    exit;
}

$data_dir = get_data_dir();
$log_path = $data_dir . '/stripe-checkout.log';
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

$return_error = isset($_POST['return-error']) ? trim($_POST['return-error']) : 'reserve-a-spot.html';
$allowed_returns = [
    'reserve-a-spot.html',
    'schedule-pricing.html',
    'how-it-works.html',
    'pricing.html',
    'open-book-hook.html',
    'schedule-pricing.html',
    'index.html'
];
if (!in_array($return_error, $allowed_returns, true)) {
    $return_error = 'reserve-a-spot.html';
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

log_debug('stripe-checkout POST program=' . $program_track . ' session=' . $preferred_session . ' payment=' . $payment_method);

if ($parent_name === '') {
    redirect_with_error($return_error, 'parent-name', 'Parent/guardian name is required.');
}

if ($parent_email === '' || !filter_var($parent_email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_error($return_error, 'parent-email', 'Please provide a valid email.');
}

if ($parent_phone === '') {
    redirect_with_error($return_error, 'parent-phone', 'Phone number is required.');
}

$phone_digits = preg_replace('/\D+/', '', $parent_phone);
if ($phone_digits === '' || strlen($phone_digits) < 10 || strlen($phone_digits) > 15) {
    redirect_with_error($return_error, 'parent-phone', 'Please provide a valid phone number.');
}

$age_value = filter_var($student_age, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 10, 'max_range' => 16]
]);
if ($age_value === false) {
    redirect_with_error($return_error, 'student-age', 'Child’s age must be between 10 and 16.');
}

if ($student_name === '') {
    redirect_with_error($return_error, 'student-name', 'Child’s name is required.');
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
    redirect_with_error($return_error, 'program-track', 'Please select a valid program.');
}

if ($preferred_session === '' && $preferred_month !== '') {
    $preferred_session = $preferred_month === 'July' ? 'session1' : 'session2';
}

if (!array_key_exists($preferred_session, $program_options[$program_track]['sessions'])) {
    redirect_with_error($return_error, 'preferred-session', 'Please select a session.');
}

$program_label = $program_options[$program_track]['label'];
$session_label = $program_options[$program_track]['sessions'][$preferred_session];
$program_fee_cents = (int) round(((float)$program_options[$program_track]['tuition']) * 100);

if ($terms_agree === '') {
    redirect_with_error($return_error, 'terms-agree', 'Please agree to the Terms & Payment Policy.');
}

if ($payment_method !== 'Credit Card') {
    redirect_with_error($return_error, 'payment-method', 'Please choose a credit card payment.');
}

$stripe_secret = getenv('STRIPE_SECRET_KEY');
if ($stripe_secret === false || $stripe_secret === '') {
    log_debug('Missing STRIPE_SECRET_KEY env var.');
    redirect_with_error($return_error, 'payment-method', 'Credit card payments are temporarily unavailable. Please use e-Transfer or try again later.');
}

$base_url = base_url();
$success_url = $base_url . '/stripe-success.php?session_id={CHECKOUT_SESSION_ID}';
$cancel_return = isset($_POST['return-cancel']) ? basename(trim($_POST['return-cancel'])) : 'reserve-a-spot.html';
$allowed_returns = [
    'reserve-a-spot.html',
    'schedule-pricing.html',
    'how-it-works.html',
    'pricing.html',
    'open-book-hook.html',
    'schedule-pricing.html',
    'index.html'
];
if (!in_array($cancel_return, $allowed_returns, true)) {
    $cancel_return = 'reserve-a-spot.html';
}
$cancel_url = $base_url . '/' . $cancel_return . '?payment=cancelled';

$hst_cents = (int) round($program_fee_cents * 0.13);
$card_surcharge_cents = (int) round(($program_fee_cents + $hst_cents) * 0.024);

$data = [
    'mode' => 'payment',
    'payment_method_types' => ['card'],
    'success_url' => $success_url,
    'cancel_url' => $cancel_url,
    'customer_email' => $parent_email,
    'client_reference_id' => $preferred_session,
    'line_items' => [
        [
            'price_data' => [
                'currency' => 'cad',
                'product_data' => [
                    'name' => $program_label . ' — ' . $session_label
                ],
                'unit_amount' => $program_fee_cents,
            ],
            'quantity' => 1,
        ],
        [
            'price_data' => [
                'currency' => 'cad',
                'product_data' => [
                    'name' => 'HST (13%)',
                ],
                'unit_amount' => $hst_cents,
            ],
            'quantity' => 1,
        ],
        [
            'price_data' => [
                'currency' => 'cad',
                'product_data' => [
                    'name' => 'Credit Card Processing (2.4%)',
                ],
                'unit_amount' => $card_surcharge_cents,
            ],
            'quantity' => 1,
        ]
    ],
    'metadata' => [
        'parent_name' => $parent_name,
        'parent_email' => $parent_email,
        'parent_phone' => $parent_phone,
        'student_name' => $student_name,
        'student_age' => (string)$student_age,
        'program_track' => $program_track,
        'program_label' => $program_label,
        'program_tuition' => number_format($program_fee_cents / 100, 2, '.', ''),
        'preferred_session' => $preferred_session,
        'session_label' => $session_label,
        'terms_agree' => 'Yes'
    ]
];

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_USERPWD, $stripe_secret . ':');
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($response === false) {
    log_debug('Stripe API error: ' . curl_error($ch));
    curl_close($ch);
    redirect_with_error($return_error, 'payment-method', 'Unable to connect to the payment processor. Please try again.');
}
curl_close($ch);

$result = json_decode($response, true);
if ($status < 200 || $status >= 300 || !isset($result['url'])) {
    $error_message = isset($result['error']['message']) ? $result['error']['message'] : 'Unable to start payment session.';
    log_debug('Stripe API response error: ' . $error_message);
    redirect_with_error($return_error, 'payment-method', $error_message);
}

log_debug('Stripe checkout created session_id=' . ($result['id'] ?? ''));

$to = ['info@the-money-club.org', 'alex@the-money-club.org', 'sarah@the-money-club.org'];
$subject = 'Reserve a Spot (Payment Started): The Money Club.Org';
$from = 'info@the-money-club.org';
$lines = [];
$lines[] = 'Parent/Guardian Name: ' . ($parent_name !== '' ? $parent_name : '(not provided)');
$lines[] = 'Email: ' . $parent_email;
$lines[] = 'Phone: ' . ($parent_phone !== '' ? $parent_phone : '(not provided)');
$lines[] = 'Child Name: ' . ($student_name !== '' ? $student_name : '(not provided)');
$lines[] = 'Child Age: ' . ($student_age !== '' ? $student_age : '(not provided)');
$lines[] = 'Program: ' . $program_label;
$lines[] = 'Session: ' . $session_label;
$lines[] = 'Program tuition: $' . number_format($program_fee_cents / 100, 2) . ' CAD (+HST)';
$lines[] = 'Payment method: Credit Card (Stripe checkout started)';
$lines[] = 'Stripe session id: ' . ($result['id'] ?? '(unknown)');
$lines[] = 'Checkout URL: ' . ($result['url'] ?? '(unknown)');
$lines[] = 'Payment status: Pending (not yet confirmed)';
$message = implode("\n", $lines);

if (!smtp_send_mail($to, $subject, $message, $from, $parent_email)) {
    log_debug('Stripe checkout email failed for session_id=' . ($result['id'] ?? ''));
}

log_debug('Stripe checkout started; parent transactional email deferred until successful payment.');

header('Location: ' . $result['url']);
exit;
?>
