import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { type SubmitEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { z } from 'zod'
import { getErrorMessage } from '../../shared/api/errors'
import { TextField } from '../../shared/ui/TextField'
import { createProject, listProjects } from './api'
import styles from './GraphPage.module.css'

const projectsQueryKey = ['projects'] as const

const createProjectSchema = z.object({
  name: z.string().trim().min(1, 'Project name is required.').max(255),
})

export function ProjectsPage() {
  const queryClient = useQueryClient()
  const projects = useQuery({ queryKey: projectsQueryKey, queryFn: listProjects })
  const [name, setName] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: createProject,
    onSuccess: async () => {
      setName('')
      setFieldErrors({})
      setFormError(null)
      await queryClient.invalidateQueries({ queryKey: projectsQueryKey })
    },
    onError: (error) => {
      setFormError(getErrorMessage(error))
    },
  })

  function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    const result = createProjectSchema.safeParse({ name })

    if (!result.success) {
      setFieldErrors(z.flattenError(result.error).fieldErrors)
      setFormError(null)
      return
    }

    setFieldErrors({})
    setFormError(null)
    mutation.mutate(result.data.name)
  }

  return (
    <div className={styles.page}>
      <h1>Projects</h1>

      {projects.isPending && <p>Loading projects…</p>}
      {projects.isError && <p className={styles.formError}>{getErrorMessage(projects.error)}</p>}
      {projects.isSuccess && projects.data.length === 0 && (
        <p className={styles.empty}>No projects yet.</p>
      )}
      {projects.isSuccess && projects.data.length > 0 && (
        <ul className={styles.list}>
          {projects.data.map((project) => (
            <li key={project.id} className={styles.listItem}>
              <Link to={`/projects/${project.id}`}>{project.name}</Link>
              <span className={styles.status}>{project.status}</span>
            </li>
          ))}
        </ul>
      )}

      <form className={styles.form} onSubmit={handleSubmit} noValidate>
        <TextField
          label="New project name"
          name="name"
          value={name}
          onChange={(event) => {
            setName(event.target.value)
          }}
          errors={fieldErrors.name}
        />
        {formError !== null && <p className={styles.formError}>{formError}</p>}
        <button type="submit" disabled={mutation.isPending}>
          {mutation.isPending ? 'Creating…' : 'Create project'}
        </button>
      </form>
    </div>
  )
}
