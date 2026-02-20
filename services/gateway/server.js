const WebSocket = require('ws');
const pty = require('node-pty');
const jwt = require('jsonwebtoken');
const { spawn } = require('child_process');

const PORT = 3002;
const JWT_SECRET = process.env.JWT_SECRET || 'default_secret';

const wss = new WebSocket.Server({ port: PORT });
const sessions = new Map();

console.log(`Terminal Gateway Started (VERSION 5.0 - Node.js) on port ${PORT}`);

wss.on('connection', (ws) => {
    console.log('New connection');
    let authenticated = false;
    let ptyProcess = null;

    ws.on('message', (data) => {
        const msg = data.toString();

        if (!authenticated) {
            // First message should be JWT token
            try {
                const decoded = jwt.verify(msg, JWT_SECRET);
                const { container_id, service_id, mode } = decoded;

                console.log(`Auth valid for ${mode === 'root' ? 'ROOT' : 'Service ' + service_id}`);
                authenticated = true;

                let shell = 'sh';
                let args = [];

                if (mode === 'root') {
                     // Root Terminal: Spawn local shell in Gateway container (which has docker socket)
                     shell = 'bash'; 
                     args = [];
                     // Optional: Set specific CWD or env vars for root
                } else {
                     // User App Terminal: Docker Exec into container
                     shell = 'docker';
                     args = [
                        'exec', '-it',
                        '-e', 'TERM=xterm-256color',
                        '-w', `/storage/service_${service_id}`,
                        container_id,
                        'sh'
                    ];
                }

                // Spawn PTY
                ptyProcess = pty.spawn(shell, args, {
                    name: 'xterm-256color',
                    cols: 80,
                    rows: 24,
                    cwd: process.env.HOME,
                    env: process.env
                });

                console.log(`[Service ${service_id}] PTY spawned with PID ${ptyProcess.pid}`);

                ptyProcess.onData((data) => {
                    if (ws.readyState === WebSocket.OPEN) {
                        ws.send(data);
                    }
                });

                ptyProcess.onExit(({ exitCode, signal }) => {
                    console.log(`[Service ${service_id}] PTY exited with code ${exitCode}, signal ${signal}`);
                    if (ws.readyState === WebSocket.OPEN) {
                        ws.close();
                    }
                });

                sessions.set(ws, { pty: ptyProcess, serviceId: service_id });

            } catch (e) {
                console.log('Auth failed:', e.message);
                ws.send('\r\n\x1b[31mAuthentication Failed\x1b[0m\r\n');
                ws.close();
            }
            return;
        }

        // Handle resize
        if (msg.startsWith('{') && msg.includes('"cols"')) {
            try {
                const { cols, rows } = JSON.parse(msg);
                if (ptyProcess && cols && rows) {
                    ptyProcess.resize(cols, rows);
                }
            } catch (e) { }
            return;
        }

        // Forward input to PTY
        if (ptyProcess) {
            ptyProcess.write(msg);
        }
    });

    ws.on('close', () => {
        console.log('Connection closed');
        const session = sessions.get(ws);
        if (session && session.pty) {
            session.pty.kill();
        }
        sessions.delete(ws);
    });

    ws.on('error', (err) => {
        console.log('WebSocket error:', err.message);
    });
});

process.on('SIGTERM', () => {
    console.log('Shutting down...');
    wss.close();
    process.exit(0);
});
