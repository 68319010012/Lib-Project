import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Dev-only proxy so the Vite dev server and PHP dev server are same-origin,
// avoiding cross-origin cookie rules while iterating (see backend-php's
// src/auth.php — prod uses real CORS + SameSite=None instead).
//
// Every path below is proxied by exact match, not a wildcard prefix, because
// React Router also owns page paths like /login, /signup, /profile — a
// wildcard would swallow those page navigations too. The one real collision
// is POST /login (API) vs the GET /login page route; `bypass` lets full page
// navigations (Accept: text/html) fall through to the SPA instead of hitting PHP.
const apiTarget = 'http://localhost:8000'

function apiProxy(path) {
  return {
    [path]: {
      target: apiTarget,
      changeOrigin: true,
      bypass(req) {
        if (req.headers.accept && req.headers.accept.includes('text/html')) {
          return '/index.html'
        }
      },
    },
  }
}

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      ...apiProxy('/register'),
      ...apiProxy('/login'),
      ...apiProxy('/logout'),
      ...apiProxy('/checkin'),
      ...apiProxy('/me'),
      ...apiProxy('/profile/change-password'),
      ...apiProxy('/admin/members'),
      ...apiProxy('/admin/reports'),
    },
  },
})
