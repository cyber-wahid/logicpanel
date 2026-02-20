<?php
$page_title = 'Tools';
$current_page = 'dashboard';

// Load system settings
$sysConfig = [];
$configFile = __DIR__ . '/../../../config/settings.json';
if (file_exists($configFile)) {
    $sysConfig = json_decode(file_get_contents($configFile), true);
}

// Get server IP - prioritize config, fallback to auto-detection
$serverIp = $sysConfig['server_ip'] ?? '';

// If server_ip is empty or localhost, try to auto-detect public IP
if (empty($serverIp) || $serverIp === '127.0.0.1' || $serverIp === 'localhost') {
    // 1. Try to get from hostname in URL (best for web panels)
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($currentHost) && !preg_match('/^localhost|127\.0\.0\.1/', $currentHost)) {
        // Try to resolve to IP
        $hostWithoutPort = preg_replace('/:\d+$/', '', $currentHost);
        $resolvedIp = gethostbyname($hostWithoutPort);
        if ($resolvedIp !== $hostWithoutPort && filter_var($resolvedIp, FILTER_VALIDATE_IP)) {
            $serverIp = $resolvedIp;
        }
    }

    // 2. Final fallback - use external service (cached for performance)
    if (empty($serverIp) || $serverIp === '127.0.0.1') {
        $cacheFile = '/tmp/logicpanel_public_ip.txt';
        $cacheTime = 3600; // Cache for 1 hour

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
            $serverIp = trim(file_get_contents($cacheFile));
        } else {
            // Try multiple services for redundancy
            $ipServices = [
                'https://api.ipify.org',
                'https://ifconfig.me/ip',
                'https://icanhazip.com'
            ];

            foreach ($ipServices as $service) {
                $ctx = stream_context_create(['http' => ['timeout' => 2]]);
                $detectedIp = @file_get_contents($service, false, $ctx);
                if ($detectedIp && filter_var(trim($detectedIp), FILTER_VALIDATE_IP)) {
                    $serverIp = trim($detectedIp);
                    @file_put_contents($cacheFile, $serverIp);
                    break;
                }
            }
        }
    }

    // Ultimate fallback
    if (empty($serverIp)) {
        $serverIp = $_SERVER['SERVER_ADDR'] ?? 'Not Configured';
    }
}

$ns1 = $sysConfig['ns1'] ?? '';
$ns2 = $sysConfig['ns2'] ?? '';

ob_start();
?>

<div class="tools-layout">
    <!-- Main Tools Area -->
    <div class="tools-main">

        <!-- Resource Usage Section (First on Desktop, Last on Mobile) -->
        <div class="tool-section-minimal resource-section" data-section="resources">
            <div class="section-header-minimal" onclick="toggleSection('resources')">
                <div class="section-label">RESOURCE USAGE</div>
                <i data-lucide="chevron-up" class="section-toggle-icon"></i>
            </div>
            <div class="section-content-minimal">
                <div class="resource-usage-grid" id="resource-usage-main">
                    <div style="text-align:center; color:var(--text-muted); padding: 30px;">
                        <i data-lucide="loader"
                            style="animation: spin 1s linear infinite; width:20px; height:20px;"></i>
                        <div>Loading usage data...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- General Section -->
        <div class="tool-section-minimal" data-section="general">
            <div class="section-header-minimal" onclick="toggleSection('general')">
                <div class="section-label">Management</div>
                <i data-lucide="chevron-up" class="section-toggle-icon"></i>
            </div>
            <div class="section-content-minimal">
                <div class="tools-grid-minimal">
                    <a href="/apps/overview" class="tool-card-minimal">
                        <div class="tool-icon-minimal">
                            <i data-lucide="server"></i>
                        </div>
                        <span class="tool-name-minimal">Overview</span>
                    </a>
                    <a href="/apps/files" target="_blank" class="tool-card-minimal">
                        <div class="tool-icon-minimal">
                            <i data-lucide="folder"></i>
                        </div>
                        <span class="tool-name-minimal">File Manager</span>
                    </a>
                    <a href="<?= $base_url ?? '' ?>/public/adminer.php" target="_blank" class="tool-card-minimal">
                        <div class="tool-icon-minimal">
                            <i data-lucide="database"></i>
                        </div>
                        <span class="tool-name-minimal">Manage DBs</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Databases Section -->
        <div class="tool-section-minimal" data-section="databases">
            <div class="section-header-minimal" onclick="toggleSection('databases')">
                <div class="section-label">DATABASES</div>
                <i data-lucide="chevron-up" class="section-toggle-icon"></i>
            </div>
            <div class="section-content-minimal">
                <div class="tools-grid-minimal">
                    <a href="<?= $base_url ?? '' ?>/databases/mysql" class="tool-card-minimal">
                        <div class="tool-icon-minimal">
                            <i data-lucide="database"></i>
                        </div>
                        <span class="tool-name-minimal">MySQL</span>
                    </a>
                    <a href="<?= $base_url ?? '' ?>/databases/postgresql" class="tool-card-minimal">
                        <div class="tool-icon-minimal">
                            <i data-lucide="database"></i>
                        </div>
                        <span class="tool-name-minimal">PostgreSQL</span>
                    </a>
                    <a href="<?= $base_url ?? '' ?>/databases/mongodb" class="tool-card-minimal">
                        <div class="tool-icon-minimal">
                            <i data-lucide="database"></i>
                        </div>
                        <span class="tool-name-minimal">MongoDB</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Software Section -->
        <div class="tool-section-minimal" data-section="software">
            <div class="section-header-minimal" onclick="toggleSection('software')">
                <div class="section-label">SOFTWARE</div>
                <i data-lucide="chevron-up" class="section-toggle-icon"></i>
            </div>
            <div class="section-content-minimal">
                <div class="tools-grid-minimal">
                    <a href="<?= $base_url ?? '' ?>/apps/nodejs" class="tool-card-minimal">
                        <div class="tool-icon-minimal">
                            <i data-lucide="hexagon"></i>
                        </div>
                        <span class="tool-name-minimal">Node.js App</span>
                    </a>
                    <a href="<?= $base_url ?? '' ?>/apps/python" class="tool-card-minimal">
                        <div class="tool-icon-minimal">
                            <i data-lucide="codepen"></i>
                        </div>
                        <span class="tool-name-minimal">Python App</span>
                    </a>
                    <a href="<?= $base_url ?? '' ?>/apps/terminal" class="tool-card-minimal">
                        <div class="tool-icon-minimal">
                            <i data-lucide="terminal"></i>
                        </div>
                        <span class="tool-name-minimal">Terminal</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Backup Section -->
        <div class="tool-section-minimal" data-section="backup">
            <div class="section-header-minimal" onclick="toggleSection('backup')">
                <div class="section-label">SPECIAL</div>
                <i data-lucide="chevron-up" class="section-toggle-icon"></i>
            </div>
            <div class="section-content-minimal">
                <div class="tools-grid-minimal">
                    <a href="<?= $base_url ?? '' ?>/backups" class="tool-card-minimal">
                        <div class="tool-icon-minimal">
                            <i data-lucide="archive"></i>
                        </div>
                        <span class="tool-name-minimal">Backup Wizard</span>
                    </a>
                    <a href="<?= $base_url ?? '' ?>/domains" class="tool-card-minimal">
                        <div class="tool-icon-minimal">
                            <i data-lucide="globe"></i>
                        </div>
                        <span class="tool-name-minimal">Domains</span>
                    </a>
                    <a href="<?= $base_url ?? '' ?>/cron" class="tool-card-minimal">
                        <div class="tool-icon-minimal">
                            <i data-lucide="clock"></i>
                        </div>
                        <span class="tool-name-minimal">Cron Jobs</span>
                    </a>
                </div>
            </div>
        </div>

    </div> <!-- End tools-main -->

    <!-- Right Sidebar - General Information -->
    <div class="tools-sidebar">

        <!-- Running Apps List -->
        <div class="info-card" id="running-apps-container" style="display:none;">
            <div class="info-card-header">
                Your Apps <span class="badge badge-primary" id="apps-count" style="margin-left:auto;">0</span>
            </div>
            <div class="info-card-body" id="running-apps-list">
                <!-- Apps loaded via JS -->
            </div>
        </div>

        <!-- Server Info -->
        <div class="info-card">
            <div class="info-card-header">Server Information</div>
            <div class="info-card-body">
                <div class="info-item">
                    <div class="info-label">Server IP Address</div>
                    <div class="info-value">
                        <?= htmlspecialchars($serverIp) ?>
                        <span style="cursor:pointer; margin-left:5px; display:inline-flex; align-items:center;"
                            onclick="copyToClipboardWithAnim('<?= htmlspecialchars($serverIp) ?>', this)">
                            <i data-lucide="copy" style="width:12px; height:12px;"></i>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Nameservers</div>
                    <div class="info-value" style="font-size: 12px; line-height: 1.4;">
                        <div><?= htmlspecialchars($ns1) ?></div>
                        <div><?= htmlspecialchars($ns2) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="info-card">
            <div class="info-card-header">General Information</div>
            <div class="info-card-body">
                <div class="info-item">
                    <div class="info-label">Current User</div>
                    <div class="info-value"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Administrator') ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Select Application Domain</div>
                    <div class="info-value">
                        <select onchange="if(this.value) window.open('http://' + this.value, '_blank')"
                            class="form-control" style="font-size: 13px; padding: 4px 8px; height: 30px;">
                            <option value="">Select & Visit...</option>
                            <?php if (!empty($services) && is_array($services)): ?>
                                <?php foreach ($services as $svc): ?>
                                    <?php if (!empty($svc['domain'])): ?>
                                        <?php
                                        // Handle multiple domains (comma separated)
                                        $domains = explode(',', $svc['domain']);
                                        foreach ($domains as $d):
                                            $d = trim($d);
                                            if (empty($d))
                                                continue;

                                            // Check if it's a full URL or just a domain
                                            $url = strpos($d, 'http') === 0 ? $d : 'http://' . $d;
                                            ?>
                                            <option value="<?= htmlspecialchars($d) ?>">
                                                <?= htmlspecialchars($svc['name']) ?> (<?= htmlspecialchars($d) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Container Status</div>
                    <div class="info-value">
                        <span class="badge badge-success">
                            <span class="badge-dot"></span>
                            <?= $runningCount ?? 0 ?> Running
                        </span>
                        <?php if (($stoppedCount ?? 0) > 0): ?>
                            <span class="badge badge-secondary" style="margin-left: 5px;">
                                <?= $stoppedCount ?> Stopped
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Total Services</div>
                    <div class="info-value"><?= $serviceCount ?? 0 ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Last Login IP</div>
                    <div class="info-value">
                        <?php
                        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                            $clientIp = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
                        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                            $clientIp = $_SERVER['HTTP_X_REAL_IP'];
                        }
                        echo htmlspecialchars($clientIp);
                        ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Server Time</div>
                    <div class="info-value"><?= date('Y-m-d H:i:s') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function createCircularProgress(percent, label, value, color = '#3b82f6', footerText = '') {
        const radius = 40;
        const circumference = 2 * Math.PI * radius;
        const offset = circumference - (percent / 100) * circumference;

        const footer = footerText || `Total Usage <strong>${percent}%</strong>`;

        return `
            <div class="circular-stat-card">
                <div class="circular-title">${label}</div>
                <div class="circular-progress-wrap">
                    <svg class="circular-progress" viewBox="0 0 100 100">
                        <circle class="progress-bg" cx="50" cy="50" r="${radius}"></circle>
                        <circle class="progress-ring" cx="50" cy="50" r="${radius}" 
                            style="stroke-dasharray: ${circumference}; stroke-dashoffset: ${offset}; stroke: ${color};"></circle>
                    </svg>
                    <div class="circular-value">${value}</div>
                </div>
                <div class="circular-footer">
                    <span class="circular-bar" style="background: ${color};"></span>
                    ${footer}
                </div>
            </div>
        `;
    }

    function updateContainerStats() {
        const token = document.querySelector('meta[name="api-token"]')?.content || '<?= $_SESSION['lp_session_token'] ?? '' ?>';

        // Fix API path - remove /public prefix
        fetch('<?= $base_url ?>/api/v1/system/container-stats', {
            headers: { 'Authorization': 'Bearer ' + token }
        })
            .then(r => {
                if (!r.ok) throw new Error('API Error: ' + r.status);
                return r.json();
            })
            .then(data => {
                if (data.error) {
                    showContainerStatsError('Error loading stats');
                    return;
                }

                const mainEl = document.getElementById('resource-usage-main');
                const containers = data.containers || [];

                if (containers.length === 0) {
                    mainEl.innerHTML = `
                        <div style="text-align:center; color:var(--text-muted); padding: 40px;">
                            <i data-lucide="box" style="width:40px; height:40px; margin-bottom:12px;"></i>
                            <div style="font-size:14px;">No running apps</div>
                            <small>Deploy an app to see resource usage</small>
                        </div>
                    `;
                    lucide.createIcons();
                    return;
                }

                // Calculate stats for circular progress
                let cpuPercent = 0, cpuValue = '0%', cpuFooter = '0%';
                let memPercent = 0, memValue = '0 B', memFooter = '0%';
                let diskPercent = 0, diskValue = '0 B', diskFooter = '0%';

                if (data.summary) {
                    const s = data.summary;

                    // CPU
                    if (typeof s.cpu === 'object') {
                        cpuPercent = s.cpu.percent || 0;
                        cpuValue = s.cpu.used;
                        cpuFooter = `${s.cpu.used} / ${s.cpu.limit}`;
                    } else {
                        cpuPercent = parseFloat(s.cpu) || 0;
                        cpuValue = cpuPercent + '%';
                        cpuFooter = `Total Usage ${cpuPercent}%`;
                    }

                    // Memory
                    if (typeof s.memory === 'object') {
                        memPercent = s.memory.percent || 0;
                        memValue = s.memory.used;
                        memFooter = `${s.memory.used} / ${s.memory.limit}`;
                    }

                    // Disk
                    if (typeof s.disk === 'object') {
                        diskPercent = s.disk.percent || 0;
                        diskValue = s.disk.used;
                        diskFooter = `${s.disk.used} / ${s.disk.limit}`;
                    }
                }

                // Create circular progress cards HTML
                let html = '<div class="circular-stats-row">';

                // CPU Card
                html += createCircularProgress(cpuPercent, 'CPU', cpuValue, '#3b82f6', cpuFooter);

                // Memory Card
                html += createCircularProgress(memPercent, 'Memory', memValue, '#3C873A', memFooter);

                // Disk Card
                html += createCircularProgress(diskPercent, 'Disk', diskValue, '#10b981', diskFooter);

                html += '</div>';

                // Apps list in Sidebar
                const sidebarAppsContainer = document.getElementById('running-apps-container');
                const sidebarAppsList = document.getElementById('running-apps-list');

                if (containers.length > 0) {
                    sidebarAppsContainer.style.display = 'block';
                    document.getElementById('apps-count').innerText = containers.length; // Ensure this element exists elsewhere or remove this line if buggy


                    let appsHtml = '';
                    containers.forEach(c => {
                        const icon = c.type === 'nodejs' ? 'hexagon' : (c.type === 'python' ? 'codepen' : 'server');
                        appsHtml += `
                            <div class="app-usage-item">
                                <div class="app-info">
                                    <i data-lucide="${icon}"></i>
                                    <span>${c.name}</span>
                                </div>
                                <div class="app-stats-mini">
                                    <div><i data-lucide="cpu" style="width:10px;"></i> ${c.cpu}</div>
                                    <div><i data-lucide="database" style="width:10px;"></i> ${c.memory}</div>
                                </div>
                            </div>
                        `;
                    });
                    sidebarAppsList.innerHTML = appsHtml;
                } else {
                    sidebarAppsContainer.style.display = 'none';
                }

                html += '</div>'; // End circular-stats-row

                mainEl.innerHTML = html;
                lucide.createIcons();
            })
            .catch(err => {
                console.error('Container stats error:', err);
                showContainerStatsError('Failed to load');
            });
    }

    function showContainerStatsError(msg) {
        document.getElementById('resource-usage-main').innerHTML = `
            <div style="text-align:center; color:var(--text-danger); padding: 30px;">
                <i data-lucide="alert-circle" style="width:28px; height:28px;"></i>
                <div style="margin-top:8px;">${msg}</div>
            </div>
        `;
        lucide.createIcons();
    }

    // Update every 10 seconds
    setInterval(updateContainerStats, 10000);
    // Initial call
    document.addEventListener('DOMContentLoaded', updateContainerStats);
</script>

<style>
    /* Minimal Tool Sections Layout */
    .tools-layout {
        display: grid;
        grid-template-columns: 1fr 280px;
        gap: 20px;
        align-items: start;
    }

    .tools-main {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    /* Minimal Section Style */
    .tool-section-minimal {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 0;
        margin-bottom: 16px;
        transition: box-shadow 0.2s ease;
    }

    .tool-section-minimal:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .section-header-minimal {
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        user-select: none;
        padding: 16px 20px 12px;
        border-bottom: 1px solid var(--border-color);
        background: #F8F9FA;
    }

    [data-theme="dark"] .section-header-minimal {
        background: transparent;
    }

    .tool-section-minimal.collapsed .section-header-minimal {
        padding-bottom: 16px;
        border-bottom: none;
    }

    .section-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-secondary);
    }

    .section-toggle-icon {
        width: 16px;
        height: 16px;
        color: var(--text-muted);
        transition: transform 0.2s;
    }

    .tool-section-minimal.collapsed .section-toggle-icon {
        transform: rotate(180deg);
    }

    .section-content-minimal {
        display: block;
        padding: 16px 20px;
    }

    .tool-section-minimal.collapsed .section-content-minimal {
        display: none;
    }

    /* Minimal Tools Grid */
    .tools-grid-minimal {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 8px;
    }

    .tool-card-minimal {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        text-decoration: none;
        color: var(--text-primary);
        background: var(--bg-content);
        transition: color 0.15s;
    }

    .tool-card-minimal:hover {
        text-decoration: none;
    }

    .tool-card-minimal:hover .tool-name-minimal {
        color: #3C873A;
        text-decoration: underline;
        text-decoration-thickness: 1px;
        text-underline-offset: 2px;
    }

    .tool-card-minimal:hover .tool-icon-minimal svg {
        color: #3C873A;
    }

    .tool-icon-minimal {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .tool-icon-minimal svg {
        width: 22px;
        height: 22px;
        color: var(--text-secondary);
        transition: color 0.15s;
    }

    .tool-name-minimal {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-primary);
        transition: color 0.15s;
    }

    /* Right Sidebar Info Cards */
    .tools-sidebar {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .info-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        overflow: hidden;
    }

    .info-card-header {
        padding: 12px 15px;
        background: var(--bg-input);
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border-color);
    }

    .info-card-body {
        padding: 0;
    }

    .info-item {
        padding: 10px 15px;
        border-bottom: 1px solid var(--border-color);
    }

    .info-item:last-child {
        border-bottom: none;
    }

    .info-label {
        font-size: 11px;
        color: var(--text-muted);
        margin-bottom: 2px;
    }

    .info-value {
        font-size: 13px;
        color: var(--text-primary);
        font-weight: 500;
    }

    .info-value a {
        color: var(--primary);
    }

    .resource-item {
        padding: 10px 15px;
    }

    .resource-header {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        margin-bottom: 6px;
    }

    /* Circular Progress Stats */
    .resource-usage-grid {
        min-height: 80px;
    }

    .circular-stats-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-bottom: 20px;
    }

    .circular-stat-card {
        background: var(--bg-body);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 16px;
        text-align: center;
    }

    .circular-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 12px;
    }

    .circular-progress-wrap {
        position: relative;
        width: 110px;
        height: 110px;
        margin: 0 auto 12px;
    }

    .circular-progress {
        transform: rotate(-90deg);
        width: 100%;
        height: 100%;
    }

    .circular-progress .progress-bg {
        fill: none;
        stroke: var(--border-color);
        stroke-width: 8;
    }

    .circular-progress .progress-ring {
        fill: none;
        stroke-width: 8;
        stroke-linecap: round;
        transition: stroke-dashoffset 0.5s ease;
    }

    .circular-value {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 18px;
        font-weight: 700;
        color: var(--text-primary);
    }

    .circular-footer {
        font-size: 11px;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .circular-footer strong {
        color: var(--text-primary);
    }

    .circular-bar {
        width: 3px;
        height: 12px;
        border-radius: 2px;
    }

    /* Apps Usage List */
    .apps-usage-list {
        background: var(--bg-body);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
    }

    .apps-list-header {
        padding: 10px 15px;
        font-size: 12px;
        font-weight: 600;
        color: var(--text-secondary);
        background: var(--bg-input);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .app-usage-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 15px;
        border-bottom: 1px solid var(--border-color);
    }

    .app-usage-item:last-child {
        border-bottom: none;
    }

    .app-info {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .app-info i {
        width: 16px;
        height: 16px;
        color: var(--text-secondary);
    }

    .app-info span:first-of-type {
        font-weight: 500;
        font-size: 13px;
    }

    .app-stats-mini {
        display: flex;
        flex-direction: column;
        gap: 2px;
        font-size: 10px;
        color: var(--text-muted);
        text-align: right;
    }

    .app-stats-mini div {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 4px;
    }

    /* Resource Section Order */
    .tools-main {
        display: flex;
        flex-direction: column;
    }

    .resource-section {
        order: -1;
        /* First on desktop */
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    /* Mobile Responsive */
    @media (max-width: 991px) {
        .tools-layout {
            grid-template-columns: 1fr;
            padding: 0;
        }

        .tools-main {
            order: 0;
        }

        .resource-section {
            order: 99;
            /* Last on mobile */
        }

        .circular-stats-row {
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .circular-stat-card {
            padding: 10px 8px;
        }

        .circular-title {
            font-size: 11px;
        }

        .circular-progress-wrap {
            width: 70px;
            height: 70px;
        }

        .circular-value {
            font-size: 12px;
        }

        .circular-footer {
            font-size: 9px;
        }

        .tools-sidebar {
            order: 1;
        }

        .tools-grid-minimal {
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        }
    }

    @media (max-width: 640px) {
        .circular-stats-row {
            grid-template-columns: 1fr 1fr 1fr;
        }

        .circular-progress-wrap {
            width: 60px;
            height: 60px;
        }

        .app-stats {
            flex-direction: column;
            gap: 2px;
            align-items: flex-end;
        }

        .app-stats span {
            min-width: auto;
        }

        @media (max-width: 640px) {
            .tools-grid-minimal {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .tool-card-minimal {
                padding: 8px 10px;
            }

            .tool-icon-minimal {
                width: 36px;
                height: 36px;
            }

            .tool-icon-minimal svg {
                width: 20px;
                height: 20px;
            }

            .tool-name-minimal {
                font-size: 13px;
            }
        }
</style>

<script>
    // Toggle section and save state to localStorage
    function toggleSection(sectionName) {
        const section = document.querySelector(`[data-section="${sectionName}"]`);
        if (!section) return;

        section.classList.toggle('collapsed');

        // Save state to localStorage
        const isCollapsed = section.classList.contains('collapsed');
        localStorage.setItem(`section-${sectionName}`, isCollapsed ? 'collapsed' : 'expanded');

        // Re-render icons
        lucide.createIcons();
    }

    // Load saved section states on page load
    document.addEventListener('DOMContentLoaded', () => {
        const sections = ['general', 'databases', 'software'];

        sections.forEach(sectionName => {
            const savedState = localStorage.getItem(`section-${sectionName}`);
            const section = document.querySelector(`[data-section="${sectionName}"]`);

            if (savedState === 'collapsed' && section) {
                section.classList.add('collapsed');
            }
        });

        // Re-render icons after applying states
        lucide.createIcons();
        // Add copy animation function
        window.copyToClipboardWithAnim = function (text, wrapper) {
            if (!navigator.clipboard) return;

            // Prevent re-trigger while animating
            if (wrapper.dataset.animating) return;
            wrapper.dataset.animating = "true";

            navigator.clipboard.writeText(text).then(() => {
                // Switch to check icon
                wrapper.innerHTML = '<i data-lucide="check" style="width:12px; height:12px; color:#10b981;"></i>';
                if (window.lucide) lucide.createIcons();

                setTimeout(() => {
                    // Revert to copy icon
                    wrapper.innerHTML = '<i data-lucide="copy" style="width:12px; height:12px;"></i>';
                    delete wrapper.dataset.animating;
                    if (window.lucide) lucide.createIcons();
                }, 1500);
            }).catch(err => console.error('Failed to copy', err));
        };
    });
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>