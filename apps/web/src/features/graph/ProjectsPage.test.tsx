import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { jsonResponse, renderAtPath, urlOf } from '../../shared/testUtils'
import { ProjectsPage } from './ProjectsPage'

describe('ProjectsPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('lists projects returned by the API', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        jsonResponse({
          projects: [
            { id: 'p-1', name: 'Acme Website', status: 'active' },
            { id: 'p-2', name: 'Acme Mobile', status: 'active' },
          ],
        }),
      ),
    )
    renderAtPath(<ProjectsPage />)

    expect(await screen.findByText('Acme Website')).toBeInTheDocument()
    expect(screen.getByText('Acme Mobile')).toBeInTheDocument()
  })

  it('shows an empty state when there are no projects', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => jsonResponse({ projects: [] })),
    )
    renderAtPath(<ProjectsPage />)

    expect(await screen.findByText('No projects yet.')).toBeInTheDocument()
  })

  it('creates a project and refreshes the list', async () => {
    let created = false

    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (input.method === 'POST' && url.endsWith('/api/projects')) {
          created = true
          return jsonResponse({ id: 'p-3', name: 'New Project', status: 'active' }, 201)
        }

        return jsonResponse({
          projects: created ? [{ id: 'p-3', name: 'New Project', status: 'active' }] : [],
        })
      }),
    )
    const user = userEvent.setup()
    renderAtPath(<ProjectsPage />)

    expect(await screen.findByText('No projects yet.')).toBeInTheDocument()

    await user.type(screen.getByLabelText(/new project name/i), 'New Project')
    await user.click(screen.getByRole('button', { name: /create project/i }))

    expect(await screen.findByText('New Project')).toBeInTheDocument()
  })

  it('shows client-side validation without calling the API', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => jsonResponse({ projects: [] })),
    )
    const user = userEvent.setup()
    renderAtPath(<ProjectsPage />)

    await screen.findByText('No projects yet.')

    const fetchSpy = vi.mocked(globalThis.fetch)
    fetchSpy.mockClear()
    await user.click(screen.getByRole('button', { name: /create project/i }))

    expect(await screen.findByText('Project name is required.')).toBeInTheDocument()
    expect(fetchSpy).not.toHaveBeenCalled()
  })
})
