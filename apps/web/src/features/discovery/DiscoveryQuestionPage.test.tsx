import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { jsonResponse, renderAtPath, urlOf } from '../../shared/testUtils'
import { DiscoveryQuestionPage } from './DiscoveryQuestionPage'

describe('DiscoveryQuestionPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows the question and its responses', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (url.endsWith('/api/discovery-questions/q-1')) {
          return jsonResponse({
            id: 'q-1',
            session_id: 'sess-1',
            prompt: 'What problem are we solving?',
            sequence: 1,
          })
        }

        if (url.endsWith('/api/discovery-questions/q-1/responses')) {
          return jsonResponse({
            responses: [
              {
                id: 'r-1',
                question_id: 'q-1',
                content: 'Users need faster checkout.',
                responded_by: 'u-1',
              },
            ],
          })
        }

        throw new Error(`Unexpected fetch to ${url}`)
      }),
    )
    renderAtPath(
      <DiscoveryQuestionPage />,
      '/discovery-questions/:questionId',
      '/discovery-questions/q-1',
    )

    expect(await screen.findByText('What problem are we solving?')).toBeInTheDocument()
    expect(await screen.findByText('Users need faster checkout.')).toBeInTheDocument()
  })

  it('records a response and shows it in the list', async () => {
    let recorded = false

    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (input.method === 'POST' && url.endsWith('/api/discovery-questions/q-1/responses')) {
          recorded = true
          return jsonResponse(
            { id: 'r-1', question_id: 'q-1', content: 'Users need faster checkout.' },
            201,
          )
        }

        if (url.endsWith('/api/discovery-questions/q-1')) {
          return jsonResponse({
            id: 'q-1',
            session_id: 'sess-1',
            prompt: 'What problem are we solving?',
            sequence: 1,
          })
        }

        if (url.endsWith('/api/discovery-questions/q-1/responses')) {
          return jsonResponse({
            responses: recorded
              ? [
                  {
                    id: 'r-1',
                    question_id: 'q-1',
                    content: 'Users need faster checkout.',
                    responded_by: 'u-1',
                  },
                ]
              : [],
          })
        }

        throw new Error(`Unexpected fetch to ${url}`)
      }),
    )
    const user = userEvent.setup()
    renderAtPath(
      <DiscoveryQuestionPage />,
      '/discovery-questions/:questionId',
      '/discovery-questions/q-1',
    )

    expect(await screen.findByText('No responses yet.')).toBeInTheDocument()

    await user.type(screen.getByLabelText(/response content/i), 'Users need faster checkout.')
    await user.click(screen.getByRole('button', { name: /record response/i }))

    expect(await screen.findByText('Users need faster checkout.')).toBeInTheDocument()
  })

  it('shows client-side validation without calling the API', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (url.endsWith('/api/discovery-questions/q-1')) {
          return jsonResponse({
            id: 'q-1',
            session_id: 'sess-1',
            prompt: 'What problem are we solving?',
            sequence: 1,
          })
        }

        return jsonResponse({ responses: [] })
      }),
    )
    const user = userEvent.setup()
    renderAtPath(
      <DiscoveryQuestionPage />,
      '/discovery-questions/:questionId',
      '/discovery-questions/q-1',
    )

    await screen.findByText('No responses yet.')

    const fetchSpy = vi.mocked(globalThis.fetch)
    fetchSpy.mockClear()
    await user.click(screen.getByRole('button', { name: /record response/i }))

    expect(await screen.findByText('Content is required.')).toBeInTheDocument()
    expect(fetchSpy).not.toHaveBeenCalled()
  })
})
