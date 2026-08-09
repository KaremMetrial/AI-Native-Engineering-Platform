import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { getToken } from '../../shared/auth/token'
import { LoginPage } from './LoginPage'
import { jsonResponse, renderAtRoute, urlOf } from './testUtils'

describe('LoginPage', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows client-side validation errors without calling the API', async () => {
    const fetchSpy = vi.fn()
    vi.stubGlobal('fetch', fetchSpy)
    const user = userEvent.setup()
    renderAtRoute(<LoginPage />)

    await user.click(screen.getByRole('button', { name: /log in/i }))

    expect(await screen.findByText('Enter a valid email address.')).toBeInTheDocument()
    expect(fetchSpy).not.toHaveBeenCalled()
  })

  it('stores the token and navigates home on a successful login', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (url.endsWith('/api/login')) {
          return jsonResponse({
            user: { id: 'u-1', name: 'Ada', email: 'ada@example.test' },
            token: 'abc123',
          })
        }

        if (url.endsWith('/api/me')) {
          return jsonResponse({
            user: { id: 'u-1', name: 'Ada', email: 'ada@example.test' },
            tenant_id: 't-1',
            role: 'owner',
          })
        }

        throw new Error(`Unexpected fetch to ${url}`)
      }),
    )
    const user = userEvent.setup()
    renderAtRoute(<LoginPage />)

    await user.type(screen.getByLabelText(/email/i), 'ada@example.test')
    await user.type(screen.getByLabelText(/password/i), 'correct-horse-battery-staple')
    await user.click(screen.getByRole('button', { name: /log in/i }))

    await waitFor(() => {
      expect(getToken()).toBe('abc123')
    })
    expect(await screen.findByText('Landed on home')).toBeInTheDocument()
  })

  it('shows the server error message on invalid credentials', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => jsonResponse({ message: 'Invalid credentials.' }, 401)),
    )
    const user = userEvent.setup()
    renderAtRoute(<LoginPage />)

    await user.type(screen.getByLabelText(/email/i), 'ada@example.test')
    await user.type(screen.getByLabelText(/password/i), 'wrong-password')
    await user.click(screen.getByRole('button', { name: /log in/i }))

    expect(await screen.findByText('Invalid credentials.')).toBeInTheDocument()
    expect(getToken()).toBeNull()
  })
})
