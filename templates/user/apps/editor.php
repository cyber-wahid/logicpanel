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
$filePath = $_GET['path'] ?? null;

if (!$serviceId || !$filePath) {
    echo "Missing service ID or file path";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="auth-token" content="<?php echo htmlspecialchars($_SESSION['lp_session_token'] ?? ''); ?>">
    <title>Editor -
        <?php echo htmlspecialchars(basename($filePath)); ?> - LogicPanel
    </title>

    <!-- CodeMirror CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css">

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1E2127;
            color: #fff;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .editor-header {
            background: #1E2127;
            border-bottom: 1px solid #3a3f4b;
            padding: 8px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .editor-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #ccc;
            font-size: 13px;
        }

        .editor-info .label {
            color: #888;
        }

        .editor-info .path {
            background: #282c34;
            padding: 5px 12px;
            border-radius: 4px;
            font-family: monospace;
            color: #61dafb;
        }

        .editor-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-save {
            background: #3C873A;
            color: white;
        }

        .btn-save:hover {
            background: #2D6A2E;
        }

        .btn-close {
            background: #555;
            color: white;
        }

        .btn-close:hover {
            background: #666;
        }

        /* Toolbar */
        .editor-toolbar {
            background: #282c34;
            padding: 6px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #3a3f4b;
        }

        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .encoding-select,
        .font-size-select {
            background: #1E2127;
            color: #ccc;
            border: 1px solid #3a3f4b;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }

        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #888;
            font-size: 12px;
        }

        /* Editor Container */
        .editor-container {
            flex: 1;
            overflow: hidden;
        }

        .CodeMirror {
            height: 100%;
            font-size: 14px;
            font-family: 'Consolas', 'Monaco', 'Fira Code', monospace;
        }

        /* Status Bar */
        .status-bar {
            background: #1E2127;
            border-top: 1px solid #3a3f4b;
            padding: 5px 15px;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #888;
        }

        .status-bar .status-left,
        .status-bar .status-right {
            display: flex;
            gap: 20px;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .loading-overlay.hidden {
            display: none;
        }

        .loading-spinner {
            text-align: center;
            color: #fff;
        }

        .loading-spinner i {
            font-size: 32px;
            margin-bottom: 10px;
        }

        /* MOBILE RESPONSIVE */
        @media (max-width: 768px) {

            /* Header - Stack vertically */
            .editor-header {
                flex-direction: column;
                padding: 8px 10px;
                gap: 8px;
            }

            .editor-info {
                width: 100%;
                flex-wrap: wrap;
                gap: 8px;
                font-size: 11px;
            }

            .editor-info .path {
                flex: 1;
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: 11px;
                padding: 4px 8px;
            }

            .editor-info .label {
                display: none;
            }

            .encoding-select {
                padding: 4px 6px;
                font-size: 11px;
            }

            .editor-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .btn {
                padding: 8px 12px;
                font-size: 12px;
            }

            .btn-save span,
            .btn-close span {
                display: none;
            }

            /* Toolbar - More compact */
            .editor-toolbar {
                padding: 5px 10px;
                flex-wrap: wrap;
                gap: 8px;
            }

            .toolbar-left {
                gap: 8px;
            }

            .toolbar-left .btn {
                padding: 5px 8px;
            }

            .font-size-select {
                padding: 4px 6px;
                font-size: 11px;
            }

            #modeLabel {
                font-size: 11px;
            }

            .toolbar-right {
                display: none;
                /* Hide keyboard shortcuts on mobile */
            }

            /* Editor */
            .CodeMirror {
                font-size: 13px !important;
            }

            /* Status Bar */
            .status-bar {
                padding: 4px 10px;
                font-size: 10px;
            }

            .status-bar .status-left,
            .status-bar .status-right {
                gap: 10px;
            }
        }

        @media (max-width: 480px) {
            .editor-header {
                padding: 6px 8px;
            }

            .editor-info .path {
                font-size: 10px;
            }

            .btn {
                padding: 6px 10px;
                font-size: 11px;
            }

            .editor-toolbar {
                padding: 4px 8px;
            }

            .toolbar-left .btn {
                padding: 4px 6px;
            }

            .CodeMirror {
                font-size: 12px !important;
            }

            .status-bar {
                flex-direction: column;
                gap: 3px;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fa fa-spinner fa-spin"></i>
            <div>Loading file...</div>
        </div>
    </div>

    <div class="editor-header">
        <div class="editor-info">
            <span class="label">Editing:</span>
            <span class="path" id="filePath">
                <?php echo htmlspecialchars($filePath); ?>
            </span>
            <span class="label">Encoding:</span>
            <select class="encoding-select" id="encoding">
                <option value="utf-8" selected>UTF-8</option>
                <option value="iso-8859-1">ISO-8859-1</option>
            </select>
        </div>
        <div class="editor-actions">
            <button class="btn btn-save" onclick="saveFile()">
                <i class="fa fa-save"></i> Save Changes
            </button>
            <button class="btn btn-close" onclick="window.close()">
                <i class="fa fa-times"></i> Close
            </button>
        </div>
    </div>

    <div class="editor-toolbar">
        <div class="toolbar-left">
            <button class="btn" onclick="editor.undo()" style="background: #3a3f4b; color: #ccc; padding: 5px 10px;">
                <i class="fa fa-undo"></i>
            </button>
            <button class="btn" onclick="editor.redo()" style="background: #3a3f4b; color: #ccc; padding: 5px 10px;">
                <i class="fa fa-redo"></i>
            </button>
            <select class="font-size-select" onchange="changeFontSize(this.value)">
                <option value="12px">12px</option>
                <option value="13px">13px</option>
                <option value="14px" selected>14px</option>
                <option value="16px">16px</option>
                <option value="18px">18px</option>
            </select>
            <span id="modeLabel" style="color: #61dafb;">Mode: Text</span>
        </div>
        <div class="toolbar-right">
            <span>Keyboard Shortcuts: Ctrl+S to Save</span>
        </div>
    </div>

    <div class="editor-container">
        <textarea id="codeEditor"></textarea>
    </div>

    <div class="status-bar">
        <div class="status-left">
            <span id="statusMessage">Ready</span>
        </div>
        <div class="status-right">
            <span id="cursorPos">Line: 1, Col: 1</span>
            <span id="fileType">Plain Text</span>
        </div>
    </div>

    <!-- CodeMirror JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/python/python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/markdown/markdown.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js"></script>

    <script>
        const serviceId = <?php echo json_encode($serviceId); ?>;
        const filePath = <?php echo json_encode($filePath); ?>;
        const baseUrl = window.base_url || '';

        let editor;
        let originalContent = '';
        let hasUnsavedChanges = false;

        function getAuthToken() {
            return document.querySelector('meta[name="auth-token"]').getAttribute('content');
        }

        function getAuthHeaders() {
            return {
                'Authorization': `Bearer ${getAuthToken()}`,
                'Content-Type': 'application/json'
            };
        }

        // Detect mode from file extension
        function detectMode(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const modeMap = {
                'js': { mode: 'javascript', label: 'JavaScript' },
                'json': { mode: { name: 'javascript', json: true }, label: 'JSON' },
                'php': { mode: 'php', label: 'PHP' },
                'html': { mode: 'htmlmixed', label: 'HTML' },
                'htm': { mode: 'htmlmixed', label: 'HTML' },
                'css': { mode: 'css', label: 'CSS' },
                'xml': { mode: 'xml', label: 'XML' },
                'py': { mode: 'python', label: 'Python' },
                'md': { mode: 'markdown', label: 'Markdown' },
                'txt': { mode: null, label: 'Plain Text' },
                'env': { mode: null, label: 'Environment' }
            };
            return modeMap[ext] || { mode: null, label: 'Plain Text' };
        }

        // Initialize editor
        document.addEventListener('DOMContentLoaded', async function () {
            const textarea = document.getElementById('codeEditor');
            const modeInfo = detectMode(filePath);

            editor = CodeMirror.fromTextArea(textarea, {
                mode: modeInfo.mode,
                theme: 'dracula',
                lineNumbers: true,
                lineWrapping: true,
                tabSize: 4,
                indentWithTabs: false,
                indentUnit: 4,
                autoCloseBrackets: true,
                matchBrackets: true,
                extraKeys: {
                    'Ctrl-S': function () { saveFile(); },
                    'Cmd-S': function () { saveFile(); }
                }
            });

            document.getElementById('modeLabel').textContent = 'Mode: ' + modeInfo.label;
            document.getElementById('fileType').textContent = modeInfo.label;

            // Track cursor position
            editor.on('cursorActivity', function () {
                const cursor = editor.getCursor();
                document.getElementById('cursorPos').textContent = `Line: ${cursor.line + 1}, Col: ${cursor.ch + 1}`;
            });

            // Track changes
            editor.on('change', function () {
                hasUnsavedChanges = editor.getValue() !== originalContent;
                updateTitle();
            });

            // Load file content
            await loadFile();
        });

        async function loadFile() {
            try {
                const url = `${baseUrl}/api/services/${serviceId}/files/read?path=${encodeURIComponent(filePath)}`;
                const response = await fetch(url, { headers: getAuthHeaders() });
                const data = await response.json();

                if (response.ok && data.content !== undefined) {
                    originalContent = data.content;
                    editor.setValue(data.content);
                    editor.clearHistory();
                    document.getElementById('loadingOverlay').classList.add('hidden');
                    document.getElementById('statusMessage').textContent = 'File loaded successfully';
                } else {
                    throw new Error(data.error || 'Failed to load file');
                }
            } catch (e) {
                document.getElementById('loadingOverlay').innerHTML = `
                    <div class="loading-spinner" style="color: #ff6b6b;">
                        <i class="fa fa-exclamation-triangle"></i>
                        <div>Error: ${e.message}</div>
                        <button onclick="window.close()" style="margin-top: 15px; padding: 8px 20px; cursor: pointer;">Close</button>
                    </div>
                `;
            }
        }

        async function saveFile() {
            document.getElementById('statusMessage').textContent = 'Saving...';

            try {
                const content = editor.getValue();
                const url = `${baseUrl}/api/services/${serviceId}/files`;

                const response = await fetch(url, {
                    method: 'PUT',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({ path: filePath, content: content })
                });

                const data = await response.json();

                if (response.ok) {
                    originalContent = content;
                    hasUnsavedChanges = false;
                    updateTitle();
                    document.getElementById('statusMessage').textContent = 'File saved successfully!';

                    // Flash green briefly
                    document.querySelector('.btn-save').style.background = '#28a745';
                    setTimeout(() => {
                        document.querySelector('.btn-save').style.background = '#3C873A';
                    }, 500);
                } else {
                    throw new Error(data.error || 'Failed to save file');
                }
            } catch (e) {
                document.getElementById('statusMessage').textContent = 'Error: ' + e.message;
                alert('Failed to save: ' + e.message);
            }
        }

        function updateTitle() {
            const prefix = hasUnsavedChanges ? '* ' : '';
            document.title = prefix + 'Editor - ' + filePath.split('/').pop() + ' - LogicPanel';
        }

        function changeFontSize(size) {
            document.querySelector('.CodeMirror').style.fontSize = size;
            editor.refresh();
        }

        // Warn before closing with unsaved changes
        window.onbeforeunload = function () {
            if (hasUnsavedChanges) {
                return 'You have unsaved changes. Are you sure you want to leave?';
            }
        };
    </script>
</body>

</html>