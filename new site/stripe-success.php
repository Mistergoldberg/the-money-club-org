<?php
require_once __DIR__ . '/smtp-send.php';
session_start();

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

$data_dir = get_data_dir();
$log_path = $data_dir . '/stripe-checkout.log';
function log_debug($message) {
    $timestamp = date('Y-m-d H:i:s');
    $line = '[' . $timestamp . '] ' . $message . "\n";
    @file_put_contents($GLOBALS['log_path'], $line, FILE_APPEND);
}

$session_id = isset($_GET['session_id']) ? trim($_GET['session_id']) : '';
if ($session_id === '') {
    header('Location: reserve-a-spot.html?payment=cancelled');
    exit;
}

$stripe_secret = getenv('STRIPE_SECRET_KEY');
if ($stripe_secret === false || $stripe_secret === '') {
    log_debug('Missing STRIPE_SECRET_KEY env var.');
    header('Location: reserve-a-spot.html?payment=cancelled');
    exit;
}

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($session_id));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $stripe_secret . ':');
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($response === false) {
    log_debug('Stripe retrieve error: ' . curl_error($ch));
    curl_close($ch);
    header('Location: reserve-a-spot.html?payment=cancelled');
    exit;
}
curl_close($ch);

$session = json_decode($response, true);
if ($status < 200 || $status >= 300 || !is_array($session)) {
    log_debug('Stripe retrieve bad status=' . $status . ' response=' . $response);
    header('Location: reserve-a-spot.html?payment=cancelled');
    exit;
}

$payment_status = $session['payment_status'] ?? '';
if ($payment_status !== 'paid') {
    log_debug('Stripe session not paid: ' . $session_id . ' status=' . $payment_status);
    header('Location: reserve-a-spot.html?payment=cancelled');
    exit;
}

$metadata = isset($session['metadata']) && is_array($session['metadata']) ? $session['metadata'] : [];
$transaction_id = isset($session['payment_intent']) && is_string($session['payment_intent']) && $session['payment_intent'] !== ''
    ? $session['payment_intent']
    : $session_id;
$purchase_currency = strtoupper((string)($session['currency'] ?? 'cad'));
$purchase_value = isset($session['amount_total']) ? round(((int)$session['amount_total']) / 100, 2) : 0.0;
$purchase_tax = null;
if (isset($session['total_details']) && is_array($session['total_details']) && isset($session['total_details']['amount_tax'])) {
    $purchase_tax = round(((int)$session['total_details']['amount_tax']) / 100, 2);
}

$parent_name = trim((string)($metadata['parent_name'] ?? ''));
$parent_email_raw = trim((string)($metadata['parent_email'] ?? ''));
$parent_email = filter_var($parent_email_raw, FILTER_VALIDATE_EMAIL) ? $parent_email_raw : '';
$parent_phone = trim((string)($metadata['parent_phone'] ?? ''));
$student_name = trim((string)($metadata['student_name'] ?? ''));
$student_age = trim((string)($metadata['student_age'] ?? ''));
$program_track = trim((string)($metadata['program_track'] ?? ''));
$program_label = trim((string)($metadata['program_label'] ?? ''));
$program_tuition = trim((string)($metadata['program_tuition'] ?? ''));
$preferred_session = trim((string)($metadata['preferred_session'] ?? ''));
$session_label = trim((string)($metadata['session_label'] ?? ''));
$terms_agree = trim((string)($metadata['terms_agree'] ?? 'Yes'));

$program_options = [
    'two-week-builder-sprint' => [
        'label' => '2-Week Builder Sprint',
        'tuition' => '750.00',
        'sessions' => [
            'session1' => 'July 6-17, 2026',
            'session2' => 'August 4-15, 2026'
        ]
    ],
    'four-week-full-program' => [
        'label' => '4-Week Full Program',
        'tuition' => '1100.00',
        'sessions' => [
            'session1' => 'July 6-31, 2026',
            'session2' => 'August 4-28, 2026'
        ]
    ]
];

if (!array_key_exists($program_track, $program_options)) {
    $program_track = 'four-week-full-program';
}
if ($program_label === '') {
    $program_label = $program_options[$program_track]['label'];
}
if ($program_tuition === '') {
    $program_tuition = $program_options[$program_track]['tuition'];
}
if ($session_label === '' && isset($program_options[$program_track]['sessions'][$preferred_session])) {
    $session_label = $program_options[$program_track]['sessions'][$preferred_session];
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

$reserve_result = ['ok' => true, 'remaining' => 'unknown'];
if (in_array($preferred_session, ['session1', 'session2'], true)) {
    $reserve_result = update_availability($availability_path, $availability_defaults, function (&$data) use ($preferred_session) {
        if ((int)$data[$preferred_session] <= 0) {
            return ['ok' => true, 'remaining' => (int)$data[$preferred_session]];
        }
        $data[$preferred_session] = (int)$data[$preferred_session] - 1;
        return ['ok' => true, 'remaining' => (int)$data[$preferred_session]];
    });
}

$to = ['info@the-money-club.org', 'alex@the-money-club.org', 'sarah@the-money-club.org'];
$subject = 'Reserve a Spot (Paid): The Money Club.Org';
$from = 'info@the-money-club.org';

$lines = [];
$lines[] = 'Parent/Guardian Name: ' . ($parent_name !== '' ? $parent_name : '(not provided)');
$lines[] = 'Email: ' . ($parent_email !== '' ? $parent_email : '(not provided)');
$lines[] = 'Phone: ' . ($parent_phone !== '' ? $parent_phone : '(not provided)');
$lines[] = 'Child Name: ' . ($student_name !== '' ? $student_name : '(not provided)');
$lines[] = 'Child Age: ' . ($student_age !== '' ? $student_age : '(not provided)');
$lines[] = 'Program: ' . ($program_label !== '' ? $program_label : '(not provided)');
$lines[] = 'Session: ' . ($session_label !== '' ? $session_label : $preferred_session);
$lines[] = 'Program tuition: $' . $program_tuition . ' CAD (+HST)';
$lines[] = 'Session spots remaining: ' . (string)$reserve_result['remaining'];
$lines[] = 'Terms agreed: ' . ($terms_agree !== '' ? 'Yes' : 'No');
$lines[] = 'Payment method: Credit Card (Stripe)';
$lines[] = 'Stripe session id: ' . $session_id;
$lines[] = 'Stripe payment status: ' . $payment_status;

$message = implode("\n", $lines);

if (!smtp_send_mail($to, $subject, $message, $from, $parent_email)) {
    log_debug('smtp_send_mail failed for ' . $parent_email . ' session=' . $session_id);
} else {
    log_debug('smtp_send_mail success for ' . $parent_email . ' session=' . $session_id);
}

if ($parent_email !== '') {
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
    $parent_lines[] = 'Session selected: ' . ($session_label !== '' ? $session_label : $preferred_session);
    $parent_lines[] = 'Program fee: $' . $program_tuition . ' CAD (+HST)';
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
        log_debug('post-payment parent email failed for ' . $parent_email . ' session=' . $session_id);
    } else {
        log_debug('post-payment parent email sent to ' . $parent_email . ' session=' . $session_id);
    }
} else {
    log_debug('post-payment parent email skipped: missing valid parent email for session=' . $session_id);
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
    '$' . $program_tuition,
    ($session_label !== '' ? $session_label : $preferred_session),
    ($terms_agree !== '' ? 'Yes' : 'No'),
    'Credit Card (Stripe) - ' . $session_id,
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

$return_page = isset($_GET['return']) ? basename(trim($_GET['return'])) : 'thank-you-credit-card.php';
if ($return_page !== 'thank-you-credit-card.php') {
    $return_page = 'thank-you-credit-card.php';
}
$_SESSION['tmc_verified_purchase'] = [
    'transaction_id' => $transaction_id,
    'currency' => $purchase_currency,
    'value' => $purchase_value,
    'tax' => $purchase_tax,
    'items' => [
        [
            'item_id' => ($program_track !== '' ? $program_track : 'summer-program'),
            'item_name' => 'The Money Club.Org ' . ($program_label !== '' ? $program_label : 'Summer Program'),
            'item_category' => 'Summer Program',
            'price' => (float)$program_tuition,
            'quantity' => 1
        ]
    ]
];
header('Location: ' . base_url() . '/' . $return_page);
exit;
?>
