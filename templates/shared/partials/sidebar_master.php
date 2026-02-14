<aside class="lp-sidebar">
    <a href="<?= $base_url ?? '' ?>/dashboard" class="lp-sidebar-brand">
        <img src="<?= $base_url ?? '' ?>/public/assets/logo.svg" alt="LogicPanel" class="lp-brand-logo">
    </a>

    <nav class="lp-sidebar-nav">
        <a href="<?= $base_url ?? '' ?>/dashboard"
            class="lp-nav-item <?= ($current_page ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i>
            <span>Dashboard</span>
        </a>

        <div class="lp-nav-section">Accounts</div>
        <a href="<?= $base_url ?? '' ?>/accounts/list"
            class="lp-nav-item <?= ($current_page ?? '') === 'accounts_list' ? 'active' : '' ?>">
            <i data-lucide="users"></i>
            <span>List Accounts</span>
        </a>
        <a href="<?= $base_url ?? '' ?>/accounts/create"
            class="lp-nav-item <?= ($current_page ?? '') === 'accounts_create' ? 'active' : '' ?>">
            <i data-lucide="user-plus"></i>
            <span>Create Account</span>
        </a>

        <?php if (($_SESSION['user_role'] ?? '') !== 'reseller'): ?>
            <div class="lp-nav-section">Resellers</div>
            <a href="<?= $base_url ?? '' ?>/resellers"
                class="lp-nav-item <?= ($current_page ?? '') === 'resellers' ? 'active' : '' ?>">
                <i data-lucide="crown"></i>
                <span>Resellers</span>
            </a>
            <a href="<?= $base_url ?? '' ?>/reseller-packages"
                class="lp-nav-item <?= ($current_page ?? '') === 'reseller_packages' ? 'active' : '' ?>">
                <i data-lucide="boxes"></i>
                <span>Reseller Pkgs</span>
            </a>
        <?php endif; ?>

        <div class="lp-nav-section">Management</div>
        <a href="<?= $base_url ?? '' ?>/packages/list"
            class="lp-nav-item <?= ($current_page ?? '') === 'packages_list' ? 'active' : '' ?>">
            <i data-lucide="package"></i>
            <span>Packages</span>
        </a>
        <a href="<?= $base_url ?? '' ?>/services/list"
            class="lp-nav-item <?= ($current_page ?? '') === 'services' ? 'active' : '' ?>">
            <i data-lucide="server"></i>
            <span>Service Manager</span>
        </a>
        <a href="<?= $base_url ?? '' ?>/databases/list"
            class="lp-nav-item <?= ($current_page ?? '') === 'databases' ? 'active' : '' ?>">
            <i data-lucide="database"></i>
            <span>Databases</span>
        </a>
        <a href="<?= $base_url ?? '' ?>/domains/list"
            class="lp-nav-item <?= ($current_page ?? '') === 'domains' ? 'active' : '' ?>">
            <i data-lucide="globe"></i>
            <span>Domains</span>
        </a>

        <?php if (($_SESSION['user_role'] ?? '') !== 'reseller'): ?>
            <div class="lp-nav-section">System</div>
            <a href="<?= $base_url ?? '' ?>/terminal"
                class="lp-nav-item <?= ($current_page ?? '') === 'terminal' ? 'active' : '' ?>">
                <i data-lucide="terminal"></i>
                <span>Root Terminal</span>
            </a>
            <a href="<?= $base_url ?? '' ?>/api-keys/list"
                class="lp-nav-item <?= ($current_page ?? '') === 'api_keys' ? 'active' : '' ?>">
                <i data-lucide="key"></i>
                <span>API Access</span>
            </a>
            <a href="<?= $base_url ?? '' ?>/security/locked-accounts"
                class="lp-nav-item <?= ($current_page ?? '') === 'locked_accounts' ? 'active' : '' ?>">
                <i data-lucide="shield-alert"></i>
                <span>Locked Accounts</span>
            </a>
            <a href="<?= $base_url ?? '' ?>/settings/config"
                class="lp-nav-item <?= ($current_page ?? '') === 'settings' ? 'active' : '' ?>">
                <i data-lucide="settings"></i>
                <span>Server Config</span>
            </a>
            <a href="<?= $base_url ?? '' ?>/settings/updates"
                class="lp-nav-item <?= ($current_page ?? '') === 'updates' ? 'active' : '' ?>">
                <i data-lucide="refresh-cw"></i>
                <span>Updater</span>
            </a>
        <?php else: ?>
            <div class="lp-nav-section">System</div>
            <a href="<?= $base_url ?? '' ?>/api-keys/list"
                class="lp-nav-item <?= ($current_page ?? '') === 'api_keys' ? 'active' : '' ?>">
                <i data-lucide="key"></i>
                <span>API Access</span>
            </a>
            <a href="<?= $base_url ?? '' ?>/settings/config"
                class="lp-nav-item <?= ($current_page ?? '') === 'settings' ? 'active' : '' ?>">
                <i data-lucide="settings"></i>
                <span>Server Config</span>
            </a>
        <?php endif; ?>
    </nav>

    <?php
    // Get logged in user info from session
    $userName = $_SESSION['user_name'] ?? 'Administrator';
    $userRole = $_SESSION['user_role'] ?? 'admin';
    $userInitial = strtoupper(substr($userName, 0, 1));

    // Role display mapping
    $roleDisplayNames = [
        'root' => 'Superuser',
        'admin' => 'Administrator',
        'reseller' => 'Reseller',
        'user' => 'User'
    ];
    $roleDisplay = $roleDisplayNames[$userRole] ?? ucfirst($userRole);

    // Avatar color based on role
    $avatarColors = [
        'root' => 'linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%)',
        'admin' => 'linear-gradient(135deg, #1976d2 0%, #0d47a1 100%)',
        'reseller' => 'linear-gradient(135deg, #7b1fa2 0%, #4a148c 100%)',
        'user' => 'linear-gradient(135deg, #388e3c 0%, #1b5e20 100%)'
    ];
    $avatarBg = $avatarColors[$userRole] ?? $avatarColors['user'];
    ?>
    <!-- User Section -->
    <div class="lp-sidebar-user">
        <a href="<?= $base_url ?? '' ?>/settings/profile" class="lp-user-profile">
            <div class="lp-user-avatar" style="background: <?= $avatarBg ?>;">
                <?= $userInitial ?>
            </div>
            <div class="lp-user-info">
                <div class="lp-user-name"><?= htmlspecialchars($userName) ?></div>
                <div class="lp-user-role"><?= $roleDisplay ?></div>
            </div>
        </a>
        <a href="<?= $base_url ?? '' ?>/logout" class="lp-logout" title="Logout">
            <i data-lucide="log-out"></i>
        </a>
    </div>
</aside>

<style>
    /* Sidebar Styles 240px */
    .lp-sidebar {
        width: 240px;
        background: #1E2127;
        position: fixed;
        left: 0;
        top: 0;
        bottom: 0;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    [data-theme="dark"] .lp-sidebar {
        border-right: 1px solid rgba(60, 135, 58, 0.3);
    }

    /* Update main content margin for new sidebar width */
    .main-content {
        margin-left: 240px !important;
    }


    /* Brand */
    .lp-sidebar-brand {
        padding: 14px 18px;
        display: flex;
        align-items: center;
        text-decoration: none;
    }

    .lp-brand-logo {
        height: 32px;
        width: auto;
    }

    /* Navigation */
    .lp-sidebar-nav {
        flex: 1;
        padding: 12px 0;
        overflow-y: auto;
    }

    .lp-nav-section {
        padding: 16px 18px 8px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #6B7280;
    }

    .lp-nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 18px;
        color: #9CA3AF;
        font-size: 13px;
        text-decoration: none !important;
        transition: color 0.15s ease, background 0.15s ease;
        border-left: 3px solid transparent;
        margin: 2px 0;
    }

    .lp-nav-item svg {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }

    .lp-nav-item:hover {
        color: #E5E7EB;
        background: rgba(255, 255, 255, 0.05);
        text-decoration: none !important;
    }

    .lp-nav-item.active {
        color: #fff;
        background: rgba(211, 47, 47, 0.1);
        /* Subtle Red tint */
        border-left-color: #d32f2f;
    }

    .lp-nav-item.active svg {
        color: #d32f2f;
    }

    /* User Section */
    .lp-sidebar-user {
        padding: 14px 16px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(0, 0, 0, 0.1);
    }

    .lp-user-profile {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        text-decoration: none;
        padding: 4px;
        margin: -4px;
        border-radius: 6px;
        transition: background 0.15s ease;
    }

    .lp-user-profile:hover {
        background: rgba(255, 255, 255, 0.05);
    }


    .lp-user-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 600;
        font-size: 13px;
        flex-shrink: 0;
    }

    .lp-user-info {
        flex: 1;
        min-width: 0;
    }

    .lp-user-name {
        color: #E5E7EB;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .lp-user-role {
        color: #6B7280;
        font-size: 10px;
    }

    .lp-logout {
        color: #6B7280;
        padding: 6px;
        border-radius: 6px;
        transition: all 0.15s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .lp-logout:hover {
        color: #EF4444;
        background: rgba(239, 68, 68, 0.1);
    }

    .lp-logout svg {
        width: 16px;
        height: 16px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .lp-sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .lp-sidebar.open {
            transform: translateX(0);
        }

        .main-content {
            margin-left: 0 !important;
        }
    }
</style>