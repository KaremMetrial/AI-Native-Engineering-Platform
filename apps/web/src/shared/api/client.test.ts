import { beforeEach, describe, expect, it } from 'vitest'
import { authMiddleware } from './client'
import { clearToken, getToken, setToken } from '../auth/token'

function fakeCallbackParams(request: Request) {
  return {
    request,
    schemaPath: '/example',
    params: {},
    id: 'test-request-id',
    options: {} as never,
  }
}

describe('authMiddleware', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('attaches the stored token as a bearer Authorization header', async () => {
    setToken('abc123')
    const request = new Request('https://example.test/api/me')

    const result = await authMiddleware.onRequest?.(fakeCallbackParams(request))

    expect(result).toBeInstanceOf(Request)
    expect((result as Request).headers.get('Authorization')).toBe('Bearer abc123')
  })

  it('does not set an Authorization header when there is no stored token', async () => {
    const request = new Request('https://example.test/api/me')

    const result = await authMiddleware.onRequest?.(fakeCallbackParams(request))

    expect((result as Request).headers.get('Authorization')).toBeNull()
  })

  it('clears the stored token when a response is unauthorized', async () => {
    setToken('abc123')
    const request = new Request('https://example.test/api/me')
    const response = new Response(null, { status: 401 })

    await authMiddleware.onResponse?.({ ...fakeCallbackParams(request), response })

    expect(getToken()).toBeNull()
  })

  it('leaves the stored token alone on a successful response', async () => {
    setToken('abc123')
    const request = new Request('https://example.test/api/me')
    const response = new Response(null, { status: 200 })

    await authMiddleware.onResponse?.({ ...fakeCallbackParams(request), response })

    expect(getToken()).toBe('abc123')
    clearToken()
  })
})
