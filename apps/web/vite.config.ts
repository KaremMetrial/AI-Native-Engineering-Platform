import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // Proxies to the Laravel dev server (composer run dev / php artisan
    // serve, port 8000) so the browser sees same-origin requests -- our
    // auth model is Sanctum bearer tokens, not cookie-based stateful SPA
    // auth, so this exists only to sidestep CORS in local dev, not to
    // satisfy Sanctum's stateful-domain requirement. Production CORS
    // configuration is deployment infrastructure, not yet built.
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
})
