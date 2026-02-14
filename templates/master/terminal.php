<?php
$page_title = 'Root Terminal';
$current_page = 'terminal';
$sidebar_type = 'master';
ob_start();
?>
<!-- XTerm Assets -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css" />
<script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js"></script>

<div class="card" style="height: calc(100vh - 140px); display:flex; flex-direction:column;">
    <div class="card-header justify-between">
        <div class="card-title"><i data-lucide="terminal"></i> Server Terminal (Root)</div>
        <span id="status" class="badge badge-danger">Disconnected</span>
    </div>
    <div class="card-body p-0" style="flex:1; background:#1e1e1e; position:relative; overflow:hidden;">
        <div id="terminal" style="width:100%; height:100%;"></div>
    </div>
</div>

<script>
    const apiUrl = (window.base_url || '') + '/public/api/master';
    // Get token from meta tag or session storage
    const apiToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content') || sessionStorage.getItem('token');

    let term;
    let socket;
    let fitAddon;
    let reconnectTimer;

    document.addEventListener('DOMContentLoaded', async () => {
        initTerminal();
        await authenticateAndConnect();
    });

    function initTerminal() {
        term = new Terminal({
            cursorBlink: true,
            theme: { background: '#1e1e1e', foreground: '#cccccc' },
            fontFamily: 'Menlo, Monaco, "Courier New", monospace',
            fontSize: 14,
            allowProposedApi: true
        });

        fitAddon = new FitAddon.FitAddon();
        term.loadAddon(fitAddon);
        term.open(document.getElementById('terminal'));
        fitAddon.fit();

        window.addEventListener('resize', () => {
            fitAddon.fit();
            if (socket && socket.readyState === WebSocket.OPEN) {
                socket.send(JSON.stringify({ cols: term.cols, rows: term.rows }));
            }
        });

        term.onData(data => {
            if (socket && socket.readyState === WebSocket.OPEN) {
                socket.send(data);
            }
        });
    }

    async function authenticateAndConnect() {
        if (socket) {
            socket.close();
            socket = null;
        }

        updateStatus('Authenticating...', 'badge-warning');

        try {
            // Fetch Token with Authorization Header
            const res = await fetch(`${apiUrl}/settings/terminal/token`, {
                headers: {
                    'Authorization': `Bearer ${apiToken}`
                }
            });
            const data = await res.json();

            if (!res.ok) {
                updateStatus('Auth Failed', 'badge-danger');
                term.writeln('\x1b[31mAuthentication failed: ' + (data.message || 'Unauthorized') + '\x1b[0m');
                return;
            }

            const { token, gateway_url } = data;

            updateStatus('Connecting...', 'badge-warning');

            // Adjust URL for localhost vs remote
            let wsUrl = gateway_url;
            if (window.location.hostname !== 'localhost') {
                const urlObj = new URL(gateway_url);
                urlObj.hostname = window.location.hostname;
                wsUrl = urlObj.toString();
            }

            socket = new WebSocket(wsUrl);
            socket.binaryType = 'arraybuffer';

            socket.onopen = () => {
                updateStatus('Connected', 'badge-success');
                socket.send(token); // Send gateway Auth Token
                socket.send(JSON.stringify({ cols: term.cols, rows: term.rows })); // Initial Resize
                term.focus();
            };

            socket.onmessage = (event) => {
                if (typeof event.data === 'string') {
                    term.write(event.data);
                } else {
                    const ENC = new TextDecoder("utf-8");
                    term.write(ENC.decode(event.data));
                }
            };

            socket.onclose = () => {
                updateStatus('Disconnected', 'badge-danger');
                term.writeln('\r\n\x1b[33mConnection lost. Reconnecting...\x1b[0m');
                setTimeout(authenticateAndConnect, 3000); // Auto reconnect
            };

            socket.onerror = (e) => {
                console.error('WS Error', e);
            };

        } catch (e) {
            console.error(e);
            updateStatus('Error', 'badge-danger');
            setTimeout(authenticateAndConnect, 5000);
        }
    }

    function updateStatus(text, badgeClass) {
        const el = document.getElementById('status');
        if (el) {
            el.innerText = text;
            el.className = 'badge ' + badgeClass;
        }
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../shared/layouts/main.php';
?>