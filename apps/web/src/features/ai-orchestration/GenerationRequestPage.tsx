import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { getErrorMessage } from '../../shared/api/errors'
import styles from '../../shared/ui/ListPage.module.css'
import { getGenerationRequest } from './api'

// P-9's sync/async boundary made visible: the POST that created this
// record returned immediately with `queued`, and ProcessGenerationRequest
// (a queued job) advances it off the request path. Poll while queued so
// the page reflects that transition without a manual refresh; stop once
// the request reaches a terminal status.
const pollWhileQueuedMs = 1000

export function GenerationRequestPage() {
  const { generationRequestId } = useParams<{ generationRequestId: string }>()

  if (generationRequestId === undefined) {
    throw new Error('GenerationRequestPage rendered without a generationRequestId route param.')
  }

  // Rebound to a fresh const: TypeScript's narrowing above doesn't
  // propagate into closures captured by useQuery's options object the
  // way it does for a directly-referenced local.
  const currentGenerationRequestId = generationRequestId

  const generationRequest = useQuery({
    queryKey: ['generation-requests', currentGenerationRequestId] as const,
    queryFn: () => getGenerationRequest(currentGenerationRequestId),
    refetchInterval: (query) => (query.state.data?.status === 'queued' ? pollWhileQueuedMs : false),
  })

  return (
    <div className={styles.page}>
      <Link className={styles.backLink} to="/generation-requests">
        ← Generation requests
      </Link>

      {generationRequest.isPending && <p>Loading generation request…</p>}
      {generationRequest.isError && (
        <p className={styles.formError}>{getErrorMessage(generationRequest.error)}</p>
      )}
      {generationRequest.isSuccess && (
        <>
          <h1>
            {generationRequest.data.workflow_name}{' '}
            <span className={styles.status}>{generationRequest.data.status}</span>
          </h1>
          {generationRequest.data.status === 'queued' && <p>Waiting for a model to be selected…</p>}
          {generationRequest.data.selected_model_id !== null && (
            <p>
              Selected model: <strong>{generationRequest.data.selected_model_id}</strong>
            </p>
          )}
          {generationRequest.data.failure_reason !== null && (
            <p className={styles.formError}>{generationRequest.data.failure_reason}</p>
          )}
        </>
      )}
    </div>
  )
}
