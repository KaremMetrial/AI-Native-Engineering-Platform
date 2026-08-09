import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { getToken } from '../../shared/auth/token'
import { RegisterPage } from './RegisterPage'
import { jsonResponse, renderAtRoute, urlOf } from './testUtils'

describe('RegisterPage', () => {
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
    renderAtRoute(<RegisterPage />)

    await user.click(screen.getByRole('button', { name: /create organization/i }))

    expect(await screen.findByText('Organization name is required.')).toBeInTheDocument()
    expect(fetchSpy).not.toHaveBeenCalled()
  })

  it('stores the token and navigates home on a successful registration', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (url.endsWith('/api/register')) {
          return jsonResponse(
            {
              tenant: { id: 't-1', name: 'Acme' },
              user: { id: 'u-1', name: 'Ada', email: 'ada@example.test' },
              role: 'owner',
              token: 'abc123',
            },
            201,
          )
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
    renderAtRoute(<RegisterPage />)

    await user.type(screen.getByLabelText(/organization name/i), 'Acme')
    await user.type(screen.getByLabelText(/your name/i), 'Ada Lovelace')
    await user.type(screen.getByLabelText(/^email$/i), 'ada@example.test')
    await user.type(screen.getByLabelText(/^password$/i), 'correct-horse-battery-staple')
    await user.click(screen.getByRole('button', { name: /create organization/i }))

    await waitFor(() => {
      expect(getToken()).toBe('abc123')
    })
    expect(await screen.findByText('Landed on home')).toBeInTheDocument()
  })

  it('shows server-side field validation errors', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        jsonResponse(
          {
            message: 'The email has already been taken.',
            errors: { email: ['The email has already been taken.'] },
          },
          422,
        ),
      ),
    )
    const user = userEvent.setup()
    renderAtRoute(<RegisterPage />)

    await user.type(screen.getByLabelText(/organization name/i), 'Acme')
    await user.type(screen.getByLabelText(/your name/i), 'Ada Lovelace')
    await user.type(screen.getByLabelText(/^email$/i), 'ada@example.test')
    await user.type(screen.getByLabelText(/^password$/i), 'correct-horse-battery-staple')
    await user.click(screen.getByRole('button', { name: /create organization/i }))

    expect(await screen.findByText('The email has already been taken.')).toBeInTheDocument()
    expect(getToken()).toBeNull()
  })
})
