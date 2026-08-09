import { beforeEach, describe, expect, it } from 'vitest'
import { clearToken, getToken, setToken } from './token'

describe('token storage', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('returns null when no token has been stored', () => {
    expect(getToken()).toBeNull()
  })

  it('returns the token that was stored', () => {
    setToken('abc123')

    expect(getToken()).toBe('abc123')
  })

  it('removes the token on clear', () => {
    setToken('abc123')

    clearToken()

    expect(getToken()).toBeNull()
  })
})
