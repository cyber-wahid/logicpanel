const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const pty = require('node-pty');
const cors = require('cors');
const jwt = require('jsonwebtoken');

const app = express();
app.use(cors());

const server = http.createServer(app);
const io = new Server(server, {
    cors: {
        origin: "*", // Adjust for production security
        methods: ["GET", "POST"]
    }
});

const JWT_SECRET = process.env.JWT_SECRET || 'secret';
const PORT = process.env.PORT || 3002;

// Auth Middleware for Socket.io
io.use((socket, next) => {
    const token = socket.handshake.auth.token;
    if (!token) return next(new Error('Authentication error: Token required'));

    jwt.verify(token, JWT_SECRET, (err, decoded) => {
        if (err) return next(new Error('Authentication error: Invalid token'));
        socket.decoded = decoded; // { service_id: 123, container_id: 'abc...' }
        next();
    });
});

io.on('connection', (socket) => {
    const { container_id, service_id } = socket.decoded;
    console.log(`Client connected: ${socket.id} for container ${container_id}`);

    // Spawn the shell process via docker exec
    // We use 'sh' inside the container.
    // -it is handled by node-pty's pseudo-terminal simulation
    // Command: docker exec -it <container> sh
    
    try {
        const term = pty.spawn('docker', ['exec', '-it', container_id, 'sh'], {
            name: 'xterm-256color',
            cols: 80,
            rows: 24,
            cwd: process.env.HOME,
            env: process.env
        });

        // Send data to client
        term.on('data', (data) => {
            socket.emit('output', data);
        });

        // Receive input from client
        socket.on('input', (data) => {
            term.write(data);
        });

        // Resize terminal
        socket.on('resize', (size) => {
            if (size && size.cols && size.rows) {
                term.resize(size.cols, size.rows);
            }
        });

        // Cleanup on disconnect
        socket.on('disconnect', () => {
            console.log(`Client disconnected: ${socket.id}`);
            term.kill();
        });

        // Cleanup if process exits (e.g. exit command)
        term.on('exit', () => {
             socket.disconnect();
        });

    } catch (e) {
        console.error('Failed to spawn terminal:', e);
        socket.disconnect();
    }
});

server.listen(PORT, () => {
    console.log(`Terminal Gateway running on port ${PORT}`);
});
