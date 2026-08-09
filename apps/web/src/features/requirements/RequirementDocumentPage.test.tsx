import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { jsonResponse, renderAtPath, urlOf } from '../../shared/testUtils'
import { RequirementDocumentPage } from './RequirementDocumentPage'

function stubDocumentFetch(overrides: { status?: string } = {}) {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: Request) => {
      const url = urlOf(input)

      if (url.endsWith('/api/requirement-documents/doc-1')) {
        return jsonResponse({
          id: 'doc-1',
          project_id: 'proj-1',
          type: 'brd',
          title: 'Acme BRD',
          status: overrides.status ?? 'draft',
        })
      }

      if (url.endsWith('/api/requirement-documents/doc-1/requirements')) {
        return jsonResponse({ requirements: [] })
      }

      throw new Error(`Unexpected fetch to ${url}`)
    }),
  )
}

describe('RequirementDocumentPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows the document and its requirements', async () => {
    stubDocumentFetch()
    renderAtPath(
      <RequirementDocumentPage />,
      '/requirement-documents/:documentId',
      '/requirement-documents/doc-1',
    )

    expect(await screen.findByText('Acme BRD')).toBeInTheDocument()
    expect(await screen.findByText('No requirements yet.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /approve document/i })).toBeInTheDocument()
  })

  it('does not show the approve button for an approved document', async () => {
    stubDocumentFetch({ status: 'approved' })
    renderAtPath(
      <RequirementDocumentPage />,
      '/requirement-documents/:documentId',
      '/requirement-documents/doc-1',
    )

    await screen.findByText('Acme BRD')
    expect(screen.queryByRole('button', { name: /approve document/i })).not.toBeInTheDocument()
  })

  it('adds a requirement and shows it in the list', async () => {
    let added = false

    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (
          input.method === 'POST' &&
          url.endsWith('/api/requirement-documents/doc-1/requirements')
        ) {
          added = true
          return jsonResponse(
            {
              id: 'req-1',
              document_id: 'doc-1',
              text: 'The system shall allow login.',
              status: 'draft',
            },
            201,
          )
        }

        if (url.endsWith('/api/requirement-documents/doc-1')) {
          return jsonResponse({
            id: 'doc-1',
            project_id: 'proj-1',
            type: 'brd',
            title: 'Acme BRD',
            status: 'draft',
          })
        }

        if (url.endsWith('/api/requirement-documents/doc-1/requirements')) {
          return jsonResponse({
            requirements: added
              ? [
                  {
                    id: 'req-1',
                    document_id: 'doc-1',
                    text: 'The system shall allow login.',
                    acceptance_criteria: [],
                    status: 'draft',
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
      <RequirementDocumentPage />,
      '/requirement-documents/:documentId',
      '/requirement-documents/doc-1',
    )

    expect(await screen.findByText('No requirements yet.')).toBeInTheDocument()

    await user.type(screen.getByLabelText(/requirement text/i), 'The system shall allow login.')
    await user.click(screen.getByRole('button', { name: /add requirement/i }))

    expect(await screen.findByText('The system shall allow login.')).toBeInTheDocument()
  })

  it('approves the document', async () => {
    let approved = false

    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (input.method === 'POST' && url.endsWith('/api/requirement-documents/doc-1/approve')) {
          approved = true
          return jsonResponse({ id: 'doc-1', status: 'approved' })
        }

        if (url.endsWith('/api/requirement-documents/doc-1')) {
          return jsonResponse({
            id: 'doc-1',
            project_id: 'proj-1',
            type: 'brd',
            title: 'Acme BRD',
            status: approved ? 'approved' : 'draft',
          })
        }

        if (url.endsWith('/api/requirement-documents/doc-1/requirements')) {
          return jsonResponse({ requirements: [] })
        }

        throw new Error(`Unexpected fetch to ${url}`)
      }),
    )
    const user = userEvent.setup()
    renderAtPath(
      <RequirementDocumentPage />,
      '/requirement-documents/:documentId',
      '/requirement-documents/doc-1',
    )

    const approveButton = await screen.findByRole('button', { name: /approve document/i })
    await user.click(approveButton)

    await screen.findByText(/brd · approved/)
    expect(screen.queryByRole('button', { name: /approve document/i })).not.toBeInTheDocument()
  })
})
