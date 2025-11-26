<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../lib/bot.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST'
    ]);
    exit;
}

// Stop the current bot process
stopBot();

// Wait a moment
sleep(1);

// Start bot again (will generate new QR)
ensureBotRunning();

sleep(2);

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Authentication cleared. Bot restarted.',
    'hint' => 'Refresh dashboard to see new QR code',
    'timestamp' => date('Y-m-d H:i:s')
]);

?>
