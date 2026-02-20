<?php
$page_title = 'App Terminal';
$current_page = 'apps';
$serviceId = $_GET['id'] ?? null;

ob_start();

if (!$serviceId) {
    // Show App Selector if no ID provided
    ?>
    <div class="app-manager-container">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i data-lucide="terminal"></i> Select Application for Terminal</div>
            </div>
            <div class="card-body">
                <div id="loading-apps" class="text-muted">Loading applications...</div>
                <div id="app-list" class="services-grid" style="display:none;"></div>
                <div id="no-apps" class="empty-state" style="display:none;">
                    <div class="empty-state-icon"><i data-lucide="box"></i></div>
                    <div class="empty-state-title">No Applications Found</div>
                    <div class="empty-state-text">You need to create an application first.</div>
                    <a href="/apps/overview" class="btn btn-primary">Create Application</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const token = document.querySelector('meta[name="api-token"]')?.getAttribute('content');
            const listEl = document.getElementById('app-list');
            const loadingEl = document.getElementById('loading-apps');
            const noAppsEl = document.getElementById('no-apps');

            try {
                // Fix API path - use base_url variable
                const res = await fetch(`${window.location.origin}${window.base_url || ''}/api/services`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                const data = await res.json();

                loadingEl.style.display = 'none';

                if (data.services && data.services.length > 0) {
                    listEl.style.display = 'grid';
                    listEl.innerHTML = data.services.map(app => `
                    <div class="service-card">
                        <div class="service-card-header">
                            <div class="service-card-icon">
                                ${app.type === 'nodejs'
                        ? '<img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/nodejs/nodejs-original.svg" style="width:24px;height:24px;">'
                        : '<img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/python/python-original.svg" style="width:24px;height:24px;">'}
                            </div>
                            <div class="service-card-info">
                                <div class="service-card-name">${app.name}</div>
                                <div class="service-card-domain">${app.type} ${app.version}</div>
                            </div>
                        </div>
                        <div class="service-card-body">
                            <div class="service-card-meta">
                                <div class="service-card-meta-item">
                                    <div class="service-card-meta-label">Status</div>
                                    <div class="service-card-meta-value">
                                        <span class="badge ${app.status === 'running' ? 'badge-success' : 'badge-danger'}">
                                            ${app.status.toUpperCase()}
                                        </span>
                                    </div>
                                </div>
                                <div class="service-card-meta-item">
                                    <div class="service-card-meta-label">Port</div>
                                    <div class="service-card-meta-value">${app.port}</div>
                                </div>
                            </div>
                            <a href="/apps/terminal?id=${app.id}" class="btn btn-secondary" style="width:100%">
                                <i data-lucide="terminal"></i> Open Terminal
                            </a>
                        </div>
                    </div>
                `).join('');
                    lucide.createIcons();
                } else {
                    noAppsEl.style.display = 'block';
                }
            } catch (e) {
                loadingEl.innerText = 'Error loading applications.';
                console.error(e);
            }
        });
    </script>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../../shared/layouts/main.php';
    exit;
}
?>

<div class="card" style="height: calc(100vh - 140px); display:flex; flex-direction:column;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div class="card-title" style="margin: 0;">
            <i data-lucide="terminal"></i> App Terminal: <?php echo htmlspecialchars($serviceId); ?>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button onclick="window.location.reload()" class="btn btn-sm btn-secondary" title="Reload Terminal">
                <i data-lucide="refresh-cw" style="width:14px; height:14px;"></i> Reload
            </button>
            <span id="term-status" class="badge badge-warning">Initializing...</span>
        </div>
    </div>
    <div class="card-body p-0" style="flex:1; background:#1e1e1e; position:relative; overflow:hidden;">
        <div id="terminal-container" style="width:100%; height:100%;"></div>
    </div>
</div>

<style>
    .xterm-viewport {
        overflow-y: auto !important;
    }
</style>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css" />
<script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xterm-addon-web-links@0.9.0/lib/xterm-addon-web-links.js"></script>

<script>
    // Fix API base URL - remove /public prefix as it's handled by routing
    const apiUrl = (window.base_url || '') + '/api';
    const apiToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content');
    const headers = { 'Content-Type': 'application/json', 'Authorization': `Bearer ${apiToken}` };
    const serviceId = <?= json_encode($serviceId) ?>;

    let term;
    let socket;
    let fitAddon;
    let reconnectTimer;

    document.addEventListener('DOMContentLoaded', async () => {
        if (!serviceId) return;

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
        term.loadAddon(new WebLinksAddon.WebLinksAddon());

        term.open(document.getElementById('terminal-container'));
        fitAddon.fit();

        window.addEventListener('resize', () => {
            fitAddon.fit();
            if (socket && socket.readyState === WebSocket.OPEN) {
                // Send custom resize command
                socket.send(JSON.stringify({ cols: term.cols, rows: term.rows }));
            }
        });

        // Handle Input
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

        updateStatus('Authenticating...', '#ccc');

        try {
            // Get Short-lived Auth Token from Backend
            const res = await fetch(`${apiUrl}/services/${serviceId}/terminal/token`, { headers });
            const data = await res.json();

            if (!res.ok) {
                updateStatus('Auth Failed', '#f48771');
                term.writeln('\x1b[31mAuthentication failed. Please refresh.\x1b[0m');
                return;
            }

            const { token, gateway_url } = data;

            // Connect to Gateway
            updateStatus('Connecting...', '#ccc');

            // Use the gateway URL provided by the API, or fallback to current host
            const wsUrl = gateway_url || `${window.location.protocol === 'https:' ? 'wss:' : 'ws:'}//${window.location.host}/ws`;
            
            // Append token to URL if not already there (gateway might expect it in query or first message)
            const finalWsUrl = wsUrl.includes('?') ? `${wsUrl}&token=${token}` : `${wsUrl}?token=${token}`;

            console.log('Connecting to WebSocket:', finalWsUrl);
            socket = new WebSocket(finalWsUrl);
            socket.binaryType = 'arraybuffer'; // Or blob? Text is usually fine for pty pipes

            socket.onopen = () => {
                updateStatus('Connected', '#4ec9b0');
                // First message: AUTH packet
                socket.send(token);
                // Second message: Initial resize
                socket.send(JSON.stringify({ cols: term.cols, rows: term.rows }));
                term.focus();

                if (reconnectTimer) {
                    clearTimeout(reconnectTimer);
                    reconnectTimer = null;
                }
            };

            socket.onmessage = (event) => {
                // Incoming data from PTY
                if (typeof event.data === 'string') {
                    term.write(event.data);
                } else {
                    // ArrayBuffer?
                    const ENC = new TextDecoder("utf-8");
                    term.write(ENC.decode(event.data));
                }
            };

            socket.onclose = () => {
                updateStatus('Disconnected', '#f48771');
                if (document.visibilityState === 'visible') {
                    term.writeln('\r\n\x1b[33mConnection lost. Reconnecting...\x1b[0m');
                    scheduleReconnect();
                }
            };

            socket.onerror = (e) => {
                console.error('WS Error', e);
                // socket.onclose will fire
            };

        } catch (e) {
            console.error(e);
            updateStatus('Error', '#f48771');
            scheduleReconnect();
        }
    }

    function updateStatus(text, color) {
        const el = document.getElementById('term-status');
        if (el) {
            el.innerText = text;
            el.style.color = color;
        }
    }

    function scheduleReconnect() {
        if (reconnectTimer) return;
        reconnectTimer = setTimeout(() => {
            reconnectTimer = null;
            authenticateAndConnect();
        }, 3000);
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>