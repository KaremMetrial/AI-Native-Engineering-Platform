import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { type SubmitEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { z } from 'zod'
import { getErrorMessage } from '../../shared/api/errors'
import { TextField } from '../../shared/ui/TextField'
import styles from '../../shared/ui/ListPage.module.css'
import { listSessions, startSession } from './api'

const sessionsQueryKey = ['discovery-sessions'] as const

const startSessionSchema = z.object({
  project_id: z.uuid('Enter a valid project id.'),
  title: z.string().trim().min(1, 'Title is required.').max(255),
})

export function DiscoverySessionsPage() {
  const queryClient = useQueryClient()
  const sessions = useQuery({ queryKey: sessionsQueryKey, queryFn: listSessions })
  const [projectId, setProjectId] = useState('')
  const [title, setTitle] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: startSession,
    onSuccess: async () => {
      setProjectId('')
      setTitle('')
      setFieldErrors({})
      setFormError(null)
      await queryClient.invalidateQueries({ queryKey: sessionsQueryKey })
    },
    onError: (error) => {
      setFormError(getErrorMessage(error))
    },
  })

  function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    const result = startSessionSchema.safeParse({ project_id: projectId, title })

    if (!result.success) {
      setFieldErrors(z.flattenError(result.error).fieldErrors)
      setFormError(null)
      return
    }

    setFieldErrors({})
    setFormError(null)
    mutation.mutate({ projectId: result.data.project_id, title: result.data.title })
  }

  return (
    <div className={styles.page}>
      <h1>Discovery sessions</h1>

      {sessions.isPending && <p>Loading sessions…</p>}
      {sessions.isError && <p className={styles.formError}>{getErrorMessage(sessions.error)}</p>}
      {sessions.isSuccess && sessions.data.length === 0 && (
        <p className={styles.empty}>No discovery sessions yet.</p>
      )}
      {sessions.isSuccess && sessions.data.length > 0 && (
        <ul className={styles.list}>
          {sessions.data.map((session) => (
            <li key={session.id} className={styles.listItem}>
              <Link to={`/discovery-sessions/${session.id}`}>{session.title}</Link>
              <span className={styles.status}>{session.status}</span>
            </li>
          ))}
        </ul>
      )}

      <form className={styles.form} onSubmit={handleSubmit} noValidate>
        <TextField
          label="Project id"
          name="project_id"
          value={projectId}
          onChange={(event) => {
            setProjectId(event.target.value)
          }}
          errors={fieldErrors.project_id}
        />
        <TextField
          label="Session title"
          name="title"
          value={title}
          onChange={(event) => {
            setTitle(event.target.value)
          }}
          errors={fieldErrors.title}
        />
        {formError !== null && <p className={styles.formError}>{formError}</p>}
        <button type="submit" disabled={mutation.isPending}>
          {mutation.isPending ? 'Starting…' : 'Start session'}
        </button>
      </form>
    </div>
  )
}
