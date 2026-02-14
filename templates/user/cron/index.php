<?php
$page_title = 'Cron Jobs';
ob_start();
?>

<div class="db-container">
    <div class="db-page-header">
        <h1>Cron Jobs</h1>
        <p>Automate repetitive tasks directly within your application containers.</p>
    </div>

    <!-- Add New Job Section -->
    <div class="db-section">
        <div class="db-section-header">
            <h3>Add New Cron Job</h3>
        </div>
        <div class="db-section-body">
            <form id="addCronForm" onsubmit="createCronJob(event)">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Application</label>
                        <select id="serviceSelect" class="form-control" required>
                            <option value="">Loading applications...</option>
                        </select>
                        <small class="form-text text-muted">Select the container where the command will run.</small>
                    </div>

                    <div class="form-group col-md-4">
                        <label>Common Settings</label>
                        <select id="commonSettings" class="form-control" onchange="applyCommonSetting()">
                            <option value="">-- Select Common Setting --</option>
                            <option value="* * * * *">Every Minute (* * * * *)</option>
                            <option value="*/5 * * * *">Every 5 Minutes (*/5 * * * *)</option>
                            <option value="0,30 * * * *">Twice Per Hour (0,30 * * * *)</option>
                            <option value="0 * * * *">Once Per Hour (0 * * * *)</option>
                            <option value="0 0 * * *">Once Per Day (0 0 * * *)</option>
                            <option value="0 0 * * 0">Once Per Week (0 0 * * 0)</option>
                            <option value="0 0 1 * *">Once Per Month (0 0 1 * *)</option>
                            <option value="0 0 1 1 *">Once Per Year (0 0 1 1 *)</option>
                        </select>
                    </div>

                    <div class="form-group col-md-4">
                        <label>Cron Schedule</label>
                        <input type="text" id="cronSchedule" class="form-control" placeholder="* * * * *" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Command (Full Path)</label>
                    <input type="text" id="cronCommand" class="form-control"
                        placeholder="php /var/www/html/artisan schedule:run" required>
                    <small class="form-text text-muted">Enter the command as you would run it in the terminal.</small>
                </div>

                <div class="form-actions text-right">
                    <button type="submit" class="btn btn-primary" id="addBtn">
                        <i data-lucide="plus"></i> Add Cron Job
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Jobs Section -->
    <div class="db-section mt-4">
        <div class="db-section-header">
            <h3>Current Cron Jobs</h3>
        </div>
        <div class="table-responsive">
            <table class="db-table">
                <thead>
                    <tr>
                        <th>Application</th>
                        <th>Schedule</th>
                        <th>Command</th>
                        <th>Last Run</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="cronList">
                    <tr>
                        <td colspan="5" class="text-center p-4">Loading cron jobs...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Help Section -->
    <div class="db-section mt-4">
        <div class="db-section-header">
            <h3><i data-lucide="book-open" style="width:18px;height:18px;vertical-align:middle;margin-right:8px;"></i>
                How to Use Cron Jobs</h3>
        </div>
        <div class="db-section-body">
            <div class="help-grid">
                <!-- Schedule Examples -->
                <div class="help-card">
                    <h4><i data-lucide="clock"
                            style="width:16px;height:16px;vertical-align:middle;margin-right:6px;color:#3C873A;"></i>
                        Schedule Format</h4>
                    <p>Cron uses 5 fields: <code>minute hour day month weekday</code></p>
                    <div class="cron-visual">
                        <span>*</span><span>*</span><span>*</span><span>*</span><span>*</span>
                        <div class="cron-labels">
                            <small>min</small><small>hour</small><small>day</small><small>month</small><small>weekday</small>
                        </div>
                    </div>
                    <table class="help-table">
                        <tr>
                            <td><code>* * * * *</code></td>
                            <td>Every minute</td>
                        </tr>
                        <tr>
                            <td><code>*/5 * * * *</code></td>
                            <td>Every 5 minutes</td>
                        </tr>
                        <tr>
                            <td><code>0 * * * *</code></td>
                            <td>Every hour (at minute 0)</td>
                        </tr>
                        <tr>
                            <td><code>0 0 * * *</code></td>
                            <td>Every day at midnight</td>
                        </tr>
                        <tr>
                            <td><code>0 9 * * *</code></td>
                            <td>Every day at 9:00 AM</td>
                        </tr>
                        <tr>
                            <td><code>0 0 * * 0</code></td>
                            <td>Every Sunday at midnight</td>
                        </tr>
                        <tr>
                            <td><code>0 0 1 * *</code></td>
                            <td>First day of every month</td>
                        </tr>
                    </table>
                </div>

                <!-- Command Examples -->
                <div class="help-card">
                    <h4><i data-lucide="terminal"
                            style="width:16px;height:16px;vertical-align:middle;margin-right:6px;color:#3C873A;"></i>
                        Command Examples</h4>
                    <p>Commands run inside your application container at <code>/storage/your_app/</code></p>

                    <div class="example-group">
                        <h5><i data-lucide="hexagon"
                                style="width:14px;height:14px;vertical-align:middle;margin-right:4px;color:#68a063;"></i>
                            Node.js Apps</h5>
                        <code class="block">node /storage/your_app/scripts/cleanup.js</code>
                        <code class="block">node /storage/your_app/cron/send-emails.js</code>
                        <code class="block">cd /storage/your_app && npm run task</code>
                    </div>

                    <div class="example-group">
                        <h5><i data-lucide="code"
                                style="width:14px;height:14px;vertical-align:middle;margin-right:4px;color:#3776ab;"></i>
                            Python Apps</h5>
                        <code class="block">python /storage/your_app/scripts/backup.py</code>
                        <code class="block">python /storage/your_app/manage.py cleanup</code>
                        <code class="block">cd /storage/your_app && python cron_task.py</code>
                    </div>
                </div>

                <!-- Tips -->
                <div class="help-card tips-card">
                    <h4><i data-lucide="lightbulb"
                            style="width:16px;height:16px;vertical-align:middle;margin-right:6px;color:#f59e0b;"></i>
                        Tips</h4>
                    <ul>
                        <li><strong>Test First:</strong> Use "Run Now" button to test your command before scheduling
                        </li>
                        <li><strong>Full Path:</strong> Always use full paths like
                            <code>/storage/your_app/script.js</code>
                        </li>
                        <li><strong>Logs:</strong> Check "Last Run" to see if your job executed successfully</li>
                        <li><strong>Timezone:</strong> All times are in server timezone (UTC)</li>
                        <li><strong>Errors:</strong> If a job fails, the output will be saved in "Last Result"</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .db-container {
        padding: 0 15px;
    }

    .db-page-header h1 {
        font-size: 24px;
        font-weight: 600;
        margin: 0 0 5px 0;
    }

    .db-page-header p {
        color: var(--text-secondary);
        font-size: 14px;
        margin-bottom: 20px;
    }

    .db-section {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 4px;
        overflow: hidden;
    }

    .db-section-header {
        padding: 15px 20px;
        background: var(--bg-input);
        border-bottom: 1px solid var(--border-color);
    }

    .db-section-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
    }

    .db-section-body {
        padding: 20px;
    }

    .form-row {
        display: flex;
        gap: 20px;
        margin-bottom: 15px;
    }

    .col-md-4 {
        flex: 1;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        font-size: 13px;
    }

    .form-control {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        background: var(--bg-input);
        color: var(--text-primary);
    }

    .form-text {
        font-size: 12px;
        margin-top: 4px;
    }

    .mt-4 {
        margin-top: 20px;
    }

    .db-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }

    .db-table th {
        background: var(--bg-input);
        padding: 10px 15px;
        text-align: left;
        font-weight: 600;
        color: var(--text-secondary);
        border-bottom: 1px solid var(--border-color);
    }

    .db-table td {
        padding: 12px 15px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }

    .text-right {
        text-align: right;
    }

    .text-center {
        text-align: center;
    }

    .btn {
        padding: 8px 16px;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-primary {
        background: #3C873A;
        color: #fff;
    }

    .btn-primary:hover {
        background: #2d6a2e;
    }

    .btn-sm {
        padding: 4px 8px;
        font-size: 12px;
    }

    .btn-danger {
        background: #d32f2f;
        color: #fff;
    }

    .btn-secondary {
        background: #555;
        color: #fff;
    }

    /* Help Section Styles */
    .help-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    .help-card {
        background: var(--bg-input);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 20px;
    }

    .help-card h4 {
        margin: 0 0 15px 0;
        font-size: 16px;
        color: var(--text-primary);
    }

    .help-card p {
        margin: 0 0 15px 0;
        font-size: 13px;
        color: var(--text-secondary);
    }

    .cron-visual {
        text-align: center;
        margin-bottom: 20px;
        padding: 15px;
        background: rgba(60, 135, 58, 0.1);
        border-radius: 8px;
    }

    .cron-visual>span {
        display: inline-block;
        width: 40px;
        height: 40px;
        line-height: 40px;
        background: #3C873A;
        color: #fff;
        border-radius: 6px;
        margin: 0 4px;
        font-weight: bold;
        font-size: 18px;
    }

    .cron-labels {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 8px;
    }

    .cron-labels small {
        width: 40px;
        text-align: center;
        font-size: 10px;
        color: var(--text-secondary);
    }

    .help-table {
        width: 100%;
        font-size: 13px;
    }

    .help-table td {
        padding: 6px 8px;
        border-bottom: 1px solid var(--border-color);
    }

    .help-table td:first-child {
        width: 120px;
    }

    .example-group {
        margin-bottom: 15px;
    }

    .example-group h5 {
        margin: 0 0 8px 0;
        font-size: 13px;
        font-weight: 600;
    }

    code.block {
        display: block;
        padding: 8px 12px;
        margin-bottom: 6px;
        background: var(--bg-body);
        border-radius: 4px;
        font-size: 12px;
        overflow-x: auto;
    }

    .tips-card ul {
        margin: 0;
        padding-left: 20px;
    }

    .tips-card li {
        margin-bottom: 8px;
        font-size: 13px;
        color: var(--text-secondary);
    }

    .tips-card li strong {
        color: var(--text-primary);
    }

    .tips-card code {
        background: var(--bg-body);
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 12px;
    }

    /* Custom Modal Styles */
    .custom-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 99999;
    }

    .custom-modal {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        width: 400px;
        max-width: 90%;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
        overflow: hidden;
    }

    .custom-modal-header {
        padding: 15px 20px;
        background: var(--bg-input);
        border-bottom: 1px solid var(--border-color);
        font-weight: 600;
        font-size: 16px;
    }

    .custom-modal-body {
        padding: 20px;
        font-size: 14px;
        color: var(--text-secondary);
    }

    .custom-modal-footer {
        padding: 15px 20px;
        background: var(--bg-input);
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
</style>

<script>
    // Fix API base URL - remove /public prefix as it's handled by routing
    const API_BASE = '<?= $base_url ?? '' ?>/api';
    const TOKEN = document.querySelector('meta[name="api-token"]')?.content;
    let services = [];

    document.addEventListener('DOMContentLoaded', () => {
        loadServices();
        loadCronJobs();
        lucide.createIcons();
    });

    function applyCommonSetting() {
        const val = document.getElementById('commonSettings').value;
        if (val) {
            document.getElementById('cronSchedule').value = val;
        }
    }

    async function loadServices() {
        try {
            const res = await fetch(`${API_BASE}/services`, {
                headers: { 'Authorization': `Bearer ${TOKEN}` }
            });
            const data = await res.json();
            services = data.services || [];

            const select = document.getElementById('serviceSelect');
            if (services.length === 0) {
                select.innerHTML = '<option value="">No applications found</option>';
            } else {
                select.innerHTML = '<option value="">-- Select Application --</option>' +
                    services.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function loadCronJobs() {
        try {
            const res = await fetch(`${API_BASE}/cron`, {
                headers: { 'Authorization': `Bearer ${TOKEN}` }
            });
            const data = await res.json();
            renderJobs(data.jobs || []);
        } catch (e) {
            console.error(e);
            document.getElementById('cronList').innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading jobs.</td></tr>';
        }
    }

    function renderJobs(jobs) {
        const tbody = document.getElementById('cronList');
        if (jobs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No cron jobs configured.</td></tr>';
            return;
        }

        tbody.innerHTML = jobs.map(job => `
            <tr>
                <td><strong>${job.service ? job.service.name : '<span class="text-danger">Unknown</span>'}</strong></td>
                <td><code>${job.schedule}</code></td>
                <td><code>${job.command}</code></td>
                <td>
                    ${job.last_run ? new Date(job.last_run).toLocaleString() : 'Never'}
                    ${job.last_result ? `<br><small class="text-muted" title="${job.last_result}">Result logged</small>` : ''}
                </td>
                <td class="text-right">
                    <button class="btn btn-sm btn-secondary" onclick="runJob(${job.id})" title="Run Now">
                        <i data-lucide="play"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteJob(${job.id})" title="Delete">
                        <i data-lucide="trash-2"></i>
                    </button>
                </td>
            </tr>
        `).join('');
        lucide.createIcons();
    }

    async function createCronJob(e) {
        e.preventDefault();
        const btn = document.getElementById('addBtn');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Adding...';

        const payload = {
            service_id: document.getElementById('serviceSelect').value,
            schedule: document.getElementById('cronSchedule').value,
            command: document.getElementById('cronCommand').value
        };

        try {
            const res = await fetch(`${API_BASE}/cron`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${TOKEN}`
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (res.ok) {
                document.getElementById('addCronForm').reset();
                loadCronJobs();
                showNotification('Cron job added successfully!', 'success');
            } else {
                showNotification(data.error || 'Failed to create job', 'error');
            }
        } catch (err) {
            showNotification('Network error', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
            lucide.createIcons();
        }
    }

    async function deleteJob(id) {
        showConfirmModal('Delete Cron Job', 'Are you sure you want to delete this cron job?', async () => {
            try {
                const res = await fetch(`${API_BASE}/cron/${id}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': `Bearer ${TOKEN}` }
                });
                if (res.ok) {
                    loadCronJobs();
                    showNotification('Cron job deleted successfully', 'success');
                } else {
                    showNotification('Failed to delete job', 'error');
                }
            } catch (e) {
                showNotification('Network error', 'error');
            }
        });
    }

    async function runJob(id) {
        showConfirmModal('Run Cron Job', 'Run this task immediately?', async () => {
            try {
                const res = await fetch(`${API_BASE}/cron/${id}/run`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${TOKEN}` }
                });
                const data = await res.json();
                if (res.ok) {
                    showOutputModal('Job Output', data.output || '(No output)');
                    loadCronJobs();
                } else {
                    showNotification('Failed to run job: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (e) {
                showNotification('Network error', 'error');
            }
        });
    }

    // Custom Confirm Modal
    function showConfirmModal(title, message, onConfirm) {
        const modal = document.createElement('div');
        modal.className = 'custom-modal-overlay';
        modal.innerHTML = `
            <div class="custom-modal">
                <div class="custom-modal-header">${title}</div>
                <div class="custom-modal-body">${message}</div>
                <div class="custom-modal-footer">
                    <button class="btn btn-secondary" onclick="this.closest('.custom-modal-overlay').remove()">Cancel</button>
                    <button class="btn btn-primary" id="confirmBtn">Confirm</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        modal.querySelector('#confirmBtn').onclick = () => {
            modal.remove();
            onConfirm();
        };
    }

    // Custom Output Modal
    function showOutputModal(title, output) {
        const modal = document.createElement('div');
        modal.className = 'custom-modal-overlay';
        modal.innerHTML = `
            <div class="custom-modal">
                <div class="custom-modal-header">${title}</div>
                <div class="custom-modal-body">
                    <pre style="background:var(--bg-input);padding:12px;border-radius:6px;margin:0;overflow-x:auto;white-space:pre-wrap;max-height:300px;">${output}</pre>
                </div>
                <div class="custom-modal-footer">
                    <button class="btn btn-primary" onclick="this.closest('.custom-modal-overlay').remove()">Close</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    // Fallback for showNotification
    if (typeof showNotification === 'undefined') {
        window.showNotification = (msg, type) => console.log(`[${type}] ${msg}`);
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>