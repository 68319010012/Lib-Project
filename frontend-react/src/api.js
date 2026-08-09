// Fetch client — port of BackEnd/static/js/api.js's apiFetch/apiPostJson.
// `credentials: 'include'` (not 'same-origin') since the API is now a
// separate origin in production; the Vite dev proxy keeps dev same-origin so
// this works unchanged in both environments.
export const API_BASE = import.meta.env.VITE_API_BASE_URL || ''

export async function apiFetch(path, options = {}) {
  const response = await fetch(`${API_BASE}${path}`, {
    credentials: 'include',
    headers:
      options.body instanceof FormData
        ? options.headers
        : { 'Content-Type': 'application/json', ...options.headers },
    ...options,
  })

  let data = null
  try {
    data = await response.json()
  } catch (_err) {
    // No JSON body — leave data as null.
  }

  if (!response.ok) {
    const error = new Error((data && data.error) || `request failed with status ${response.status}`)
    error.status = response.status
    error.data = data
    throw error
  }
  return data
}

export function apiGet(path) {
  return apiFetch(path)
}

export function apiPostJson(path, body) {
  return apiFetch(path, { method: 'POST', body: JSON.stringify(body) })
}
