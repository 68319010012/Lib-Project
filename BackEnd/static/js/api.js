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
