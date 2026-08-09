import createClient from 'openapi-fetch'
import type { Middleware } from 'openapi-fetch'
import type { paths } from './schema'
import { clearToken, getToken } from '../auth/token'

export const authMiddleware: Middleware = {
  onRequest({ request }) {
    const token = getToken()

    if (token !== null) {
      request.headers.set('Authorization', `Bearer ${token}`)
    }

    return request
  },
  onResponse({ response }) {
    // A 401 means the token is invalid or revoked -- there is no
    // refresh flow (plain bearer tokens, not stateful sessions), so the
    // only correct move is to drop it and let route guards redirect to
    // login on the next render.
    if (response.status === 401) {
      clearToken()
    }

    return response
  },
}

// A bare '/api' resolves fine in a real browser (relative fetches resolve
// against the page's own origin implicitly), but Node's fetch/Request/URL
// -- unlike a browser's -- have no notion of "current document" to
// resolve a relative base against, and throw when tests stub fetch and
// exercise the real request-construction path. Resolving explicitly
// against window.location.origin is correct in both: same behavior in
// the browser regardless of deployment domain, and a valid absolute URL
// under jsdom (see vitest.config.ts's environmentOptions.jsdom.url).
const baseUrl = new URL('/api', window.location.origin).toString()

export const apiClient = createClient<paths>({
  baseUrl,
  // openapi-fetch reads its `fetch` option once at client-creation time
  // (`fetch: baseFetch = globalThis.fetch` in its source), not fresh per
  // call -- so a test that replaces `globalThis.fetch` after this module
  // has already loaded would silently keep talking to the original.
  // Resolving it lazily here means every call sees whatever
  // `globalThis.fetch` currently is.
  fetch: (input: Request) => globalThis.fetch(input),
})

apiClient.use(authMiddleware)
