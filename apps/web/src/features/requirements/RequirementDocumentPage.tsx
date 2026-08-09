import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { type SubmitEvent, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { z } from 'zod'
import { getErrorMessage } from '../../shared/api/errors'
import styles from '../../shared/ui/ListPage.module.css'
import { addRequirement, approveDocument, getDocument, listRequirements } from './api'

const addRequirementSchema = z.object({
  text: z.string().trim().min(1, 'Text is required.'),
})

export function RequirementDocumentPage() {
  const { documentId } = useParams<{ documentId: string }>()
  const queryClient = useQueryClient()

  if (documentId === undefined) {
    throw new Error('RequirementDocumentPage rendered without a documentId route param.')
  }

  // Rebound to a fresh const: TypeScript's narrowing above doesn't
  // propagate into the hoisted `function handleSubmit` declaration below,
  // only into arrow-function closures.
  const currentDocumentId = documentId

  const documentQueryKey = ['requirement-documents', currentDocumentId] as const
  const requirementsQueryKey = ['requirement-documents', currentDocumentId, 'requirements'] as const

  const document = useQuery({
    queryKey: documentQueryKey,
    queryFn: () => getDocument(currentDocumentId),
  })
  const requirements = useQuery({
    queryKey: requirementsQueryKey,
    queryFn: () => listRequirements(currentDocumentId),
  })

  const approve = useMutation({
    mutationFn: () => approveDocument(currentDocumentId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: documentQueryKey })
    },
  })

  const [text, setText] = useState('')
  const [acceptanceCriteriaText, setAcceptanceCriteriaText] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)

  const addRequirementMutation = useMutation({
    mutationFn: addRequirement,
    onSuccess: async () => {
      setText('')
      setAcceptanceCriteriaText('')
      setFieldErrors({})
      setFormError(null)
      await queryClient.invalidateQueries({ queryKey: requirementsQueryKey })
    },
    onError: (error) => {
      setFormError(getErrorMessage(error))
    },
  })

  function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    const result = addRequirementSchema.safeParse({ text })

    if (!result.success) {
      setFieldErrors(z.flattenError(result.error).fieldErrors)
      setFormError(null)
      return
    }

    setFieldErrors({})
    setFormError(null)
    const acceptanceCriteria = acceptanceCriteriaText
      .split('\n')
      .map((line) => line.trim())
      .filter((line) => line.length > 0)

    addRequirementMutation.mutate({
      documentId: currentDocumentId,
      text: result.data.text,
      acceptanceCriteria,
    })
  }

  return (
    <div className={styles.page}>
      <Link className={styles.backLink} to="/requirement-documents">
        ← Requirement documents
      </Link>

      {document.isPending && <p>Loading document…</p>}
      {document.isError && <p className={styles.formError}>{getErrorMessage(document.error)}</p>}
      {document.isSuccess && (
        <h1>
          {document.data.title}{' '}
          <span className={styles.status}>
            {document.data.type} · {document.data.status}
          </span>
        </h1>
      )}
      {document.isSuccess && document.data.status === 'draft' && (
        <button
          type="button"
          disabled={approve.isPending}
          onClick={() => {
            approve.mutate()
          }}
        >
          {approve.isPending ? 'Approving…' : 'Approve document'}
        </button>
      )}
      {approve.isError && <p className={styles.formError}>{getErrorMessage(approve.error)}</p>}

      <div className={styles.section}>
        <h2 className={styles.sectionTitle}>Requirements</h2>
        {requirements.isPending && <p>Loading requirements…</p>}
        {requirements.isError && (
          <p className={styles.formError}>{getErrorMessage(requirements.error)}</p>
        )}
        {requirements.isSuccess && requirements.data.length === 0 && (
          <p className={styles.empty}>No requirements yet.</p>
        )}
        {requirements.isSuccess && requirements.data.length > 0 && (
          <ul className={styles.list}>
            {requirements.data.map((requirement) => (
              <li key={requirement.id} className={styles.listItem}>
                <Link to={`/requirements/${requirement.id}`}>{requirement.text}</Link>
                <span className={styles.status}>{requirement.status}</span>
              </li>
            ))}
          </ul>
        )}
        <form className={styles.form} onSubmit={handleSubmit} noValidate>
          <textarea
            className={styles.textarea}
            aria-label="Requirement text"
            rows={2}
            value={text}
            onChange={(event) => {
              setText(event.target.value)
            }}
          />
          {fieldErrors.text !== undefined && fieldErrors.text.length > 0 && (
            <p className={styles.formError}>{fieldErrors.text[0]}</p>
          )}
          <textarea
            className={styles.textarea}
            aria-label="Acceptance criteria (one per line)"
            placeholder="One acceptance criterion per line"
            rows={3}
            value={acceptanceCriteriaText}
            onChange={(event) => {
              setAcceptanceCriteriaText(event.target.value)
            }}
          />
          {formError !== null && <p className={styles.formError}>{formError}</p>}
          <button type="submit" disabled={addRequirementMutation.isPending}>
            {addRequirementMutation.isPending ? 'Adding…' : 'Add requirement'}
          </button>
        </form>
      </div>
    </div>
  )
}
