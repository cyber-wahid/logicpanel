<?php
$page_title = 'List Accounts';
$current_page = 'accounts_list';
$sidebar_type = 'master';
ob_start();
?>

<div class="card">
    <div class="card-header justify-between">
        <div class="card-title">Accounts List</div>
        <a href="create" class="btn btn-primary btn-sm"><i data-lucide="plus"></i> Create New</a>
    </div>

    <div class="card-body p-0">
        <!-- Search -->
        <div class="d-flex gap-10 mb-15 align-center" style="padding: 15px;">
            <input type="text" class="form-control" style="max-width:300px;"
                placeholder="Search by Domain, User, or IP...">
            <button class="btn btn-secondary btn-sm">Search</button>
        </div>

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>IP Address</th>
                        <th>Package</th>
                        <th>Setup Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Mock Data -->
                    <!-- Data loaded via API -->
                    <tr>
                        <td colspan="8" class="text-muted" style="text-align:center; padding:20px;">
                            <i class="lucide-loader-2 spin-anim" style="vertical-align:middle; margin-right:8px;"></i>
                            Loading accounts...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Use global API_URL defined in dashboard.js
    // Use global API_URL defined in dashboard.js
    const MASTER_API_URL = `${API_URL}/master/accounts`;
    // Use token from global scope synced by layout
    const token = window.apiToken || sessionStorage.getItem('token');
    const headers = {
        'Authorization': 'Bearer ' + token,
        'Accept': 'application/json'
    };

    async function loadAccounts() {
        if (!token) {
            showNotification('Please login to view accounts', 'error');
            console.error('No token found');
            return;
        }

        try {
            const res = await fetch(`${MASTER_API_URL}?role=user`, { headers });
            const contentType = res.headers.get("content-type");

            if (!contentType || !contentType.includes("application/json")) {
                const text = await res.text();
                console.error('Non-JSON response received:', text);
                throw new Error('Server returned HTML/Text instead of JSON. Check console for details.');
            }

            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.message || data.error || 'Server error: ' + res.status);
            }

            const tbody = document.querySelector('tbody');

            if (!data.accounts || data.accounts.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted" style="text-align:center; padding:20px;">No accounts found</td></tr>';
                return;
            }

            tbody.innerHTML = data.accounts.map(acc => `
                <tr>
                    <td><strong>${acc.domain}</strong></td>
                    <td>
                        ${acc.username}
                        ${acc.owner ? `<br><small class="text-muted" title="Created by reseller"><i data-lucide="user-check" style="width:12px;height:12px;"></i> ${acc.owner.username}</small>` : ''}
                    </td>
                    <td><span class="badge badge-${acc.role === 'reseller' ? 'info' : 'secondary'}">${acc.role === 'reseller' ? 'Reseller' : 'User'}</span></td>
                    <td>${acc.ip}</td>
                    <td>${acc.package}</td>
                    <td>${new Date(acc.created_at).toLocaleDateString()}</td>
                    <td><span class="badge badge-${acc.status === 'active' ? 'success' : (acc.status === 'suspended' ? 'warning' : 'danger')}">${acc.status}</span></td>
                    <td>
                        <div class="d-flex gap-5">
                            <button class="btn btn-primary btn-sm" onclick="loginAsUser(${acc.id})" title="Login as User"><i data-lucide="log-in" class="icon-sm"></i></button>
                            ${acc.status === 'suspended'
                    ? `<button class="btn btn-success btn-sm" onclick="toggleSuspend(${acc.id}, 'unsuspend', '${acc.username}')" title="Unsuspend"><i data-lucide="play" class="icon-sm"></i></button>`
                    : `<button class="btn btn-warning btn-sm" onclick="toggleSuspend(${acc.id}, 'suspend', '${acc.username}')" title="Suspend"><i data-lucide="pause" class="icon-sm"></i></button>`
                }
                            <button class="btn btn-secondary btn-sm" onclick="manageAccount(${acc.id})" title="Manage"><i data-lucide="settings" class="icon-sm"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="terminateAccount('${acc.username}')" title="Terminate"><i data-lucide="trash-2" class="icon-sm"></i></button>
                        </div>
                    </td>
                </tr>
            `).join('');
            if (window.lucide) lucide.createIcons();
        } catch (err) {
            console.error('Load Accounts Error:', err);
            document.querySelector('tbody').innerHTML = '<tr><td colspan="8" class="text-danger text-center" style="text-align:center;">Failed to load accounts: ' + err.message + '</td></tr>';
        }
    }

    async function loginAsUser(id) {
        try {
            const res = await fetch(`${MASTER_API_URL}/${id}/login`, {
                method: 'POST',
                headers: headers
            });
            const data = await res.json();

            if (res.ok && data.token) {
                // Open new tab with full redirect URL from backend
                const targetUrl = data.redirect_url || ('/?token=' + data.token);
                window.open(targetUrl, '_blank');
                showNotification('Logged in as user in new tab', 'success');
            } else {
                showNotification(data.message || data.error || 'Login failed', 'error');
            }

        } catch (e) {
            console.error(e);
            showNotification('Error logging in', 'error');
        }
    }

    function manageAccount(id) {
        window.location.href = 'edit?id=' + id;
    }

    async function toggleSuspend(id, action, username) {
        // action = 'suspend' or 'unsuspend'
        const confirmMsg = action === 'suspend'
            ? `Suspend ${username}? This will STOP all running applications and databases for this user.`
            : `Unsuspend ${username}? This will restore access and START their applications.`;

        showConfirm(
            action === 'suspend' ? 'Suspend Account?' : 'Unsuspend Account?',
            confirmMsg,
            () => performSuspendAction(id, action, username),
            'Yes, do it',
            action === 'suspend' ? 'btn-warning' : 'btn-success'
        );
    }

    async function performSuspendAction(id, action, username) {
        // Route: POST /master/accounts/{id}/suspend or /unsuspend
        // Actually AccountController methods suspend/unsuspend take body/args
        // The previous code used POST /master/accounts/terminate with body.
        // Let's stick to consistent REST if possible or use what Controller supports.
        // Controller suspend($req, $res, $args).
        // If I update routes to be: $group->post('/accounts/{id}/suspend', ...)
        try {
            const res = await fetch(`${MASTER_API_URL}/${id}/${action}`, {
                method: 'POST',
                headers: { ...headers, 'Content-Type': 'application/json' },
                body: JSON.stringify({ username }) // Sending username as fallback if ID logic is mixed, but ID in URL is primary
            });
            const data = await res.json();

            if (res.ok) {
                showNotification(data.message, 'success');
                loadAccounts();
            } else {
                showNotification(data.message || 'Action failed', 'error');
            }
        } catch (e) {
            showNotification('Request failed', 'error');
        }
    }

    // Terminate Account
    async function terminateAccount(username) {
        showConfirm(
            'Terminate Account?',
            `Are you sure you want to terminate ${username}? This will PERMANENTLY DELETE all data, containers, and databases.`,
            () => performTerminate(username),
            'Yes, Terminate',
            'btn-danger'
        );
    }

    async function performTerminate(username) {
        try {
            const res = await fetch(MASTER_API_URL + '/terminate', {
                method: 'POST',
                headers: { ...headers, 'Content-Type': 'application/json' },
                body: JSON.stringify({ username })
            });
            const data = await res.json();

            if (res.ok && data.result === 'success') {
                showNotification('Account ' + username + ' terminated successfully', 'success');
                loadAccounts();
            } else {
                showNotification('Error: ' + (data.message || data.error), 'error');
            }
        } catch (err) {
            console.error(err);
            showNotification('Failed to connect to server', 'error');
        }
    }

    loadAccounts();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>