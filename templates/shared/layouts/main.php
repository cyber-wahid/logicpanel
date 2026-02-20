<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme ?? 'light') ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'LogicPanel') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $base_url ?? '' ?>/public/assets/favicon.svg">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>



    <!-- API Token for JavaScript -->
    <?php if (isset($_SESSION['lp_session_token'])): ?>
        <meta name="api-token" content="<?= htmlspecialchars($_SESSION['lp_session_token']) ?>">
    <?php endif; ?>

    <!-- Dashboard JavaScript -->
    <script>
        window.base_url = "<?= $base_url ?? '' ?>";
        window.apiUrl = "<?= $base_url ?? '' ?>/api"; // Global API URL
        
        // Sync Token
        const metaToken = document.querySelector('meta[name="api-token"]')?.content;
        if (metaToken) {
            window.apiToken = metaToken;
            sessionStorage.setItem('token', metaToken);
        } else {
            console.warn('No API Token found in meta tag');
            window.apiToken = sessionStorage.getItem('token');
        }

        // Global Error Handler for UI debugging
        window.addEventListener('error', function (event) {
            if (typeof showNotification === 'function') {
                showNotification('UI Error: ' + event.message, 'error');
            }
        });
    </script>
    <script src="<?= $base_url ?? '' ?>/public/assets/dashboard.js"></script>

    <!-- LogicPanel cPanel-style CSS -->
    <style>
        :root {
            /* Node.js Inspired Professional Green Palette */
            --primary: #3C873A;
            /* Node.js Green */
            --primary-dark: #2D6A2E;
            /* Darker green */
            --primary-light: #68A063;
            /* Lighter green */
            --primary-hover: #4E9C4B;
            --accent: #215732;
            /* Deep forest green */

            /* Light Theme */
            --bg-body: #f5f6f8;
            --bg-content: #ffffff;
            --bg-sidebar: #2b3e50;
            --bg-header: #ffffff;
            --bg-card: #ffffff;
            --bg-input: #f8f9fa;

            --text-primary: #333333;
            --text-secondary: #666666;
            --text-muted: #999999;
            --text-sidebar: #d1d8dd;
            --text-sidebar-hover: #ffffff;

            --border-color: #e3e6e8;
            --border-radius: 4px;

            /* Status Colors */
            --success: #4CAF50;
            --warning: #FF9800;
            --danger: #f44336;
            --info: #2196F3;

            /* Shadows */
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 4px 12px rgba(0, 0, 0, 0.12);

            /* Dimensions */
            --sidebar-width: 220px;
            --sidebar-width: 220px;
            --header-height: 55px;
        }

        /* Utility Classes */
        .mb-20 {
            margin-bottom: 20px !important;
        }

        .mt-20 {
            margin-top: 20px !important;
        }

        .mr-2 {
            margin-right: 0.5rem !important;
        }

        .gap-2 {
            gap: 0.5rem !important;
        }

        /* Dark Theme Override */
        [data-theme="dark"] {
            --bg-body: #161B22;
            --bg-content: #1e252bff;
            --bg-sidebar: #1e2127;
            --bg-header: #161b22;
            --bg-card: #1e252bff;
            --bg-input: #181f24ff;

            --text-primary: #e6edf3;
            --text-secondary: #8b949e;
            --text-muted: #6e7681;

            --border-color: #30363d;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.6);
        }

        /* Dark mode toast specific overrides */
        [data-theme="dark"] .toast {
            background: #2d333b !important;
            color: #e6edf3 !important;
            border-color: #444c56 !important;
        }

        [data-theme="dark"] .toast-title {
            color: #e6edf3 !important;
        }

        [data-theme="dark"] .toast-message {
            color: #8b949e !important;
        }

        [data-theme="dark"] .toast-close {
            color: #8b949e !important;
        }

        [data-theme="dark"] .toast-close:hover {
            color: #e6edf3 !important;
        }

        /* Reset & Base */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', 'Open Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: var(--text-primary);
            background: var(--bg-body);
            min-height: 100vh;
        }

        a {
            color: var(--primary);
            text-decoration: none;
        }

        a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Layout */
        .app-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar styles are now in sidebar.php */

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Header - cPanel Style */
        .header {
            height: var(--header-height);
            background: var(--bg-header);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .header-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-btn {
            width: 36px;
            height: 36px;
            background: none;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.15s ease;
        }

        .header-btn:hover {
            background: var(--bg-input);
            color: var(--text-primary);
        }

        .header-btn svg {
            width: 18px;
            height: 18px;
        }

        /* Content Area */
        .content {
            flex: 1;
            padding: 20px;
        }

        /* Page Header */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .page-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Cards - cPanel Box Style */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            animation: lpFadeInUp 0.45s ease-out both;
            transition: box-shadow 0.25s ease;
        }

        .card:hover {
            box-shadow: var(--shadow);
        }

        .card-header {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .card-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title svg {
            width: 16px;
            height: 16px;
            color: var(--primary);
        }

        .card-body {
            padding: 15px;
        }

        /* Stats Cards Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 15px;
            box-shadow: var(--shadow-sm);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            animation: lpFadeInUp 0.4s ease-out both;
        }

        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.2s; }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            border-color: var(--primary-light);
        }

        .stat-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }

        .stat-card-icon.green {
            background: rgba(60, 135, 58, 0.15);
            color: var(--primary);
        }

        .stat-card-icon.blue {
            background: rgba(33, 150, 243, 0.15);
            color: var(--info);
        }

        .stat-card-icon.orange {
            background: rgba(255, 152, 0, 0.15);
            color: var(--warning);
        }

        .stat-card-icon.red {
            background: rgba(244, 67, 54, 0.15);
            color: var(--danger);
        }

        .stat-card-icon svg {
            width: 20px;
            height: 20px;
        }

        .stat-card-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .stat-card-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 500;
            border-radius: var(--border-radius);
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn:active {
            transform: translateY(0) scale(0.97);
            box-shadow: none;
            transition-duration: 0.1s;
        }

        .btn svg {
            width: 16px;
            height: 16px;
            transition: transform 0.2s ease;
        }

        .btn:hover svg {
            transform: scale(1.1);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            text-decoration: none;
            color: white;
            box-shadow: 0 4px 14px rgba(60, 135, 58, 0.4);
        }

        .btn-secondary {
            background: var(--bg-input);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--border-color);
            text-decoration: none;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
        }

        .btn-danger:hover {
            background: #d32f2f;
            box-shadow: 0 4px 14px rgba(244, 67, 54, 0.4);
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
        }

        /* Loading state for buttons */
        .btn.loading {
            pointer-events: none;
            opacity: 0.75;
        }

        .btn.loading::after {
            content: '';
            width: 14px;
            height: 14px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: lpSpin 0.6s linear infinite;
            margin-left: 6px;
        }

        /* Tables */
        .table-wrapper {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .table th {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            background: var(--bg-input);
        }

        .table tr {
            transition: background-color 0.15s ease;
        }

        .table tr:hover td {
            background: var(--bg-input);
        }

        /* Status Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 3px;
        }

        .badge-success {
            background: rgba(60, 135, 58, 0.15);
            color: var(--primary);
        }

        .badge-warning {
            background: rgba(255, 152, 0, 0.15);
            color: var(--warning);
        }

        .badge-danger {
            background: rgba(244, 67, 54, 0.15);
            color: var(--danger);
        }

        .badge-info {
            background: rgba(33, 150, 243, 0.15);
            color: var(--info);
        }

        .badge-secondary {
            background: var(--bg-input);
            color: var(--text-secondary);
        }

        .badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        /* Quick Links Grid - cPanel Style */
        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 10px;
        }

        .quick-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15px 10px;
            text-align: center;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .quick-link:hover {
            border-color: var(--primary);
            box-shadow: 0 6px 20px rgba(60, 135, 58, 0.15);
            text-decoration: none;
            transform: translateY(-3px);
        }

        .quick-link:active {
            transform: translateY(0) scale(0.97);
            transition-duration: 0.1s;
        }

        .quick-link-icon {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            background: rgba(60, 135, 58, 0.12);
            color: var(--primary);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .quick-link:hover .quick-link-icon {
            transform: scale(1.1);
            background: rgba(60, 135, 58, 0.2);
        }

        .quick-link-icon svg {
            width: 22px;
            height: 22px;
        }

        .quick-link-text {
            font-size: 12px;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .quick-link:hover .quick-link-text {
            color: var(--primary);
        }

        /* Service Cards Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
        }

        .service-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .service-card-header {
            padding: 12px 15px;
            background: var(--bg-input);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .service-card-icon {
            width: 38px;
            height: 38px;
            background: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .service-card-icon svg {
            width: 20px;
            height: 20px;
        }

        .service-card-info {
            flex: 1;
            min-width: 0;
        }

        .service-card-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .service-card-domain {
            font-size: 12px;
            color: var(--primary);
        }

        .service-card-body {
            padding: 12px 15px;
        }

        .service-card-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }

        .service-card-meta-item {
            font-size: 12px;
        }

        .service-card-meta-label {
            color: var(--text-muted);
        }

        .service-card-meta-value {
            color: var(--text-primary);
            font-weight: 500;
        }

        .service-card-actions {
            display: flex;
            gap: 6px;
        }

        .service-card-actions .btn {
            flex: 1;
        }

        /* Forms */
        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            font-size: 13px;
            color: var(--text-primary);
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            transition: border-color 0.15s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(60, 135, 58, 0.1);
        }

        /* Terminal */
        .terminal {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 13px;
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .terminal-header {
            background: #323232;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .terminal-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .terminal-dot.red {
            background: #ff5f56;
        }

        .terminal-dot.yellow {
            background: #ffbd2e;
        }

        .terminal-dot.green {
            background: #27c93f;
        }

        .terminal-body {
            padding: 15px;
            height: 300px;
            overflow-y: auto;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        .empty-state-icon {
            width: 60px;
            height: 60px;
            background: var(--bg-input);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .empty-state-icon svg {
            width: 28px;
            height: 28px;
            opacity: 0.5;
        }

        .empty-state-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .empty-state-text {
            font-size: 13px;
            margin-bottom: 15px;
        }

        /* Responsive */
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-menu-btn {
                display: flex !important;
            }

            .mobile-overlay {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 99;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }

            .mobile-overlay.visible {
                opacity: 1;
                visibility: visible;
            }

            /* Responsive Utilities */
            .table-responsive {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin-bottom: 15px;
                border: 1px solid var(--border-color);
                border-radius: var(--border-radius);
            }

            .table-responsive .table {
                margin-bottom: 0;
                min-width: 600px;
                /* Ensure some width for scrolling */
            }

            .header {
                padding: 0 15px;
            }

            .content {
                padding: 15px;
            }

            .card-header {
                padding: 10px 12px;
            }

            .card-body {
                padding: 12px;
            }
        }

        .mobile-menu-btn {
            display: none;
        }

        /* Utility */
        .text-success {
            color: var(--success);
        }

        .text-warning {
            color: var(--warning);
        }

        .text-danger {
            color: var(--danger);
        }

        .text-muted {
            color: var(--text-muted);
        }

        .text-primary {
            color: var(--primary);
        }

        .mt-0 {
            margin-top: 0;
        }

        .mb-0 {
            margin-bottom: 0;
        }

        .mb-10 {
            margin-bottom: 10px;
        }

        .mb-15 {
            margin-bottom: 15px;
        }

        .mb-20 {
            margin-bottom: 20px;
        }

        .d-flex {
            display: flex;
        }

        .align-center {
            align-items: center;
        }

        .justify-between {
            justify-content: space-between;
        }

        .gap-5 {
            gap: 5px;
        }

        .gap-10 {
            gap: 10px;
        }

        /* Progress Bar */
        .progress {
            height: 6px;
            background: var(--bg-input);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: var(--primary);
            width: 0%;
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* === Global Animations === */
        @keyframes lpFadeInUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes lpSpin {
            to { transform: rotate(360deg); }
        }

        @keyframes lpPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Page content entrance */
        .content {
            animation: lpFadeInUp 0.35s ease-out;
        }

        /* Badge pulse for live statuses */
        .badge-success .badge-dot {
            animation: lpPulse 2s ease-in-out infinite;
        }

        /* Smooth sidebar link transitions */
        .sidebar-link {
            transition: all 0.2s ease !important;
        }

        .sidebar-link:hover {
            transform: translateX(3px);
        }

        /* Header button animations */
        .header-btn {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .header-btn:hover {
            transform: scale(1.08);
        }

        .header-btn:active {
            transform: scale(0.95);
        }

        /* Form focus glow animation */
        .form-control {
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        /* Skeleton loading placeholder */
        .skeleton {
            background: linear-gradient(90deg, var(--bg-input) 25%, var(--border-color) 50%, var(--bg-input) 75%);
            background-size: 200% 100%;
            animation: lpShimmer 1.5s infinite;
            border-radius: var(--border-radius);
        }

        @keyframes lpShimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }

        .toast {
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--primary);
            padding: 12px 16px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            min-width: 300px;
            max-width: 400px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transform: translateX(100%);
            animation: slideIn 0.3s forwards;
            pointer-events: auto;
            position: relative;
        }

        /* Force dark mode toast styling */
        html[data-theme="dark"] .toast,
        [data-theme="dark"] .toast {
            background: #2d333b !important;
            color: #e6edf3 !important;
            border-color: #444c56 !important;
        }

        html[data-theme="dark"] .toast-title,
        [data-theme="dark"] .toast-title {
            color: #e6edf3 !important;
        }

        html[data-theme="dark"] .toast-message,
        [data-theme="dark"] .toast-message {
            color: #adbac7 !important;
        }

        .toast.success {
            border-left-color: var(--success);
        }

        .toast.error {
            border-left-color: var(--danger);
        }

        .toast.warning {
            border-left-color: var(--warning);
        }

        .toast.info {
            border-left-color: var(--info);
        }

        .toast-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 2px;
        }

        .toast.success .toast-icon {
            color: var(--success);
        }

        .toast.error .toast-icon {
            color: var(--danger);
        }

        .toast.warning .toast-icon {
            color: var(--warning);
        }

        .toast.info .toast-icon {
            color: var(--info);
        }

        .toast-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .toast-title {
            font-weight: 600;
            font-size: 14px;
        }

        .toast-message {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .toast-close {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            margin-left: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.15s;
        }

        .toast-close:hover {
            color: var(--text-primary);
        }

        /* Modal CSS */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            display: none;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .modal-overlay.open {
            display: flex;
            opacity: 1;
        }

        .modal {
            background: var(--bg-card);
            padding: 24px;
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 400px;
            box-shadow: var(--shadow-lg);
            transform: scale(0.95);
            transition: transform 0.2s;
            border: 1px solid var(--border-color);
        }

        .modal-overlay.open .modal {
            transform: scale(1);
        }

        .modal-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .modal-text {
            color: var(--text-secondary);
            margin-bottom: 20px;
            font-size: 14px;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        @keyframes slideIn {
            to {
                transform: translateX(0);
            }
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateX(20px);
            }
        }


        .progress-bar {
            height: 100%;
            background: var(--primary);
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        /* Animations & Loading */
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }

        /* Dark spinner for light backgrounds if needed */
        .spinner-dark {
            border-color: rgba(0, 0, 0, 0.1);
            border-top-color: var(--primary);
        }

        /* Apply fadeIn to main content */
        .content {
            animation: fadeIn 0.4s ease-out;
        }

        /* Button Loading Item */
        .btn-loading-content {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: var(--primary);
            transition: width 0.3s ease;
        }

        /* Mobile Overlay */
        .mobile-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .mobile-overlay.visible {
            display: block;
            opacity: 1;
            visibility: visible;
        }

        @media (max-width: 768px) {
            .mobile-overlay.visible {
                display: block;
            }
        }

        /* Loading Spinner */
        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Mobile Compact Styles - Optimized */
        @media (max-width: 768px) {

            html,
            body {
                overflow-x: hidden;
                width: 100vw;
            }

            .main-content {
                width: 100%;
                margin-left: 0 !important;
                padding: 0;
            }

            .content {
                padding: 10px;
                width: 100%;
                box-sizing: border-box;
            }

            .header {
                padding: 0 15px;
            }

            /* Stack Header Items if crowded */
            .header-title {
                font-size: 15px !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 150px;
            }

            /* Cards */
            .card {
                margin-bottom: 15px;
                border-radius: 6px;
                width: 100%;
                box-sizing: border-box;
            }

            .card-header,
            .card-body {
                padding: 12px 15px;
            }

            /* Tables - Ensure Scroll */
            .table-wrapper {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border: 1px solid var(--border-color);
                border-radius: 4px;
                background: var(--bg-card);
                margin-bottom: 0;
            }

            .table {
                width: 100%;
                margin-bottom: 0;
            }

            .table th,
            .table td {
                padding: 8px 6px;
                font-size: 11px;
                white-space: nowrap;
            }

            /* Badges & Buttons */
            .badge {
                padding: 2px 5px;
                font-size: 10px;
            }

            .btn {
                padding: 6px 10px;
                font-size: 12px;
            }

            /* Quick Links Compact */
            .quick-links {
                gap: 8px;
                grid-template-columns: repeat(2, 1fr);
            }

            .quick-link {
                padding: 12px 8px;
            }

            .quick-link-icon {
                width: 32px;
                height: 32px;
                margin-bottom: 5px;
            }

            .quick-link-icon svg {
                width: 18px;
                height: 18px;
            }

            .quick-link-text {
                font-size: 11px;
            }

            /* Stats Compact */
            .stats-row {
                gap: 10px;
                margin-bottom: 15px;
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            /* Assuming stats-row exists in user dashboard too */
            .stat-card {
                padding: 12px;
            }

            .stat-card-value {
                font-size: 16px;
            }

            .stat-card-label {
                font-size: 10px;
            }

            /* Typography */
            h1 {
                font-size: 16px !important;
            }

            .card-title {
                font-size: 14px;
            }

            /* Form inputs */
            .form-group {
                margin-bottom: 10px;
            }

            .form-control {
                font-size: 13px;
                padding: 6px 10px;
            }
        }

        .btn-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.6);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
            margin-left: 8px;
        }

        /* Ensure specific alignment in flex buttons */
        .btn .btn-spinner {
            margin-left: 8px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .spin-anim {
            animation: spin 1s linear infinite;
        }
    </style>
</head>

<body>
    <div class="app-wrapper">
        <?php
        $sidebar_file = ($sidebar_type ?? 'user') === 'master' ? 'sidebar_master.php' : 'sidebar.php';
        include __DIR__ . '/../partials/' . $sidebar_file;
        ?>

        <div class="mobile-overlay" onclick="toggleSidebar()"></div>

        <div class="main-content">
            <?php include __DIR__ . '/../partials/header.php'; ?>

            <main class="content">
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Theme handling
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('logicpanel-theme', theme);
        }

        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme');
            const newTheme = current === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);

            fetch('<?= $base_url ?? '' ?>/settings/theme', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ theme: newTheme })
            });
        }

        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.lp-sidebar').classList.toggle('open');
            document.querySelector('.mobile-overlay').classList.toggle('visible');
        }

        // Load saved theme
        const savedTheme = localStorage.getItem('logicpanel-theme');
        if (savedTheme) {
            setTheme(savedTheme);
        }
    </script>
    <!-- Custom Confirmation Modal -->
    <div id="confirm-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-title" id="modal-title">Confirm Action</div>
            <div class="modal-text" id="modal-text">Are you sure?</div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeConfirmModal()">Cancel</button>
                <button class="btn btn-danger" id="modal-confirm-btn">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        let confirmCallback = null;

        function showConfirm(title, message, callback, btnText = 'Confirm', btnClass = 'btn-danger') {
            document.getElementById('modal-title').textContent = title;
            document.getElementById('modal-text').textContent = message;

            const btn = document.getElementById('modal-confirm-btn');
            btn.textContent = btnText;
            btn.className = 'btn ' + btnClass;

            confirmCallback = callback;

            const overlay = document.getElementById('confirm-modal');
            overlay.classList.add('open');
        }

        function closeConfirmModal() {
            document.getElementById('confirm-modal').classList.remove('open');
            confirmCallback = null;
        }

        document.getElementById('modal-confirm-btn').addEventListener('click', () => {
            if (confirmCallback) confirmCallback();
            closeConfirmModal();
        });

        // Close on escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeConfirmModal();
        });
    </script>
    <div id="toast-container" class="toast-container"></div>

    <script>
        function showToast(message, type = 'success', title = '') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;

            let iconName = 'check-circle';
            if (type === 'error') iconName = 'alert-circle';
            if (type === 'warning') iconName = 'alert-triangle';
            if (type === 'info') iconName = 'info';

            if (!title) {
                title = type.charAt(0).toUpperCase() + type.slice(1);
            }

            toast.innerHTML = `
                <div class="toast-icon"><i data-lucide="${iconName}"></i></div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()"><i data-lucide="x"></i></button>
            `;

            container.appendChild(toast);
            lucide.createIcons();

            // Auto dismiss
            setTimeout(() => {
                toast.style.animation = 'fadeOut 0.3s forwards';
                toast.addEventListener('animationend', () => toast.remove());
            }, 5000);
        }

        // Expose to window
        window.showToast = showToast;
        
        // Helper function to replace native confirm()
        window.confirmAction = function(message, title = 'Confirm Action') {
            return new Promise((resolve) => {
                showConfirm(title, message, () => resolve(true));
                // Override close to resolve false
                const originalClose = window.closeConfirmModal;
                window.closeConfirmModal = function() {
                    originalClose();
                    resolve(false);
                    window.closeConfirmModal = originalClose;
                };
            });
        };
        
        // Alias for backward compatibility
        window.showNotification = showToast;
    </script>
</body>

</html>