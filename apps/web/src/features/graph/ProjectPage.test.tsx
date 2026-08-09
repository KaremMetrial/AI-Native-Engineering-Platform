import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { jsonResponse, renderAtPath, urlOf } from '../../shared/testUtils'
import { ProjectPage } from './ProjectPage'

describe('ProjectPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows the project and its artifacts', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (url.endsWith('/api/projects/proj-1')) {
          return jsonResponse({ id: 'proj-1', name: 'Acme Website', status: 'active' })
        }

        if (url.endsWith('/api/projects/proj-1/artifacts')) {
          return jsonResponse({
            artifacts: [
              {
                id: 'art-1',
                project_id: 'proj-1',
                type: 'srs',
                status: 'active',
                current_version_id: 'v-1',
                version_count: 1,
              },
            ],
          })
        }

        throw new Error(`Unexpected fetch to ${url}`)
      }),
    )
    renderAtPath(<ProjectPage />, '/projects/:projectId', '/projects/proj-1')

    expect(await screen.findByText('Acme Website')).toBeInTheDocument()
    expect(await screen.findByText('srs')).toBeInTheDocument()
  })

  it('shows an empty state when the project has no artifacts', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (url.endsWith('/api/projects/proj-1')) {
          return jsonResponse({ id: 'proj-1', name: 'Acme Website', status: 'active' })
        }

        return jsonResponse({ artifacts: [] })
      }),
    )
    renderAtPath(<ProjectPage />, '/projects/:projectId', '/projects/proj-1')

    expect(await screen.findByText('No artifacts yet.')).toBeInTheDocument()
  })

  it('creates an artifact and refreshes the list', async () => {
    let created = false

    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (url.endsWith('/api/projects/proj-1') && input.method === 'GET') {
          return jsonResponse({ id: 'proj-1', name: 'Acme Website', status: 'active' })
        }

        if (input.method === 'POST' && url.endsWith('/api/artifacts')) {
          created = true
          return jsonResponse(
            { id: 'art-2', type: 'task', status: 'active', current_version_id: 'v-2' },
            201,
          )
        }

        if (url.endsWith('/api/projects/proj-1/artifacts')) {
          return jsonResponse({
            artifacts: created
              ? [
                  {
                    id: 'art-2',
                    project_id: 'proj-1',
                    type: 'task',
                    status: 'active',
                    current_version_id: 'v-2',
                    version_count: 1,
                  },
                ]
              : [],
          })
        }

        throw new Error(`Unexpected fetch to ${url}`)
      }),
    )
    const user = userEvent.setup()
    renderAtPath(<ProjectPage />, '/projects/:projectId', '/projects/proj-1')

    expect(await screen.findByText('No artifacts yet.')).toBeInTheDocument()

    await user.type(screen.getByLabelText(/artifact type/i), 'task')
    await user.type(screen.getByLabelText(/^content$/i), 'Build the API.')
    await user.click(screen.getByRole('button', { name: /create artifact/i }))

    expect(await screen.findByText('task')).toBeInTheDocument()
  })
})
