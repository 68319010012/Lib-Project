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

// Escapes a value for interpolation into an HTML template string.
//
// Students type their own prefix/name/department at signup with no approval
// step, so those fields are attacker-controlled and reach the admin tables
// through innerHTML. Without this, a surname of
//   <img src=x onerror="...">
// runs as script in the logged-in admin's browser the moment they open the
// members list. Escape at the point of output, never trusting that the value
// was cleaned on the way in.
//
// Also covers quotes, because several templates interpolate into attributes
// (data-name="${...}") where an unescaped " breaks out of the attribute.
function escapeHtml(value) {
  if (value === null || value === undefined) return '';
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function apiGet(path) {
  return apiFetch(path);
}

function apiPostJson(path, body) {
  return apiFetch(path, { method: 'POST', body: JSON.stringify(body) });
}
