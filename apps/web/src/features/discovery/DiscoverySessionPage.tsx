import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { type SubmitEvent, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { z } from 'zod'
import { getErrorMessage } from '../../shared/api/errors'
import styles from '../../shared/ui/ListPage.module.css'
import {
  addQuestion,
  captureAssumption,
  captureConstraint,
  completeSession,
  getSession,
  listAssumptions,
  listConstraints,
  listQuestions,
} from './api'

const statementSchema = z.object({
  statement: z.string().trim().min(1, 'This field is required.'),
})

const promptSchema = z.object({
  prompt: z.string().trim().min(1, 'This field is required.'),
})

export function DiscoverySessionPage() {
  const { sessionId } = useParams<{ sessionId: string }>()
  const queryClient = useQueryClient()

  if (sessionId === undefined) {
    throw new Error('DiscoverySessionPage rendered without a sessionId route param.')
  }

  const sessionQueryKey = ['discovery-sessions', sessionId] as const
  const questionsQueryKey = ['discovery-sessions', sessionId, 'questions'] as const
  const assumptionsQueryKey = ['discovery-sessions', sessionId, 'assumptions'] as const
  const constraintsQueryKey = ['discovery-sessions', sessionId, 'constraints'] as const

  const session = useQuery({ queryKey: sessionQueryKey, queryFn: () => getSession(sessionId) })
  const questions = useQuery({
    queryKey: questionsQueryKey,
    queryFn: () => listQuestions(sessionId),
  })
  const assumptions = useQuery({
    queryKey: assumptionsQueryKey,
    queryFn: () => listAssumptions(sessionId),
  })
  const constraints = useQuery({
    queryKey: constraintsQueryKey,
    queryFn: () => listConstraints(sessionId),
  })

  const complete = useMutation({
    mutationFn: () => completeSession(sessionId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: sessionQueryKey })
    },
  })

  const [prompt, setPrompt] = useState('')
  const [questionFieldErrors, setQuestionFieldErrors] = useState<Record<string, string[]>>({})
  const [questionFormError, setQuestionFormError] = useState<string | null>(null)

  const addQuestionMutation = useMutation({
    mutationFn: (value: string) => addQuestion(sessionId, value),
    onSuccess: async () => {
      setPrompt('')
      setQuestionFieldErrors({})
      setQuestionFormError(null)
      await queryClient.invalidateQueries({ queryKey: questionsQueryKey })
    },
    onError: (error) => {
      setQuestionFormError(getErrorMessage(error))
    },
  })

  function handleAddQuestion(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    const result = promptSchema.safeParse({ prompt })

    if (!result.success) {
      setQuestionFieldErrors(z.flattenError(result.error).fieldErrors)
      setQuestionFormError(null)
      return
    }

    setQuestionFieldErrors({})
    setQuestionFormError(null)
    addQuestionMutation.mutate(result.data.prompt)
  }

  const [assumptionStatement, setAssumptionStatement] = useState('')
  const [assumptionFieldErrors, setAssumptionFieldErrors] = useState<Record<string, string[]>>({})
  const [assumptionFormError, setAssumptionFormError] = useState<string | null>(null)

  const addAssumption = useMutation({
    mutationFn: (value: string) => captureAssumption(sessionId, value),
    onSuccess: async () => {
      setAssumptionStatement('')
      setAssumptionFieldErrors({})
      setAssumptionFormError(null)
      await queryClient.invalidateQueries({ queryKey: assumptionsQueryKey })
    },
    onError: (error) => {
      setAssumptionFormError(getErrorMessage(error))
    },
  })

  function handleAddAssumption(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    const result = statementSchema.safeParse({ statement: assumptionStatement })

    if (!result.success) {
      setAssumptionFieldErrors(z.flattenError(result.error).fieldErrors)
      setAssumptionFormError(null)
      return
    }

    setAssumptionFieldErrors({})
    setAssumptionFormError(null)
    addAssumption.mutate(result.data.statement)
  }

  const [constraintStatement, setConstraintStatement] = useState('')
  const [constraintFieldErrors, setConstraintFieldErrors] = useState<Record<string, string[]>>({})
  const [constraintFormError, setConstraintFormError] = useState<string | null>(null)

  const addConstraint = useMutation({
    mutationFn: (value: string) => captureConstraint(sessionId, value),
    onSuccess: async () => {
      setConstraintStatement('')
      setConstraintFieldErrors({})
      setConstraintFormError(null)
      await queryClient.invalidateQueries({ queryKey: constraintsQueryKey })
    },
    onError: (error) => {
      setConstraintFormError(getErrorMessage(error))
    },
  })

  function handleAddConstraint(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    const result = statementSchema.safeParse({ statement: constraintStatement })

    if (!result.success) {
      setConstraintFieldErrors(z.flattenError(result.error).fieldErrors)
      setConstraintFormError(null)
      return
    }

    setConstraintFieldErrors({})
    setConstraintFormError(null)
    addConstraint.mutate(result.data.statement)
  }

  return (
    <div className={styles.page}>
      <Link className={styles.backLink} to="/discovery-sessions">
        ← Discovery sessions
      </Link>

      {session.isPending && <p>Loading session…</p>}
      {session.isError && <p className={styles.formError}>{getErrorMessage(session.error)}</p>}
      {session.isSuccess && (
        <h1>
          {session.data.title} <span className={styles.status}>{session.data.status}</span>
        </h1>
      )}
      {session.isSuccess && session.data.status === 'in_progress' && (
        <button
          type="button"
          disabled={complete.isPending}
          onClick={() => {
            complete.mutate()
          }}
        >
          {complete.isPending ? 'Completing…' : 'Complete session'}
        </button>
      )}

      <div className={styles.section}>
        <h2 className={styles.sectionTitle}>Questions</h2>
        {questions.isPending && <p>Loading questions…</p>}
        {questions.isError && (
          <p className={styles.formError}>{getErrorMessage(questions.error)}</p>
        )}
        {questions.isSuccess && questions.data.length === 0 && (
          <p className={styles.empty}>No questions yet.</p>
        )}
        {questions.isSuccess && questions.data.length > 0 && (
          <ul className={styles.list}>
            {questions.data.map((question) => (
              <li key={question.id} className={styles.listItem}>
                <Link to={`/discovery-questions/${question.id}`}>{question.prompt}</Link>
              </li>
            ))}
          </ul>
        )}
        <form className={styles.form} onSubmit={handleAddQuestion} noValidate>
          <textarea
            className={styles.textarea}
            aria-label="Question prompt"
            rows={2}
            value={prompt}
            onChange={(event) => {
              setPrompt(event.target.value)
            }}
          />
          {questionFieldErrors.prompt !== undefined && questionFieldErrors.prompt.length > 0 && (
            <p className={styles.formError}>{questionFieldErrors.prompt[0]}</p>
          )}
          {questionFormError !== null && <p className={styles.formError}>{questionFormError}</p>}
          <button type="submit" disabled={addQuestionMutation.isPending}>
            {addQuestionMutation.isPending ? 'Adding…' : 'Ask question'}
          </button>
        </form>
      </div>

      <div className={styles.section}>
        <h2 className={styles.sectionTitle}>Assumptions</h2>
        {assumptions.isPending && <p>Loading assumptions…</p>}
        {assumptions.isError && (
          <p className={styles.formError}>{getErrorMessage(assumptions.error)}</p>
        )}
        {assumptions.isSuccess && assumptions.data.length === 0 && (
          <p className={styles.empty}>No assumptions yet.</p>
        )}
        {assumptions.isSuccess && assumptions.data.length > 0 && (
          <ul className={styles.list}>
            {assumptions.data.map((assumption) => (
              <li key={assumption.id} className={styles.listItem}>
                <span>{assumption.statement}</span>
              </li>
            ))}
          </ul>
        )}
        <form className={styles.form} onSubmit={handleAddAssumption} noValidate>
          <textarea
            className={styles.textarea}
            aria-label="Assumption statement"
            rows={2}
            value={assumptionStatement}
            onChange={(event) => {
              setAssumptionStatement(event.target.value)
            }}
          />
          {assumptionFieldErrors.statement !== undefined &&
            assumptionFieldErrors.statement.length > 0 && (
              <p className={styles.formError}>{assumptionFieldErrors.statement[0]}</p>
            )}
          {assumptionFormError !== null && (
            <p className={styles.formError}>{assumptionFormError}</p>
          )}
          <button type="submit" disabled={addAssumption.isPending}>
            {addAssumption.isPending ? 'Capturing…' : 'Capture assumption'}
          </button>
        </form>
      </div>

      <div className={styles.section}>
        <h2 className={styles.sectionTitle}>Constraints</h2>
        {constraints.isPending && <p>Loading constraints…</p>}
        {constraints.isError && (
          <p className={styles.formError}>{getErrorMessage(constraints.error)}</p>
        )}
        {constraints.isSuccess && constraints.data.length === 0 && (
          <p className={styles.empty}>No constraints yet.</p>
        )}
        {constraints.isSuccess && constraints.data.length > 0 && (
          <ul className={styles.list}>
            {constraints.data.map((constraint) => (
              <li key={constraint.id} className={styles.listItem}>
                <span>{constraint.statement}</span>
              </li>
            ))}
          </ul>
        )}
        <form className={styles.form} onSubmit={handleAddConstraint} noValidate>
          <textarea
            className={styles.textarea}
            aria-label="Constraint statement"
            rows={2}
            value={constraintStatement}
            onChange={(event) => {
              setConstraintStatement(event.target.value)
            }}
          />
          {constraintFieldErrors.statement !== undefined &&
            constraintFieldErrors.statement.length > 0 && (
              <p className={styles.formError}>{constraintFieldErrors.statement[0]}</p>
            )}
          {constraintFormError !== null && (
            <p className={styles.formError}>{constraintFormError}</p>
          )}
          <button type="submit" disabled={addConstraint.isPending}>
            {addConstraint.isPending ? 'Capturing…' : 'Capture constraint'}
          </button>
        </form>
      </div>
    </div>
  )
}
