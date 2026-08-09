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

export const apiClient = createClient<paths>({ baseUrl: '/api' })

apiClient.use(authMiddleware)
