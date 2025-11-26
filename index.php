<?php
// Start bot daemon if not running
require_once __DIR__ . '/lib/bot.php';
ensureBotRunning();

// Get bot status
$status = getBotStatus();
$qr = getQRCode();

?>
<!DOCTYPE html>
<html>
<head>
    <title>WhatsApp Bot API - PHP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 2em;
        }
        .status {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: bold;
            margin: 20px 0;
        }
        .connected { background: #10b981; color: white; }
        .disconnected { background: #ef4444; color: white; }
        .connecting { background: #f59e0b; color: white; }
        .info {
            background: #f3f4f6;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .info p {
            margin: 10px 0;
            color: #666;
        }
        .endpoints {
            margin-top: 30px;
        }
        .endpoint {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #667eea;
        }
        .endpoint code {
            background: #e5e7eb;
            padding: 8px;
            border-radius: 4px;
            display: block;
            margin-top: 8px;
            font-size: 0.85em;
            word-break: break-all;
        }
        .qr-section {
            text-align: center;
            margin: 20px 0;
            padding: 25px;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-radius: 15px;
            border: 2px solid #10b981;
        }
        #qrcode {
            margin: 20px auto;
            display: inline-block;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
            margin: 10px 5px;
            transition: all 0.3s;
        }
        button:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .success-box {
            background: #d1fae5;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .warning {
            background: #fef3c7;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }
        .loading {
            background: #fef3c7;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        input, textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            font-size: 1em;
        }
        textarea {
            height: 100px;
            resize: vertical;
        }
        form button {
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 WhatsApp Bot API</h1>
        
        <div class="status <?php echo $status['connected'] ? 'connected' : ($status['qr_available'] ? 'connecting' : 'disconnected'); ?>" id="status">
            <?php 
            if ($status['connected']) echo '✅ Connected';
            elseif ($status['qr_available']) echo '🔄 Waiting for scan';
            else echo '❌ Disconnected';
            ?>
        </div>
        
        <!-- QR Code Section -->
        <?php if (!$status['connected'] && $status['qr_available'] && $qr): ?>
        <div class="qr-section">
            <h3>📱 Scan QR Code dengan WhatsApp</h3>
            <p style="margin: 10px 0; color: #059669; font-weight: 500;">
                Buka WhatsApp → Settings → Linked Devices → Link a Device
            </p>
            <div id="qrcode"></div>
            <div class="success-box">
                ✅ QR Code aktif! Scan sekarang untuk connect.
            </div>
            <button onclick="location.reload()">🔄 Refresh QR</button>
        </div>
        <?php endif; ?>
        
        <!-- Loading State -->
        <?php if (!$status['connected'] && !$status['qr_available']): ?>
        <div class="loading">
            <div class="pulse">
                <h3>⏳ Menunggu QR Code...</h3>
                <p style="margin: 10px 0; color: #92400e;">
                    Sedang connecting ke WhatsApp servers...
                </p>
            </div>
            <div class="warning" style="margin-top: 15px;">
                <strong>⚠️ Jika QR tidak muncul dalam 30 detik:</strong><br>
                <button onclick="clearAuth()" style="margin-top: 10px; background: #ef4444;">
                    🗑️ Clear Auth & Restart
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Connected State -->
        <?php if ($status['connected']): ?>
        <div class="success-box">
            <strong>🎉 Bot berhasil terhubung!</strong>
            <p style="margin-top: 8px;">Sekarang kamu bisa mengirim pesan via API</p>
        </div>
        <?php endif; ?>
        
        <!-- Info Box -->
        <div class="info">
            <p><strong>📡 Server:</strong> <span style="color: #10b981;">● Online</span></p>
            <p><strong>🔗 Bot Number:</strong> <?php echo $status['bot_number'] ?? 'Not connected'; ?></p>
            <p><strong>📛 Bot Name:</strong> <?php echo $status['bot_name'] ?? 'N/A'; ?></p>
            <p><strong>🔒 Status:</strong> <?php echo ucfirst($status['status']); ?></p>
            <p><strong>⏰ Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        
        <!-- API Documentation -->
        <div class="endpoints">
            <h3 style="margin-bottom: 15px;">📚 Available Endpoints</h3>
            
            <div class="endpoint">
                <strong>POST /api/send-message.php</strong>
                <p style="margin: 8px 0; color: #666;">Send text message to WhatsApp</p>
                <code>{ "phone": "6281234567890", "message": "Hello!" }</code>
            </div>
            
            <div class="endpoint">
                <strong>GET /api/status.php</strong>
                <p style="margin: 8px 0; color: #666;">Get bot status</p>
            </div>
            
            <div class="endpoint">
                <strong>GET /api/qr.php</strong>
                <p style="margin: 8px 0; color: #666;">Get QR code data</p>
            </div>
            
            <div class="endpoint">
                <strong>POST /api/clear-auth.php</strong>
                <p style="margin: 8px 0; color: #666;">Clear authentication</p>
            </div>
        </div>
        
        <!-- Message Sender -->
        <div class="endpoints" style="margin-top: 30px;">
            <h3 style="margin-bottom: 15px;">💬 Send Message Test</h3>
            <form id="messageForm">
                <input type="text" id="phone" placeholder="Phone: 6281234567890" required>
                <textarea id="message" placeholder="Message..." required></textarea>
                <button type="submit">📤 Send Message</button>
            </form>
            <div id="messageResult"></div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <script>
        // Render QR Code
        <?php if ($qr): ?>
        try {
            const qrContainer = document.getElementById('qrcode');
            if (qrContainer && qrContainer.children.length === 0) {
                new QRCode(qrContainer, {
                    text: '<?php echo addslashes($qr); ?>',
                    width: 280,
                    height: 280,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        } catch (error) {
            console.error('QR Error:', error);
        }
        <?php endif; ?>
        
        // Clear auth function
        function clearAuth() {
            if (confirm('Clear authentication dan restart bot?')) {
                fetch('/api/clear-auth.php', { method: 'POST' })
                    .then(r => r.json())
                    .then(data => {
                        alert(data.message || 'Auth cleared');
                        setTimeout(() => window.location.reload(), 2000);
                    })
                    .catch(err => alert('Error: ' + err));
            }
        }
        
        // Send message handler
        document.getElementById('messageForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const phone = document.getElementById('phone').value;
            const message = document.getElementById('message').value;
            const resultDiv = document.getElementById('messageResult');
            
            resultDiv.innerHTML = '<div class="loading">Sending...</div>';
            
            try {
                const response = await fetch('/api/send-message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phone, message })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = '<div class="success-box">✅ ' + data.message + '</div>';
                    document.getElementById('phone').value = '';
                    document.getElementById('message').value = '';
                } else {
                    resultDiv.innerHTML = '<div class="warning">❌ ' + data.error + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="warning">❌ Error: ' + error.message + '</div>';
            }
        });
        
        // Auto refresh status setiap 5 detik
        setInterval(async () => {
            try {
                const response = await fetch('/api/status.php');
                const data = await response.json();
                
                if (data.connected && document.getElementById('status').classList.contains('disconnected')) {
                    window.location.reload();
                }
            } catch (error) {
                console.error('Status check failed');
            }
        }, 5000);
    </script>
</body>
</html>