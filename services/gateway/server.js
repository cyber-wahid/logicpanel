const WebSocket = require('ws');
const pty = require('node-pty');
const jwt = require('jsonwebtoken');
const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

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
                    // Root Terminal: Use SSH to jump to host root
                    const hostIp = process.env.HOST_IP || '172.17.0.1';
                    const keyPath = '/app/.ssh/id_rsa';

                    // Diagnostic: Check if SSH binary and Key exist
                    const sshBinary = '/usr/bin/ssh';
                    if (!fs.existsSync(sshBinary)) {
                        ws.send("\r\n\x1b[31m[ERROR] SSH client NOT found in container.\x1b[0m\r\n");
                        ws.send("\x1b[33m[FIX] Please run: \x1b[1mdocker compose up -d --build terminal-gateway\x1b[0m\r\n");
                        ws.send("\x1b[32mFalling back to container shell...\x1b[0m\r\n\r\n");
                        shell = 'bash';
                        args = [];
                    } else if (!fs.existsSync(keyPath)) {
                        ws.send(`\r\n\x1b[31m[ERROR] SSH Key not found at ${keyPath}\r\n`);
                        ws.send("\x1b[32mFalling back to container shell...\x1b[0m\r\n\r\n");
                        shell = 'bash';
                        args = [];
                    } else {
                        shell = sshBinary;
                        args = [
                            '-i', keyPath,
                            '-o', 'StrictHostKeyChecking=no',
                            '-o', 'UserKnownHostsFile=/dev/null',
                            '-o', 'LogLevel=ERROR',
                            `root@${hostIp}`
                        ];
                        console.log(`Spawning host terminal via: ${shell} ${args.join(' ')}`);
                    }
                } else {
                    // User App Terminal: Docker Exec into container
                    const dockerBinary = '/usr/bin/docker';
                    shell = fs.existsSync(dockerBinary) ? dockerBinary : 'docker';
                    args = [
                        'exec', '-it',
                        '-e', 'TERM=xterm-256color',
                        '-w', `/storage/service_${service_id}`,
                        container_id,
                        'sh'
                    ];
                }

                // Spawn PTY
                try {
                    ptyProcess = pty.spawn(shell, args, {
                        name: 'xterm-256color',
                        cols: 80,
                        rows: 24,
                        cwd: process.env.HOME || '/tmp',
                        env: process.env
                    });
                } catch (spawnErr) {
                    ws.send(`\r\n\x1b[31m[FATAL] Failed to spawn ${shell}: ${spawnErr.message}\x1b[0m\r\n`);
                    ws.close();
                    return;
                }

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
                ws.send(`\r\n\x1b[31mAuthentication Failed: ${e.message}\x1b[0m\r\n`);
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
