<?php

function tmc_get_data_dir() {
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

function tmc_client_ip() {
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || trim($candidate) === '') {
            continue;
        }

        $first = trim(explode(',', $candidate)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }

    return 'unknown';
}

function tmc_trim_post($key, $max_length = 0) {
    $value = trim((string)($_POST[$key] ?? ''));
    if ($max_length > 0 && strlen($value) > $max_length) {
        return substr($value, 0, $max_length);
    }
    return $value;
}

function tmc_is_valid_email($email) {
    if (!is_string($email)) {
        return false;
    }
    $email = trim($email);
    if ($email === '' || strlen($email) > 254) {
        return false;
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function tmc_phone_digits($value) {
    return preg_replace('/\D+/', '', (string)$value);
}

function tmc_resolve_return_target($candidate, $allowed_returns, $default_return) {
    $candidate = basename(trim((string)$candidate));
    if (in_array($candidate, $allowed_returns, true)) {
        return $candidate;
    }
    return $default_return;
}

function tmc_redirect_with_error($return_to, $field, $message) {
    $params = [
        'status' => 'error',
        'field' => $field,
        'message' => $message
    ];
    $separator = (strpos($return_to, '?') === false) ? '?' : '&';
    header('Location: ' . $return_to . $separator . http_build_query($params));
    exit;
}

function tmc_redirect_with_status($return_to, $status) {
    $separator = (strpos($return_to, '?') === false) ? '?' : '&';
    header('Location: ' . $return_to . $separator . 'status=' . rawurlencode($status));
    exit;
}

function tmc_log_form_security_event($endpoint, $event, $extra = []) {
    $data_dir = tmc_get_data_dir();
    $log_path = $data_dir . '/form-security.log';

    $record = [
        'ts' => gmdate('c'),
        'endpoint' => (string)$endpoint,
        'event' => (string)$event,
        'ip' => tmc_client_ip()
    ];

    foreach ($extra as $key => $value) {
        if (!is_scalar($value)) {
            continue;
        }
        $record[(string)$key] = (string)$value;
    }

    @file_put_contents($log_path, json_encode($record, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
}

function tmc_is_same_origin_request() {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return false;
    }

    $sec_fetch_site = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($sec_fetch_site !== '' && !in_array($sec_fetch_site, ['same-origin', 'same-site', 'none'], true)) {
        return false;
    }

    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '') {
        $origin_host = strtolower((string)parse_url($origin, PHP_URL_HOST));
        return $origin_host !== '' && $origin_host === $host;
    }

    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer !== '') {
        $referer_host = strtolower((string)parse_url($referer, PHP_URL_HOST));
        return $referer_host !== '' && $referer_host === $host;
    }

    return $sec_fetch_site === 'same-origin' || $sec_fetch_site === 'same-site' || $sec_fetch_site === 'none';
}

function tmc_issue_csrf_cookie() {
    $cookie_name = 'tmc_form_csrf';
    $token = (string)($_COOKIE[$cookie_name] ?? '');

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        try {
            $token = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            throw new RuntimeException('Unable to generate CSRF token securely.');
        }
    }

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    setcookie($cookie_name, $token, [
        'expires' => time() + 7200,
        'path' => '/',
        'secure' => $is_https,
        'httponly' => false,
        'samesite' => 'Strict'
    ]);

    $_COOKIE[$cookie_name] = $token;
    return $token;
}

function tmc_verify_csrf_token($allow_same_origin_fallback, &$reason = '') {
    $cookie_token = trim((string)($_COOKIE['tmc_form_csrf'] ?? ''));
    $posted_token = trim((string)($_POST['_csrf'] ?? ''));

    $cookie_valid = preg_match('/^[a-f0-9]{64}$/', $cookie_token) === 1;

    if ($posted_token !== '') {
        $posted_valid = preg_match('/^[a-f0-9]{64}$/', $posted_token) === 1;
        if ($cookie_valid && $posted_valid && hash_equals($cookie_token, $posted_token)) {
            $reason = 'token_ok';
            return true;
        }

        $reason = 'token_mismatch';
        return false;
    }

    if ($allow_same_origin_fallback && tmc_is_same_origin_request()) {
        $reason = $cookie_valid ? 'same_origin_cookie_only' : 'same_origin_no_cookie';
        return true;
    }

    $reason = 'token_missing';
    return false;
}

function tmc_honeypot_triggered() {
    $honeypot_fields = [
        '_tmc_hp',
        '_tmc_website',
        'website',
        'company'
    ];

    foreach ($honeypot_fields as $field) {
        if (!array_key_exists($field, $_POST)) {
            continue;
        }

        $value = trim((string)$_POST[$field]);
        if ($value !== '') {
            return true;
        }
    }

    return false;
}

function tmc_rate_limit_check($endpoint, $max_requests, $window_seconds) {
    $data_dir = tmc_get_data_dir();
    $path = $data_dir . '/form-rate-limit.json';
    $ip = tmc_client_ip();
    $key = $endpoint . '|' . $ip;
    $now = time();
    $window_start = $now - $window_seconds;

    $fp = @fopen($path, 'c+');
    if (!$fp) {
        return [
            'allowed' => false,
            'retry_after' => 60,
            'error' => 'storage_open_failed'
        ];
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return [
            'allowed' => false,
            'retry_after' => 60,
            'error' => 'storage_lock_failed'
        ];
    }

    $raw = stream_get_contents($fp);
    $store = json_decode($raw, true);
    if (!is_array($store)) {
        $store = ['records' => []];
    }
    if (!isset($store['records']) || !is_array($store['records'])) {
        $store['records'] = [];
    }

    foreach ($store['records'] as $record_key => $timestamps) {
        if (!is_array($timestamps)) {
            unset($store['records'][$record_key]);
            continue;
        }

        $filtered = [];
        foreach ($timestamps as $timestamp) {
            $timestamp = (int)$timestamp;
            if ($timestamp >= $window_start) {
                $filtered[] = $timestamp;
            }
        }

        if (empty($filtered)) {
            unset($store['records'][$record_key]);
        } else {
            $store['records'][$record_key] = $filtered;
        }
    }

    $current = $store['records'][$key] ?? [];
    if (!is_array($current)) {
        $current = [];
    }

    $allowed = count($current) < $max_requests;
    $retry_after = 0;

    if ($allowed) {
        $current[] = $now;
        $store['records'][$key] = $current;
    } else {
        $oldest = (int)min($current);
        $retry_after = max(1, ($oldest + $window_seconds) - $now);
    }

    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($store));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return [
        'allowed' => $allowed,
        'retry_after' => $retry_after,
        'error' => null
    ];
}

?>
