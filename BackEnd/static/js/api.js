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
