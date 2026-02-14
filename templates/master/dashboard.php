<?php
$page_title = 'Master Panel';
$current_page = 'dashboard';
$sidebar_type = 'master';
ob_start();
?>

<?php if (($_SESSION['user_role'] ?? 'admin') === 'reseller'): ?>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-card-icon green">
                <i data-lucide="users"></i>
            </div>
            <div class="stat-card-value" id="r-users-stat">Loading...</div>
            <div class="stat-card-label">User Accounts</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon blue">
                <i data-lucide="hard-drive"></i>
            </div>
            <div class="stat-card-value" id="r-disk-stat">Loading...</div>
            <div class="stat-card-label">Disk Allocated</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon orange">
                <i data-lucide="activity"></i>
            </div>
            <div class="stat-card-value" id="r-bw-stat">Loading...</div>
            <div class="stat-card-label">Bandwidth Allocated</div>
        </div>
    </div>
<?php else: ?>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-card-icon green">
                <i data-lucide="cpu"></i>
            </div>
            <div class="stat-card-value" id="cpu-stat">0%</div>
            <div class="stat-card-label">CPU Usage</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon blue">
                <i data-lucide="activity"></i>
            </div>
            <div class="stat-card-value" id="mem-stat">Loading...</div>
            <div class="stat-card-label">Memory Usage</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon orange">
                <i data-lucide="hard-drive"></i>
            </div>
            <div class="stat-card-value" id="disk-stat">Loading...</div>
            <div class="stat-card-label">Disk Usage</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon red">
                <i data-lucide="heart-pulse"></i>
            </div>
            <div class="stat-card-value">Healthy</div>
            <div class="stat-card-label">System Status</div>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-20">
    <div class="card-header">
        <div class="card-title">
            <i data-lucide="user-plus" class="mr-2"></i> Account Functions
        </div>
    </div>
    <div class="card-body">
        <div class="quick-links">
            <a href="accounts/create" class="quick-link">
                <div class="quick-link-icon">
                    <i data-lucide="user-plus"></i>
                </div>
                <div class="quick-link-text">Create Account</div>
            </a>
            <a href="accounts/list" class="quick-link">
                <div class="quick-link-icon">
                    <i data-lucide="users"></i>
                </div>
                <div class="quick-link-text">List Accounts</div>
            </a>
            <a href="packages/list" class="quick-link">
                <div class="quick-link-icon">
                    <i data-lucide="package"></i>
                </div>
                <div class="quick-link-text">Packages</div>
            </a>
            <a href="services/list" class="quick-link">
                <div class="quick-link-icon">
                    <i data-lucide="server"></i>
                </div>
                <div class="quick-link-text">Services</div>
            </a>
            <a href="databases/list" class="quick-link">
                <div class="quick-link-icon">
                    <i data-lucide="database"></i>
                </div>
                <div class="quick-link-text">Databases</div>
            </a>
            <a href="domains/list" class="quick-link">
                <div class="quick-link-icon">
                    <i data-lucide="globe"></i>
                </div>
                <div class="quick-link-text">Domains</div>
            </a>
        </div>
    </div>
</div>

<?php if (($_SESSION['user_role'] ?? 'admin') !== 'reseller'): ?>
    <div class="card mb-20">
        <div class="card-header">
            <div class="card-title">
                <i data-lucide="settings" class="mr-2"></i> Server Configuration
            </div>
        </div>
        <div class="card-body">
            <div class="quick-links">
                <a href="settings/config" class="quick-link">
                    <div class="quick-link-icon">
                        <i data-lucide="sliders"></i>
                    </div>
                    <div class="quick-link-text">Basic Setup</div>
                </a>
                <a href="terminal" class="quick-link">
                    <div class="quick-link-icon">
                        <i data-lucide="terminal"></i>
                    </div>
                    <div class="quick-link-text">Root Terminal</div>
                </a>
                <a href="api-keys/list" class="quick-link">
                    <div class="quick-link-icon">
                        <i data-lucide="key"></i>
                    </div>
                    <div class="quick-link-text">API Access</div>
                </a>
                <a href="settings/updates" class="quick-link">
                    <div class="quick-link-icon">
                        <i data-lucide="refresh-cw"></i>
                    </div>
                    <div class="quick-link-text">Updater</div>
                </a>
            </div>
        </div>
    </div>

    <div class="card mb-20">
        <div class="card-header">
            <div class="card-title">
                <i data-lucide="briefcase" class="mr-2"></i> Reseller Management
            </div>
        </div>
        <div class="card-body">
            <div class="quick-links">
                <a href="packages/create?type=reseller" class="quick-link">
                    <div class="quick-link-icon">
                        <i data-lucide="package-plus"></i>
                    </div>
                    <div class="quick-link-text">Create Reseller Plan</div>
                </a>
                <a href="reseller-packages" class="quick-link">
                    <div class="quick-link-icon">
                        <i data-lucide="package-search"></i>
                    </div>
                    <div class="quick-link-text">Reseller Packages</div>
                </a>
                <a href="resellers" class="quick-link">
                    <div class="quick-link-icon">
                        <i data-lucide="users"></i>
                    </div>
                    <div class="quick-link-text">Reseller Users</div>
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    // Fetch System Stats (Reusing Logic)
    function updateStats() {
        const token = window.apiToken || sessionStorage.getItem('token');
        if (!token) return;

        // Determine if Reseller or Admin based on UI presence
        const isReseller = document.getElementById('r-users-stat') !== null;

        const endpoint = isReseller ? '/public/api/master/reseller/stats' : '/public/api/master/system/stats';

        fetch(endpoint, {
            headers: { 'Authorization': 'Bearer ' + token }
        })
            .then(res => res.json())
            .then(data => {
                if (isReseller) {
                    // Reseller Stats
                    if (data.users) {
                        document.getElementById('r-users-stat').innerText = `${data.users.used} / ${data.users.limit === 0 ? '∞' : data.users.limit}`;
                    }
                    if (data.disk) {
                        document.getElementById('r-disk-stat').innerText = `${data.disk.used} / ${data.disk.limit === 0 ? '∞' : data.disk.limit}`;
                    }
                    if (data.bandwidth) {
                        document.getElementById('r-bw-stat').innerText = `${data.bandwidth.used} / ${data.bandwidth.limit === 0 ? '∞' : data.bandwidth.limit}`;
                    }

                } else {
                    // Admin System Stats
                    // CPU
                    if (document.getElementById('cpu-stat')) {
                        document.getElementById('cpu-stat').innerText = data.cpu + '%';
                    }

                    // Memory
                    const memStat = document.getElementById('mem-stat');
                    if (memStat && data.memory) {
                        if (typeof data.memory === 'string') {
                            memStat.innerText = data.memory;
                        } else if (data.memory.percent) {
                            memStat.innerText = data.memory.percent + '%';
                        }
                    }

                    // Disk
                    const diskStat = document.getElementById('disk-stat');
                    if (diskStat && data.disk) {
                        if (typeof data.disk === 'string') {
                            diskStat.innerText = data.disk;
                        } else if (data.disk.percent) {
                            diskStat.innerText = data.disk.percent + '%';
                        }
                    }
                }
            })
            .catch(e => console.error('Stats Update Error:', e));
    }

    // Run
    updateStats();
    setInterval(updateStats, 5000);
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../shared/layouts/main.php';
?>