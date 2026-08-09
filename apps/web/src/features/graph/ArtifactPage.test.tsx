import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { jsonResponse, renderAtPath, urlOf } from '../../shared/testUtils'
import { ArtifactPage } from './ArtifactPage'

function stubArtifactFetch(
  overrides: { onPost?: (url: string, input: Request) => Response | undefined } = {},
) {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: Request) => {
      const url = urlOf(input)

      if (input.method === 'POST' && overrides.onPost) {
        const response = overrides.onPost(url, input)
        if (response) {
          return response
        }
      }

      if (url.endsWith('/api/artifacts/art-1')) {
        return jsonResponse({
          id: 'art-1',
          project_id: 'proj-1',
          type: 'srs',
          status: 'active',
          current_version_id: 'v-1',
          versions: [
            {
              id: 'v-1',
              version_number: 1,
              content: 'The system shall...',
              lineage: {
                model: null,
                prompt_version: null,
                input_version_ids: [],
                tokens: null,
                cost: null,
              },
              created_by: 'u-1',
              created_at: '2026-08-09T00:00:00+00:00',
            },
          ],
        })
      }

      if (url.endsWith('/api/artifact-versions/v-1/links')) {
        return jsonResponse({ outgoing: [], incoming: [] })
      }

      if (url.endsWith('/api/artifact-versions/v-1/approvals')) {
        return jsonResponse({ approvals: [] })
      }

      throw new Error(`Unexpected fetch to ${url}`)
    }),
  )
}

describe('ArtifactPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows the artifact, its version history, links and approvals', async () => {
    stubArtifactFetch()
    renderAtPath(<ArtifactPage />, '/artifacts/:artifactId', '/artifacts/art-1')

    expect(await screen.findByText('srs')).toBeInTheDocument()
    expect(await screen.findByText(/The system shall.../)).toBeInTheDocument()
    expect(await screen.findByText('No links yet.')).toBeInTheDocument()
    expect(await screen.findByText('No approvals yet.')).toBeInTheDocument()
  })

  it('adds a new version and shows it in the history', async () => {
    let versionAdded = false

    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (input.method === 'POST' && url.endsWith('/api/artifacts/art-1/versions')) {
          versionAdded = true
          return jsonResponse({ id: 'v-2', artifact_id: 'art-1', version_number: 2 }, 201)
        }

        if (url.endsWith('/api/artifacts/art-1')) {
          const versions = [
            {
              id: 'v-1',
              version_number: 1,
              content: 'The system shall...',
              lineage: {
                model: null,
                prompt_version: null,
                input_version_ids: [],
                tokens: null,
                cost: null,
              },
              created_by: 'u-1',
              created_at: '2026-08-09T00:00:00+00:00',
            },
          ]

          if (versionAdded) {
            versions.push({
              id: 'v-2',
              version_number: 2,
              content: 'Revised draft.',
              lineage: {
                model: null,
                prompt_version: null,
                input_version_ids: [],
                tokens: null,
                cost: null,
              },
              created_by: 'u-1',
              created_at: '2026-08-09T00:01:00+00:00',
            })
          }

          return jsonResponse({
            id: 'art-1',
            project_id: 'proj-1',
            type: 'srs',
            status: 'active',
            current_version_id: versionAdded ? 'v-2' : 'v-1',
            versions,
          })
        }

        if (
          url.endsWith('/api/artifact-versions/v-1/links') ||
          url.endsWith('/api/artifact-versions/v-2/links')
        ) {
          return jsonResponse({ outgoing: [], incoming: [] })
        }

        if (
          url.endsWith('/api/artifact-versions/v-1/approvals') ||
          url.endsWith('/api/artifact-versions/v-2/approvals')
        ) {
          return jsonResponse({ approvals: [] })
        }

        throw new Error(`Unexpected fetch to ${url}`)
      }),
    )
    const user = userEvent.setup()
    renderAtPath(<ArtifactPage />, '/artifacts/:artifactId', '/artifacts/art-1')

    await screen.findByText(/The system shall.../)

    await user.type(screen.getByLabelText(/new version content/i), 'Revised draft.')
    await user.click(screen.getByRole('button', { name: /add version/i }))

    expect(await screen.findByText(/Revised draft\./)).toBeInTheDocument()
  })

  it('links the current version to another version', async () => {
    let linked = false

    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (input.method === 'POST' && url.endsWith('/api/artifact-links')) {
          linked = true
          return jsonResponse(
            { id: 'link-1', from_version_id: 'v-1', to_version_id: 'v-9', link_type: 'implements' },
            201,
          )
        }

        if (url.endsWith('/api/artifacts/art-1')) {
          return jsonResponse({
            id: 'art-1',
            project_id: 'proj-1',
            type: 'srs',
            status: 'active',
            current_version_id: 'v-1',
            versions: [
              {
                id: 'v-1',
                version_number: 1,
                content: 'The system shall...',
                lineage: {
                  model: null,
                  prompt_version: null,
                  input_version_ids: [],
                  tokens: null,
                  cost: null,
                },
                created_by: 'u-1',
                created_at: '2026-08-09T00:00:00+00:00',
              },
            ],
          })
        }

        if (url.endsWith('/api/artifact-versions/v-1/links')) {
          return jsonResponse({
            outgoing: linked
              ? [
                  {
                    id: 'link-1',
                    from_version_id: 'v-1',
                    to_version_id: 'v-9',
                    link_type: 'implements',
                  },
                ]
              : [],
            incoming: [],
          })
        }

        if (url.endsWith('/api/artifact-versions/v-1/approvals')) {
          return jsonResponse({ approvals: [] })
        }

        throw new Error(`Unexpected fetch to ${url}`)
      }),
    )
    const user = userEvent.setup()
    renderAtPath(<ArtifactPage />, '/artifacts/:artifactId', '/artifacts/art-1')

    expect(await screen.findByText('No links yet.')).toBeInTheDocument()

    await user.type(
      screen.getByLabelText(/target version id/i),
      '123e4567-e89b-12d3-a456-426614174000',
    )
    await user.click(screen.getByRole('button', { name: /link versions/i }))

    expect(await screen.findByText(/implements → v-9/)).toBeInTheDocument()
  })

  it('records an approval decision', async () => {
    let approved = false

    vi.stubGlobal(
      'fetch',
      vi.fn((input: Request) => {
        const url = urlOf(input)

        if (input.method === 'POST' && url.endsWith('/api/artifact-versions/v-1/approvals')) {
          approved = true
          return jsonResponse(
            { id: 'appr-1', artifact_version_id: 'v-1', decision: 'approved' },
            201,
          )
        }

        if (url.endsWith('/api/artifacts/art-1')) {
          return jsonResponse({
            id: 'art-1',
            project_id: 'proj-1',
            type: 'srs',
            status: 'active',
            current_version_id: 'v-1',
            versions: [
              {
                id: 'v-1',
                version_number: 1,
                content: 'The system shall...',
                lineage: {
                  model: null,
                  prompt_version: null,
                  input_version_ids: [],
                  tokens: null,
                  cost: null,
                },
                created_by: 'u-1',
                created_at: '2026-08-09T00:00:00+00:00',
              },
            ],
          })
        }

        if (url.endsWith('/api/artifact-versions/v-1/links')) {
          return jsonResponse({ outgoing: [], incoming: [] })
        }

        if (url.endsWith('/api/artifact-versions/v-1/approvals')) {
          return jsonResponse({
            approvals: approved
              ? [
                  {
                    id: 'appr-1',
                    artifact_version_id: 'v-1',
                    approved_by: 'u-1',
                    decision: 'approved',
                    comment: null,
                    created_at: '2026-08-09T00:00:00+00:00',
                  },
                ]
              : [],
          })
        }

        throw new Error(`Unexpected fetch to ${url}`)
      }),
    )
    const user = userEvent.setup()
    renderAtPath(<ArtifactPage />, '/artifacts/:artifactId', '/artifacts/art-1')

    expect(await screen.findByText('No approvals yet.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /record decision/i }))

    expect(await screen.findByText('approved')).toBeInTheDocument()
  })
})
