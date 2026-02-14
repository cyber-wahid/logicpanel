<?php
$page_title = 'Server Configuration';
$current_page = 'settings';
$sidebar_type = 'master';
ob_start();
?>

<div class="card" style="max-width:800px; margin:0 auto;">
    <div class="card-header">
        <div class="card-title">Server Configuration</div>
    </div>
    <div class="card-body">
        <form id="settingsForm">
            <!-- Admin-only fields -->
            <div class="form-section admin-only-section">
                <h5 style="margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                    General Information</h5>
                <div class="row">
                    <div class="col">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" placeholder="e.g. LogicPanel">
                    </div>
                    <div class="col">
                        <label class="form-label">Admin Email</label>
                        <input type="email" name="contact_email" class="form-control" placeholder="admin@example.com">
                    </div>
                </div>
            </div>

            <div class="form-section mt-20">
                <h5 style="margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                    Network Settings</h5>
                <div class="row admin-only-section">
                    <div class="col">
                        <label class="form-label">Hostname</label>
                        <input type="text" name="hostname" class="form-control" placeholder="panel.example.com">
                    </div>
                    <div class="col">
                        <label class="form-label d-flex justify-content-between">
                            Server IP
                            <a href="javascript:void(0)" onclick="detectIP(this)" class="text-primary admin-only-action"
                                style="font-size: 11px; text-decoration: none;">
                                <i data-lucide="refresh-cw" style="width:10px; height:10px; vertical-align:middle;"></i>
                                Auto-detect
                            </a>
                        </label>
                        <input type="text" name="server_ip" id="server_ip" class="form-control"
                            placeholder="192.168.1.100">
                    </div>
                </div>
                <!-- Server IP for Resellers (read-only) -->
                <div class="row reseller-only-section" style="display:none;">
                    <div class="col">
                        <label class="form-label">Server IP</label>
                        <input type="text" name="server_ip_readonly" id="server_ip_readonly" class="form-control"
                            placeholder="192.168.1.100" readonly>
                        <small class="text-muted">Point your domain to this IP address</small>
                    </div>
                </div>
                <div class="row mt-10">
                    <div class="col">
                        <label class="form-label">Shared Base Domain (for subdomains)</label>
                        <input type="text" name="shared_domain" class="form-control"
                            placeholder="example.com">
                    </div>
                </div>
                <div class="row mt-10">
                    <div class="col">
                        <label class="form-label">Default Nameserver 1</label>
                        <input type="text" name="ns1" class="form-control" placeholder="ns1.example.com">
                    </div>
                    <div class="col">
                        <label class="form-label">Default Nameserver 2</label>
                        <input type="text" name="ns2" class="form-control" placeholder="ns2.example.com">
                    </div>
                </div>
            </div>

            <div class="form-section mt-20 admin-only-section">
                <h5 style="margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                    SSL & Security</h5>
                <div class="row">
                    <div class="col">
                        <label class="form-label">Enable Let's Encrypt SSL</label>
                        <select name="enable_ssl" class="form-control">
                            <option value="1">Enabled (Production)</option>
                        </select>
                        <small class="text-muted">SSL is always enabled for security</small>
                    </div>
                    <div class="col">
                        <label class="form-label">Let's Encrypt Email</label>
                        <input type="email" name="letsencrypt_email" class="form-control"
                            placeholder="security@example.com">
                    </div>
                </div>
            </div>

            <div class="form-section mt-20">
                <h5 style="margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                    Defaults & Localization</h5>
                <div class="row">
                    <div class="col">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" class="form-control">
                            <option value="UTC">UTC (Coordinated Universal Time)</option>
                            <optgroup label="Asia">
                                <option value="Asia/Dhaka">Bangladesh (Dhaka)</option>
                                <option value="Asia/Kolkata">India (Kolkata)</option>
                                <option value="Asia/Karachi">Pakistan (Karachi)</option>
                                <option value="Asia/Shanghai">China (Shanghai)</option>
                                <option value="Asia/Tokyo">Japan (Tokyo)</option>
                                <option value="Asia/Seoul">South Korea (Seoul)</option>
                                <option value="Asia/Singapore">Singapore</option>
                                <option value="Asia/Hong_Kong">Hong Kong</option>
                                <option value="Asia/Dubai">UAE (Dubai)</option>
                                <option value="Asia/Riyadh">Saudi Arabia (Riyadh)</option>
                                <option value="Asia/Tehran">Iran (Tehran)</option>
                                <option value="Asia/Bangkok">Thailand (Bangkok)</option>
                                <option value="Asia/Jakarta">Indonesia (Jakarta)</option>
                                <option value="Asia/Manila">Philippines (Manila)</option>
                                <option value="Asia/Kuala_Lumpur">Malaysia (Kuala Lumpur)</option>
                            </optgroup>
                            <optgroup label="America">
                                <option value="America/New_York">USA - New York (EST)</option>
                                <option value="America/Chicago">USA - Chicago (CST)</option>
                                <option value="America/Denver">USA - Denver (MST)</option>
                                <option value="America/Los_Angeles">USA - Los Angeles (PST)</option>
                                <option value="America/Toronto">Canada - Toronto</option>
                                <option value="America/Vancouver">Canada - Vancouver</option>
                                <option value="America/Mexico_City">Mexico - Mexico City</option>
                                <option value="America/Sao_Paulo">Brazil - São Paulo</option>
                                <option value="America/Buenos_Aires">Argentina - Buenos Aires</option>
                                <option value="America/Lima">Peru - Lima</option>
                                <option value="America/Bogota">Colombia - Bogotá</option>
                            </optgroup>
                            <optgroup label="Europe">
                                <option value="Europe/London">United Kingdom - London</option>
                                <option value="Europe/Paris">France - Paris</option>
                                <option value="Europe/Berlin">Germany - Berlin</option>
                                <option value="Europe/Rome">Italy - Rome</option>
                                <option value="Europe/Madrid">Spain - Madrid</option>
                                <option value="Europe/Amsterdam">Netherlands - Amsterdam</option>
                                <option value="Europe/Brussels">Belgium - Brussels</option>
                                <option value="Europe/Vienna">Austria - Vienna</option>
                                <option value="Europe/Warsaw">Poland - Warsaw</option>
                                <option value="Europe/Moscow">Russia - Moscow</option>
                                <option value="Europe/Istanbul">Turkey - Istanbul</option>
                                <option value="Europe/Athens">Greece - Athens</option>
                            </optgroup>
                            <optgroup label="Africa">
                                <option value="Africa/Cairo">Egypt - Cairo</option>
                                <option value="Africa/Lagos">Nigeria - Lagos</option>
                                <option value="Africa/Johannesburg">South Africa - Johannesburg</option>
                                <option value="Africa/Nairobi">Kenya - Nairobi</option>
                                <option value="Africa/Casablanca">Morocco - Casablanca</option>
                            </optgroup>
                            <optgroup label="Australia & Pacific">
                                <option value="Australia/Sydney">Australia - Sydney</option>
                                <option value="Australia/Melbourne">Australia - Melbourne</option>
                                <option value="Australia/Perth">Australia - Perth</option>
                                <option value="Pacific/Auckland">New Zealand - Auckland</option>
                                <option value="Pacific/Fiji">Fiji</option>
                                <option value="Pacific/Honolulu">Hawaii - Honolulu</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="col">
                        <label class="form-label">Allow Registration</label>
                        <select name="allow_registration" class="form-control">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-20">
                <button type="button" class="btn btn-primary" onclick="saveSettings()">Save Configuration</button>
            </div>
        </form>
    </div>
</div>

<style>
    .row {
        display: flex;
        gap: 15px;
        margin-bottom: 10px;
    }

    .col {
        flex: 1;
    }

    @media (max-width: 768px) {
        .row {
            flex-direction: column;
        }
    }
</style>

<script>
    const API = '/public/api/master/settings';
    const token = sessionStorage.getItem('token') || document.querySelector('meta[name="api-token"]')?.content;
    const form = document.getElementById('settingsForm');
    let userRole = '<?= $_SESSION['user_role'] ?? 'user' ?>';

    // Hide admin-only sections for resellers
    if (userRole === 'reseller') {
        document.querySelectorAll('.admin-only-section').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.admin-only-action').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.reseller-only-section').forEach(el => el.style.display = 'block');
    }

    async function loadSettings() {
        try {
            const res = await fetch(API, { headers: { 'Authorization': 'Bearer ' + token } });
            const data = await res.json();

            if (res.ok) {
                // Populate form
                for (const key in data) {
                    if (form[key]) {
                        form[key].value = data[key];
                    }
                }
                
                // Populate read-only server IP for resellers
                if (userRole === 'reseller' && data.server_ip) {
                    document.getElementById('server_ip_readonly').value = data.server_ip;
                }
            } else {
                showNotification('Failed to load settings', 'error');
            }
        } catch (e) {
            console.error(e);
            showNotification('Error loading settings', 'error');
        }
    }

    async function detectIP(link) {
        // Only allow admin to detect IP
        if (userRole === 'reseller') {
            showNotification('Only administrators can auto-detect IP', 'error');
            return;
        }

        const originalHtml = link.innerHTML;
        link.innerHTML = '<i class="lucide-loader-2 spin-anim" style="width:10px; height:10px;"></i> Detecting...';
        link.style.pointerEvents = 'none';

        try {
            const res = await fetch('/public/api/master/settings/detect-ip', {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('server_ip').value = data.ip;
                showNotification('IP detected successfully', 'success');
            } else {
                showNotification('Detection failed. Fallback to ' + data.ip, 'warning');
            }
        } catch (e) {
            showNotification('Failed to detect IP', 'error');
        } finally {
            link.innerHTML = originalHtml;
            link.style.pointerEvents = 'auto';
        }
    }

    async function saveSettings() {
        const btn = document.querySelector('.btn-primary');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<i data-lucide="loader-2" class="spin-anim"></i> Saving...`;
        if (window.lucide) lucide.createIcons();

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        // Remove admin-only fields for resellers
        if (userRole === 'reseller') {
            delete data.company_name;
            delete data.contact_email;
            delete data.enable_ssl;
            delete data.letsencrypt_email;
            delete data.hostname;
            delete data.server_ip;
        }

        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify(data)
            });
            const result = await res.json();

            if (res.ok) {
                showNotification('Settings saved successfully', 'success');
            } else {
                showNotification(result.error || 'Failed to save', 'error');
            }
        } catch (e) {
            console.error(e);
            showNotification('Network error', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    loadSettings();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>