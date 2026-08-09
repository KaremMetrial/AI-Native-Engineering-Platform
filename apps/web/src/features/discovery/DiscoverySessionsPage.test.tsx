import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { jsonResponse, renderAtPath, urlOf } from '../../shared/testUtils'
import { DiscoverySessionsPage } from './DiscoverySessionsPage'

describe('DiscoverySessionsPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('lists sessions returned by the API', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        jsonResponse({
          sessions: [
            {
              id: 's-1',
              project_id: 'p-1',
              title: 'Acme kickoff',
              status: 'in_progress',
              completed_at: null,
            },
          ],
        }),
      ),
    )
    renderAtPath(<DiscoverySessionsPage />)

    expect(await screen.findByText('Acme kickoff')).toBeInTheDocument()
  })

  it('shows an empty state when there are no sessions', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => jsonResponse({ sessions: [] })),
    )
    renderAtPath(<DiscoverySessionsPage />)

    expect(await screen.findByText('No discovery sessions yet.')).toBeInTheDocument()
  })

  it('starts a session and refreshes the list', async () => {
    let started = false

    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (input.method === 'POST' && url.endsWith('/api/discovery-sessions')) {
          started = true
          return jsonResponse(
            {
              id: 's-2',
              project_id: '123e4567-e89b-12d3-a456-426614174000',
              title: 'New Session',
              status: 'in_progress',
            },
            201,
          )
        }

        return jsonResponse({
          sessions: started
            ? [
                {
                  id: 's-2',
                  project_id: '123e4567-e89b-12d3-a456-426614174000',
                  title: 'New Session',
                  status: 'in_progress',
                  completed_at: null,
                },
              ]
            : [],
        })
      }),
    )
    const user = userEvent.setup()
    renderAtPath(<DiscoverySessionsPage />)

    expect(await screen.findByText('No discovery sessions yet.')).toBeInTheDocument()

    await user.type(screen.getByLabelText(/project id/i), '123e4567-e89b-12d3-a456-426614174000')
    await user.type(screen.getByLabelText(/session title/i), 'New Session')
    await user.click(screen.getByRole('button', { name: /start session/i }))

    expect(await screen.findByText('New Session')).toBeInTheDocument()
  })

  it('shows client-side validation without calling the API', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => jsonResponse({ sessions: [] })),
    )
    const user = userEvent.setup()
    renderAtPath(<DiscoverySessionsPage />)

    await screen.findByText('No discovery sessions yet.')

    const fetchSpy = vi.mocked(globalThis.fetch)
    fetchSpy.mockClear()
    await user.click(screen.getByRole('button', { name: /start session/i }))

    expect(await screen.findByText('Enter a valid project id.')).toBeInTheDocument()
    expect(fetchSpy).not.toHaveBeenCalled()
  })
})
