<?php
header('Content-Type: application/json');

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

$defaults = [
    'session1' => 30,
    'session2' => 30
];

$path = get_data_dir() . '/availability.json';
$fp = fopen($path, 'c+');
if (!$fp) {
    echo json_encode([
        'session1' => $defaults['session1'],
        'session2' => $defaults['session2'],
        'capacity' => 30
    ]);
    exit;
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

// Write back normalized data if file was empty or malformed.
rewind($fp);
ftruncate($fp, 0);
fwrite($fp, json_encode($data));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

$response = [
    'session1' => (int)$data['session1'],
    'session2' => (int)$data['session2'],
    'capacity' => 30
];

echo json_encode($response);
