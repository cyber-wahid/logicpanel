<?php
$page_title = 'Create Reseller Package';
$current_page = 'reseller_packages';
$sidebar_type = 'master';
ob_start();
?>

<div class="card" style="max-width:800px; margin:0 auto;">
    <div class="card-header">
        <div class="card-title">Add a New Reseller Package</div>
    </div>
    <div class="card-body">
        <form id="createPackageForm">
            <div class="form-group">
                <label class="form-label">Package Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Reseller Gold" required>
            </div>

            <div class="form-group">
                <input type="hidden" name="type" value="reseller">
            </div>

            <div class="divider">Reseller Account Limits</div>

            <div class="row" style="display:flex; gap:15px; margin-bottom:15px;">
                <div class="col" style="flex:1;">
                    <label class="form-label">Max Accounts</label>
                    <input type="number" name="limit_users" class="form-control" placeholder="10" value="10" required>
                </div>
                <div class="col" style="flex:1;">
                    <label class="form-label">Total Disk Quota</label>
                    <div style="display:flex; gap:5px;">
                        <input type="number" name="limit_disk_total" class="form-control" placeholder="50" value="50"
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
                        <input type="number" name="limit_bandwidth_total" class="form-control" placeholder="500"
                            value="500" required>
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
                    <input type="number" name="max_services" class="form-control" placeholder="25" value="25" required>
                </div>
                <div class="col" style="flex:1;">
                    <label class="form-label">Max Databases</label>
                    <input type="number" name="max_databases" class="form-control" placeholder="10" value="10" required>
                </div>
            </div>

            <div class="row" style="display:flex; gap:15px; margin-bottom:15px;">
                <div class="col" style="flex:1;">
                    <label class="form-label">Max Subdomains</label>
                    <input type="number" name="max_subdomains" class="form-control" placeholder="10" value="10">
                </div>
                <div class="col" style="flex:1;">
                    <label class="form-label">Max Addon Domains</label>
                    <input type="number" name="max_addon_domains" class="form-control" placeholder="5" value="5">
                </div>
            </div>

            <div class="row" style="display:flex; gap:15px; margin-bottom:15px;">
                <div class="col" style="flex:1;">
                    <label class="form-label">CPU Limit (Cores)</label>
                    <input type="number" name="cpu_limit" class="form-control" step="0.5" placeholder="4.0" value="4.0">
                </div>
                <div class="col" style="flex:1;">
                    <label class="form-label">RAM Limit</label>
                    <div style="display:flex; gap:5px;">
                        <input type="number" name="memory_limit" class="form-control" placeholder="8" value="8">
                        <select id="memory_unit" class="form-control" style="width:80px;">
                            <option value="MB">MB</option>
                            <option value="GB" selected>GB</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-20" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <button type="button" class="btn btn-primary" style="justify-content:center;" onclick="createPackage()">
                    <i data-lucide="plus"></i> Create Package
                </button>
                <a href="<?= $base_url ?? '' ?>/reseller-packages" class="btn btn-secondary"
                    style="justify-content:center; text-align:center;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
    async function createPackage() {
        const btn = document.querySelector('.btn-primary');
        const originalText = btn.innerHTML;
        const form = document.getElementById('createPackageForm');

        // Utility to convert back to MB
        function toMB(val, unit) {
            val = parseFloat(val) || 0;
            if (unit === 'GB') return val * 1024;
            return val;
        }

        const diskMB = toMB(form.limit_disk_total.value, document.getElementById('disk_unit').value);
        const bwMB = toMB(form.limit_bandwidth_total.value, document.getElementById('bandwidth_unit').value);
        const memMB = toMB(form.memory_limit.value, document.getElementById('memory_unit').value);

        const data = {
            name: form.name.value,
            type: 'reseller',
            is_global: 1,
            limit_users: parseInt(form.limit_users.value) || 10,
            limit_disk_total: diskMB,
            limit_bandwidth_total: bwMB,
            cpu_limit: parseFloat(form.cpu_limit.value) || null,
            memory_limit: memMB || null,
            max_services: parseInt(form.max_services.value) || 25,
            max_databases: parseInt(form.max_databases.value) || 10,
            max_subdomains: parseInt(form.max_subdomains.value) || 10,
            max_addon_domains: parseInt(form.max_addon_domains.value) || 5
        };

        if (!data.name) {
            showNotification('Package Name is required', 'warning');
            return;
        }

        try {
            btn.disabled = true;
            btn.innerHTML = `<i class="lucide-loader-2 spin-anim"></i> Creating...`;
            if (window.lucide) lucide.createIcons();

            const token = sessionStorage.getItem('token') || document.querySelector('meta[name="api-token"]')?.content;

            const res = await fetch('/public/api/master/packages', {
                method: 'POST',
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
                showNotification('Reseller package created successfully!', 'success');
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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>