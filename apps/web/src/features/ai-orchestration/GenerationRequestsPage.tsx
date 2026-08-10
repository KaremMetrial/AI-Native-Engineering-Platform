import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { type SubmitEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { z } from 'zod'
import { getErrorMessage } from '../../shared/api/errors'
import { TextField } from '../../shared/ui/TextField'
import styles from '../../shared/ui/ListPage.module.css'
import {
  type StreamingCapability,
  type StructuredOutputCapability,
  type ToolUseCapability,
  listGenerationRequests,
  requestGeneration,
} from './api'

const generationRequestsQueryKey = ['generation-requests'] as const

const structuredOutputCapabilities: StructuredOutputCapability[] = [
  'none',
  'json_mode',
  'strict_schema',
  'tool_schema',
]
const toolUseCapabilities: ToolUseCapability[] = ['none', 'sequential', 'parallel']
const streamingCapabilities: StreamingCapability[] = ['none', 'text', 'text_and_tools']

const requestGenerationSchema = z.object({
  workflow_name: z.string().trim().min(1, 'Workflow name is required.').max(255),
  structured_output: z.enum(structuredOutputCapabilities),
  tool_use: z.enum(toolUseCapabilities),
  streaming: z.enum(streamingCapabilities),
  min_context_window: z.coerce
    .number('Enter a context window of at least 1 token.')
    .int('Enter a whole number of tokens.')
    .min(1, 'Enter a context window of at least 1 token.'),
})

export function GenerationRequestsPage() {
  const queryClient = useQueryClient()
  const generationRequests = useQuery({
    queryKey: generationRequestsQueryKey,
    queryFn: listGenerationRequests,
  })

  const [workflowName, setWorkflowName] = useState('')
  const [structuredOutput, setStructuredOutput] = useState<StructuredOutputCapability>('none')
  const [toolUse, setToolUse] = useState<ToolUseCapability>('none')
  const [streaming, setStreaming] = useState<StreamingCapability>('none')
  const [minContextWindow, setMinContextWindow] = useState('4000')
  const [requiresVision, setRequiresVision] = useState(false)
  const [requiresDeterministicSeed, setRequiresDeterministicSeed] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: requestGeneration,
    onSuccess: async () => {
      setWorkflowName('')
      setFieldErrors({})
      setFormError(null)
      await queryClient.invalidateQueries({ queryKey: generationRequestsQueryKey })
    },
    onError: (error) => {
      setFormError(getErrorMessage(error))
    },
  })

  function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    const result = requestGenerationSchema.safeParse({
      workflow_name: workflowName,
      structured_output: structuredOutput,
      tool_use: toolUse,
      streaming,
      min_context_window: minContextWindow,
    })

    if (!result.success) {
      setFieldErrors(z.flattenError(result.error).fieldErrors)
      setFormError(null)
      return
    }

    setFieldErrors({})
    setFormError(null)
    mutation.mutate({
      workflowName: result.data.workflow_name,
      structuredOutput: result.data.structured_output,
      toolUse: result.data.tool_use,
      streaming: result.data.streaming,
      minContextWindow: result.data.min_context_window,
      requiresVision,
      requiresDeterministicSeed,
    })
  }

  return (
    <div className={styles.page}>
      <h1>Generation requests</h1>

      {generationRequests.isPending && <p>Loading generation requests…</p>}
      {generationRequests.isError && (
        <p className={styles.formError}>{getErrorMessage(generationRequests.error)}</p>
      )}
      {generationRequests.isSuccess && generationRequests.data.length === 0 && (
        <p className={styles.empty}>No generation requests yet.</p>
      )}
      {generationRequests.isSuccess && generationRequests.data.length > 0 && (
        <ul className={styles.list}>
          {generationRequests.data.map((request) => (
            <li key={request.id} className={styles.listItem}>
              <Link to={`/generation-requests/${request.id}`}>{request.workflow_name}</Link>
              <span className={styles.status}>{request.status}</span>
            </li>
          ))}
        </ul>
      )}

      <form className={styles.form} onSubmit={handleSubmit} noValidate>
        <TextField
          label="Workflow name"
          name="workflow_name"
          value={workflowName}
          onChange={(event) => {
            setWorkflowName(event.target.value)
          }}
          errors={fieldErrors.workflow_name}
        />
        <select
          className={styles.select}
          aria-label="Structured output"
          value={structuredOutput}
          onChange={(event) => {
            setStructuredOutput(event.target.value as StructuredOutputCapability)
          }}
        >
          {structuredOutputCapabilities.map((capability) => (
            <option key={capability} value={capability}>
              {capability}
            </option>
          ))}
        </select>
        <select
          className={styles.select}
          aria-label="Tool use"
          value={toolUse}
          onChange={(event) => {
            setToolUse(event.target.value as ToolUseCapability)
          }}
        >
          {toolUseCapabilities.map((capability) => (
            <option key={capability} value={capability}>
              {capability}
            </option>
          ))}
        </select>
        <select
          className={styles.select}
          aria-label="Streaming"
          value={streaming}
          onChange={(event) => {
            setStreaming(event.target.value as StreamingCapability)
          }}
        >
          {streamingCapabilities.map((capability) => (
            <option key={capability} value={capability}>
              {capability}
            </option>
          ))}
        </select>
        <TextField
          label="Minimum context window (tokens)"
          name="min_context_window"
          value={minContextWindow}
          onChange={(event) => {
            setMinContextWindow(event.target.value)
          }}
          errors={fieldErrors.min_context_window}
        />
        <label>
          <input
            type="checkbox"
            checked={requiresVision}
            onChange={(event) => {
              setRequiresVision(event.target.checked)
            }}
          />
          Requires vision
        </label>
        <label>
          <input
            type="checkbox"
            checked={requiresDeterministicSeed}
            onChange={(event) => {
              setRequiresDeterministicSeed(event.target.checked)
            }}
          />
          Requires deterministic seed
        </label>
        {formError !== null && <p className={styles.formError}>{formError}</p>}
        <button type="submit" disabled={mutation.isPending}>
          {mutation.isPending ? 'Submitting…' : 'Request generation'}
        </button>
      </form>
    </div>
  )
}
