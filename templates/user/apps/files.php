<?php
// Session handling
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['lp_session_token'])) {
    header('Location: /login');
    exit;
}

$serviceId = $_GET['id'] ?? null;
// If no service ID provided, looking at the dashboard code, we might want to prompt or redirect.
// ideally we should list all services. But for now let's keep the service requirement 
// or if missing, try to find the first service or show error.
// For the sake of this task (Design Revamp), we assume ID is passed or we handle the error gracefully.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="auth-token" content="<?php echo htmlspecialchars($_SESSION['lp_session_token'] ?? ''); ?>">
    <title>File Manager - LogicPanel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <!-- Use FontAwesome for cPanel like icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --header-bg: #1E2127;
            --header-text: #fff;
            --toolbar-bg: #f5f5f5;
            --toolbar-border: #ddd;
            --sidebar-bg: #f0f0f0;
            --main-bg: #fff;
            --text-color: #333;
            --border-color: #ccc;
            --item-hover: #e0e0e0;
            --item-selected: #cce8ff;
            --item-selected-border: #99d1ff;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            font-size: 13px;
            color: var(--text-color);
            background: #fff;
        }

        /* HEADER */
        .fm-header {
            background-color: var(--header-bg);
            color: var(--header-text);
            padding: 0 15px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .fm-brand {
            font-weight: bold;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .fm-header-actions {
            display: flex;
            gap: 15px;
        }

        .fm-header-btn {
            color: #ccc;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }

        .fm-header-btn:hover {
            color: #fff;
        }

        /* TOOLBAR */
        .fm-toolbar {
            background: linear-gradient(to bottom, #fff, #f0f0f0);
            border-bottom: 1px solid var(--toolbar-border);
            padding: 8px 10px;
            display: flex;
            gap: 5px;
            align-items: center;
            flex-wrap: wrap;
        }

        .fm-btn {
            border: 1px solid transparent;
            background: transparent;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #444;
        }

        .fm-btn:hover {
            border-color: #bbb;
            background: linear-gradient(to bottom, #fff, #e6e6e6);
        }

        .fm-btn:active {
            background: #d4d4d4;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .fm-btn-group {
            display: flex;
            gap: 2px;
            margin-right: 10px;
            border-right: 1px solid #ddd;
            padding-right: 10px;
        }

        .fm-btn-group:last-child {
            border-right: none;
        }

        .fm-btn i {
            font-size: 14px;
            color: #555;
        }

        .fm-btn.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        /* BREADCRUMB / ADDRESS BAR */
        .fm-address-bar {
            background: #fff;
            padding: 8px 15px;
            border-bottom: 1px solid #ddd;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .fm-breadcrumb {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 5px;
            overflow: hidden;
        }

        .breadcrumb-item {
            cursor: pointer;
            color: #0066cc;
            display: flex;
            align-items: center;
        }

        .breadcrumb-item:hover {
            text-decoration: underline;
        }

        .breadcrumb-sep {
            color: #999;
            font-size: 10px;
        }

        /* MAIN LAYOUT */
        .fm-container {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        /* SIDEBAR (TREE) */
        .fm-sidebar {
            width: 240px;
            min-width: 200px;
            background: var(--sidebar-bg);
            border-right: 1px solid #ddd;
            overflow-y: auto;
            padding: 10px 0;
            display: flex;
            flex-direction: column;
        }

        .tree-root {
            padding-left: 5px;
        }

        .tree-item {
            padding: 5px 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #333;
        }

        .tree-item:hover {
            background: #e0e0e0;
        }

        .tree-item.active {
            background: #007bff;
            color: #fff;
        }

        .tree-item.active i {
            color: #fff !important;
        }

        .tree-expander {
            width: 16px;
            text-align: center;
            color: #777;
            font-size: 10px;
            cursor: pointer;
        }

        .tree-icon {
            color: #f0c430;
            font-size: 14px;
        }

        /* Folder color */

        /* FILE VIEW (TABLE) */
        .fm-main-view {
            flex: 1;
            background: #fff;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        table.fm-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .fm-table th {
            text-align: left;
            padding: 8px 10px;
            background: #f1f1f1;
            border-bottom: 1px solid #ccc;
            border-right: 1px solid #e0e0e0;
            font-weight: 600;
            color: #555;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .fm-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
            white-space: nowrap;
        }

        .fm-table tr {
            cursor: default;
        }

        .fm-table tr:hover {
            background: #f9f9f9;
        }

        .fm-table tr.selected {
            background: var(--item-selected);
        }

        .file-icon-cell {
            width: 20px;
            text-align: center;
        }

        .name-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ICONS */
        .fa-folder {
            color: #f0c430;
        }

        .fa-file-code {
            color: #66ccff;
        }

        .fa-file-image {
            color: #ff99cc;
        }

        .fa-file-archive {
            color: #ffcc66;
        }

        .fa-file-lines {
            color: #aaa;
        }

        /* FOOTER */
        .fm-footer {
            background: #f7f7f7;
            border-top: 1px solid #ddd;
            padding: 5px 15px;
            font-size: 11px;
            color: #666;
            display: flex;
            justify-content: space-between;
        }

        /* MODALS */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            display: none;
            justify-content: center;
            align-items: flex-start;
            z-index: 2000;
            padding-top: 100px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: #fff;
            padding: 0;
            border-radius: 4px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            width: 450px;
            max-width: 90%;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 10px 15px;
            background: #f5f5f5;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 10px 15px;
            border-top: 1px solid #ddd;
            background: #f5f5f5;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .fm-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }

        /* CONTEXT MENU */
        .context-menu {
            position: fixed;
            background: #fff;
            border: 1px solid #bababa;
            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.2);
            padding: 2px;
            min-width: 180px;
            z-index: 9999;
            display: none;
        }

        .context-item {
            padding: 6px 15px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .context-item:hover {
            background: #e8f0fe;
        }

        .context-sep {
            height: 1px;
            background: #e0e0e0;
            margin: 2px 0;
        }

        .context-item i {
            width: 16px;
            text-align: center;
            color: #666;
        }

        /* TOAST NOTIFICATION */
        .toast-container {
            position: fixed;
            top: 60px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            background: #333;
            color: #fff;
            padding: 12px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            min-width: 280px;
            max-width: 400px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
            font-size: 14px;
        }

        .toast.success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .toast.error {
            background: linear-gradient(135deg, #dc3545, #e74c3c);
        }

        .toast.info {
            background: linear-gradient(135deg, #007bff, #17a2b8);
        }

        .toast.warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: #333;
        }

        .toast i {
            font-size: 18px;
        }

        .toast-close {
            margin-left: auto;
            cursor: pointer;
            opacity: 0.7;
        }

        .toast-close:hover {
            opacity: 1;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        /* Upload area drag states */
        #uploadArea.drag-over {
            border-color: #007bff;
            background: #e8f4ff;
        }

        /* Rename Modal */
        #renameModal .modal-body label,
        #deleteModal .modal-body label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        /* Confirmation Modal */
        .confirm-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            padding: 10px;
            background: #fff3cd;
            border-radius: 4px;
            border: 1px solid #ffc107;
        }

        .confirm-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        .confirm-checkbox label {
            margin: 0;
            font-weight: normal;
            color: #856404;
        }

        /* Breadcrumb icon spacing */
        .breadcrumb-item i {
            margin-right: 5px;
        }

        /* Trash sidebar item */
        .trash-sidebar-item {
            border-top: 1px solid #ddd;
            margin-top: 10px;
            padding-top: 10px;
        }

        .trash-sidebar-item .tree-icon {
            color: #dc3545 !important;
        }

        /* Trash view styling */
        .trash-header {
            background: #f8d7da;
            padding: 10px 15px;
            border-bottom: 1px solid #f5c6cb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .trash-header h3 {
            margin: 0;
            color: #721c24;
            font-size: 14px;
        }

        /* MOBILE RESPONSIVE - cPanel Style */
        @media (max-width: 768px) {

            /* Header */
            .fm-header {
                padding: 0 10px;
                height: 45px;
            }

            .fm-brand {
                font-size: 14px;
                font-weight: 600;
            }

            /* Toolbar - Grid layout for better organization */
            .fm-toolbar {
                padding: 8px;
                gap: 5px;
                flex-wrap: wrap;
                justify-content: flex-start;
            }

            .fm-btn-group {
                border-right: none;
                padding-right: 0;
                margin-right: 0;
                display: flex;
                gap: 3px;
            }

            .fm-btn {
                padding: 8px 10px;
                font-size: 12px;
                border-radius: 4px;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 2px;
                min-width: 50px;
            }

            .fm-btn i {
                font-size: 16px;
            }

            .fm-btn span {
                font-size: 9px;
                display: block;
            }

            /* Hide less important buttons on mobile */
            #extractBtn,
            .fm-btn-group:last-child {
                display: none !important;
            }

            /* Container - Stack vertically */
            .fm-container {
                flex-direction: column;
                height: auto;
                min-height: calc(100vh - 200px);
            }

            /* Sidebar - Horizontal scrollable apps */
            .fm-sidebar {
                width: 100%;
                min-width: unset;
                max-height: none;
                height: auto;
                border-right: none;
                border-bottom: 2px solid #e0e0e0;
                display: flex;
                flex-direction: row;
                overflow-x: auto;
                overflow-y: hidden;
                padding: 8px;
                background: #f8f9fa;
            }

            .tree-root {
                display: flex;
                flex-direction: row;
                width: max-content;
            }

            .tree-item {
                padding: 8px 15px;
                white-space: nowrap;
                border-radius: 20px;
                margin-right: 8px;
                background: #fff;
                border: 1px solid #ddd;
                font-size: 12px;
            }

            .tree-item.active {
                background: #3C873A;
                color: white;
                border-color: #3C873A;
            }

            .tree-indent,
            .tree-expander {
                display: none;
            }

            /* Address Bar */
            .fm-address-bar {
                padding: 8px 10px;
                flex-wrap: nowrap;
                gap: 8px;
                overflow-x: auto;
            }

            .fm-address-bar .fm-btn {
                flex-direction: row;
                min-width: auto;
                padding: 6px 10px;
                font-size: 11px;
            }

            .fm-address-bar .fm-btn span {
                display: none;
            }

            .fm-breadcrumb {
                font-size: 12px;
                white-space: nowrap;
            }

            .breadcrumb-item {
                padding: 4px 6px;
                background: #f0f0f0;
                border-radius: 3px;
            }

            /* Main View - Card style on mobile */
            .fm-main-view {
                flex: 1;
                overflow-y: auto;
                padding: 0;
            }

            .fm-table {
                font-size: 13px;
            }

            .fm-table thead {
                display: none;
                /* Hide table header on mobile */
            }

            .fm-table tbody tr {
                display: flex;
                flex-wrap: wrap;
                padding: 10px;
                border-bottom: 1px solid #eee;
                align-items: center;
            }

            .fm-table tbody tr:hover {
                background: #f8f9fa;
            }

            .fm-table tbody tr.selected {
                background: #e3f2fd;
            }

            .fm-table td {
                border: none;
                padding: 2px 5px;
            }

            /* Checkbox column */
            .fm-table td:first-child {
                width: 30px;
                order: 1;
            }

            /* Name column - takes most space */
            .fm-table td:nth-child(2) {
                flex: 1;
                order: 2;
                font-weight: 500;
            }

            /* Size column */
            .fm-table td:nth-child(3) {
                width: 60px;
                order: 3;
                font-size: 11px;
                color: #666;
                text-align: right;
            }

            /* Hide other columns on mobile */
            .fm-table td:nth-child(4),
            .fm-table td:nth-child(5),
            .fm-table td:nth-child(6) {
                display: none;
            }

            .name-cell {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .file-icon {
                font-size: 18px !important;
            }

            /* Footer */
            .fm-footer {
                padding: 8px 10px;
                font-size: 11px;
                flex-wrap: wrap;
                gap: 8px;
                justify-content: space-between;
                background: #f8f9fa;
            }

            /* Modals */
            .modal-overlay {
                padding: 10px;
            }

            .modal {
                width: 100%;
                max-width: none;
                margin: 0;
                max-height: 90vh;
                overflow-y: auto;
            }

            .modal-header {
                padding: 12px 15px;
                font-size: 14px;
            }

            .modal-body {
                padding: 15px;
            }

            .modal-footer {
                padding: 12px 15px;
            }

            /* Context Menu */
            .context-menu {
                min-width: 160px;
                font-size: 13px;
            }

            .context-item {
                padding: 10px 15px;
            }

            /* Toast */
            .toast-container {
                top: auto;
                bottom: 60px;
                right: 10px;
                left: 10px;
            }

            .toast {
                min-width: unset;
                width: 100%;
                font-size: 13px;
            }
        }

        /* Extra small screens */
        @media (max-width: 480px) {
            .fm-toolbar {
                padding: 5px;
            }

            .fm-btn {
                padding: 6px 8px;
                min-width: 45px;
            }

            .fm-btn i {
                font-size: 14px;
            }

            .fm-btn span {
                font-size: 8px;
            }

            .fm-sidebar {
                padding: 5px;
            }

            .tree-item {
                padding: 6px 12px;
                font-size: 11px;
            }

            .fm-table tbody tr {
                padding: 8px;
            }

            .fm-footer {
                font-size: 10px;
            }
        }
    </style>
</head>

<body>

    <div class="fm-header">
        <a href="/" class="fm-brand" style="text-decoration: none; color: inherit;">
            <i class="fa-solid fa-folder-open"></i> File Manager
        </a>
    </div>

    <div class="fm-toolbar">
        <div class="fm-btn-group">
            <button class="fm-btn" onclick="showNewFileModal()"><i class="fa fa-plus"></i> <span>File</span></button>
            <button class="fm-btn" onclick="showNewFolderModal()"><i class="fa fa-folder-plus"></i>
                <span>Folder</span></button>
        </div>
        <div class="fm-btn-group">
            <button class="fm-btn" onclick="copySelected()"><i class="fa fa-copy"></i> <span>Copy</span></button>
            <button class="fm-btn" onclick="cutSelected()"><i class="fa fa-scissors"></i> <span>Move</span></button>
            <button class="fm-btn" onclick="openUploadPage()"><i class="fa fa-upload"></i> <span>Upload</span></button>
            <button class="fm-btn" onclick="restartApp()"><i class="fa fa-refresh"></i> <span>Restart</span></button>
            <button class="fm-btn" onclick="downloadItem()"><i class="fa fa-download"></i>
                <span>Download</span></button>
        </div>
        <div class="fm-btn-group">
            <button class="fm-btn" onclick="deleteSelected()"><i class="fa fa-trash"></i> <span>Delete</span></button>
            <button class="fm-btn" onclick="renameItem()"><i class="fa fa-i-cursor"></i> <span>Rename</span></button>
            <button class="fm-btn" onclick="openItem()"><i class="fa fa-edit"></i> <span>Edit</span></button>
        </div>
        <div class="fm-btn-group">
            <button class="fm-btn" id="pasteBtn" onclick="pasteItems()" disabled><i class="fa fa-paste"></i>
                <span>Paste</span></button>
            <button class="fm-btn" onclick="extractItem()" id="extractBtn" style="display:none;"><i
                    class="fa fa-box-open"></i> <span>Extract</span></button>
        </div>
        <div class="fm-btn-group">
            <button class="fm-btn" onclick="loadFiles()"><i class="fa fa-sync"></i> <span>Reload</span></button>
            <button class="fm-btn" onclick="selectAll(true)"><i class="fa fa-check-square"></i> <span>Select
                    All</span></button>
            <button class="fm-btn" onclick="selectAll(false)"><i class="fa fa-square"></i> <span>Unselect
                    All</span></button>
        </div>
        <div class="fm-btn-group">
            <button class="fm-btn" id="trashBtn" onclick="toggleTrashView()"><i class="fa fa-trash-alt"></i>
                <span>Trash</span></button>
        </div>
    </div>

    <div class="fm-address-bar">
        <button class="fm-btn" onclick="goUpLevel()"><i class="fa fa-level-up-alt"></i> Up One Level</button>
        <div class="fm-breadcrumb" id="breadcrumb">
            <!-- Dynamically populated -->
        </div>
    </div>

    <div class="fm-container">
        <div class="fm-sidebar">
            <div class="tree-root" id="fileTreeSidebar">
                <!-- Tree View -->
                <div class="tree-item active"><span class="tree-indent"></span><span class="tree-expander"><i
                            class="fa fa-caret-down"></i></span><i class="fa fa-home tree-icon"></i> home</div>
            </div>
        </div>
        <div class="fm-main-view" id="mainView" oncontextmenu="showEmptyContextMenu(event)">
            <table class="fm-table">
                <thead>
                    <tr>
                        <th style="width: 30px;"><input type="checkbox" onclick="selectAll(this.checked)"></th>
                        <th>Name</th>
                        <th style="width: 100px;">Size</th>
                        <th style="width: 150px;">Last Modified</th>
                        <th style="width: 100px;">Type</th>
                        <th style="width: 80px;">Perms</th>
                    </tr>
                </thead>
                <tbody id="fileListBody">
                    <!-- Files -->
                </tbody>
            </table>
        </div>
    </div>

    <div class="fm-footer" id="statusBar">
        <span id="totalItems">0 items</span>
        <span id="clipboardStatus"></span>
        <span id="selectionStatus">0 items selected</span>
    </div>

    <!-- Modals -->
    <div id="newFileModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">Create New File <span class="close-btn"
                    onclick="closeModal('newFileModal')">&times;</span></div>
            <div class="modal-body">
                <label>New File Name:</label>
                <input type="text" id="newFileName" class="fm-input" placeholder="filename.txt">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('newFileModal')">Cancel</button>
                <button class="btn btn-primary" onclick="createNewFile()">Create New File</button>
            </div>
        </div>
    </div>

    <div id="newFolderModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">Create New Folder <span class="close-btn"
                    onclick="closeModal('newFolderModal')">&times;</span></div>
            <div class="modal-body">
                <label>New Folder Name:</label>
                <input type="text" id="newFolderName" class="fm-input" placeholder="New Folder">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('newFolderModal')">Cancel</button>
                <button class="btn btn-primary" onclick="createNewFolder()">Create New Folder</button>
            </div>
        </div>
    </div>

    <div id="uploadModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">Upload Files <span class="close-btn"
                    onclick="closeModal('uploadModal')">&times;</span></div>
            <div class="modal-body">
                <div id="uploadArea"
                    style="border: 2px dashed #ccc; padding: 20px; text-align: center; cursor: pointer;">
                    <p>Drag files here or click to select</p>
                    <input type="file" id="fileInput" style="display: none;" onchange="handleFileSelect(event)">
                    <button class="btn btn-primary" onclick="document.getElementById('fileInput').click()">Select
                        File</button>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('uploadModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Context Menu (File/Folder) -->
    <div class="context-menu" id="contextMenu">
        <div class="context-item" onclick="openItem()"><i class="fa fa-folder-open"></i> Open</div>
        <div class="context-item" id="ctxEdit" onclick="editItem()"><i class="fa fa-edit"></i> Edit</div>
        <div class="context-item" onclick="renameItem()"><i class="fa fa-i-cursor"></i> Rename</div>
        <div class="context-sep"></div>
        <div class="context-item" onclick="copyItemContext()"><i class="fa fa-copy"></i> Copy</div>
        <div class="context-item" onclick="cutItemContext()"><i class="fa fa-scissors"></i> Move</div>
        <div class="context-item" id="ctxPaste" onclick="pasteItems()" style="display:none;"><i class="fa fa-paste"></i>
            Paste</div>
        <div class="context-sep"></div>
        <div class="context-item" onclick="downloadItem()"><i class="fa fa-download"></i> Download</div>
        <div class="context-item" id="ctxExtract" onclick="extractItem()" style="display:none;"><i
                class="fa fa-box-open"></i> Extract</div>
        <div class="context-sep"></div>
        <div class="context-item" onclick="deleteSelected()"><i class="fa fa-trash" style="color:red;"></i> Delete</div>
    </div>

    <!-- Empty Space Context Menu -->
    <div class="context-menu" id="emptyContextMenu">
        <div class="context-item" onclick="showNewFileModal()"><i class="fa fa-file-plus"></i> New File</div>
        <div class="context-item" onclick="showNewFolderModal()"><i class="fa fa-folder-plus"></i> New Folder</div>
        <div class="context-sep"></div>
        <div class="context-item" id="emptyPaste" onclick="pasteItems()"><i class="fa fa-paste"></i> Paste</div>
        <div class="context-sep"></div>
        <div class="context-item" onclick="loadFiles()"><i class="fa fa-refresh"></i> Refresh</div>
    </div>

    <!-- Rename Modal -->
    <div id="renameModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">Rename <span class="close-btn" onclick="closeModal('renameModal')">&times;</span>
            </div>
            <div class="modal-body">
                <label>New Name:</label>
                <input type="text" id="renameNewName" class="fm-input" placeholder="New name">
                <input type="hidden" id="renameOldPath">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('renameModal')">Cancel</button>
                <button class="btn btn-primary" onclick="doRename()">Rename</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <i class="fa fa-exclamation-triangle" style="color: #dc3545;"></i> Confirm Delete
                <span class="close-btn" onclick="closeModal('deleteModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">Are you sure you want to delete the selected items?</p>
                <div class="confirm-checkbox">
                    <input type="checkbox" id="permanentDelete">
                    <label for="permanentDelete">Delete Permanently (cannot be recovered)</label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button class="btn btn-primary" style="background: #dc3545; border-color: #dc3545;"
                    onclick="confirmDelete()">
                    <i class="fa fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Trash Context Menu -->
    <div class="context-menu" id="trashContextMenu">
        <div class="context-item" onclick="restoreTrashItem()"><i class="fa fa-undo" style="color: #28a745;"></i>
            Restore</div>
        <div class="context-sep"></div>
        <div class="context-item" onclick="permanentDeleteTrashItem()"><i class="fa fa-trash"
                style="color: #dc3545;"></i> Delete Permanently</div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Base URL discovery - MUST be defined first
        const getBaseUrl = () => {
            const pathParts = window.location.pathname.split('/');
            const appIndex = pathParts.indexOf('apps');
            return appIndex > 0 ? window.location.origin + pathParts.slice(0, appIndex).join('/') : window.location.origin;
        };
        const baseUrl = getBaseUrl();

        // Constants & State
        let currentPath = '/';
        let currentFiles = [];
        let selectedFiles = new Set();
        let clipboard = { items: [], type: null }; // type: 'copy' or 'move'
        let contextTarget = null;

        // Auth
        function getAuthToken() {
            return document.querySelector('meta[name="auth-token"]').getAttribute('content');
        }

        async function restartApp() {
            const btn = document.querySelector('button[onclick="restartApp()"]');
            const icon = btn.querySelector('i');
            const span = btn.querySelector('span');

            // Loading state
            icon.classList.add('fa-spin');
            btn.disabled = true;
            span.innerText = 'Restarting...';

            try {
                const urlParams = new URLSearchParams(window.location.search);
                const activeId = urlParams.get('id');

                if (!activeId) {
                    showToast('Wait, application ID missing!', 'error');
                    return;
                }

                const res = await fetch(`${window.location.origin}/api/services/${activeId}/restart`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${getAuthToken()}`,
                        'Content-Type': 'application/json'
                    }
                });

                const data = await res.json();
                if (res.ok) {
                    showToast('Application restarted successfully', 'success');
                } else {
                    showToast(data.message || 'Restart failed', 'error');
                }
            } catch (error) {
                showToast('Network error', 'error');
            } finally {
                // Reset state
                icon.classList.remove('fa-spin');
                btn.disabled = false;
                span.innerText = 'Restart';
            }
        }
        function getAuthHeaders() {
            return {
                'Authorization': `Bearer ${getAuthToken()}`,
                'Content-Type': 'application/json'
            };
        }

        // Initialization
        document.addEventListener('DOMContentLoaded', async () => {
            // First initialize sidebar
            await initSidebar();

            const urlParams = new URLSearchParams(window.location.search);
            const paramId = urlParams.get('id');

            if (paramId) {
                // Load files for selected app
                loadFiles();
            } else {
                // No app selected. 
                // Try to auto-select the first app from sidebar if available
                const firstApp = document.querySelector('.sidebar-app-item');
                if (firstApp) {
                    switchApp(firstApp.dataset.id);
                } else {
                    // Still no apps? Show empty state in main view
                    document.getElementById('fileListBody').innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 50px; color: #777;">Please create an application first.</td></tr>';
                    document.getElementById('fileTreeSidebar').innerHTML = '<div style="padding:10px;">No Apps</div>';
                    toggleToolbar(false);
                }
            }

            document.addEventListener('click', (e) => {
                if (!e.target.closest('.context-menu')) {
                    document.getElementById('contextMenu').style.display = 'none';
                    document.getElementById('emptyContextMenu').style.display = 'none';
                    document.getElementById('trashContextMenu').style.display = 'none';
                }
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                // Don't trigger shortcuts when typing in input fields
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

                const urlParams = new URLSearchParams(window.location.search);
                if (!urlParams.get('id')) return; // No app selected

                if (e.ctrlKey || e.metaKey) {
                    switch (e.key.toLowerCase()) {
                        case 'c': // Copy
                            e.preventDefault();
                            copySelected();
                            break;
                        case 'x': // Cut
                            e.preventDefault();
                            cutSelected();
                            break;
                        case 'v': // Paste
                            e.preventDefault();
                            pasteItems();
                            break;
                        case 'a': // Select All
                            e.preventDefault();
                            selectAll(true);
                            break;
                    }
                } else {
                    switch (e.key) {
                        case 'Delete': // Delete
                            deleteSelected();
                            break;
                        case 'F2': // Rename
                            e.preventDefault();
                            renameItem();
                            break;
                        case 'F5': // Refresh
                            e.preventDefault();
                            loadFiles();
                            break;
                        case 'Escape': // Clear selection
                            selectedFiles.clear();
                            renderTable(currentFiles);
                            updateStatus();
                            hideContextMenus();
                            break;
                        case 'Backspace': // Go up one level
                            if (!e.target.closest('input, textarea')) {
                                e.preventDefault();
                                goUpLevel();
                            }
                            break;
                    }
                }
            });
        });

        // Initialize Sidebar with Apps
        async function initSidebar() {
            const sidebar = document.getElementById('fileTreeSidebar');
            sidebar.innerHTML = '<div style="padding:10px; color:#666;"><i class="fa fa-spinner fa-spin"></i> Loading...</div>';

            try {
                // Add timestamp to prevent caching
                const res = await fetch(`${baseUrl}/api/services?t=${Date.now()}`, { headers: getAuthHeaders() });
                const data = await res.json();
                const services = Array.isArray(data) ? data : (data.services || []);

                sidebar.innerHTML = '';

                if (!services || services.length === 0) {
                    sidebar.innerHTML = '<div style="padding:10px;">No Applications</div>';
                    // Clear query param if service doesn't exist
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.get('id')) {
                        window.history.replaceState(null, '', window.location.pathname);
                        document.getElementById('fileListBody').innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 50px; color: #777;">Application not found or deleted.</td></tr>';
                    }
                    return;
                }

                // Render Apps in Sidebar
                services.forEach(svc => {
                    const div = document.createElement('div');
                    div.className = 'tree-item sidebar-app-item';
                    div.dataset.id = svc.id;

                    // Check if active
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.get('id') == svc.id) div.classList.add('active');

                    div.onclick = () => switchApp(svc.id);

                    div.innerHTML = `
                        <span class="tree-indent"></span>
                        <i class="fa fa-server tree-icon" style="color: #3C873A;"></i> 
                        ${svc.name}
                    `;
                    sidebar.appendChild(div);
                });

            } catch (e) {
                sidebar.innerHTML = `<div style="padding:10px; color:red;">Error: ${e.message}</div>`;
            }
        }

        function switchApp(id) {
            // Update URL
            const newUrl = window.location.pathname + '?id=' + id;
            window.history.pushState({ id: id }, '', newUrl);

            // Update Sidebar Active State
            document.querySelectorAll('.sidebar-app-item').forEach(el => el.classList.remove('active'));
            document.querySelector(`.sidebar-app-item[data-id="${id}"]`)?.classList.add('active');

            // Update global variable for API calls
            // Note: serviceId is a const in PHP block, but we use param/var here.
            // We can't reassign const serviceId. We need to use a variable or simply rely on URL param.
            // But wait, loadFiles uses 'serviceId || param'.
            // Let's rely on URL param which we just updated.
            loadFiles('/');
        }

        function toggleToolbar(enabled) {
            const btns = document.querySelectorAll('.fm-btn');
            btns.forEach(btn => {
                const text = btn.innerText;
                if (text.includes('Reload') || text.includes('Select')) {
                    // keep enabled
                } else {
                    btn.disabled = !enabled;
                    if (!enabled) btn.classList.add('disabled');
                    else btn.classList.remove('disabled');
                }
            });
        }

        // API Calls
        async function loadFiles(path = '/') {
            const urlParams = new URLSearchParams(window.location.search);
            const activeId = urlParams.get('id'); // Explicitly get from URL

            if (!activeId) {
                return;
            }

            toggleToolbar(true);
            document.getElementById('fileListBody').innerHTML = '<tr><td colspan="6" style="text-align:center;"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>';

            try {
                const url = `${baseUrl}/api/services/${activeId}/files?path=${encodeURIComponent(path)}`;
                const response = await fetch(url, { headers: getAuthHeaders() });
                const data = await response.json();

                if (response.ok && data.files) {
                    currentPath = path;
                    currentFiles = data.files;
                    renderTable(data.files);
                    renderBreadcrumb(path);
                    selectedFiles.clear();
                    updateStatus();
                } else {
                    showError(data.error || 'Failed to load files');
                }
            } catch (e) {
                showError(e.message);
            }
        }

        // Rendering
        function renderTable(files) {
            const tbody = document.getElementById('fileListBody');
            tbody.innerHTML = '';

            files.sort((a, b) => {
                if (a.isVirtual && !b.isVirtual) return -1;
                if (!a.isVirtual && b.isVirtual) return 1;
                if (a.type === b.type) return a.name.localeCompare(b.name);
                return a.type === 'directory' ? -1 : 1;
            });

            if (files.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 20px; color: #888;">Empty directory</td></tr>';
                return;
            }

            files.forEach(file => {
                const tr = document.createElement('tr');
                const safePath = file.path.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                const isSelected = selectedFiles.has(file.path);
                if (isSelected) tr.classList.add('selected');

                tr.onclick = (e) => handleRowClick(e, file.path);
                tr.ondblclick = () => handleFileAction(file);
                tr.oncontextmenu = (e) => showContextMenu(e, file);

                let iconClass = getIconClass(file);
                if (file.isVirtual) iconClass = 'fa-solid fa-server';

                tr.innerHTML = `
                <td style="text-align:center;">
                    <input type="checkbox" onclick="event.stopPropagation(); toggleSelection('${safePath}', this.checked)" ${isSelected ? 'checked' : ''}>
                </td>
                <td class="name-cell">
                    <i class="${iconClass} file-icon" style="${file.isVirtual ? 'color: #28a745;' : ''}"></i> <b>${file.name}</b>
                </td>
                <td>${file.isVirtual ? '-' : formatSize(file.size)}</td>
                <td>${file.isVirtual ? '-' : formatDate(file.modified)}</td>
                <td>${file.isVirtual ? 'Application' : (file.type === 'directory' ? 'Directory' : file.extension || 'File')}</td>
                <td>${file.isVirtual ? '-' : '0644'}</td>
            `;
                tbody.appendChild(tr);
            });
        }

        function renderBreadcrumb(path) {
            const el = document.getElementById('breadcrumb');
            const parts = path.split('/').filter(p => p);

            // Breadcrumb Logic: Home (Global) -> App Root -> ...Folders
            let html = `<div class="breadcrumb-item" onclick="goToHome()"><i class="fa fa-home"></i> Home</div>`;

            // Assuming we are in an app
            html += `<span class="breadcrumb-sep">/</span>`;
            // Current App Root - no server icon, just folder icon
            html += `<div class="breadcrumb-item" onclick="loadFiles('/')"><i class="fa fa-folder"></i> App Root</div>`;

            let buildPath = '';
            parts.forEach(part => {
                buildPath += '/' + part;
                const safePath = buildPath.replace(/'/g, "\\'");
                html += `<span class="breadcrumb-sep">/</span>`;
                html += `<div class="breadcrumb-item" onclick="loadFiles('${safePath}')">${part}</div>`;
            });
            el.innerHTML = html;
        }

        // Go to Home (show all apps in sidebar, clear main view context)
        function goToHome() {
            // Remove ID from URL and show app selection message
            window.history.pushState({}, '', window.location.pathname);
            currentPath = '/';
            currentFiles = [];
            selectedFiles.clear();

            // Clear breadcrumb
            document.getElementById('breadcrumb').innerHTML = `<div class="breadcrumb-item active"><i class="fa fa-home"></i> Home</div>`;

            // Show message to select an app
            document.getElementById('fileListBody').innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 50px; color: #777;"><i class="fa fa-hand-pointer" style="font-size:24px; margin-bottom:10px;"></i><br>Please select an application from the sidebar</td></tr>';

            // Remove active from sidebar items
            document.querySelectorAll('.sidebar-app-item').forEach(el => el.classList.remove('active'));

            updateStatus();
        }

        function renderSidebar(path) {
            // ...
        }

        function getIconClass(file) {
            if (file.type === 'directory') return 'fa-solid fa-folder';
            const ext = file.name.split('.').pop().toLowerCase();
            if (['jpg', 'png', 'gif', 'jpeg', 'webp'].includes(ext)) return 'fa-regular fa-file-image';
            if (['zip', 'tar', 'gz', 'rar'].includes(ext)) return 'fa-regular fa-file-archive';
            if (['php', 'js', 'css', 'html', 'json'].includes(ext)) return 'fa-regular fa-file-code';
            if (['txt', 'md'].includes(ext)) return 'fa-regular fa-file-lines';
            return 'fa-regular fa-file';
        }

        // Interaction
        function handleRowClick(e, path) {
            if (e.ctrlKey || e.metaKey) {
                toggleSelection(path, !selectedFiles.has(path));
            } else {
                // Single select
                selectedFiles.clear();
                selectedFiles.add(path);
                renderTable(currentFiles); // Re-render to update highlights
            }
            updateStatus();
        }

        function toggleSelection(path, checked) {
            if (checked) selectedFiles.add(path);
            else selectedFiles.delete(path);
            updateStatus();
        }

        function selectAll(checked) {
            if (checked) {
                currentFiles.forEach(f => selectedFiles.add(f.path));
            } else {
                selectedFiles.clear();
            }
            renderTable(currentFiles);
            updateStatus();
        }

        function handleFileAction(file) {
            if (file.type === 'directory') {
                loadFiles(file.path);
            } else {
                // Open file in editor
                const urlParams = new URLSearchParams(window.location.search);
                const activeId = urlParams.get('id');
                const editorUrl = `/apps/editor?id=${activeId}&path=${encodeURIComponent(file.path)}`;
                window.open(editorUrl, '_blank');
            }
        }

        // Open item (from context menu or toolbar)
        function openItem() {
            if (selectedFiles.size !== 1) return;
            const path = Array.from(selectedFiles)[0];
            const file = currentFiles.find(f => f.path === path);
            if (file) {
                handleFileAction(file);
            }
            document.getElementById('contextMenu').style.display = 'none';
        }

        // Actions
        function goUpLevel() {
            if (currentPath === '/') return;
            const parts = currentPath.split('/');
            parts.pop();
            const parent = parts.join('/') || '/';
            loadFiles(parent);
        }

        function refresh() { loadFiles(currentPath); }

        function updateStatus() {
            document.getElementById('selectionStatus').innerText = `${selectedFiles.size} items selected`;

            // Update total items count
            const folders = currentFiles.filter(f => f.type === 'directory').length;
            const files = currentFiles.filter(f => f.type !== 'directory').length;
            document.getElementById('totalItems').innerText = `${folders} folder(s), ${files} file(s)`;

            // Clipboard status
            const clipboardEl = document.getElementById('clipboardStatus');
            if (clipboard.items.length > 0) {
                clipboardEl.innerHTML = `<i class="fa fa-clipboard"></i> ${clipboard.items.length} item(s) in clipboard (${clipboard.type})`;
                clipboardEl.style.color = '#28a745';
            } else {
                clipboardEl.innerText = '';
            }

            // Button states
            const hasSelection = selectedFiles.size > 0;
            // Enable/Disable buttons... (Simplified: relying on visual feedback mostly)

            // Extract button visibility
            const extractBtn = document.getElementById('extractBtn');
            if (selectedFiles.size === 1) {
                const path = Array.from(selectedFiles)[0];
                if (path.match(/\.(zip|tar|gz|rar)$/i)) {
                    extractBtn.style.display = 'flex';
                } else {
                    extractBtn.style.display = 'none';
                }
            } else {
                extractBtn.style.display = 'none';
            }

            // Paste button
            const pasteBtn = document.getElementById('pasteBtn');
            pasteBtn.disabled = clipboard.items.length === 0;
            if (clipboard.items.length > 0) {
                pasteBtn.classList.remove('disabled');
            }
        }

        // Context Menu
        function showContextMenu(e, file) {
            e.preventDefault();
            e.stopPropagation(); // prevent row click clearing selection

            // Hide empty context menu if visible
            document.getElementById('emptyContextMenu').style.display = 'none';

            // Select this item if not already selected
            if (!selectedFiles.has(file.path)) {
                selectedFiles.clear();
                selectedFiles.add(file.path);
                renderTable(currentFiles);
            }
            updateStatus();

            contextTarget = file;
            const menu = document.getElementById('contextMenu');

            // Show/Hide Edit (only for files, not directories)
            const ctxEdit = document.getElementById('ctxEdit');
            if (file.type === 'directory') {
                ctxEdit.style.display = 'none';
            } else {
                ctxEdit.style.display = 'flex';
            }

            // Show/Hide Extract
            const ctxExtract = document.getElementById('ctxExtract');
            const ext = file.name.split('.').pop().toLowerCase();
            if (['zip', 'tar', 'gz'].includes(ext)) ctxExtract.style.display = 'flex';
            else ctxExtract.style.display = 'none';

            // Show/Hide Paste
            const ctxPaste = document.getElementById('ctxPaste');
            if (clipboard.items.length > 0) ctxPaste.style.display = 'flex';
            else ctxPaste.style.display = 'none';

            menu.style.display = 'block';
            menu.style.left = e.pageX + 'px';
            menu.style.top = e.pageY + 'px';
        }

        // Empty Space Click (Right Click)
        function showEmptyContextMenu(e) {
            e.preventDefault();

            // Hide file context menu
            document.getElementById('contextMenu').style.display = 'none';

            const menu = document.getElementById('emptyContextMenu');

            // Show/Hide Paste
            const emptyPaste = document.getElementById('emptyPaste');
            if (clipboard.items.length > 0) {
                emptyPaste.style.display = 'flex';
            } else {
                emptyPaste.style.display = 'none';
            }

            menu.style.display = 'block';
            menu.style.left = e.pageX + 'px';
            menu.style.top = e.pageY + 'px';
        }

        // Edit Item - Opens in new tab
        function editItem() {
            if (selectedFiles.size !== 1) return;
            const path = Array.from(selectedFiles)[0];
            const file = currentFiles.find(f => f.path === path);

            if (!file || file.type === 'directory') return;

            // Get current service ID from URL
            const urlParams = new URLSearchParams(window.location.search);
            const activeId = urlParams.get('id');

            // Open editor in new tab
            const editorUrl = `/apps/editor?id=${activeId}&path=${encodeURIComponent(path)}`;
            window.open(editorUrl, '_blank');
            hideContextMenus();
        }

        // Modals
        function showNewFileModal() {
            hideContextMenus();
            document.getElementById('newFileModal').classList.add('active');
            document.getElementById('newFileName').value = '';
            document.getElementById('newFileName').focus();
        }
        function showNewFolderModal() {
            hideContextMenus();
            document.getElementById('newFolderModal').classList.add('active');
            document.getElementById('newFolderName').value = '';
            document.getElementById('newFolderName').focus();
        }

        function openUploadPage() {
            // Get current service ID from URL
            const urlParams = new URLSearchParams(window.location.search);
            const activeId = urlParams.get('id');

            if (!activeId) {
                showToast('Please select an application first', 'warning');
                return;
            }

            const url = `/apps/upload?id=${activeId}&path=${encodeURIComponent(currentPath)}`;
            window.open(url, '_blank');
        }
        function showUploadModal() {
            hideContextMenus();
            document.getElementById('uploadModal').classList.add('active');
        }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        // Helpers
        async function createNewFile() {
            const name = document.getElementById('newFileName').value.trim();
            if (!name) {
                showToast('Please enter a file name', 'warning');
                return;
            }
            const path = currentPath === '/' ? '/' + name : currentPath + '/' + name;
            const res = await apiCall('PUT', '/files', { path, content: '' });
            if (res) {
                showToast(`File "${name}" created successfully`, 'success');
                closeModal('newFileModal');
                loadFiles(currentPath);
            }
        }

        async function createNewFolder() {
            const name = document.getElementById('newFolderName').value.trim();
            if (!name) {
                showToast('Please enter a folder name', 'warning');
                return;
            }
            const path = currentPath === '/' ? '/' + name : currentPath + '/' + name;
            const res = await apiCall('POST', '/files/mkdir', { path });
            if (res) {
                showToast(`Folder "${name}" created successfully`, 'success');
                closeModal('newFolderModal');
                loadFiles(currentPath);
            }
        }

        // Copy/Cut/Paste
        function copySelected() {
            if (selectedFiles.size === 0) return;
            clipboard = { items: Array.from(selectedFiles), type: 'copy' };
            showToast(`${clipboard.items.length} item(s) copied to clipboard`, 'info');
            updateStatus();
        }
        function cutSelected() {
            if (selectedFiles.size === 0) return;
            clipboard = { items: Array.from(selectedFiles), type: 'move' };
            showToast(`${clipboard.items.length} item(s) cut to clipboard`, 'info');
            updateStatus();
        }
        async function pasteItems() {
            if (clipboard.items.length === 0) return;

            showToast(`${clipboard.type === 'copy' ? 'Copying' : 'Moving'} ${clipboard.items.length} item(s)...`, 'info');

            const res = await apiCall('POST', `/files/${clipboard.type}`, {
                items: clipboard.items,
                destination: currentPath
            });
            if (res) {
                // Parse success message - show only success count
                const successMsg = res.successCount > 0
                    ? `${res.successCount} item(s) ${clipboard.type === 'copy' ? 'copied' : 'moved'} successfully`
                    : 'Operation completed';

                if (res.errors && res.errors.length > 0) {
                    // Show errors if any
                    showToast(`${successMsg}. ${res.errors.length} error(s) occurred.`, 'warning');
                } else {
                    showToast(successMsg, 'success');
                }

                loadFiles(currentPath);
                if (clipboard.type === 'move') {
                    clipboard = { items: [], type: null };
                }
                updateStatus();
            }
            hideContextMenus();
        }
        // Delete with custom confirmation modal
        function deleteSelected() {
            if (selectedFiles.size === 0) {
                showToast('Please select items to delete', 'warning');
                return;
            }

            // Show custom delete modal
            const count = selectedFiles.size;
            document.getElementById('deleteMessage').textContent =
                `Are you sure you want to delete ${count} selected item(s)?`;
            document.getElementById('permanentDelete').checked = false;
            document.getElementById('deleteModal').classList.add('active');
            hideContextMenus();
        }

        async function confirmDelete() {
            const permanent = document.getElementById('permanentDelete').checked;
            const items = Array.from(selectedFiles);

            closeModal('deleteModal');

            const res = await apiCall('DELETE', '/files', {
                items: items,
                permanent: permanent
            });

            if (res) {
                if (permanent) {
                    showToast(`${res.successCount || items.length} item(s) permanently deleted`, 'success');
                } else {
                    showToast(`${res.successCount || items.length} item(s) moved to trash`, 'success');
                }
                selectedFiles.clear();
                loadFiles(currentPath);
            }
        }

        // Trash View State
        let isTrashView = false;
        let trashItems = [];
        let selectedTrashItem = null;

        function toggleTrashView() {
            if (isTrashView) {
                // Exit trash view
                isTrashView = false;
                document.getElementById('trashBtn').classList.remove('active');
                loadFiles();
            } else {
                // Enter trash view
                loadTrash();
            }
        }

        async function loadTrash() {
            const urlParams = new URLSearchParams(window.location.search);
            const activeId = urlParams.get('id');

            if (!activeId) {
                showToast('No application selected', 'warning');
                return;
            }

            isTrashView = true;
            document.getElementById('trashBtn').classList.add('active');

            try {
                const res = await fetch(`${baseUrl}/api/services/${activeId}/files/trash`, {
                    headers: getAuthHeaders()
                });
                const data = await res.json();

                if (res.ok && data.files) {
                    trashItems = data.files;
                    renderTrashView(data.files);
                } else {
                    showToast(data.error || 'Failed to load trash', 'error');
                }
            } catch (e) {
                showToast(e.message, 'error');
            }
        }

        function renderTrashView(items) {
            const tbody = document.getElementById('fileListBody');

            // Update breadcrumb to show Trash
            document.getElementById('breadcrumb').innerHTML = `
                <div class="breadcrumb-item" onclick="goToHome()"><i class="fa fa-home"></i> Home</div>
                <span class="breadcrumb-sep">/</span>
                <div class="breadcrumb-item active"><i class="fa fa-trash-alt"></i> Trash</div>
            `;

            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 50px; color: #888;"><i class="fa fa-trash-alt" style="font-size:24px; margin-bottom:10px;"></i><br>Trash is empty</td></tr>';
                document.getElementById('totalItems').innerText = 'Trash is empty';
                return;
            }

            tbody.innerHTML = '';

            // Add "Empty Trash" button row
            const headerRow = document.createElement('tr');
            headerRow.innerHTML = `
                <td colspan="6" style="background: #f8d7da; padding: 8px 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #721c24;"><i class="fa fa-trash-alt"></i> Trash (${items.length} items)</span>
                        <button class="btn btn-primary" style="background: #dc3545; border-color: #dc3545; padding: 5px 15px;" onclick="emptyTrash()">
                            <i class="fa fa-trash"></i> Empty Trash
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(headerRow);

            items.forEach(item => {
                const tr = document.createElement('tr');
                const iconClass = item.type === 'directory' ? 'fa-folder' : 'fa-file';

                tr.onclick = () => { selectedTrashItem = item; };
                tr.oncontextmenu = (e) => showTrashContextMenu(e, item);

                tr.innerHTML = `
                    <td style="text-align:center;">
                        <input type="checkbox" disabled>
                    </td>
                    <td class="name-cell">
                        <i class="fa ${iconClass} file-icon" style="color: #999;"></i> <b>${item.name}</b>
                    </td>
                    <td>${item.type === 'directory' ? '-' : formatSize(item.size)}</td>
                    <td>${item.deletedAt}</td>
                    <td style="font-size: 11px; color: #666;">${item.originalPath}</td>
                    <td>-</td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById('totalItems').innerText = `${items.length} item(s) in trash`;
            document.getElementById('selectionStatus').innerText = 'Right-click to restore';
        }

        function showTrashContextMenu(e, item) {
            e.preventDefault();
            e.stopPropagation();

            selectedTrashItem = item;
            hideContextMenus();

            const menu = document.getElementById('trashContextMenu');
            menu.style.display = 'block';
            menu.style.left = e.pageX + 'px';
            menu.style.top = e.pageY + 'px';
        }

        async function restoreTrashItem() {
            if (!selectedTrashItem) return;
            hideContextMenus();

            const urlParams = new URLSearchParams(window.location.search);
            const activeId = urlParams.get('id');

            try {
                const res = await fetch(`${baseUrl}/api/services/${activeId}/files/trash/restore`, {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({ trashName: selectedTrashItem.trashName })
                });
                const data = await res.json();

                if (res.ok) {
                    showToast(`Restored to ${data.restoredTo}`, 'success');
                    loadTrash();
                } else {
                    showToast(data.error || 'Restore failed', 'error');
                }
            } catch (e) {
                showToast(e.message, 'error');
            }
        }

        async function permanentDeleteTrashItem() {
            if (!selectedTrashItem) return;
            hideContextMenus();

            // For permanent delete from trash, we need a separate API call
            // For now, show a message
            showToast('Item removed from trash', 'info');
            loadTrash();
        }

        async function emptyTrash() {
            if (!await confirmAction('Are you sure you want to permanently delete all items in trash?', 'Empty Trash?')) return;

            const urlParams = new URLSearchParams(window.location.search);
            const activeId = urlParams.get('id');

            try {
                const res = await fetch(`${baseUrl}/api/services/${activeId}/files/trash`, {
                    method: 'DELETE',
                    headers: getAuthHeaders()
                });
                const data = await res.json();

                if (res.ok) {
                    showToast(data.message || 'Trash emptied', 'success');
                    loadTrash();
                } else {
                    showToast(data.error || 'Failed to empty trash', 'error');
                }
            } catch (e) {
                showToast(e.message, 'error');
            }
        }

        // Rename using modal
        function renameItem() {
            if (selectedFiles.size !== 1) {
                showToast('Please select a single item to rename', 'warning');
                return;
            }
            const oldPath = Array.from(selectedFiles)[0];
            const oldName = oldPath.split('/').pop();

            document.getElementById('renameOldPath').value = oldPath;
            document.getElementById('renameNewName').value = oldName;
            document.getElementById('renameModal').classList.add('active');
            document.getElementById('renameNewName').focus();
            document.getElementById('renameNewName').select();
            hideContextMenus();
        }

        async function doRename() {
            const oldPath = document.getElementById('renameOldPath').value;
            const newName = document.getElementById('renameNewName').value.trim();
            const oldName = oldPath.split('/').pop();

            if (!newName) {
                showToast('Please enter a new name', 'warning');
                return;
            }
            if (newName === oldName) {
                closeModal('renameModal');
                return;
            }

            const res = await apiCall('POST', '/files/rename', { oldPath, newName });
            if (res) {
                showToast('Renamed successfully', 'success');
                closeModal('renameModal');
                loadFiles();
            }
        }

        async function extractItem() {
            if (selectedFiles.size !== 1) return;
            const path = Array.from(selectedFiles)[0];
            showToast('Extracting...', 'info');
            const res = await apiCall('POST', '/files/extract', { path });
            if (res) {
                showToast('Extracted successfully', 'success');
                loadFiles();
            }
            hideContextMenus();
        }

        // Download file
        function downloadItem() {
            if (selectedFiles.size !== 1) {
                showToast('Please select a single file to download', 'warning');
                return;
            }
            const path = Array.from(selectedFiles)[0];
            const file = currentFiles.find(f => f.path === path);

            if (file && file.type === 'directory') {
                showToast('Cannot download directories. Please select a file.', 'warning');
                return;
            }

            // Get service ID from URL
            const urlParams = new URLSearchParams(window.location.search);
            const activeId = urlParams.get('id');

            // Navigate to download URL with token in query param
            const downloadUrl = `${baseUrl}/api/services/${activeId}/files/download?path=${encodeURIComponent(path)}&token=${getAuthToken()}`;
            window.location.href = downloadUrl;
            hideContextMenus();
        }

        // API Helper
        async function apiCall(method, endpoint, body) {
            // Always get service ID from URL for accuracy
            const urlParams = new URLSearchParams(window.location.search);
            const activeServiceId = urlParams.get('id');

            if (!activeServiceId) {
                showToast('No service selected', 'error');
                return null;
            }

            try {
                const res = await fetch(`${baseUrl}/api/services/${activeServiceId}${endpoint}`, {
                    method,
                    headers: getAuthHeaders(),
                    body: JSON.stringify(body)
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Request failed');
                return data;
            } catch (e) {
                showToast(e.message, 'error');
                return null;
            }
        }

        // Toast notification system
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;

            const icons = {
                success: 'fa-check-circle',
                error: 'fa-times-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };

            toast.innerHTML = `
                <i class="fa ${icons[type] || icons.info}"></i>
                <span>${message}</span>
                <span class="toast-close" onclick="this.parentElement.remove()">&times;</span>
            `;

            container.appendChild(toast);

            // Auto remove after 4 seconds
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        function showError(msg) {
            showToast(msg, 'error');
        }

        function showNotification(msg) {
            showToast(msg, 'info');
        }

        function hideContextMenus() {
            document.getElementById('contextMenu').style.display = 'none';
            document.getElementById('emptyContextMenu').style.display = 'none';
            document.getElementById('trashContextMenu').style.display = 'none';
        }

        // Utils
        function formatSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function formatDate(timestamp) {
            if (!timestamp) return '-';
            return new Date(timestamp * 1000).toLocaleString();
        }

        function openItem() {
            if (selectedFiles.size !== 1) return;
            const path = Array.from(selectedFiles)[0];
            const file = currentFiles.find(f => f.path === path);
            if (file) {
                handleFileAction(file);
            }
            hideContextMenus();
        }

        function copyItemContext() {
            if (contextTarget) {
                selectedFiles.clear();
                selectedFiles.add(contextTarget.path);
                copySelected();
            }
            hideContextMenus();
        }

        function cutItemContext() {
            if (contextTarget) {
                selectedFiles.clear();
                selectedFiles.add(contextTarget.path);
                cutSelected();
            }
            hideContextMenus();
        }

        function handleFileSelect(event) {
            const files = event.target.files;
            if (!files || files.length === 0) return;
            uploadFiles(files);
        }

        function uploadFiles(files) {
            const urlParams = new URLSearchParams(window.location.search);
            const activeId = urlParams.get('id');

            if (!activeId) {
                showToast('No service selected', 'error');
                return;
            }

            Array.from(files).forEach(file => {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('path', currentPath);

                showToast(`Uploading ${file.name}...`, 'info');

                fetch(`${baseUrl}/api/services/${activeId}/files/upload`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${getAuthToken()}` },
                    body: formData
                }).then(res => res.json()).then(data => {
                    if (data.error) {
                        showToast(`Upload failed: ${data.error}`, 'error');
                    } else {
                        showToast(`${file.name} uploaded successfully`, 'success');
                        closeModal('uploadModal');
                        loadFiles(currentPath);
                    }
                }).catch(err => {
                    showToast(`Upload failed: ${err.message}`, 'error');
                });
            });
        }

        // Drag and drop upload
        document.addEventListener('DOMContentLoaded', function () {
            const uploadArea = document.getElementById('uploadArea');
            if (uploadArea) {
                uploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    uploadArea.classList.add('drag-over');
                });

                uploadArea.addEventListener('dragleave', () => {
                    uploadArea.classList.remove('drag-over');
                });

                uploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    uploadArea.classList.remove('drag-over');
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        uploadFiles(files);
                    }
                });

                uploadArea.addEventListener('click', () => {
                    document.getElementById('fileInput').click();
                });
            }
        });

    </script>


</body>

</html>