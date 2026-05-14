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
    'jul_6_11' => 30,
    'aug_10_14' => 30
];

$path = get_data_dir() . '/availability.json';
$fp = fopen($path, 'c+');
if (!$fp) {
    echo json_encode([
        'jul_6_11' => $defaults['jul_6_11'],
        'aug_10_14' => $defaults['aug_10_14'],
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
    'jul_6_11' => (int)$data['jul_6_11'],
    'aug_10_14' => (int)$data['aug_10_14'],
    'capacity' => 30
];

echo json_encode($response);
