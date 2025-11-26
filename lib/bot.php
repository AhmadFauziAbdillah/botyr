<?php

/**
 * Ensure bot daemon is running
 * Starts Node.js bot process if not already running
 */
function ensureBotRunning() {
    $pidFile = '/tmp/bot.pid';
    $logFile = '/tmp/bot.log';
    $running = false;
    
    // Check if bot process is running
    if (file_exists($pidFile)) {
        $pid = trim(file_get_contents($pidFile));
        if (!empty($pid) && shell_exec("ps -p $pid 2>/dev/null")) {
            $running = true;
        }
    }
    
    // Start bot if not running
    if (!$running) {
        $botPath = __DIR__ . '/../bot.js';
        
        if (file_exists($botPath)) {
            // Start Node.js bot in background
            $cmd = "nohup node " . escapeshellarg($botPath) . " > " . escapeshellarg($logFile) . " 2>&1 & echo $!";
            $output = shell_exec($cmd);
            
            if ($output) {
                $pid = trim($output);
                file_put_contents($pidFile, $pid);
                error_log("[Bot] Started bot process with PID: $pid");
            }
            
            // Give it time to start
            sleep(2);
        } else {
            error_log("[Bot] bot.js not found at " . $botPath);
        }
    }
}

/**
 * Get current bot status
 * Connects to localhost:3000 to fetch status
 */
function getBotStatus() {
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
            $data = json_decode($response, true);
            
            if (is_array($data)) {
                return [
                    'connected' => $data['connected'] ?? false,
                    'qr_available' => !empty($data['qrAvailable']),
                    'status' => $data['status'] ?? 'offline',
                    'bot_number' => $data['botNumber'] ?? null,
                    'bot_name' => $data['botName'] ?? null,
                    'uptime' => $data['uptime'] ?? 0
                ];
            }
        }
    } catch (Exception $e) {
        error_log("[Bot] Status fetch error: " . $e->getMessage());
    }
    
    return [
        'connected' => false,
        'qr_available' => false,
        'status' => 'offline',
        'bot_number' => null,
        'bot_name' => null,
        'uptime' => 0
    ];
}

/**
 * Get QR Code from bot
 * Returns QR code string for display
 */
function getQRCode() {
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
            $data = json_decode($response, true);
            
            if ($data && isset($data['qr'])) {
                return $data['qr'];
            }
        }
    } catch (Exception $e) {
        error_log("[Bot] QR fetch error: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Send message via bot API
 */
function sendMessage($phone, $message) {
    $sendUrl = "http://localhost:3000/send-message";
    
    $data = json_encode([
        'phone' => $phone,
        'message' => $message
    ]);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json' . "\r\n" .
                       'Content-Length: ' . strlen($data) . "\r\n",
            'content' => $data,
            'timeout' => 10
        ]
    ]);
    
    try {
        $response = @file_get_contents($sendUrl, false, $context);
        
        if ($response) {
            return json_decode($response, true);
        }
    } catch (Exception $e) {
        error_log("[Bot] Send message error: " . $e->getMessage());
    }
    
    return [
        'success' => false,
        'error' => 'Failed to send message'
    ];
}

/**
 * Clear bot authentication
 */
function clearBotAuth() {
    $clearUrl = "http://localhost:3000/clear-auth";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'timeout' => 5
        ]
    ]);
    
    try {
        $response = @file_get_contents($clearUrl, false, $context);
        
        if ($response) {
            return json_decode($response, true);
        }
    } catch (Exception $e) {
        error_log("[Bot] Clear auth error: " . $e->getMessage());
    }
    
    return [
        'success' => false,
        'error' => 'Failed to clear auth'
    ];
}

/**
 * Get bot logs
 */
function getBotLogs($lines = 50) {
    $logFile = '/tmp/bot.log';
    
    if (!file_exists($logFile)) {
        return [];
    }
    
    $logs = file($logFile, FILE_IGNORE_NEW_LINES);
    return array_slice($logs, -$lines);
}

/**
 * Stop bot process
 */
function stopBot() {
    $pidFile = '/tmp/bot.pid';
    
    if (file_exists($pidFile)) {
        $pid = trim(file_get_contents($pidFile));
        
        if (!empty($pid)) {
            shell_exec("kill -9 $pid 2>/dev/null");
            unlink($pidFile);
            error_log("[Bot] Bot process stopped");
            return true;
        }
    }
    
    return false;
}

?>
