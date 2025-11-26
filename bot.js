import { default as makeWASocket, DisconnectReason, useMultiFileAuthState, fetchLatestBaileysVersion } from '@whiskeysockets/baileys';
import express from 'express';
import pino from 'pino';
import qrcode from 'qrcode-terminal';
import fs from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const app = express();
app.use(express.json());

let sock;
let qrGenerated = false;
let isConnected = false;
let currentQR = null;
let connectionAttempts = 0;
const MAX_RETRY_ATTEMPTS = 3;

// Folder untuk auth
const authFolder = join(__dirname, 'auth_info_baileys');
if (!fs.existsSync(authFolder)) {
    fs.mkdirSync(authFolder, { recursive: true });
    console.log('✅ Auth folder created');
}

// Connect to WhatsApp
async function connectToWhatsApp() {
    try {
        connectionAttempts++;
        console.log(`🔄 Connection attempt ${connectionAttempts}/${MAX_RETRY_ATTEMPTS}`);
        
        const { version, isLatest } = await fetchLatestBaileysVersion();
        console.log(`📦 Using Baileys v${version.join('.')}`);
        
        const { state, saveCreds } = await useMultiFileAuthState(authFolder);
        
        sock = makeWASocket({
            version,
            auth: state,
            logger: pino({ level: 'error' }),
            browser: ['Warranty Bot', 'Chrome', '4.0.0'],
            connectTimeoutMs: 60000,
            defaultQueryTimeoutMs: 0,
            keepAliveIntervalMs: 10000,
            emitOwnEvents: true,
            getMessage: async (key) => {
                return { conversation: '' };
            }
        });

        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;
            
            if (qr) {
                console.log('📱 QR Code generated');
                qrcode.generate(qr, { small: true });
                currentQR = qr;
                qrGenerated = true;
                isConnected = false;
                connectionAttempts = 0;
            }
            
            if (connection === 'close') {
                const statusCode = lastDisconnect?.error?.output?.statusCode;
                const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
                
                console.log('❌ Connection closed. Status:', statusCode);
                isConnected = false;
                
                if (statusCode === DisconnectReason.loggedOut) {
                    console.log('🚪 Logged out - clearing auth');
                    currentQR = null;
                    qrGenerated = false;
                    try {
                        const files = fs.readdirSync(authFolder);
                        for (const file of files) {
                            fs.unlinkSync(join(authFolder, file));
                        }
                    } catch (err) {
                        console.error('Error clearing auth:', err);
                    }
                }
                
                if (shouldReconnect && connectionAttempts < MAX_RETRY_ATTEMPTS) {
                    const delay = Math.min(5000 * connectionAttempts, 15000);
                    console.log(`⏳ Reconnecting in ${delay/1000}s...`);
                    setTimeout(() => connectToWhatsApp(), delay);
                } else if (connectionAttempts >= MAX_RETRY_ATTEMPTS) {
                    console.log('❌ Max retry attempts reached');
                    connectionAttempts = 0;
                    setTimeout(() => connectToWhatsApp(), 5000);
                }
            } else if (connection === 'open') {
                console.log('✅ WhatsApp Connected!');
                console.log(`📱 Bot: ${sock.user.id.split(':')[0]}`);
                currentQR = null;
                qrGenerated = false;
                isConnected = true;
                connectionAttempts = 0;
            } else if (connection === 'connecting') {
                console.log('🔄 Connecting...');
            }
        });

        sock.ev.on('creds.update', saveCreds);
        sock.ev.on('messages.upsert', async ({ messages }) => {
            // Handle incoming messages if needed
        });
        
    } catch (error) {
        console.error('❌ Connection error:', error.message);
        
        if (connectionAttempts < MAX_RETRY_ATTEMPTS) {
            setTimeout(() => connectToWhatsApp(), 10000);
        } else {
            connectionAttempts = 0;
        }
    }
}

// Format phone number
function formatPhoneNumber(phone) {
    let formatted = phone.replace(/\D/g, '');
    if (formatted.startsWith('0')) {
        formatted = '62' + formatted.substring(1);
    } else if (!formatted.startsWith('62')) {
        formatted = '62' + formatted;
    }
    return formatted;
}

// Get status
app.get('/status', (req, res) => {
    res.json({
        connected: isConnected,
        status: isConnected ? 'connected' : 'disconnected',
        qrRequired: qrGenerated,
        qrAvailable: !!currentQR,
        botNumber: sock?.user?.id ? sock.user.id.split(':')[0] : null,
        botName: sock?.user?.name || null,
        uptime: Math.floor(process.uptime()),
        timestamp: new Date().toISOString()
    });
});

// Get QR
app.get('/qr', (req, res) => {
    if (currentQR) {
        res.json({
            success: true,
            qr: currentQR,
            message: 'Scan with WhatsApp'
        });
    } else {
        res.json({
            success: false,
            message: isConnected ? 'Already connected' : 'QR not available',
            connected: isConnected
        });
    }
});

// Send message
app.post('/send-message', async (req, res) => {
    const { phone, message } = req.body;
    
    if (!phone || !message) {
        return res.status(400).json({
            success: false,
            error: 'Phone and message required'
        });
    }

    if (!isConnected || !sock) {
        return res.status(503).json({
            success: false,
            error: 'Bot not connected'
        });
    }

    try {
        const formattedPhone = formatPhoneNumber(phone);
        const jid = `${formattedPhone}@s.whatsapp.net`;
        
        // Check if number exists
        const [exists] = await sock.onWhatsApp(jid);
        if (!exists) {
            return res.status(404).json({
                success: false,
                error: 'Phone not registered on WhatsApp',
                phone: formattedPhone
            });
        }
        
        // Send message
        const result = await sock.sendMessage(jid, { text: message });
        
        console.log(`✅ Message sent to ${formattedPhone}`);
        
        res.json({
            success: true,
            message: 'Message sent',
            to: formattedPhone,
            messageId: result.key.id,
            timestamp: new Date().toISOString()
        });
    } catch (error) {
        console.error('❌ Send error:', error);
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

// Clear auth
app.post('/clear-auth', (req, res) => {
    try {
        const files = fs.readdirSync(authFolder);
        for (const file of files) {
            fs.unlinkSync(join(authFolder, file));
        }
        
        currentQR = null;
        qrGenerated = false;
        isConnected = false;
        connectionAttempts = 0;
        
        if (sock) {
            sock.end();
            sock = null;
        }
        
        console.log('✅ Auth cleared, reconnecting...');
        setTimeout(() => connectToWhatsApp(), 2000);
        
        res.json({ 
            success: true, 
            message: 'Auth cleared' 
        });
    } catch (error) {
        res.status(500).json({ 
            success: false, 
            error: error.message 
        });
    }
});

// Health check
app.get('/health', (req, res) => {
    res.json({ 
        status: 'ok',
        connected: isConnected,
        timestamp: new Date().toISOString()
    });
});

// Graceful shutdown
const shutdown = async (signal) => {
    console.log(`⚠️ ${signal} received, shutting down...`);
    if (sock) {
        try {
            await sock.end();
        } catch (err) {
            console.error('Error closing socket:', err);
        }
    }
    process.exit(0);
};

process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));

// Start
const PORT = process.env.PORT || 3000;
const server = app.listen(PORT, '0.0.0.0', () => {
    console.log('='.repeat(50));
    console.log('🚀 WhatsApp Bot Started');
    console.log(`📡 Port: ${PORT}`);
    console.log(`⏰ Time: ${new Date().toLocaleString()}`);
    console.log('='.repeat(50));
    
    connectToWhatsApp();
});

server.on('error', (error) => {
    console.error('❌ Server error:', error);
    process.exit(1);
});