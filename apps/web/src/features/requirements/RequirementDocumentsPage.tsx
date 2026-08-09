import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { type SubmitEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { z } from 'zod'
import { getErrorMessage } from '../../shared/api/errors'
import { TextField } from '../../shared/ui/TextField'
import styles from '../../shared/ui/ListPage.module.css'
import { type DocumentType, createDocument, listDocuments } from './api'

const documentsQueryKey = ['requirement-documents'] as const

const documentTypes: DocumentType[] = ['brd', 'srs']

const createDocumentSchema = z.object({
  project_id: z.uuid('Enter a valid project id.'),
  type: z.enum(documentTypes),
  title: z.string().trim().min(1, 'Title is required.').max(255),
})

export function RequirementDocumentsPage() {
  const queryClient = useQueryClient()
  const documents = useQuery({ queryKey: documentsQueryKey, queryFn: listDocuments })
  const [projectId, setProjectId] = useState('')
  const [type, setType] = useState<DocumentType>('brd')
  const [title, setTitle] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: createDocument,
    onSuccess: async () => {
      setProjectId('')
      setTitle('')
      setFieldErrors({})
      setFormError(null)
      await queryClient.invalidateQueries({ queryKey: documentsQueryKey })
    },
    onError: (error) => {
      setFormError(getErrorMessage(error))
    },
  })

  function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    const result = createDocumentSchema.safeParse({ project_id: projectId, type, title })

    if (!result.success) {
      setFieldErrors(z.flattenError(result.error).fieldErrors)
      setFormError(null)
      return
    }

    setFieldErrors({})
    setFormError(null)
    mutation.mutate({
      projectId: result.data.project_id,
      type: result.data.type,
      title: result.data.title,
    })
  }

  return (
    <div className={styles.page}>
      <h1>Requirement documents</h1>

      {documents.isPending && <p>Loading documents…</p>}
      {documents.isError && <p className={styles.formError}>{getErrorMessage(documents.error)}</p>}
      {documents.isSuccess && documents.data.length === 0 && (
        <p className={styles.empty}>No requirement documents yet.</p>
      )}
      {documents.isSuccess && documents.data.length > 0 && (
        <ul className={styles.list}>
          {documents.data.map((document) => (
            <li key={document.id} className={styles.listItem}>
              <Link to={`/requirement-documents/${document.id}`}>{document.title}</Link>
              <span className={styles.status}>
                {document.type} · {document.status}
              </span>
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
        <select
          className={styles.select}
          aria-label="Document type"
          value={type}
          onChange={(event) => {
            setType(event.target.value as DocumentType)
          }}
        >
          {documentTypes.map((documentType) => (
            <option key={documentType} value={documentType}>
              {documentType}
            </option>
          ))}
        </select>
        <TextField
          label="Document title"
          name="title"
          value={title}
          onChange={(event) => {
            setTitle(event.target.value)
          }}
          errors={fieldErrors.title}
        />
        {formError !== null && <p className={styles.formError}>{formError}</p>}
        <button type="submit" disabled={mutation.isPending}>
          {mutation.isPending ? 'Creating…' : 'Create document'}
        </button>
      </form>
    </div>
  )
}
