<?php
header('Content-Type: application/json');

$path = __DIR__ . '/data/availability.json';
$defaults = [
    'session1' => 30,
    'session2' => 30
];

$fp = fopen($path, 'c+');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to read availability.']);
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
