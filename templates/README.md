# LogicDock Templates Documentation

This directory contains all the UI templates for LogicDock.

## 📁 Directory Structure

```
templates/
├── apps/
│   ├── create.php      # Application creation wizard
│   ├── editor.php      # Code Editor (CodeMirror-based)
│   └── files.php       # File Manager (cPanel-style)
├── dashboard/
│   └── index.php       # Main dashboard with tool cards
├── layouts/
│   └── main.php        # Base layout template
├── partials/
│   ├── header.php      # Top navigation bar
│   └── sidebar.php     # Left sidebar menu
├── terminal/
│   └── index.php       # Terminal interface
└── login.php           # Login page
```

---

## 📝 File Manager (`apps/files.php`)

### Overview
A cPanel-inspired file manager for managing application files.

### Features
- **Multi-Application Support**: All apps shown in left sidebar
- **Tree Navigation**: Click apps to browse their files
- **Toolbar Actions**: File, Folder, Copy, Move, Upload, Download, Delete, Rename, Edit, Paste
- **Context Menus**: Right-click on files/folders or empty space
- **Breadcrumb Navigation**: Easy path navigation

### Usage
1. Access via Dashboard → File Manager (opens in new tab)
2. Select an application from the left sidebar
3. Browse files and folders
4. Right-click for context menu options
5. Double-click to open files in editor or enter folders

### Context Menu Options

| Action | Files | Folders | Empty Space |
|--------|-------|---------|-------------|
| Open | ✅ (Editor) | ✅ (Enter) | ❌ |
| Edit | ✅ | ❌ | ❌ |
| Rename | ✅ | ✅ | ❌ |
| Copy | ✅ | ✅ | ❌ |
| Move | ✅ | ✅ | ❌ |
| Paste | ✅ | ✅ | ✅ |
| Download | ✅ | ❌ | ❌ |
| Delete | ✅ | ✅ | ❌ |
| New File | ❌ | ❌ | ✅ |
| New Folder | ❌ | ❌ | ✅ |
| Refresh | ❌ | ❌ | ✅ |

### Technical Details
- Uses JavaScript for all file operations (no page reload)
- API calls authenticated via session token
- Files list cached and refreshed on actions
- Supports file selection (single and multi with Ctrl+Click)

---

## 📝 Code Editor (`apps/editor.php`)

### Overview
A full-featured code editor powered by CodeMirror with syntax highlighting.

### Features
- **Syntax Highlighting** for multiple languages
- **Dark Theme** (Dracula)
- **Line Numbers**
- **Keyboard Shortcuts** (Ctrl+S to save)
- **Undo/Redo**
- **Font Size Control**
- **Encoding Selection**
- **Unsaved Changes Warning**

### Supported Languages
| Extension | Language |
|-----------|----------|
| .js | JavaScript |
| .json | JSON |
| .php | PHP |
| .html, .htm | HTML |
| .css | CSS |
| .xml | XML |
| .py | Python |
| .md | Markdown |
| .txt, .env | Plain Text |

### Usage
1. From File Manager, right-click a file → Edit
2. Or double-click any file
3. Editor opens in new tab
4. Make changes
5. Press Ctrl+S or click "Save Changes"
6. Close tab when done

### URL Parameters
```
/apps/editor?id={serviceId}&path={filePath}
```

### Technical Details
- CodeMirror 5.65.16 from CDN
- Dracula theme for dark mode
- Auto-detects file type from extension
- Communicates with `/api/services/{id}/files/read` and PUT endpoints

---

## 🎨 Dashboard (`dashboard/index.php`)

### Overview
Main control panel showing statistics and quick-access tools.

### Components
- **Statistics Cards**: Services count, running, stopped, resources
- **Quick Actions**: Create Node.js App, Python App
- **Tool Cards**: File Manager, Terminal, Database tools, etc.

### Tool Cards
Each tool card is a clickable link or action trigger:
- **Services**: View all services
- **File Manager**: Opens file manager in new tab
- **Adminer**: Database management
- **MySQL/PostgreSQL/MongoDB**: Create databases
- **Node.js/Python App**: Create applications
- **Terminal**: Access container terminal

---

## 🔧 Layouts (`layouts/main.php`)

### Overview
Base template that wraps all authenticated pages.

### Features
- Responsive sidebar
- Top header with user menu
- Theme toggle (dark/light)
- Mobile hamburger menu
- Lucide icons integration

### CSS Variables
```css
:root {
    --primary: #3C873A;
    --bg-dark: #1a1a2e;
    --bg-card: #ffffff;
    --text-primary: #333333;
    /* ... */
}
```

---

## 🔐 Authentication

All templates except `login.php` require authentication.

### Session Variables Available
- `$_SESSION['lp_session_token']` - JWT token for API calls
- `$_SESSION['user_name']` - Display name
- `$_SESSION['user_email']` - Email address
- `$_SESSION['user_role']` - User role (admin/user)

### Adding Auth Token to JavaScript
```php
<meta name="auth-token" content="<?php echo htmlspecialchars($_SESSION['token']); ?>">
```

```javascript
function getAuthToken() {
    return document.querySelector('meta[name="auth-token"]').getAttribute('content');
}
```

---

## 📝 Notes for Developers

1. **API Base URL**: Use `window.base_url` or PHP `$base_url` variable
2. **Icons**: FontAwesome 6.4 for file manager, Lucide for dashboard
3. **Styling**: Each template includes inline CSS in `<style>` tags
4. **JavaScript**: All templates use vanilla JavaScript (no jQuery)

---

**Last Updated**: 2026-01-22
