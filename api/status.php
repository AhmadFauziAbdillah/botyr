<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../lib/bot.php';

// Ensure bot is running
ensureBotRunning();

// Get status from bot
$statusUrl = "http://localhost:3000/status";

$context = stream_context_create([
    'http' => [
        'timeout' => 3,
        'ignore_errors' => true
    ]
]);

try {
    $response = @file_get_contents($statusUrl, false, $context);
    
    if ($response) {
        // Return bot status as-is with additional PHP info
        $data = json_decode($response, true);
        
        $data['php_version'] = phpversion();
        $data['server_time'] = date('Y-m-d H:i:s');
        $data['timestamp'] = time();
        
        http_response_code(200);
        echo json_encode($data);
    } else {
        http_response_code(503);
        echo json_encode([
            'connected' => false,
            'status' => 'offline',
            'message' => 'Bot not responding',
            'server_time' => date('Y-m-d H:i:s')
        ]);
    }
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode([
        'connected' => false,
        'status' => 'error',
        'error' => $e->getMessage(),
        'server_time' => date('Y-m-d H:i:s')
    ]);
}

?>
