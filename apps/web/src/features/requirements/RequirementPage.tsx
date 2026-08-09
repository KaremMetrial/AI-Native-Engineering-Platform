import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { getErrorMessage } from '../../shared/api/errors'
import styles from '../../shared/ui/ListPage.module.css'
import { approveRequirement, getRequirement } from './api'

export function RequirementPage() {
  const { requirementId } = useParams<{ requirementId: string }>()
  const queryClient = useQueryClient()

  if (requirementId === undefined) {
    throw new Error('RequirementPage rendered without a requirementId route param.')
  }

  const requirementQueryKey = ['requirements', requirementId] as const
  const requirement = useQuery({
    queryKey: requirementQueryKey,
    queryFn: () => getRequirement(requirementId),
  })

  const approve = useMutation({
    mutationFn: () => approveRequirement(requirementId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: requirementQueryKey })
    },
  })

  return (
    <div className={styles.page}>
      {requirement.isSuccess && (
        <Link
          className={styles.backLink}
          to={`/requirement-documents/${requirement.data.document_id}`}
        >
          ← Document
        </Link>
      )}

      {requirement.isPending && <p>Loading requirement…</p>}
      {requirement.isError && (
        <p className={styles.formError}>{getErrorMessage(requirement.error)}</p>
      )}
      {requirement.isSuccess && (
        <h1>
          {requirement.data.text} <span className={styles.status}>{requirement.data.status}</span>
        </h1>
      )}
      {requirement.isSuccess && requirement.data.status === 'draft' && (
        <button
          type="button"
          disabled={approve.isPending}
          onClick={() => {
            approve.mutate()
          }}
        >
          {approve.isPending ? 'Approving…' : 'Approve requirement'}
        </button>
      )}
      {approve.isError && <p className={styles.formError}>{getErrorMessage(approve.error)}</p>}

      {requirement.isSuccess && (
        <div className={styles.section}>
          <h2 className={styles.sectionTitle}>Acceptance criteria</h2>
          {requirement.data.acceptance_criteria.length === 0 && (
            <p className={styles.empty}>No acceptance criteria.</p>
          )}
          {requirement.data.acceptance_criteria.length > 0 && (
            <ul className={styles.list}>
              {requirement.data.acceptance_criteria.map((criterion) => (
                <li key={criterion} className={styles.listItem}>
                  <span>{criterion}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  )
}
