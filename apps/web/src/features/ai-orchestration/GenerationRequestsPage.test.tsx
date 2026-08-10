import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { jsonResponse, renderAtPath, urlOf } from '../../shared/testUtils'
import { GenerationRequestsPage } from './GenerationRequestsPage'

describe('GenerationRequestsPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('lists generation requests returned by the API', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        jsonResponse({
          generation_requests: [
            {
              id: 'gr-1',
              workflow_name: 'brd-synthesis',
              status: 'selected',
              selected_model_id: 'claude-sonnet-5',
              failure_reason: null,
            },
          ],
        }),
      ),
    )
    renderAtPath(<GenerationRequestsPage />)

    expect(await screen.findByText('brd-synthesis')).toBeInTheDocument()
    expect(await screen.findByText('selected')).toBeInTheDocument()
  })

  it('shows an empty state when there are no generation requests', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => jsonResponse({ generation_requests: [] })),
    )
    renderAtPath(<GenerationRequestsPage />)

    expect(await screen.findByText('No generation requests yet.')).toBeInTheDocument()
  })

  it('submits a generation request and refreshes the list', async () => {
    let requested = false

    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (input.method === 'POST' && url.endsWith('/api/generation-requests')) {
          requested = true
          return jsonResponse({ id: 'gr-2', status: 'queued' }, 202)
        }

        return jsonResponse({
          generation_requests: requested
            ? [
                {
                  id: 'gr-2',
                  workflow_name: 'srs-synthesis',
                  status: 'queued',
                  selected_model_id: null,
                  failure_reason: null,
                },
              ]
            : [],
        })
      }),
    )
    const user = userEvent.setup()
    renderAtPath(<GenerationRequestsPage />)

    expect(await screen.findByText('No generation requests yet.')).toBeInTheDocument()

    await user.type(screen.getByLabelText(/workflow name/i), 'srs-synthesis')
    await user.click(screen.getByRole('button', { name: /request generation/i }))

    expect(await screen.findByText('srs-synthesis')).toBeInTheDocument()
  })

  it('shows client-side validation without calling the API', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => jsonResponse({ generation_requests: [] })),
    )
    const user = userEvent.setup()
    renderAtPath(<GenerationRequestsPage />)

    await screen.findByText('No generation requests yet.')

    const fetchSpy = vi.mocked(globalThis.fetch)
    fetchSpy.mockClear()
    await user.click(screen.getByRole('button', { name: /request generation/i }))

    expect(await screen.findByText('Workflow name is required.')).toBeInTheDocument()
    expect(fetchSpy).not.toHaveBeenCalled()
  })
})
