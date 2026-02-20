<?php
$page_title = 'Account Settings';
ob_start();
?>

<div class="db-container">
    <div class="db-page-header">
        <h1>Account Settings</h1>
        <p>Manage your account information and preferences.</p>
    </div>

    <div class="db-section">
        <h2>Profile Information</h2>

        <div class="db-form-group">
            <label for="username">Username</label>
            <input type="text" id="username" class="form-control"
                value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>" disabled>
            <small style="color:var(--text-secondary); margin-top:5px; display:block;">Username cannot be
                changed.</small>
        </div>

        <div class="db-form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" class="form-control"
                value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>" disabled>
            <small style="color:var(--text-secondary); margin-top:5px; display:block;">Contact support to update your
                email.</small>
        </div>

        <div class="db-form-group">
            <label for="role">Account Role</label>
            <input type="text" id="role" class="form-control"
                value="<?= ucfirst(htmlspecialchars($_SESSION['user_role'] ?? 'user')) ?>" disabled>
        </div>
    </div>

    <div class="db-section">
        <h2>Change Password</h2>
        <form id="passwordForm" onsubmit="event.preventDefault(); updatePassword();">
            <div class="db-form-group">
                <label for="currentPassword">Current Password</label>
                <input type="password" id="currentPassword" class="form-control" required>
            </div>

            <div class="db-form-group">
                <label for="newPassword">New Password</label>
                <input type="password" id="newPassword" class="form-control" required minlength="8">
            </div>

            <div class="db-form-group">
                <label for="confirmPassword">Confirm New Password</label>
                <input type="password" id="confirmPassword" class="form-control" required minlength="8">
            </div>

            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    </div>
</div>

<style>
    .db-container {
        padding: 0 15px;
        max-width: 800px;
    }

    .db-page-header {
        margin-bottom: 30px;
    }

    .db-page-header h1 {
        font-size: 24px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 10px 0;
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
        padding: 20px;
        margin-bottom: 30px;
    }

    .db-section h2 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
    }

    .db-form-group {
        margin-bottom: 20px;
    }

    .db-form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text-primary);
    }

    .form-control {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        background: var(--bg-input);
        color: var(--text-primary);
        font-size: 14px;
        font-family: 'Poppins', sans-serif;
    }

    .form-control:disabled {
        background-color: var(--bg-body);
        cursor: not-allowed;
        opacity: 0.7;
    }

    .btn {
        padding: 10px 20px;
        border-radius: 4px;
        font-weight: 500;
        cursor: pointer;
        font-size: 14px;
        border: none;
    }

    .btn-primary {
        background-color: #3C873A;
        color: white;
    }

    .btn-primary:hover {
        background-color: #2D6A2E;
    }
</style>

<script>
    // Fix API base URL - remove /public prefix as it's handled by routing
    const API_BASE = '<?= $base_url ?? '' ?>/api';
    const TOKEN = document.querySelector('meta[name="api-token"]')?.content;

    async function updatePassword() {
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const btn = document.querySelector('#passwordForm button[type="submit"]');

        if (newPassword !== confirmPassword) {
            showNotification('New passwords do not match', 'error');
            return;
        }

        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spin-anim" style="display:inline-block; border:2px solid #fff; border-top-color:transparent; border-radius:50%; width:14px; height:14px; margin-right:5px;"></span> Updating...';
        btn.disabled = true;

        try {
            const res = await fetch(`${API_BASE}/auth/password`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${TOKEN}`
                },
                body: JSON.stringify({
                    current_password: currentPassword,
                    new_password: newPassword,
                    new_password_confirmation: confirmPassword
                })
            });

            const data = await res.json();

            if (res.ok) {
                showNotification('Password updated successfully', 'success');
                document.getElementById('passwordForm').reset();
            } else {
                showNotification(data.message || data.error || 'Failed to update password', 'error');
            }
        } catch (e) {
            showNotification('Network error occurred', 'error');
            console.error(e);
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    // Simple helper if notification function is missing
    if (typeof showNotification === 'undefined') {
        window.showNotification = (msg, type) => alert(msg);
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../shared/layouts/main.php';
?>