const apiUrl = (window.base_url || '') + '/public/api';
const apiToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content');
const headers = { 'Content-Type': 'application/json', 'Authorization': `Bearer ${apiToken}` };

document.addEventListener('DOMContentLoaded', () => {
    loadServices();
    loadDatabases();
});

async function loginToAdminer(driver, host, user, encryptedPass, db) {
    try {
        const pass = encryptedPass ? atob(encryptedPass) : '';
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/public/adminer_login.php';
        form.target = '_blank';

        const fields = { driver, server: host, username: user, password: pass, db };
        for (const [name, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    } catch (e) {
        console.error('Adminer Login Error:', e);
        showNotification('Error opening Adminer', 'error');
    }
}
async function loadServices() {
    try {
        const res = await fetch(`${apiUrl}/services`, { headers });
        const data = await res.json();
        const select = document.getElementById('serviceSelect');
        if (res.ok && data.services) {
            if (data.services.length === 0) { select.innerHTML = '<option value="">No applications found</option>'; return; }
            select.innerHTML = data.services.map(s => `<option value="${s.id}">${s.name} (${s.domain})</option>`).join('');
        }
    } catch (e) { console.error(e); }
}

async function loadDatabases() {
    const tbody = document.getElementById('dbList');
    try {
        const res = await fetch(`${apiUrl}/databases`, { headers });
        const data = await res.json();
        const databases = (data.databases || []).filter(db => db.type === 'postgresql');

        if (databases.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center" style="padding:20px;color:var(--text-muted)">No databases found.</td></tr>';
            return;
        }

        tbody.innerHTML = databases.map(db => `
            <tr>
                <td>
                    <div style="font-weight:600;font-size:13px">${db.name}</div>
                    <div style="font-size:10px;color:var(--text-secondary);opacity:0.8">${db.host}</div>
                </td>
                <td>0 MB</td>
                <td><span class="user-badge">${db.user}</span></td>
                <td class="text-right">
                    <a href="/public/adminer.php?pgsql=${db.host}&username=${db.user}&db=${db.name}" target="_blank" class="btn btn-sm btn-secondary" title="Open Adminer"><i data-lucide="database"></i></a>
                    <button class="btn btn-sm btn-danger" onclick="deleteDb('${db.id}')" title="Delete"><i data-lucide="trash-2"></i></button>
                </td>
            </tr>
        `).join('');
        lucide.createIcons();
    } catch (e) { tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading databases</td></tr>'; }
}

async function createDatabase() {
    const serviceId = document.getElementById('serviceSelect').value;
    const btn = document.querySelector('button[onclick="createDatabase()"]');

    if (!serviceId) { showNotification('Please select a service', 'error'); return; }

    btn.disabled = true; btn.innerHTML = 'Generating...';
    try {
        const res = await fetch(`${apiUrl}/services/${serviceId}/databases`, {
            method: 'POST',
            headers,
            body: JSON.stringify({ type: 'postgresql' })
        });
        const data = await res.json();
        if (res.ok) {
            // Success Modal with One-Click Login
            await showCustomAlert({
                title: 'Database Created!',
                html: `<div style="text-align:left;font-size:13px;background:#f3f4f6;padding:12px;border-radius:6px;border:1px solid #e5e7eb;margin-bottom:12px">
                        <div style="margin-bottom:4px"><b>DB:</b> <span style="color:#2563eb">${data.database.name}</span></div>
                        <div style="margin-bottom:4px"><b>User:</b> <span style="color:#059669">${data.database.user}</span></div>
                        <div style="margin-bottom:4px"><b>Pass:</b> <code style="background:#fff;color:#dc2626;padding:2px 6px;border:1px solid #e5e7eb;border-radius:4px">${data.database.password}</code></div>
                        <div><b>Host:</b> ${data.database.host}</div>
                       </div>
                       
                           <form action="/public/adminer_login.php" method="POST" target="_blank">
                                <input type="hidden" name="driver" value="pgsql">
                                <input type="hidden" name="server" value="${data.database.host}">
                                <input type="hidden" name="username" value="${data.database.user}">
                                <input type="hidden" name="password" value="${data.database.password}">
                                <input type="hidden" name="db" value="${data.database.name}">
                                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;font-weight:600">
                                    <i data-lucide="external-link" style="width:16px;height:16px;margin-right:6px"></i> 
                                    One-Click Login to Adminer (Auto)
                                </button>
                           </form>
                       
                       <p style="margin-top:12px;font-size:11px;color:#d00">Save this password now! It won't be shown again.</p>`
            });
            loadDatabases();
        } else { showCustomAlert({ title: 'Failed', message: data.message }); }
    } catch (e) { showNotification('Network error', 'error'); }
    finally { btn.disabled = false; btn.innerHTML = 'Create Database'; }
}

async function deleteDb(id) {
    if (await showCustomConfirm('Delete Database?', 'Are you sure you want to delete this database? This action cannot be undone.', true)) {
        await fetch(`${apiUrl}/databases/${id}`, { method: 'DELETE', headers });
        loadDatabases();
        showNotification('Database deleted successfully', 'success');
    }
}
