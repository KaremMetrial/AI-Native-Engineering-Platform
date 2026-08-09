import { defineConfig, mergeConfig } from 'vitest/config'
import viteConfig from './vite.config.ts'

export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      environment: 'jsdom',
      // Without an explicit origin, jsdom's default location gives
      // Node's Request/URL constructors nothing to resolve a relative
      // API path against (apiClient's baseUrl is '/api', which only
      // resolves in a real browser against the current page). Only
      // matters for tests that stub fetch and exercise the real
      // apiClient/openapi-fetch request construction.
      environmentOptions: { jsdom: { url: 'http://localhost:3000/' } },
      setupFiles: ['./src/test-setup.ts'],
      globals: false,
      coverage: {
        provider: 'v8',
        reporter: ['text', 'html'],
      },
    },
  }),
)
