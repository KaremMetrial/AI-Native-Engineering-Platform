import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { type SubmitEvent, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { z } from 'zod'
import { getErrorMessage } from '../../shared/api/errors'
import styles from '../../shared/ui/ListPage.module.css'
import { getQuestion, listResponses, recordResponse } from './api'

const responseSchema = z.object({
  content: z.string().trim().min(1, 'Content is required.'),
})

export function DiscoveryQuestionPage() {
  const { questionId } = useParams<{ questionId: string }>()
  const queryClient = useQueryClient()

  if (questionId === undefined) {
    throw new Error('DiscoveryQuestionPage rendered without a questionId route param.')
  }

  const questionQueryKey = ['discovery-questions', questionId] as const
  const responsesQueryKey = ['discovery-questions', questionId, 'responses'] as const

  const question = useQuery({ queryKey: questionQueryKey, queryFn: () => getQuestion(questionId) })
  const responses = useQuery({
    queryKey: responsesQueryKey,
    queryFn: () => listResponses(questionId),
  })

  const [content, setContent] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: (value: string) => recordResponse(questionId, value),
    onSuccess: async () => {
      setContent('')
      setFieldErrors({})
      setFormError(null)
      await queryClient.invalidateQueries({ queryKey: responsesQueryKey })
    },
    onError: (error) => {
      setFormError(getErrorMessage(error))
    },
  })

  function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    const result = responseSchema.safeParse({ content })

    if (!result.success) {
      setFieldErrors(z.flattenError(result.error).fieldErrors)
      setFormError(null)
      return
    }

    setFieldErrors({})
    setFormError(null)
    mutation.mutate(result.data.content)
  }

  return (
    <div className={styles.page}>
      {question.isSuccess && (
        <Link className={styles.backLink} to={`/discovery-sessions/${question.data.session_id}`}>
          ← Session
        </Link>
      )}

      {question.isPending && <p>Loading question…</p>}
      {question.isError && <p className={styles.formError}>{getErrorMessage(question.error)}</p>}
      {question.isSuccess && <h1>{question.data.prompt}</h1>}

      <div className={styles.section}>
        <h2 className={styles.sectionTitle}>Responses</h2>
        {responses.isPending && <p>Loading responses…</p>}
        {responses.isError && (
          <p className={styles.formError}>{getErrorMessage(responses.error)}</p>
        )}
        {responses.isSuccess && responses.data.length === 0 && (
          <p className={styles.empty}>No responses yet.</p>
        )}
        {responses.isSuccess && responses.data.length > 0 && (
          <ul className={styles.list}>
            {responses.data.map((response) => (
              <li key={response.id} className={styles.listItem}>
                <span>{response.content}</span>
              </li>
            ))}
          </ul>
        )}
        <form className={styles.form} onSubmit={handleSubmit} noValidate>
          <textarea
            className={styles.textarea}
            aria-label="Response content"
            rows={3}
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
            {mutation.isPending ? 'Recording…' : 'Record response'}
          </button>
        </form>
      </div>
    </div>
  )
}
