<?php
$page_title = 'Edit Reseller Package';
$current_page = 'reseller_packages';
$sidebar_type = 'master';
$package_id = $_GET['id'] ?? null;
ob_start();
?>

<div class="card" style="max-width:800px; margin:0 auto;">
    <div class="card-header">
        <div class="card-title">Edit Reseller Package</div>
    </div>
    <div class="card-body">
        <div id="loading" style="text-align:center; padding:20px;">Loading package data...</div>
        <form id="editPackageForm" style="display:none;">
            <input type="hidden" id="package_id" value="<?= htmlspecialchars($package_id) ?>">

            <div class="form-group">
                <label class="form-label">Package Name</label>
                <input type="text" name="name" id="name" class="form-control" required>
            </div>

            <div class="divider">Reseller Account Limits</div>

            <div class="row" style="display:flex; gap:15px; margin-bottom:15px;">
                <div class="col" style="flex:1;">
                    <label class="form-label">Max Accounts</label>
                    <input type="number" name="limit_users" id="limit_users" class="form-control" required>
                </div>
                <div class="col" style="flex:1;">
                    <label class="form-label">Total Disk Quota</label>
                    <div style="display:flex; gap:5px;">
                        <input type="number" name="limit_disk_total" id="limit_disk_total" class="form-control"
                            required>
                        <select id="disk_unit" class="form-control" style="width:80px;">
                            <option value="MB">MB</option>
                            <option value="GB" selected>GB</option>
                        </select>
                    </div>
                </div>
                <div class="col" style="flex:1;">
                    <label class="form-label">Total Bandwidth</label>
                    <div style="display:flex; gap:5px;">
                        <input type="number" name="limit_bandwidth_total" id="limit_bandwidth_total"
                            class="form-control" required>
                        <select id="bandwidth_unit" class="form-control" style="width:80px;">
                            <option value="MB">MB</option>
                            <option value="GB" selected>GB</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="divider">Per-User Defaults</div>

            <div class="row" style="display:flex; gap:15px; margin-bottom:15px;">
                <div class="col" style="flex:1;">
                    <label class="form-label">Max Applications</label>
                    <input type="number" name="max_services" id="max_services" class="form-control" required>
                </div>
                <div class="col" style="flex:1;">
                    <label class="form-label">Max Databases</label>
                    <input type="number" name="max_databases" id="max_databases" class="form-control" required>
                </div>
            </div>

            <div class="row" style="display:flex; gap:15px; margin-bottom:15px;">
                <div class="col" style="flex:1;">
                    <label class="form-label">Max Subdomains</label>
                    <input type="number" name="max_subdomains" id="max_subdomains" class="form-control">
                </div>
                <div class="col" style="flex:1;">
                    <label class="form-label">Max Addon Domains</label>
                    <input type="number" name="max_addon_domains" id="max_addon_domains" class="form-control">
                </div>
            </div>

            <div class="row" style="display:flex; gap:15px; margin-bottom:15px;">
                <div class="col" style="flex:1;">
                    <label class="form-label">CPU Limit (Cores)</label>
                    <input type="number" name="cpu_limit" id="cpu_limit" class="form-control" step="0.5">
                </div>
                <div class="col" style="flex:1;">
                    <label class="form-label">RAM Limit</label>
                    <div style="display:flex; gap:5px;">
                        <input type="number" name="memory_limit" id="memory_limit" class="form-control">
                        <select id="memory_unit" class="form-control" style="width:80px;">
                            <option value="MB">MB</option>
                            <option value="GB" selected>GB</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-20" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <button type="button" class="btn btn-primary" style="justify-content:center;" onclick="updatePackage()">
                    <i data-lucide="save"></i> Save Changes
                </button>
                <a href="<?= $base_url ?? '' ?>/reseller-packages" class="btn btn-secondary"
                    style="justify-content:center; text-align:center;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
    const API = '/public/api/master/packages';
    const token = sessionStorage.getItem('token') || document.querySelector('meta[name="api-token"]')?.content;
    const packageId = document.getElementById('package_id').value;

    // Utility to convert MB to display units
    function fromMB(mb, targetUnit) {
        if (!mb) return '';
        if (targetUnit === 'GB') return (mb / 1024).toFixed(2);
        return mb;
    }

    // Utility to convert to MB
    function toMB(val, unit) {
        val = parseFloat(val) || 0;
        if (unit === 'GB') return val * 1024;
        return val;
    }

    async function loadPackage() {
        try {
            const res = await fetch(`${API}/${packageId}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });

            if (!res.ok) throw new Error('Failed to load package');

            const data = await res.json();
            const pkg = data.package;

            // Populate form - convert MB to GB for display
            document.getElementById('name').value = pkg.name || '';
            document.getElementById('limit_users').value = pkg.limit_users || '';
            document.getElementById('limit_disk_total').value = fromMB(pkg.limit_disk_total, 'GB');
            document.getElementById('limit_bandwidth_total').value = fromMB(pkg.limit_bandwidth_total, 'GB');
            document.getElementById('cpu_limit').value = pkg.cpu_limit || '';
            document.getElementById('memory_limit').value = fromMB(pkg.memory_limit, 'GB');
            document.getElementById('max_services').value = pkg.max_services || '';
            document.getElementById('max_databases').value = pkg.max_databases || '';
            document.getElementById('max_addon_domains').value = pkg.max_addon_domains || '';
            document.getElementById('max_subdomains').value = pkg.max_subdomains || '';

            document.getElementById('loading').style.display = 'none';
            document.getElementById('editPackageForm').style.display = 'block';

            if (window.lucide) lucide.createIcons();
        } catch (e) {
            document.getElementById('loading').innerHTML = '<div class="text-danger">Error: ' + e.message + '</div>';
        }
    }

    async function updatePackage() {
        const btn = document.querySelector('.btn-primary');
        const originalText = btn.innerHTML;
        const form = document.getElementById('editPackageForm');

        const diskMB = toMB(form.limit_disk_total.value, document.getElementById('disk_unit').value);
        const bwMB = toMB(form.limit_bandwidth_total.value, document.getElementById('bandwidth_unit').value);
        const memMB = toMB(form.memory_limit.value, document.getElementById('memory_unit').value);

        const data = {
            name: form.name.value,
            type: 'reseller',
            limit_users: parseInt(form.limit_users.value) || 10,
            limit_disk_total: diskMB,
            limit_bandwidth_total: bwMB,
            cpu_limit: parseFloat(form.cpu_limit.value) || null,
            memory_limit: memMB || null,
            max_services: parseInt(form.max_services.value) || 25,
            max_databases: parseInt(form.max_databases.value) || 10,
            max_subdomains: parseInt(form.max_subdomains.value) || 10,
            max_addon_domains: parseInt(form.max_addon_domains.value) || 5,
        };

        if (!data.name) {
            showNotification('Package Name is required', 'warning');
            return;
        }

        try {
            btn.disabled = true;
            btn.innerHTML = `<i class="lucide-loader-2 spin-anim"></i> Saving...`;
            if (window.lucide) lucide.createIcons();

            const res = await fetch(`${API}/${packageId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify(data)
            });

            let result;
            const contentType = res.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                result = await res.json();
            } else {
                const text = await res.text();
                console.error('Non-JSON response:', text);
                throw new Error('Server returned non-JSON response. Check console for details.');
            }

            if (res.ok) {
                showNotification('Package updated successfully!', 'success');
                setTimeout(() => window.location.href = '<?= $base_url ?? '' ?>/reseller-packages', 1000);
            } else {
                showNotification(result.error || result.message || 'Unknown error', 'error');
            }
        } catch (e) {
            console.error(e);
            showNotification(e.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
            if (window.lucide) lucide.createIcons();
        }
    }

    loadPackage();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>