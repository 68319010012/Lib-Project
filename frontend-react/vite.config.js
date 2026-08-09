import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Dev-only proxy so the Vite dev server and PHP dev server are same-origin,
// avoiding cross-origin cookie rules while iterating (see backend-php's
// src/auth.php — prod uses real CORS + SameSite=None instead).
//
// Vite/http-proxy-middleware matches these string keys by PREFIX, not exact
// string (a rule for '/admin/reports' also matches '/admin/reports/print'),
// and rules are tried in the order they're defined here — first match wins.
// Because React Router also owns page paths like /login, /signup, /profile,
// each API rule's `bypass` lets full page navigations (Accept: text/html)
// fall through to the SPA instead of hitting PHP, so a real prefix collision
// (e.g. POST /login (API) vs the GET /login page route) resolves correctly.
// /admin/reports/print's report pages are the one case that's the opposite:
// they're real PHP pages meant to be *navigated to* directly, so that rule
// must (a) come before the broader '/admin/reports' rule below so its more
// specific prefix wins, and (b) NOT bypass on text/html.
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
      ...apiProxy('/checkin/extend'),
      ...apiProxy('/library-info'),
      ...apiProxy('/me'),
      ...apiProxy('/profile/change-password'),
      ...apiProxy('/admin/members'),
      // Must come before '/admin/reports' below — see the comment above.
      '/admin/reports/print': { target: apiTarget, changeOrigin: true },
      ...apiProxy('/admin/reports'),
    },
  },
})
