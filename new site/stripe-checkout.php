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
$preferred_session = isset($_POST['preferred-session']) ? trim($_POST['preferred-session']) : '';
$preferred_month = isset($_POST['preferred-month']) ? trim($_POST['preferred-month']) : '';
$payment_method = isset($_POST['payment-method']) ? trim($_POST['payment-method']) : '';
$terms_agree = isset($_POST['terms-agree']) ? trim($_POST['terms-agree']) : '';

$return_error = isset($_POST['return-error']) ? trim($_POST['return-error']) : 'reserve-a-spot.html';
$allowed_returns = [
    'reserve-a-spot.html',
    'schedule-pricing.html',
    'how-it-works.html',
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

log_debug('stripe-checkout POST email=' . $parent_email . ' phone=' . $parent_phone . ' age=' . $student_age . ' session=' . $preferred_session . ' payment=' . $payment_method);

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
    'options' => ['min_range' => 11, 'max_range' => 17]
]);
if ($age_value === false) {
    redirect_with_error($return_error, 'student-age', 'Child’s age must be between 11 and 17.');
}

if ($student_name === '') {
    redirect_with_error($return_error, 'student-name', 'Child’s name is required.');
}

$session_map = [
    'session1' => 'Session 1: July 6-31, 2026',
    'session2' => 'Session 2: August 4-28, 2026'
];

if ($preferred_session === '' && $preferred_month !== '') {
    $preferred_session = $preferred_month === 'July' ? 'session1' : 'session2';
}

if (!array_key_exists($preferred_session, $session_map)) {
    redirect_with_error($return_error, 'preferred-session', 'Please select a session.');
}

if ($terms_agree === '') {
    redirect_with_error($return_error, 'terms-agree', 'Please agree to the program terms and refund policy.');
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
$success_url = $base_url . '/stripe-success.php?session_id={CHECKOUT_SESSION_ID}&return=thank-you-credit-card.php';
$cancel_return = isset($_POST['return-cancel']) ? basename(trim($_POST['return-cancel'])) : 'reserve-a-spot.html';
$allowed_returns = [
    'reserve-a-spot.html',
    'schedule-pricing.html',
    'how-it-works.html',
    'open-book-hook.html',
    'schedule-pricing.html',
    'index.html'
];
if (!in_array($cancel_return, $allowed_returns, true)) {
    $cancel_return = 'reserve-a-spot.html';
}
$cancel_url = $base_url . '/' . $cancel_return . '?payment=cancelled';

$session_product_map = [
    'session1' => 'prod_U0Kd1JWTTeNs6L',
    'session2' => 'prod_U0KdEfczdJUihI'
];
$surcharge_product_id = 'prod_U0Kd6qahHtdbbU';
$session_product_id = $session_product_map[$preferred_session] ?? null;
if ($session_product_id === null) {
    redirect_with_error($return_error, 'preferred-session', 'Please select a valid session.');
}

$program_fee_cents = 150000;
$hst_cents = (int) round($program_fee_cents * 0.13);
$card_surcharge_cents = 4068;

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
                'product' => $session_product_id,
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
                'product' => $surcharge_product_id,
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
        'preferred_session' => $preferred_session,
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

log_debug('Stripe checkout created session_id=' . ($result['id'] ?? '') . ' email=' . $parent_email);

$to = ['info@the-money-club.org', 'alex@the-money-club.org', 'sarah@the-money-club.org'];
$subject = 'Reserve a Spot (Payment Started): The Money Club.Org';
$from = 'info@the-money-club.org';
$lines = [];
$lines[] = 'Parent/Guardian Name: ' . ($parent_name !== '' ? $parent_name : '(not provided)');
$lines[] = 'Email: ' . $parent_email;
$lines[] = 'Phone: ' . ($parent_phone !== '' ? $parent_phone : '(not provided)');
$lines[] = 'Child Name: ' . ($student_name !== '' ? $student_name : '(not provided)');
$lines[] = 'Child Age: ' . ($student_age !== '' ? $student_age : '(not provided)');
$lines[] = 'Session: ' . ($session_map[$preferred_session] ?? $preferred_session);
$lines[] = 'Payment method: Credit Card (Stripe checkout started)';
$lines[] = 'Stripe session id: ' . ($result['id'] ?? '(unknown)');
$lines[] = 'Checkout URL: ' . ($result['url'] ?? '(unknown)');
$lines[] = 'Payment status: Pending (not yet confirmed)';
$message = implode("\n", $lines);

if (!smtp_send_mail($to, $subject, $message, $from, $parent_email)) {
    log_debug('Stripe checkout email failed for session_id=' . ($result['id'] ?? ''));
}

$parent_subject = 'The Money Club.Org reservation — Payment Instructions';
$credit_card_link = 'https://checkout.stripe.com/c/pay/cs_live_b1RVNM06xWT2MiNRIovexSQB0sDZVqNTSr7LPxKKQUuN4s45FGBTW3Q1Zl#fidnandhYHdWcXxpYCc%2FJ2FgY2RwaXEnKSdkdWxOYHwnPyd1blppbHNgWjA0VnxNN0c2TGJvdzQwYn1MYW5TPXw1PV12VGNMMlI2VUN1UHRcaG9Gck09UXRSdEJ0amw9RDNUdU5XTnB9YGFRM3w3S3NIN2hDYHx9b1dyNklzVFF8a001NTVsaG5LdV9RbCcpJ2N3amhWYHdzYHcnP3F3cGApJ2dkZm5id2pwa2FGamlqdyc%2FJyY1Nz1mPD0nKSdpZHxqcHFRfHVgJz8naHBpcWxabHFgaCcpJ2BrZGdpYFVpZGZgbWppYWB3dic%2FcXdwYHgl';
$safe_parent_name = htmlspecialchars($parent_name !== '' ? $parent_name : 'Parent', ENT_QUOTES, 'UTF-8');
$safe_student_name = htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8');
$safe_student_age = htmlspecialchars((string) $student_age, ENT_QUOTES, 'UTF-8');
$safe_session_label = htmlspecialchars(($session_map[$preferred_session] ?? $preferred_session), ENT_QUOTES, 'UTF-8');
$parent_message = 'Hi ' . $safe_parent_name . ',<br><br>'
    . '<strong>Thanks</strong> — we’ve received your reservation details for The Money Club.<br><br>'
    . '<strong>Student:</strong> ' . $safe_student_name . ' (Age ' . $safe_student_age . ')<br>'
    . '<strong>Session:</strong> ' . $safe_session_label . '<br><br>'
    . "If you've already made payment - thank you for investing in the local economy.<br><br>"
    . 'To confirm your seat, please complete your payment using one of the options below:<br><br>'
    . '<strong>Option 1: Credit Card (instant confirmation)</strong><br>'
    . '<a href="' . $credit_card_link . '">Click here to pay by credit card</a><br><br>'
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
    log_debug('Stripe parent payment instructions email failed for session_id=' . ($result['id'] ?? ''));
} else {
    log_debug('Stripe parent payment instructions email sent to ' . $parent_email);
}

header('Location: ' . $result['url']);
exit;
?>
