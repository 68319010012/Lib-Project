// Shared fetch helper for all pages. Same-origin, so no CORS/credentials setup
// beyond `same-origin` (the browser default already sends the session cookie).
async function apiFetch(path, options = {}) {
    const response = await fetch(path, {
        credentials: "same-origin",
        headers: options.body instanceof FormData
            ? options.headers
            : { "Content-Type": "application/json", ...options.headers },
        ...options,
    });

    let data = null;
    try {
        data = await response.json();
    } catch (_err) {
        // No JSON body (e.g. 204) — leave data as null.
    }

    if (!response.ok) {
        const error = new Error((data && data.error) || `request failed with status ${response.status}`);
        error.status = response.status;
        error.data = data;
        throw error;
    }
    return data;
}

function apiPostJson(path, body) {
    return apiFetch(path, { method: "POST", body: JSON.stringify(body) });
}

// Wires a sun/moon icon button to toggle <html class="dark|light">, persisted in
// localStorage. The initial class is already set by the inline anti-flash script
// in each page's <head> (runs before paint) — this just handles the click + icon sync.
function initThemeToggle(buttonId) {
    const btn = document.getElementById(buttonId);
    if (!btn) return;
    const icon = btn.querySelector(".material-symbols-outlined");

    function sync() {
        const isDark = document.documentElement.classList.contains("dark");
        if (icon) icon.textContent = isDark ? "light_mode" : "dark_mode";
        btn.setAttribute("aria-label", isDark ? "สลับเป็นโหมดสว่าง" : "สลับเป็นโหมดมืด");
        btn.title = isDark ? "สลับเป็นโหมดสว่าง" : "สลับเป็นโหมดมืด";
    }

    sync();
    btn.addEventListener("click", () => {
        const isDark = !document.documentElement.classList.contains("dark");
        document.documentElement.classList.toggle("dark", isDark);
        document.documentElement.classList.toggle("light", !isDark);
        localStorage.setItem("nntc-theme", isDark ? "dark" : "light");
        sync();
    });
}

// ---- Styled toast + confirm dialog, replacing the browser's native alert()/confirm() ----
// Reuses each page's existing --maroon/--orange/--card-bg/etc. CSS variables so it
// automatically matches that page's light/dark theme with no extra config.
function _nntcEnsureNotifyStyles() {
    if (document.getElementById("nntc-notify-styles")) return;
    const style = document.createElement("style");
    style.id = "nntc-notify-styles";
    style.textContent = `
        .nntc-toast-stack {
            position: fixed; top: 16px; right: 16px; z-index: 300;
            display: flex; flex-direction: column; gap: 10px; max-width: min(360px, calc(100vw - 32px));
        }
        .nntc-toast {
            display: flex; align-items: flex-start; gap: 10px;
            background: var(--card-bg, #fff); border: 1px solid var(--border, #e5e7eb);
            border-radius: 12px; padding: 14px 14px 14px 16px; box-shadow: 0 10px 30px -8px rgba(31,35,40,.25);
            font-family: 'Noto Sans Thai', 'Segoe UI', sans-serif; font-size: 13.5px; color: var(--ink, #1f2328);
            border-left: 4px solid var(--maroon, #7a2734);
            opacity: 0; transform: translateX(24px); transition: opacity .18s ease, transform .18s ease;
        }
        .nntc-toast.show { opacity: 1; transform: translateX(0); }
        .nntc-toast .material-symbols-outlined { font-size: 20px; flex-shrink: 0; color: var(--maroon, #7a2734); }
        .nntc-toast-success { border-left-color: var(--green, #4a7c3f); }
        .nntc-toast-success .material-symbols-outlined { color: var(--green, #4a7c3f); }
        .nntc-toast-error { border-left-color: #a3312f; }
        .nntc-toast-error .material-symbols-outlined { color: #a3312f; }
        .nntc-toast-msg { flex: 1; line-height: 1.5; padding-top: 1px; }
        .nntc-toast-close {
            background: none; border: none; cursor: pointer; padding: 0; color: var(--gray, #6b7280);
            display: flex; flex-shrink: 0;
        }
        .nntc-toast-close .material-symbols-outlined { font-size: 18px; color: inherit; }

        .nntc-confirm-overlay {
            position: fixed; inset: 0; z-index: 300; background: rgba(20,17,18,.55);
            display: flex; align-items: center; justify-content: center; padding: 20px;
            opacity: 0; transition: opacity .18s ease; backdrop-filter: blur(2px);
        }
        .nntc-confirm-overlay.show { opacity: 1; }
        .nntc-confirm-card {
            width: 100%; max-width: 380px; background: var(--card-bg, #fff); border-radius: 18px; overflow: hidden;
            box-shadow: 0 25px 60px -15px rgba(0,0,0,.4); transform: translateY(10px) scale(.97);
            transition: transform .18s ease; font-family: 'Noto Sans Thai', 'Segoe UI', sans-serif;
        }
        .nntc-confirm-overlay.show .nntc-confirm-card { transform: translateY(0) scale(1); }
        .nntc-confirm-banner {
            display: flex; align-items: center; gap: 12px; padding: 20px 22px;
            background-image:
                repeating-linear-gradient(90deg, rgba(255,255,255,0.06) 0 2px, transparent 2px 28px),
                linear-gradient(135deg, var(--maroon, #7a2734) 0%, var(--maroon-dark, #5c1c26) 100%);
            color: #fff;
        }
        .nntc-confirm-banner .material-symbols-outlined {
            font-size: 26px; width: 42px; height: 42px; border-radius: 10px; flex-shrink: 0;
            background: rgba(255,255,255,.18); display: flex; align-items: center; justify-content: center;
        }
        .nntc-confirm-college { font-size: 14px; font-weight: 800; margin: 0; line-height: 1.3; }
        .nntc-confirm-sub { font-size: 11.5px; margin: 2px 0 0; opacity: .8; letter-spacing: .02em; }
        .nntc-confirm-body { padding: 22px; }
        .nntc-confirm-title { font-size: 16px; font-weight: 800; color: var(--ink, #1f2328); margin: 0 0 8px; }
        .nntc-confirm-msg { font-size: 14px; color: var(--gray, #6b7280); line-height: 1.6; margin: 0 0 22px; }
        .nntc-confirm-actions { display: flex; gap: 10px; }
        .nntc-confirm-actions button {
            flex: 1; border: none; border-radius: 12px; padding: 12px 0; font-size: 14px; font-weight: 700;
            cursor: pointer; font-family: inherit; transition: filter .15s, transform .1s;
        }
        .nntc-confirm-actions button:active { transform: scale(.98); }
        .nntc-confirm-cancel { background: var(--bg, #f5f6f8); color: var(--ink, #1f2328); border: 1px solid var(--border, #e5e7eb) !important; }
        .nntc-confirm-cancel:hover { filter: brightness(.97); }
        .nntc-confirm-ok { background: var(--orange, #e0672e); color: #fff; }
        .nntc-confirm-ok:hover { filter: brightness(1.08); }
    `;
    document.head.appendChild(style);
}

// Styled drop-in replacement for alert(). type: 'info' | 'success' | 'error'.
function showToast(message, type = "info") {
    _nntcEnsureNotifyStyles();
    let stack = document.getElementById("nntc-toast-stack");
    if (!stack) {
        stack = document.createElement("div");
        stack.id = "nntc-toast-stack";
        stack.className = "nntc-toast-stack";
        document.body.appendChild(stack);
    }

    const icons = { success: "check_circle", error: "error", info: "info" };
    const toast = document.createElement("div");
    toast.className = `nntc-toast nntc-toast-${type}`;
    toast.innerHTML = `
        <span class="material-symbols-outlined">${icons[type] || icons.info}</span>
        <span class="nntc-toast-msg"></span>
        <button class="nntc-toast-close" type="button" aria-label="ปิด"><span class="material-symbols-outlined">close</span></button>
    `;
    toast.querySelector(".nntc-toast-msg").textContent = message;
    stack.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add("show"));

    const dismiss = () => {
        toast.classList.remove("show");
        setTimeout(() => toast.remove(), 200);
    };
    toast.querySelector(".nntc-toast-close").addEventListener("click", dismiss);
    setTimeout(dismiss, 4500);
}

// Styled drop-in replacement for confirm() — returns a Promise<boolean> instead of a
// synchronous value, so call sites need `await showConfirm(...)`.
function showConfirm(message, options = {}) {
    _nntcEnsureNotifyStyles();
    return new Promise((resolve) => {
        const overlay = document.createElement("div");
        overlay.className = "nntc-confirm-overlay";
        overlay.innerHTML = `
            <div class="nntc-confirm-card">
                <div class="nntc-confirm-banner">
                    <span class="material-symbols-outlined">local_library</span>
                    <div>
                        <p class="nntc-confirm-college">วิทยาลัยเทคนิคนครนายก</p>
                        <p class="nntc-confirm-sub">ห้องสมุด NNTC · Library System</p>
                    </div>
                </div>
                <div class="nntc-confirm-body">
                    <p class="nntc-confirm-title"></p>
                    <p class="nntc-confirm-msg"></p>
                    <div class="nntc-confirm-actions">
                        <button class="nntc-confirm-cancel" type="button"></button>
                        <button class="nntc-confirm-ok" type="button"></button>
                    </div>
                </div>
            </div>
        `;
        overlay.querySelector(".nntc-confirm-title").textContent = options.title || "ยืนยันการทำรายการ";
        overlay.querySelector(".nntc-confirm-msg").textContent = message;
        const cancelBtn = overlay.querySelector(".nntc-confirm-cancel");
        const okBtn = overlay.querySelector(".nntc-confirm-ok");
        cancelBtn.textContent = options.cancelText || "ยกเลิก";
        okBtn.textContent = options.confirmText || "ยืนยัน";

        const close = (result) => {
            overlay.classList.remove("show");
            setTimeout(() => overlay.remove(), 180);
            resolve(result);
        };
        cancelBtn.addEventListener("click", () => close(false));
        okBtn.addEventListener("click", () => close(true));
        overlay.addEventListener("click", (e) => { if (e.target === overlay) close(false); });
        document.addEventListener("keydown", function onKey(e) {
            if (e.key === "Escape") { document.removeEventListener("keydown", onKey); close(false); }
        });

        document.body.appendChild(overlay);
        requestAnimationFrame(() => overlay.classList.add("show"));
    });
}
