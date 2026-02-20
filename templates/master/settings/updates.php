<?php
$page_title = 'System Updates';
$current_page = 'updates';
$sidebar_type = 'master';
ob_start();
?>

<div class="header-actions"
    style="display: flex; justify-content: flex-end; margin-bottom: 20px; gap: 15px; align-items: center;">
    <span style="color: var(--text-secondary); font-size: 13px;" id="current-version-display">Version:
        Checking...</span>
    <button class="btn btn-primary" onclick="checkForUpdates()">
        <i data-lucide="refresh-cw"></i> Check for Updates
    </button>
</div>

<div class="updates-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <!-- Main Status Card -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i data-lucide="activity"></i> Update Status
            </div>
        </div>
        <div class="card-body"
            style="min-height: 300px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;">

            <!-- Loading State -->
            <div id="update-spinner" style="display: block;">
                <div class="spinner"
                    style="border: 3px solid rgba(0,0,0,0.1); border-left-color: var(--primary); border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px;">
                </div>
                <p style="color: var(--text-secondary);">Checking for updates...</p>
            </div>

            <!-- Message/Error State -->
            <div id="update-message" style="display: none; color: var(--text-secondary); max-width: 80%;"></div>

            <!-- Update Available -->
            <div id="update-available-section" style="display:none; width: 100%; text-align: left;">
                <div
                    style="background: rgba(33, 150, 243, 0.1); color: var(--info); padding: 15px; border-radius: var(--border-radius); margin-bottom: 20px; border-left: 4px solid var(--info);">
                    <h3 style="font-size: 16px; margin-bottom: 5px; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="arrow-up-circle"></i> New Version Available!
                    </h3>
                    <p>Version <strong id="new-version-tag"></strong> is available. Published: <span
                            id="release-date"></span></p>
                </div>

                <div style="background: var(--bg-input); padding: 15px; border-radius: var(--border-radius); margin-bottom: 20px; font-family: monospace; white-space: pre-wrap; color: var(--text-primary); max-height: 300px; overflow-y: auto;"
                    id="release-notes"></div>

                <div style="text-align: center;">
                    <button class="btn btn-primary" style="padding: 10px 20px; font-size: 14px;"
                        onclick="performUpdate()">
                        <i data-lucide="download"></i> Update Now
                    </button>
                </div>
            </div>

            <!-- Up to Date -->
            <div id="up-to-date-section" style="display:none;">
                <div style="color: var(--success); margin-bottom: 15px;">
                    <i data-lucide="check-circle" style="width: 64px; height: 64px;"></i>
                </div>
                <h3 style="color: var(--text-primary); margin-bottom: 5px;">You are up to date!</h3>
                <p style="color: var(--text-secondary);">Current Version: <strong id="current-version"></strong></p>
            </div>

            <!-- In Progress -->
            <div id="update-in-progress" style="display:none; width: 100%; max-width: 400px;">
                <div
                    style="background: var(--bg-input); height: 10px; border-radius: 5px; overflow: hidden; margin-bottom: 15px;">
                    <div
                        style="background: var(--success); width: 50%; height: 100%; animation: progress-indeterminate 2s infinite linear;">
                    </div>
                </div>
                <p style="color: var(--warning); font-weight: 500;">Update in progress...</p>
                <p style="font-size: 12px; color: var(--text-muted);">Please do not close this page.</p>
            </div>

        </div>
    </div>

    <!-- About Card -->
    <div class="card" style="height: fit-content;">
        <div class="card-header">
            <div class="card-title">
                <i data-lucide="info"></i> About LogicPanel
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <ul style="list-style: none; padding: 0; margin: 0;">
                <li
                    style="padding: 12px 15px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: var(--text-secondary);">Current Version</span>
                    <span class="badge badge-secondary" id="sidebar-version">Loading...</span>
                </li>
                <li
                    style="padding: 12px 15px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: var(--text-secondary);">License</span>
                    <span class="badge badge-success">Community Edition</span>
                </li>
                <li style="padding: 12px 15px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: var(--text-secondary);">Channel</span>
                    <span class="badge badge-info">Stable</span>
                </li>
            </ul>
            <div style="padding: 15px; border-top: 1px solid var(--border-color); background: var(--bg-input);">
                <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 15px; line-height: 1.5;">
                    LogicPanel is open-source software. Updates are pulled directly from the official GitHub repository.
                </p>
                <div style="display: grid; gap: 10px;">
                    <a href="https://github.com/cyber-wahid/logicpanel" target="_blank" class="btn btn-secondary"
                        style="justify-content: center;">
                        <i data-lucide="github"></i> GitHub Repository
                    </a>
                    <a href="https://docs.logicdock.cloud" target="_blank" class="btn btn-secondary"
                        style="justify-content: center;">
                        <i data-lucide="book"></i> Documentation
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    @keyframes progress-indeterminate {
        0% {
            margin-left: -50%;
            width: 50%;
        }

        100% {
            margin-left: 100%;
            width: 50%;
        }
    }

    @media (max-width: 900px) {
        .updates-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.lucide) {
            lucide.createIcons();
        }
        checkForUpdates();
    });

    function checkForUpdates() {
        const spinner = document.getElementById('update-spinner');
        const message = document.getElementById('update-message');
        const availableSection = document.getElementById('update-available-section');
        const upToDateSection = document.getElementById('up-to-date-section');
        const inProgressSection = document.getElementById('update-in-progress');

        // Reset State
        if (spinner) spinner.style.display = 'block';
        if (message) message.style.display = 'none';

        availableSection.style.display = 'none';
        upToDateSection.style.display = 'none';
        inProgressSection.style.display = 'none';

        // Use base_url for client-side calls
        const apiUrl = '<?= $base_url ?>/public/api';
        const token = localStorage.getItem('lp_session_token') || '<?= $_SESSION['lp_session_token'] ?? '' ?>';

        fetch(apiUrl + '/master/system/update/check', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            }
        })
            .then(response => {
                // Handle 404/500 gracefully as JSON
                return response.json().catch(() => {
                    throw new Error('Invalid response from server');
                });
            })
            .then(data => {
                if (spinner) spinner.style.display = 'none';

                const ver = data.current_version || '0.0.0';
                if (document.getElementById('current-version-display')) document.getElementById('current-version-display').innerText = 'Version: ' + ver;
                if (document.getElementById('sidebar-version')) document.getElementById('sidebar-version').innerText = ver;
                if (document.getElementById('current-version')) document.getElementById('current-version').innerText = ver;

                if (data.error) {
                    if (message) {
                        message.style.display = 'block';
                        // Simplify 404 error message for users
                        if (data.error.includes('404')) {
                            message.innerText = 'System is up to date (No releases found in repository).';
                            message.style.color = 'var(--text-secondary)';
                            // Show up to date section actually
                            if (upToDateSection) upToDateSection.style.display = 'block';
                            if (message) message.style.display = 'none';
                        } else {
                            message.innerText = 'Error: ' + data.error;
                            message.style.color = 'var(--danger)';
                        }
                    }
                    return;
                }

                if (data.has_update) {
                    availableSection.style.display = 'block';
                    document.getElementById('new-version-tag').innerText = data.latest_version;
                    document.getElementById('release-date').innerText = data.published_at ? new Date(data.published_at).toLocaleDateString() : 'N/A';
                    document.getElementById('release-notes').innerText = data.release_notes || 'No release notes available.';
                } else {
                    upToDateSection.style.display = 'block';
                }
            })
            .catch(err => {
                if (spinner) spinner.style.display = 'none';
                if (message) {
                    message.style.display = 'block';
                    message.innerText = 'Failed to connect to update service. ' + err.message;
                    message.style.color = 'var(--danger)';
                }
                console.error('Update Check Error:', err);
            });
    }

    async function performUpdate() {
        if (!await confirmAction('Are you sure you want to update? The system services may restart during this process.', 'System Update')) return;

        const availableSection = document.getElementById('update-available-section');
        const inProgressSection = document.getElementById('update-in-progress');

        availableSection.style.display = 'none';
        inProgressSection.style.display = 'block';

        const apiUrl = '<?= $base_url ?>/public/api';
        const token = localStorage.getItem('lp_session_token') || '<?= $_SESSION['lp_session_token'] ?? '' ?>';

        fetch(apiUrl + '/master/system/update/perform', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'updating') {
                    alert('Update started! Please wait a few minutes and refresh the page.');
                } else {
                    alert('Update failed to start: ' + (data.message || 'Unknown error'));
                    inProgressSection.style.display = 'none';
                    availableSection.style.display = 'block';
                }
            })
            .catch(err => {
                alert('Error starting update: ' + err.message);
                inProgressSection.style.display = 'none';
                availableSection.style.display = 'block';
            });
    }
</script>

<?php
$content = ob_get_clean();
// Adjust path to layout based on depth
include __DIR__ . '/../../shared/layouts/main.php';
?>