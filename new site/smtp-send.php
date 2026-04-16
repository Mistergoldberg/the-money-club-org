<?php
function smtp_set_last_error($message) {
    $GLOBALS['tmc_smtp_last_error'] = (string)$message;
}

function smtp_get_last_error() {
    return isset($GLOBALS['tmc_smtp_last_error']) ? (string)$GLOBALS['tmc_smtp_last_error'] : '';
}

function smtp_log_failure($stage, $context = []) {
    smtp_set_last_error($stage);
    $parts = ['[smtp]', $stage];
    foreach ($context as $key => $value) {
        if (!is_scalar($value)) {
            continue;
        }
        $clean = preg_replace('/[\r\n\t]+/', ' ', (string)$value);
        $parts[] = $key . '=' . $clean;
    }
    error_log(implode(' ', $parts));
}

function smtp_load_config() {
    try {
        $config = require __DIR__ . '/smtp-config.php';
    } catch (Throwable $e) {
        error_log('[smtp] Configuration load failed: ' . $e->getMessage());
        return null;
    }

    if (!is_array($config)) {
        error_log('[smtp] Configuration must be an array.');
        return null;
    }

    $required = ['host', 'port', 'username', 'password', 'use_tls'];
    foreach ($required as $key) {
        if (!array_key_exists($key, $config)) {
            error_log('[smtp] Missing required configuration key: ' . $key);
            return null;
        }
    }

    if (!is_string($config['host']) || trim($config['host']) === '') {
        error_log('[smtp] Invalid SMTP host configuration.');
        return null;
    }
    if (!is_numeric($config['port']) || (int)$config['port'] < 1 || (int)$config['port'] > 65535) {
        error_log('[smtp] Invalid SMTP port configuration.');
        return null;
    }
    if (!is_string($config['username']) || trim($config['username']) === '') {
        error_log('[smtp] Invalid SMTP username configuration.');
        return null;
    }
    if (!is_string($config['password']) || $config['password'] === '') {
        error_log('[smtp] Invalid SMTP password configuration.');
        return null;
    }

    return $config;
}

function smtp_send_mail($to, $subject, $body, $from_email, $reply_to, $from_name = 'The Money Club.Org', $is_html = false) {
    smtp_set_last_error('');
    $config = smtp_load_config();
    if ($config === null) {
        error_log('[smtp] Sending skipped: SMTP configuration is missing or invalid.');
        smtp_set_last_error('config_invalid');
        return false;
    }

    $host = $config['host'] ?? '';
    $port = $config['port'] ?? 25;
    $username = $config['username'] ?? '';
    $password = $config['password'] ?? '';
    $use_tls = !empty($config['use_tls']);
    $is_implicit_tls = $use_tls && (int)$port === 465;
    $transport_host = $is_implicit_tls ? ('ssl://' . $host) : $host;

    $helo = $_SERVER['SERVER_NAME'] ?? 'localhost';

    $sanitize_header = function ($value) {
        return trim(str_replace(["\r", "\n"], '', $value));
    };
    $is_ascii_header = function ($value) {
        return preg_match('/^[\x20-\x7E]*$/', $value) === 1;
    };
    $encode_header = function ($value) use ($is_ascii_header) {
        if ($value === '' || $is_ascii_header($value)) {
            return $value;
        }
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    };

    $recipients = is_array($to) ? $to : preg_split('/\s*[;,]\s*/', (string) $to);
    $recipients = array_values(array_filter(array_map($sanitize_header, $recipients)));
    if (!$recipients) {
        return false;
    }
    $subject = $sanitize_header($subject);
    $from_email = $sanitize_header($from_email);
    $reply_to = $sanitize_header($reply_to);
    $from_name = $sanitize_header($from_name);
    $subject_header = $encode_header($subject);
    $from_name_header = $encode_header($from_name);

    $socket = fsockopen($transport_host, $port, $errno, $errstr, 15);
    if (!$socket) {
        smtp_log_failure('socket_open_failed', [
            'host' => $host,
            'port' => $port,
            'errno' => $errno,
            'error' => $errstr
        ]);
        return false;
    }

    stream_set_timeout($socket, 15);

    $get_lines = function () use ($socket) {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
        return $data;
    };

    $send_cmd = function ($command) use ($socket, $get_lines) {
        fwrite($socket, $command . "\r\n");
        return $get_lines();
    };

    $expect_code = function ($response, $code) {
        return strpos($response, (string) $code) === 0;
    };

    $response = $get_lines();
    if (!$expect_code($response, 220)) {
        smtp_log_failure('smtp_banner_unexpected', ['response' => trim($response)]);
        fclose($socket);
        return false;
    }

    $response = $send_cmd('EHLO ' . $helo);
    if (!$expect_code($response, 250)) {
        smtp_log_failure('ehlo_failed', ['response' => trim($response)]);
        fclose($socket);
        return false;
    }

    if ($use_tls && !$is_implicit_tls) {
        if (stripos($response, 'STARTTLS') !== false) {
            $response = $send_cmd('STARTTLS');
            if (!$expect_code($response, 220)) {
                smtp_log_failure('starttls_command_failed', ['response' => trim($response)]);
                fclose($socket);
                return false;
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                smtp_log_failure('starttls_handshake_failed', ['host' => $host, 'port' => $port]);
                fclose($socket);
                return false;
            }
            $response = $send_cmd('EHLO ' . $helo);
            if (!$expect_code($response, 250)) {
                smtp_log_failure('ehlo_after_starttls_failed', ['response' => trim($response)]);
                fclose($socket);
                return false;
            }
        } else {
            smtp_log_failure('starttls_not_advertised', ['host' => $host, 'port' => $port]);
        }
    }

    if ($username !== '' && $password !== '') {
        $response = $send_cmd('AUTH LOGIN');
        if (!$expect_code($response, 334)) {
            smtp_log_failure('auth_login_not_accepted', ['response' => trim($response)]);
            fclose($socket);
            return false;
        }
        $response = $send_cmd(base64_encode($username));
        if (!$expect_code($response, 334)) {
            smtp_log_failure('auth_username_rejected', ['response' => trim($response)]);
            fclose($socket);
            return false;
        }
        $response = $send_cmd(base64_encode($password));
        if (!$expect_code($response, 235)) {
            smtp_log_failure('auth_password_rejected', ['response' => trim($response)]);
            fclose($socket);
            return false;
        }
    }

    $mail_from_candidates = [$from_email];
    if (filter_var($username, FILTER_VALIDATE_EMAIL) && strcasecmp($username, $from_email) !== 0) {
        $mail_from_candidates[] = $username;
    }

    $mail_from_ok = false;
    $mail_from_response = '';
    foreach ($mail_from_candidates as $candidate_from) {
        $response = $send_cmd('MAIL FROM:<' . $candidate_from . '>');
        if ($expect_code($response, 250)) {
            $mail_from_ok = true;
            if (strcasecmp($candidate_from, $from_email) !== 0) {
                smtp_log_failure('mail_from_fallback_used', [
                    'requested_from' => $from_email,
                    'fallback_from' => $candidate_from
                ]);
            }
            break;
        }
        $mail_from_response = trim($response);
    }

    if (!$mail_from_ok) {
        smtp_log_failure('mail_from_failed', ['response' => $mail_from_response]);
        fclose($socket);
        return false;
    }

    foreach ($recipients as $recipient) {
        $response = $send_cmd('RCPT TO:<' . $recipient . '>');
        if (!$expect_code($response, 250) && !$expect_code($response, 251)) {
            smtp_log_failure('rcpt_to_failed', ['recipient' => $recipient, 'response' => trim($response)]);
            fclose($socket);
            return false;
        }
    }

    $response = $send_cmd('DATA');
    if (!$expect_code($response, 354)) {
        smtp_log_failure('data_command_failed', ['response' => trim($response)]);
        fclose($socket);
        return false;
    }

    $from_header = $from_name_header !== '' ? $from_name_header . ' <' . $from_email . '>' : $from_email;
    $to_header = implode(', ', $recipients);
    $content_type = $is_html ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8';
    $headers = [
        'From: ' . $from_header,
        'Reply-To: ' . $reply_to,
        'To: ' . $to_header,
        'Subject: ' . $subject_header,
        'MIME-Version: 1.0',
        'Content-Type: ' . $content_type,
    ];

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $message = preg_replace("/(?<!\r)\n/", "\r\n", $message);
    $message = str_replace("\r\n.", "\r\n..", $message);

    fwrite($socket, $message . "\r\n.\r\n");
    $response = $get_lines();
    if (!$expect_code($response, 250)) {
        smtp_log_failure('message_rejected', ['response' => trim($response)]);
        fclose($socket);
        return false;
    }

    $send_cmd('QUIT');
    fclose($socket);
    smtp_set_last_error('');
    return true;
}
