/**
 * LogicPanel - Dashboard JavaScript
 * Refined for premium UI and robust performance.
 */

// API Configuration - FIXED: Use /api instead of /public/api
const API_URL = (window.base_url || '') + '/api';
let authToken = window.apiToken || null;

// Inject Styles Immediately
const modalStyles = `
<style>
/* Base Overlay */
.lp-modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10001;
    animation: lpFadeIn 0.2s ease;
}

/* Modal Box */
.lp-modal-content {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    width: 90%;
    max-width: 480px;
    padding: 0;
    overflow: hidden;
    position: relative;
    border: 1px solid #e5e7eb;
}

.lp-modal-content.zoom-in {
    animation: lpZoomIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

/* Header */
.lp-modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.lp-modal-header h3 {
    margin: 0;
    font-size: 18px;
    color: #111827;
    font-weight: 600;
}

/* Body */
.lp-modal-body {
    padding: 24px;
    color: #4B5563;
    font-size: 14px;
    line-height: 1.6;
}

/* Footer */
.lp-modal-footer {
    padding: 16px 24px;
    background: #F9FAFB;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    border-top: 1px solid #f3f4f6;
}

/* Buttons */
.lp-btn {
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.lp-btn-primary { background: #3C873A; color: white; }
.lp-btn-primary:hover { background: #2D6A2E; transform: translateY(-1px); }

.lp-btn-secondary { background: white; color: #374151; border-color: #D1D5DB; }
.lp-btn-secondary:hover { background: #F3F4F6; color: #111827; }

.lp-btn-danger { background: #EF4444; color: white; }
.lp-btn-danger:hover { background: #DC2626; transform: translateY(-1px); }

/* Toast Notification */
.lp-toast {
    position: fixed;
    top: 24px;
    right: 24px;
    background: white;
    color: #374151;
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 12px;
    transform: translateX(120%);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    z-index: 10002;
    font-size: 14px;
}

.lp-toast.show { transform: translateX(0); }
.lp-toast-success { border-left: 4px solid #3C873A; }
.lp-toast-error { border-left: 4px solid #EF4444; }
.lp-toast-info { border-left: 4px solid #2196F3; }
.lp-toast-warning { border-left: 4px solid #FF9800; }

/* Dark Mode Support */
html[data-theme="dark"] .lp-modal-content,
[data-theme="dark"] .lp-modal-content {
    background: #2d333b;
    border-color: #444c56;
}

html[data-theme="dark"] .lp-modal-header,
[data-theme="dark"] .lp-modal-header {
    border-bottom-color: #444c56;
}

html[data-theme="dark"] .lp-modal-header h3,
[data-theme="dark"] .lp-modal-header h3 {
    color: #e6edf3;
}

html[data-theme="dark"] .lp-modal-body,
[data-theme="dark"] .lp-modal-body {
    color: #adbac7;
}

html[data-theme="dark"] .lp-modal-footer,
[data-theme="dark"] .lp-modal-footer {
    background: #22272e;
    border-top-color: #444c56;
}

html[data-theme="dark"] .lp-btn-secondary,
[data-theme="dark"] .lp-btn-secondary {
    background: #373e47;
    color: #adbac7;
    border-color: #444c56;
}

html[data-theme="dark"] .lp-btn-secondary:hover,
[data-theme="dark"] .lp-btn-secondary:hover {
    background: #444c56;
    color: #e6edf3;
}

html[data-theme="dark"] .lp-toast,
[data-theme="dark"] .lp-toast {
    background: #2d333b !important;
    color: #e6edf3 !important;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
}

/* Animations */
@keyframes lpFadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes lpZoomIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
</style>
`;
document.head.insertAdjacentHTML('beforeend', modalStyles);

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    // Refresh token from meta if not set
    if (!authToken) {
        authToken = document.querySelector('meta[name="api-token"]')?.content;
    }

    if (window.lucide) lucide.createIcons();

    // If on tools page, load services
    if (document.querySelector('.tools-layout')) {
        // loadServices(); // Placeholder or specific logic
    }
});

/** MODAL SYSTEM **/

function closeModal() {
    document.querySelectorAll('.lp-modal-overlay').forEach(m => m.remove());
}

function createModalBase() {
    closeModal();
    const overlay = document.createElement('div');
    overlay.className = 'lp-modal-overlay';
    overlay.onclick = (e) => { if (e.target === overlay) closeModal(); };
    return overlay;
}

window.showCustomAlert = function (options) {
    return new Promise((resolve) => {
        const overlay = createModalBase();
        const content = options.html || `<p>${options.message || ''}</p>`;

        overlay.innerHTML = `
            <div class="lp-modal-content zoom-in">
                <div class="lp-modal-header">
                    <h3>${options.title || 'Alert'}</h3>
                </div>
                <div class="lp-modal-body">
                    ${content}
                </div>
                <div class="lp-modal-footer">
                    <button class="lp-btn lp-btn-primary" id="modal-ok">OK</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        overlay.querySelector('#modal-ok').onclick = () => { resolve(true); closeModal(); };
        if (window.lucide) lucide.createIcons();
    });
};

window.showCustomConfirm = function (title, message, isDangerous = false) {
    return new Promise((resolve) => {
        const overlay = createModalBase();
        const btnClass = isDangerous ? 'lp-btn-danger' : 'lp-btn-primary';
        const btnText = isDangerous ? 'Yes, Delete' : 'Confirm';

        overlay.innerHTML = `
            <div class="lp-modal-content zoom-in">
                <div class="lp-modal-header">
                    <h3 class="${isDangerous ? 'text-danger' : ''}">${title}</h3>
                </div>
                <div class="lp-modal-body">
                    <p>${message}</p>
                </div>
                <div class="lp-modal-footer">
                    <button class="lp-btn lp-btn-secondary" id="modal-cancel">Cancel</button>
                    <button class="lp-btn ${btnClass}" id="modal-confirm">${btnText}</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        overlay.querySelector('#modal-confirm').onclick = () => { resolve(true); closeModal(); };
        overlay.querySelector('#modal-cancel').onclick = () => { resolve(false); closeModal(); };
        if (window.lucide) lucide.createIcons();
    });
};

window.showNotification = function (message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `lp-toast lp-toast-${type}`;
    const icon = type === 'success' ? 'check-circle' : (type === 'error' ? 'alert-circle' : 'info');

    toast.innerHTML = `
        <i data-lucide="${icon}"></i>
        <span>${message}</span>
    `;

    document.body.appendChild(toast);
    if (window.lucide) lucide.createIcons();

    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 500);
    }, 4000);
};

// Globalize for utility
window.apiCall = async function (endpoint, method = 'GET', data = null) {
    const headers = { 'Content-Type': 'application/json' };
    if (authToken) headers['Authorization'] = `Bearer ${authToken}`;

    try {
        const res = await fetch(`${API_URL}${endpoint}`, {
            method,
            headers,
            body: data ? JSON.stringify(data) : null
        });
        const json = await res.json();
        return { ok: res.ok, status: res.status, data: json };
    } catch (e) {
        return { ok: false, error: e.message };
    }
};
