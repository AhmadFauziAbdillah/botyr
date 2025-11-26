<?php
header('Content-Type: application/json');

// Get JSON input
$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);

$phone = isset($input['phone']) ? trim($input['phone']) : null;
$message = isset($input['message']) ? trim($input['message']) : null;

// Validate input
if (empty($phone) || empty($message)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Phone dan message diperlukan',
        'received' => [
            'phone' => $phone,
            'message' => $message
        ]
    ]);
    exit;
}

// Validate message length
if (strlen($message) > 4096) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Message terlalu panjang (max 4096 karakter)'
    ]);
    exit;
}

// Load bot helper
require_once __DIR__ . '/../lib/bot.php';

// Ensure bot is running
ensureBotRunning();

// Get status first to verify bot is connected
$status = getBotStatus();

if (!$status['connected']) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'WhatsApp bot belum connect. Scan QR code dulu di dashboard',
        'status' => $status['status']
    ]);
    exit;
}

// Send message via bot API
$botApiUrl = "http://localhost:3000/send-message";

$payload = json_encode([
    'phone' => $phone,
    'message' => $message
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $botApiUrl,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 5
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Log the request
error_log("[Send] To: $phone | Status: $httpCode | Error: $curlError");

if ($curlError) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to send message: ' . $curlError
    ]);
    exit;
}

// Parse response
if ($response) {
    $data = json_decode($response, true);
    
    if ($data && isset($data['success'])) {
        http_response_code($data['success'] ? 200 : 400);
        echo $response;
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Message sent',
            'phone' => $phone
        ]);
    }
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'No response from bot'
    ]);
}

?>
