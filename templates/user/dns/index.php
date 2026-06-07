<?php
$page_title = 'DNS Manager';
ob_start();
?>

<div class="db-container">
    <div class="db-page-header">
        <h1>DNS Manager</h1>
        <p>Manage your DNS zones and records</p>
    </div>
    
    <div class="db-section">
        <div class="db-toolbar" style="justify-content: flex-end;">
            <!-- Zone Editor only lists domains, adding domains is automated -->
        </div>

        <div class="table-responsive">
            <table class="db-table" id="domainsTable">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Status</th>
                        <th>Records</th>
                        <th>Added</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="domainsList">
                    <tr>
                        <td colspan="5" class="text-center">Loading domains...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
<!-- Removed Add Domain Modal as domains are automatically synced -->

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
    
    .action-buttons { display: flex; gap: 5px; justify-content: flex-end; }
</style>

<script>
    const DNS_API_URL = '<?= $base_url ?? '' ?>/api/v1';
    const TOKEN = document.querySelector('meta[name="api-token"]')?.content;

    async function loadDomains() {
        try {
            const response = await fetch(`${DNS_API_URL}/dns`, {
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
                throw new Error(result.error || 'Failed to load domains');
            }

            const tbody = document.getElementById('domainsList');
            
            if (!result.domains || result.domains.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i data-lucide="globe" style="width: 32px; height: 32px; opacity: 0.5; margin-bottom: 10px;"></i>
                            <br>No domains added to DNS Manager yet.
                        </td>
                    </tr>
                `;
                lucide.createIcons();
                return;
            }

            tbody.innerHTML = result.domains.map(d => `
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <i data-lucide="globe" class="text-muted"></i>
                            <strong>${d.domain_name}</strong>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-${d.status === 'active' ? 'success' : 'warning'}">${d.status}</span>
                    </td>
                    <td>${d.records_count} records</td>
                    <td>${new Date(d.created_at).toLocaleDateString()}</td>
                    <td class="text-right">
                        <div class="action-buttons">
                            <a href="<?= $base_url ?? '' ?>/dns/manage/${d.id}" class="btn btn-sm btn-primary" title="Manage Records">
                                <i data-lucide="settings"></i> Manage Records
                            </a>
                        </div>
                    </td>
                </tr>
            `).join('');
            
            lucide.createIcons();
        } catch (error) {
            document.getElementById('domainsList').innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-danger">Error: ${error.message}</td>
                </tr>
            `;
        }
    }

    // Functions for manual domain addition and deletion were removed.

    document.addEventListener('DOMContentLoaded', () => {
        loadDomains();
        lucide.createIcons();
    });
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>
