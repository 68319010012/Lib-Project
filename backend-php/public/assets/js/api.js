// Fetch client — port of frontend-react/src/api.js. Same-origin now (this
// site is served by the same Apache+PHP host as the API), so API_BASE is
// always empty — no env var / build step needed to configure it.
const API_BASE = '';

async function apiFetch(path, options = {}) {
  const response = await fetch(`${API_BASE}${path}`, {
    credentials: 'include',
    headers:
      options.body instanceof FormData
        ? options.headers
        : { 'Content-Type': 'application/json', ...options.headers },
    ...options,
  });

  let data = null;
  try {
    data = await response.json();
  } catch (_err) {
    // No JSON body — leave data as null.
  }

  if (!response.ok) {
    const error = new Error((data && data.error) || `request failed with status ${response.status}`);
    error.status = response.status;
    error.data = data;
    throw error;
  }
  return data;
}

function apiGet(path) {
  return apiFetch(path);
}

function apiPostJson(path, body) {
  return apiFetch(path, { method: 'POST', body: JSON.stringify(body) });
}
