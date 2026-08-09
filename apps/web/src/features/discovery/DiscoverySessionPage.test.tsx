import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { jsonResponse, renderAtPath, urlOf } from '../../shared/testUtils'
import { DiscoverySessionPage } from './DiscoverySessionPage'

function stubSessionFetch(overrides: { status?: string } = {}) {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: Request) => {
      const url = urlOf(input)

      if (url.endsWith('/api/discovery-sessions/sess-1')) {
        return jsonResponse({
          id: 'sess-1',
          project_id: 'proj-1',
          title: 'Acme kickoff',
          status: overrides.status ?? 'in_progress',
          completed_at: null,
        })
      }

      if (url.endsWith('/api/discovery-sessions/sess-1/questions')) {
        return jsonResponse({ questions: [] })
      }

      if (url.endsWith('/api/discovery-sessions/sess-1/assumptions')) {
        return jsonResponse({ assumptions: [] })
      }

      if (url.endsWith('/api/discovery-sessions/sess-1/constraints')) {
        return jsonResponse({ constraints: [] })
      }

      throw new Error(`Unexpected fetch to ${url}`)
    }),
  )
}

describe('DiscoverySessionPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows the session, its questions, assumptions and constraints', async () => {
    stubSessionFetch()
    renderAtPath(
      <DiscoverySessionPage />,
      '/discovery-sessions/:sessionId',
      '/discovery-sessions/sess-1',
    )

    expect(await screen.findByText('Acme kickoff')).toBeInTheDocument()
    expect(await screen.findByText('No questions yet.')).toBeInTheDocument()
    expect(await screen.findByText('No assumptions yet.')).toBeInTheDocument()
    expect(await screen.findByText('No constraints yet.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /complete session/i })).toBeInTheDocument()
  })

  it('does not show the complete button for a completed session', async () => {
    stubSessionFetch({ status: 'completed' })
    renderAtPath(
      <DiscoverySessionPage />,
      '/discovery-sessions/:sessionId',
      '/discovery-sessions/sess-1',
    )

    await screen.findByText('Acme kickoff')
    expect(screen.queryByRole('button', { name: /complete session/i })).not.toBeInTheDocument()
  })

  it('adds a question and shows it in the list', async () => {
    let added = false

    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (input.method === 'POST' && url.endsWith('/api/discovery-sessions/sess-1/questions')) {
          added = true
          return jsonResponse(
            {
              id: 'q-1',
              session_id: 'sess-1',
              prompt: 'What problem are we solving?',
              sequence: 1,
            },
            201,
          )
        }

        if (url.endsWith('/api/discovery-sessions/sess-1')) {
          return jsonResponse({
            id: 'sess-1',
            project_id: 'proj-1',
            title: 'Acme kickoff',
            status: 'in_progress',
            completed_at: null,
          })
        }

        if (url.endsWith('/api/discovery-sessions/sess-1/questions')) {
          return jsonResponse({
            questions: added
              ? [
                  {
                    id: 'q-1',
                    session_id: 'sess-1',
                    prompt: 'What problem are we solving?',
                    sequence: 1,
                  },
                ]
              : [],
          })
        }

        if (url.endsWith('/api/discovery-sessions/sess-1/assumptions')) {
          return jsonResponse({ assumptions: [] })
        }

        if (url.endsWith('/api/discovery-sessions/sess-1/constraints')) {
          return jsonResponse({ constraints: [] })
        }

        throw new Error(`Unexpected fetch to ${url}`)
      }),
    )
    const user = userEvent.setup()
    renderAtPath(
      <DiscoverySessionPage />,
      '/discovery-sessions/:sessionId',
      '/discovery-sessions/sess-1',
    )

    expect(await screen.findByText('No questions yet.')).toBeInTheDocument()

    await user.type(screen.getByLabelText(/question prompt/i), 'What problem are we solving?')
    await user.click(screen.getByRole('button', { name: /ask question/i }))

    expect(await screen.findByText('What problem are we solving?')).toBeInTheDocument()
  })

  it('completes the session', async () => {
    let completed = false

    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (input.method === 'POST' && url.endsWith('/api/discovery-sessions/sess-1/complete')) {
          completed = true
          return jsonResponse({ id: 'sess-1', status: 'completed' })
        }

        if (url.endsWith('/api/discovery-sessions/sess-1')) {
          return jsonResponse({
            id: 'sess-1',
            project_id: 'proj-1',
            title: 'Acme kickoff',
            status: completed ? 'completed' : 'in_progress',
            completed_at: completed ? '2026-08-09T00:00:00+00:00' : null,
          })
        }

        if (url.endsWith('/api/discovery-sessions/sess-1/questions')) {
          return jsonResponse({ questions: [] })
        }

        if (url.endsWith('/api/discovery-sessions/sess-1/assumptions')) {
          return jsonResponse({ assumptions: [] })
        }

        if (url.endsWith('/api/discovery-sessions/sess-1/constraints')) {
          return jsonResponse({ constraints: [] })
        }

        throw new Error(`Unexpected fetch to ${url}`)
      }),
    )
    const user = userEvent.setup()
    renderAtPath(
      <DiscoverySessionPage />,
      '/discovery-sessions/:sessionId',
      '/discovery-sessions/sess-1',
    )

    const completeButton = await screen.findByRole('button', { name: /complete session/i })
    await user.click(completeButton)

    expect(await screen.findByText('completed')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /complete session/i })).not.toBeInTheDocument()
  })
})
