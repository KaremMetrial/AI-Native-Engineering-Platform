import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { jsonResponse, renderAtPath, urlOf } from '../../shared/testUtils'
import { RequirementPage } from './RequirementPage'

describe('RequirementPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows the requirement and its acceptance criteria', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        jsonResponse({
          id: 'req-1',
          document_id: 'doc-1',
          text: 'The system shall allow login.',
          acceptance_criteria: ['Login succeeds with valid credentials.'],
          status: 'draft',
        }),
      ),
    )
    renderAtPath(<RequirementPage />, '/requirements/:requirementId', '/requirements/req-1')

    expect(await screen.findByText('The system shall allow login.')).toBeInTheDocument()
    expect(await screen.findByText('Login succeeds with valid credentials.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /approve requirement/i })).toBeInTheDocument()
  })

  it('shows an empty state when there are no acceptance criteria', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        jsonResponse({
          id: 'req-1',
          document_id: 'doc-1',
          text: 'The system shall allow login.',
          acceptance_criteria: [],
          status: 'draft',
        }),
      ),
    )
    renderAtPath(<RequirementPage />, '/requirements/:requirementId', '/requirements/req-1')

    expect(await screen.findByText('No acceptance criteria.')).toBeInTheDocument()
  })

  it('does not show the approve button for an approved requirement', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        jsonResponse({
          id: 'req-1',
          document_id: 'doc-1',
          text: 'The system shall allow login.',
          acceptance_criteria: [],
          status: 'approved',
        }),
      ),
    )
    renderAtPath(<RequirementPage />, '/requirements/:requirementId', '/requirements/req-1')

    await screen.findByText('The system shall allow login.')
    expect(screen.queryByRole('button', { name: /approve requirement/i })).not.toBeInTheDocument()
  })

  it('approves the requirement', async () => {
    let approved = false

    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (input.method === 'POST' && url.endsWith('/api/requirements/req-1/approve')) {
          approved = true
          return jsonResponse({ id: 'req-1', status: 'approved' })
        }

        return jsonResponse({
          id: 'req-1',
          document_id: 'doc-1',
          text: 'The system shall allow login.',
          acceptance_criteria: [],
          status: approved ? 'approved' : 'draft',
        })
      }),
    )
    const user = userEvent.setup()
    renderAtPath(<RequirementPage />, '/requirements/:requirementId', '/requirements/req-1')

    const approveButton = await screen.findByRole('button', { name: /approve requirement/i })
    await user.click(approveButton)

    await screen.findByText(/approved/)
    expect(screen.queryByRole('button', { name: /approve requirement/i })).not.toBeInTheDocument()
  })
})
