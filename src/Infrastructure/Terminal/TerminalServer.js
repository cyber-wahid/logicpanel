const WebSocket = require('ws');
const os = require('os');
const pty = require('node-pty');
const jwt = require('jsonwebtoken');
const path = require('path');
require('dotenv').config({ path: path.join(__dirname, '../../../../.env') });

const wss = new WebSocket.Server({ port: 5789 });

console.log('Terminal Server started on port 5789');

wss.on('connection', (ws, req) => {
    // Basic Token Validation
    const urlParams = new URLSearchParams(req.url.split('?')[1]);
    const token = urlParams.get('token');

    if (!token) {
        ws.close(1008, 'Token required');
        return;
    }
    
    // Verify JWT token
    try {
        jwt.verify(token, process.env.JWT_SECRET);
    } catch(e) { 
        console.error('Available env:', process.env.JWT_SECRET ? 'YES' : 'NO');
        console.error('Token verification failed:', e.message);
        ws.close(1008, 'Invalid Token'); 
        return; 
    }

    console.log('Client connected with token');

    const shell = os.platform() === 'win32' ? 'powershell.exe' : 'bash';

    // Spawn a new pty process
    const ptyProcess = pty.spawn(shell, [], {
        name: 'xterm-color',
        cols: 80,
        rows: 30,
        cwd: process.env.HOME || process.env.USERPROFILE,
        env: process.env
    });

    // Data from pty -> websocket
    ptyProcess.on('data', (data) => {
        if (ws.readyState === WebSocket.OPEN) {
            ws.send(data);
        }
    });

    // Data from websocket -> pty
    ws.on('message', (message) => {
        ptyProcess.write(message);
    });

    // Resize terminal
    // Expected message format: JSON { type: 'resize', cols: 80, rows: 30 }
    // But since valid xterm input is raw text, we might need a protocol. 
    // For simplicity, we assume raw text is for shell, unless we define a protocol.
    // Standard xterm attach addon sends raw strings.
    // If we want resize, we usually need a specific packet structure or binary protocol.
    // For this MVP, we will stick to basic stream.

    ws.on('close', () => {
        console.log('Client disconnected');
        ptyProcess.kill();
    });
});
