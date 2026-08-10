import { screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { jsonResponse, renderAtPath } from '../../shared/testUtils'
import { GenerationRequestPage } from './GenerationRequestPage'

describe('GenerationRequestPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows a selected generation request and its selected model', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        jsonResponse({
          id: 'gr-1',
          workflow_name: 'brd-synthesis',
          status: 'selected',
          selected_model_id: 'claude-sonnet-5',
          failure_reason: null,
        }),
      ),
    )
    renderAtPath(
      <GenerationRequestPage />,
      '/generation-requests/:generationRequestId',
      '/generation-requests/gr-1',
    )

    expect(await screen.findByText('brd-synthesis')).toBeInTheDocument()
    expect(await screen.findByText('selected')).toBeInTheDocument()
    expect(await screen.findByText('claude-sonnet-5')).toBeInTheDocument()
  })

  it('shows the failure reason for a failed generation request', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        jsonResponse({
          id: 'gr-1',
          workflow_name: 'brd-synthesis',
          status: 'failed',
          selected_model_id: null,
          failure_reason: 'No active model satisfies the requested capabilities.',
        }),
      ),
    )
    renderAtPath(
      <GenerationRequestPage />,
      '/generation-requests/:generationRequestId',
      '/generation-requests/gr-1',
    )

    expect(
      await screen.findByText('No active model satisfies the requested capabilities.'),
    ).toBeInTheDocument()
  })

  it('polls while queued and stops once a model is selected', async () => {
    let calls = 0
    vi.stubGlobal(
      'fetch',
      vi.fn(() => {
        calls += 1
        if (calls === 1) {
          return jsonResponse({
            id: 'gr-1',
            workflow_name: 'brd-synthesis',
            status: 'queued',
            selected_model_id: null,
            failure_reason: null,
          })
        }
        return jsonResponse({
          id: 'gr-1',
          workflow_name: 'brd-synthesis',
          status: 'selected',
          selected_model_id: 'claude-sonnet-5',
          failure_reason: null,
        })
      }),
    )
    renderAtPath(
      <GenerationRequestPage />,
      '/generation-requests/:generationRequestId',
      '/generation-requests/gr-1',
    )

    expect(await screen.findByText('Waiting for a model to be selected…')).toBeInTheDocument()
    await waitFor(
      () => {
        expect(screen.getByText('claude-sonnet-5')).toBeInTheDocument()
      },
      { timeout: 3000 },
    )
  })
})
