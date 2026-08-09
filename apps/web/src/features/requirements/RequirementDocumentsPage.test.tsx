import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { jsonResponse, renderAtPath, urlOf } from '../../shared/testUtils'
import { RequirementDocumentsPage } from './RequirementDocumentsPage'

describe('RequirementDocumentsPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('lists documents returned by the API', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        jsonResponse({
          documents: [
            { id: 'd-1', project_id: 'p-1', type: 'brd', title: 'Acme BRD', status: 'draft' },
          ],
        }),
      ),
    )
    renderAtPath(<RequirementDocumentsPage />)

    expect(await screen.findByText('Acme BRD')).toBeInTheDocument()
  })

  it('shows an empty state when there are no documents', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => jsonResponse({ documents: [] })),
    )
    renderAtPath(<RequirementDocumentsPage />)

    expect(await screen.findByText('No requirement documents yet.')).toBeInTheDocument()
  })

  it('creates a document and refreshes the list', async () => {
    let created = false

    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (input.method === 'POST' && url.endsWith('/api/requirement-documents')) {
          created = true
          return jsonResponse(
            {
              id: 'd-2',
              project_id: '123e4567-e89b-12d3-a456-426614174000',
              type: 'srs',
              title: 'New Document',
              status: 'draft',
            },
            201,
          )
        }

        return jsonResponse({
          documents: created
            ? [
                {
                  id: 'd-2',
                  project_id: '123e4567-e89b-12d3-a456-426614174000',
                  type: 'srs',
                  title: 'New Document',
                  status: 'draft',
                },
              ]
            : [],
        })
      }),
    )
    const user = userEvent.setup()
    renderAtPath(<RequirementDocumentsPage />)

    expect(await screen.findByText('No requirement documents yet.')).toBeInTheDocument()

    await user.type(screen.getByLabelText(/project id/i), '123e4567-e89b-12d3-a456-426614174000')
    await user.selectOptions(screen.getByLabelText(/document type/i), 'srs')
    await user.type(screen.getByLabelText(/document title/i), 'New Document')
    await user.click(screen.getByRole('button', { name: /create document/i }))

    expect(await screen.findByText('New Document')).toBeInTheDocument()
  })

  it('shows client-side validation without calling the API', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => jsonResponse({ documents: [] })),
    )
    const user = userEvent.setup()
    renderAtPath(<RequirementDocumentsPage />)

    await screen.findByText('No requirement documents yet.')

    const fetchSpy = vi.mocked(globalThis.fetch)
    fetchSpy.mockClear()
    await user.click(screen.getByRole('button', { name: /create document/i }))

    expect(await screen.findByText('Enter a valid project id.')).toBeInTheDocument()
    expect(fetchSpy).not.toHaveBeenCalled()
  })
})
