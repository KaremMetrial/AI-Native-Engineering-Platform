import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { type SubmitEvent, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { z } from 'zod'
import { getErrorMessage } from '../../shared/api/errors'
import { TextField } from '../../shared/ui/TextField'
import { createArtifact, getProject, listProjectArtifacts } from './api'
import styles from './GraphPage.module.css'

const createArtifactSchema = z.object({
  type: z.string().trim().min(1, 'Artifact type is required.').max(100),
  content: z.string().trim().min(1, 'Content is required.'),
})

export function ProjectPage() {
  const { projectId } = useParams<{ projectId: string }>()
  const queryClient = useQueryClient()

  if (projectId === undefined) {
    throw new Error('ProjectPage rendered without a projectId route param.')
  }

  // Rebound to a fresh const: TypeScript's narrowing above doesn't
  // propagate into the hoisted `function handleSubmit` declaration below,
  // only into arrow-function closures.
  const currentProjectId = projectId

  const projectQueryKey = ['projects', currentProjectId] as const
  const artifactsQueryKey = ['projects', currentProjectId, 'artifacts'] as const

  const project = useQuery({
    queryKey: projectQueryKey,
    queryFn: () => getProject(currentProjectId),
  })
  const artifacts = useQuery({
    queryKey: artifactsQueryKey,
    queryFn: () => listProjectArtifacts(currentProjectId),
  })

  const [type, setType] = useState('')
  const [content, setContent] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: createArtifact,
    onSuccess: async () => {
      setType('')
      setContent('')
      setFieldErrors({})
      setFormError(null)
      await queryClient.invalidateQueries({ queryKey: artifactsQueryKey })
    },
    onError: (error) => {
      setFormError(getErrorMessage(error))
    },
  })

  function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    const result = createArtifactSchema.safeParse({ type, content })

    if (!result.success) {
      setFieldErrors(z.flattenError(result.error).fieldErrors)
      setFormError(null)
      return
    }

    setFieldErrors({})
    setFormError(null)
    mutation.mutate({
      projectId: currentProjectId,
      type: result.data.type,
      content: result.data.content,
    })
  }

  return (
    <div className={styles.page}>
      <Link className={styles.backLink} to="/projects">
        ← Projects
      </Link>

      {project.isPending && <p>Loading project…</p>}
      {project.isError && <p className={styles.formError}>{getErrorMessage(project.error)}</p>}
      {project.isSuccess && (
        <h1>
          {project.data.name} <span className={styles.status}>{project.data.status}</span>
        </h1>
      )}

      <div className={styles.section}>
        <h2 className={styles.sectionTitle}>Artifacts</h2>
        {artifacts.isPending && <p>Loading artifacts…</p>}
        {artifacts.isError && (
          <p className={styles.formError}>{getErrorMessage(artifacts.error)}</p>
        )}
        {artifacts.isSuccess && artifacts.data.length === 0 && (
          <p className={styles.empty}>No artifacts yet.</p>
        )}
        {artifacts.isSuccess && artifacts.data.length > 0 && (
          <ul className={styles.list}>
            {artifacts.data.map((artifact) => (
              <li key={artifact.id} className={styles.listItem}>
                <Link to={`/artifacts/${artifact.id}`}>{artifact.type}</Link>
                <span className={styles.status}>
                  {artifact.status} · v{artifact.version_count}
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>

      <form className={styles.form} onSubmit={handleSubmit} noValidate>
        <TextField
          label="Artifact type"
          name="type"
          placeholder="e.g. srs, task, test"
          value={type}
          onChange={(event) => {
            setType(event.target.value)
          }}
          errors={fieldErrors.type}
        />
        <textarea
          className={styles.textarea}
          aria-label="Content"
          rows={4}
          value={content}
          onChange={(event) => {
            setContent(event.target.value)
          }}
        />
        {fieldErrors.content !== undefined && fieldErrors.content.length > 0 && (
          <p className={styles.formError}>{fieldErrors.content[0]}</p>
        )}
        {formError !== null && <p className={styles.formError}>{formError}</p>}
        <button type="submit" disabled={mutation.isPending}>
          {mutation.isPending ? 'Creating…' : 'Create artifact'}
        </button>
      </form>
    </div>
  )
}
