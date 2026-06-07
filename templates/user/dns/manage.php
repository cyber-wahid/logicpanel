<?php
$page_title = 'Manage DNS Records';
ob_start();
?>

<div class="db-container">
    <div class="db-page-header">
        <h1><a href="<?= $base_url ?? '' ?>/dns" class="text-muted text-decoration-none"><i data-lucide="arrow-left" style="width:20px;height:20px;vertical-align:middle;margin-right:5px;"></i></a> Manage DNS Records</h1>
        <p id="domainNameDisplay">Loading...</p>
    </div>
    
    <!-- Add Record Classic Form -->
    <div class="db-section" id="addRecordSection" style="display: none;">
        <h3 style="margin-top:0; margin-bottom:15px; font-size:16px; font-weight:600;">Add New DNS Record</h3>
        <form id="addRecordForm" onsubmit="addRecord(event)">
            <div class="row" style="display:flex; gap:15px; margin-bottom:15px;">
                <div class="col-md-3" style="flex:1;">
                    <div class="form-group">
                        <label style="display:block; margin-bottom:5px; font-weight:500;">Type</label>
                        <select id="recordType" class="form-control" style="width:100%; padding:8px 10px; border:1px solid var(--border-color); border-radius:4px; background:var(--bg-input); color:var(--text-primary);" onchange="togglePrioField()" required>
                            <option value="A">A</option>
                            <option value="AAAA">AAAA</option>
                            <option value="CNAME">CNAME</option>
                            <option value="MX">MX</option>
                            <option value="TXT">TXT</option>
                            <option value="SRV">SRV</option>
                            <option value="NS">NS</option>
                            <option value="CAA">CAA</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4" style="flex:2;">
                    <div class="form-group">
                        <label style="display:block; margin-bottom:5px; font-weight:500;">Name</label>
                        <input type="text" id="recordName" class="form-control" style="width:100%; padding:8px 10px; border:1px solid var(--border-color); border-radius:4px; background:var(--bg-input); color:var(--text-primary);" placeholder="@ or www" required>
                    </div>
                </div>
                <div class="col-md-5" style="flex:2;">
                    <div class="form-group">
                        <label style="display:block; margin-bottom:5px; font-weight:500;">Content / Value</label>
                        <input type="text" id="recordContent" class="form-control" style="width:100%; padding:8px 10px; border:1px solid var(--border-color); border-radius:4px; background:var(--bg-input); color:var(--text-primary);" placeholder="192.168.1.1" required>
                    </div>
                </div>
            </div>
            
            <div class="row" style="display:flex; gap:15px; margin-bottom:15px;">
                <div class="col-md-6" id="prioContainer" style="display: none; flex:1;">
                    <div class="form-group">
                        <label style="display:block; margin-bottom:5px; font-weight:500;">Priority (for MX/SRV)</label>
                        <input type="number" id="recordPrio" class="form-control" style="width:100%; padding:8px 10px; border:1px solid var(--border-color); border-radius:4px; background:var(--bg-input); color:var(--text-primary);" value="10">
                    </div>
                </div>
                <div class="col-md-6" style="flex:1;">
                    <div class="form-group">
                        <label style="display:block; margin-bottom:5px; font-weight:500;">TTL</label>
                        <select id="recordTtl" class="form-control" style="width:100%; padding:8px 10px; border:1px solid var(--border-color); border-radius:4px; background:var(--bg-input); color:var(--text-primary);" required>
                            <option value="300">5 minutes</option>
                            <option value="3600" selected>1 hour</option>
                            <option value="14400">4 hours</option>
                            <option value="86400">1 day</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid var(--border-color); padding-top:15px;">
                <button type="button" class="btn btn-secondary" onclick="toggleAddForm()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Record</button>
            </div>
        </form>
    </div>

    <div class="db-section">
        <div class="db-toolbar" style="display: flex; justify-content: flex-end; margin-bottom: 15px;">
            <button class="btn btn-primary" onclick="toggleAddForm()" style="padding: 8px 16px; font-weight: 500;">
                <i data-lucide="plus" style="width:16px;height:16px;margin-right:4px;vertical-align:middle;"></i> <span style="vertical-align:middle;">Add Record</span>
            </button>
        </div>

        <div class="table-responsive">
            <table class="db-table" id="recordsTable">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Content</th>
                        <th>Priority</th>
                        <th>TTL</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="recordsList">
                    <tr>
                        <td colspan="6" class="text-center">Loading records...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="alert alert-info mt-4" style="background:#e0f2fe; color:#0369a1; padding:15px; border-radius:5px; border:1px solid #bae6fd;">
            <div class="d-flex align-items-center gap-2">
                <i data-lucide="info" style="width:20px;height:20px;"></i>
                <span><strong>Note:</strong> DNS changes may take up to 24-48 hours to propagate globally, though typically they update within an hour. Use <code>@</code> for root domain.</span>
            </div>
        </div>
    </div>
</div>

<style>
    .db-container { padding: 10px; width: 100%; max-width: 100%; }
    .db-page-header h1 { font-size: 20px; font-weight: 500; margin: 0 0 5px 0; color: var(--text-primary); }
    .db-page-header p { color: var(--text-secondary); font-size: 13px; margin-bottom: 20px; }
    .db-section { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 4px; padding: 15px; margin-bottom: 20px; }
    
    .db-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .db-table th { background: var(--bg-input); padding: 12px 15px; text-align: left; font-weight: 500; color: var(--text-secondary); border-bottom: 1px solid var(--border-color); }
    .db-table td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); vertical-align: middle; color: var(--text-primary); }
    .db-table tr:hover td { background: rgba(0, 0, 0, 0.02); }
    [data-theme="dark"] .db-table tr:hover td { background: rgba(255, 255, 255, 0.02); }
    

</style>

<script>
    const DNS_API_URL = '<?= $base_url ?? '' ?>/api/v1';
    const DOMAIN_ID = <?= isset($_GET['domain_id']) ? intval($_GET['domain_id']) : 0 ?>;
    const TOKEN = document.querySelector('meta[name="api-token"]')?.content;

    function togglePrioField() {
        const type = document.getElementById('recordType').value;
        const prioContainer = document.getElementById('prioContainer');
        if (type === 'MX' || type === 'SRV') {
            prioContainer.style.display = 'block';
        } else {
            prioContainer.style.display = 'none';
        }
    }

    async function loadRecords() {
        try {
            const response = await fetch(`${DNS_API_URL}/dns/${DOMAIN_ID}`, {
                headers: {
                    'Authorization': `Bearer ${TOKEN}`
                }
            });
            
            if (response.status === 401) {
                window.location.href = '<?= $base_url ?? '' ?>/login';
                return;
            }
            
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.error || 'Failed to load records');
            }

            document.getElementById('domainNameDisplay').textContent = result.domain.domain_name;

            const tbody = document.getElementById('recordsList');
            
            if (!result.domain.records || result.domain.records.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No records found.</td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = result.domain.records.map(r => `
                <tr>
                    <td><span class="badge badge-secondary">${r.type}</span></td>
                    <td><strong>${r.name}</strong></td>
                    <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${r.content}">${r.content}</td>
                    <td>${r.prio > 0 ? r.prio : '-'}</td>
                    <td>${r.ttl}</td>
                    <td class="text-right">
                        <button class="btn btn-sm btn-danger" onclick="deleteRecord(${r.id})" title="Delete">
                            <i data-lucide="trash-2"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
            
            lucide.createIcons();
        } catch (error) {
            document.getElementById('recordsList').innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-danger">Error: ${error.message}</td>
                </tr>
            `;
        }
    }

    function toggleAddForm() {
        const form = document.getElementById('addRecordSection');
        if (form.style.display === 'none') {
            document.getElementById('recordName').value = '';
            document.getElementById('recordContent').value = '';
            document.getElementById('recordPrio').value = '10';
            document.getElementById('recordType').value = 'A';
            togglePrioField();
            form.style.display = 'block';
        } else {
            form.style.display = 'none';
        }
    }

    async function addRecord(e) {
        e.preventDefault();
        
        const btn = e.target.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        
        const data = {
            type: document.getElementById('recordType').value,
            name: document.getElementById('recordName').value,
            content: document.getElementById('recordContent').value,
            ttl: parseInt(document.getElementById('recordTtl').value),
            prio: parseInt(document.getElementById('recordPrio').value) || 0
        };
        
        try {
            btn.innerHTML = '<i class="spinner"></i> Saving...';
            btn.disabled = true;
            
            const response = await fetch(`${DNS_API_URL}/dns/${DOMAIN_ID}/records`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${TOKEN}`
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.error || 'Failed to add record');
            }
            
            showToast('Record added successfully!', 'success');
            toggleAddForm();
            loadRecords();
            
        } catch (error) {
            showToast(error.message, 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    async function deleteRecord(id) {
        if (!confirm(`Are you sure you want to delete this DNS record?`)) {
            return;
        }
        
        try {
            const response = await fetch(`${DNS_API_URL}/dns/${DOMAIN_ID}/records/${id}`, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${TOKEN}`
                }
            });
            
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.error || 'Failed to delete record');
            }
            
            showToast('Record deleted successfully', 'success');
            loadRecords();
        } catch (error) {
            showToast(error.message, 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadRecords();
        lucide.createIcons();
    });
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>
