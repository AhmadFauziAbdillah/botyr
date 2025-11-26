<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../lib/bot.php';

// Ensure bot is running
ensureBotRunning();

$qrUrl = "http://localhost:3000/qr";

$context = stream_context_create([
    'http' => [
        'timeout' => 3,
        'ignore_errors' => true
    ]
]);

try {
    $response = @file_get_contents($qrUrl, false, $context);
    
    if ($response) {
        http_response_code(200);
        echo $response;
    } else {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'QR not available',
            'hint' => 'Bot might not be ready yet'
        ]);
    }
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

?>
