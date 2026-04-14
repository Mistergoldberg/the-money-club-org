<?php
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
    $config = smtp_load_config();
    if ($config === null) {
        throw new RuntimeException(
            'SMTP configuration is missing or invalid. Set SMTP_* env vars or smtp-config.local.php.'
        );
    }

    $host = $config['host'] ?? '';
    $port = $config['port'] ?? 25;
    $username = $config['username'] ?? '';
    $password = $config['password'] ?? '';
    $use_tls = !empty($config['use_tls']);

    $helo = $_SERVER['SERVER_NAME'] ?? 'localhost';

    $sanitize_header = function ($value) {
        return trim(str_replace(["\r", "\n"], '', $value));
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

    $socket = fsockopen($host, $port, $errno, $errstr, 15);
    if (!$socket) {
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
        fclose($socket);
        return false;
    }

    $response = $send_cmd('EHLO ' . $helo);
    if (!$expect_code($response, 250)) {
        fclose($socket);
        return false;
    }

    if ($use_tls && stripos($response, 'STARTTLS') !== false) {
        $response = $send_cmd('STARTTLS');
        if (!$expect_code($response, 220)) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        $response = $send_cmd('EHLO ' . $helo);
        if (!$expect_code($response, 250)) {
            fclose($socket);
            return false;
        }
    }

    if ($username !== '' && $password !== '') {
        $response = $send_cmd('AUTH LOGIN');
        if (!$expect_code($response, 334)) {
            fclose($socket);
            return false;
        }
        $response = $send_cmd(base64_encode($username));
        if (!$expect_code($response, 334)) {
            fclose($socket);
            return false;
        }
        $response = $send_cmd(base64_encode($password));
        if (!$expect_code($response, 235)) {
            fclose($socket);
            return false;
        }
    }

    $response = $send_cmd('MAIL FROM:<' . $from_email . '>');
    if (!$expect_code($response, 250)) {
        fclose($socket);
        return false;
    }

    foreach ($recipients as $recipient) {
        $response = $send_cmd('RCPT TO:<' . $recipient . '>');
        if (!$expect_code($response, 250) && !$expect_code($response, 251)) {
            fclose($socket);
            return false;
        }
    }

    $response = $send_cmd('DATA');
    if (!$expect_code($response, 354)) {
        fclose($socket);
        return false;
    }

    $from_header = $from_name !== '' ? $from_name . ' <' . $from_email . '>' : $from_email;
    $to_header = implode(', ', $recipients);
    $content_type = $is_html ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8';
    $headers = [
        'From: ' . $from_header,
        'Reply-To: ' . $reply_to,
        'To: ' . $to_header,
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: ' . $content_type,
    ];

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $message = preg_replace("/(?<!\r)\n/", "\r\n", $message);
    $message = str_replace("\r\n.", "\r\n..", $message);

    fwrite($socket, $message . "\r\n.\r\n");
    $response = $get_lines();
    if (!$expect_code($response, 250)) {
        fclose($socket);
        return false;
    }

    $send_cmd('QUIT');
    fclose($socket);
    return true;
}
