import { apiClient } from '../../shared/api/client'
import { toApiError } from '../../shared/api/errors'

export type StructuredOutputCapability = 'none' | 'json_mode' | 'strict_schema' | 'tool_schema'
export type ToolUseCapability = 'none' | 'sequential' | 'parallel'
export type StreamingCapability = 'none' | 'text' | 'text_and_tools'
export type GenerationStatus = 'queued' | 'selected' | 'failed'

export interface GenerationRequest {
  id: string
  workflow_name: string
  status: GenerationStatus
  selected_model_id: string | null
  failure_reason: string | null
}

export async function listGenerationRequests(): Promise<GenerationRequest[]> {
  const { data, error } = await apiClient.GET('/generation-requests')

  if (error) {
    throw toApiError(error)
  }

  // Scramble can't statically infer the shape of a hand-built
  // array_map(...) response (see ListGenerationRequestsController), so
  // the generated type is `unknown[]`; the actual shape is asserted by
  // ListGenerationRequestsTest.
  return data.generation_requests as GenerationRequest[]
}

export interface RequestGenerationInput {
  workflowName: string
  structuredOutput: StructuredOutputCapability
  toolUse: ToolUseCapability
  streaming: StreamingCapability
  minContextWindow: number
  requiresVision: boolean
  requiresDeterministicSeed: boolean
}

export interface CreatedGenerationRequest {
  id: string
  status: GenerationStatus
}

export async function requestGeneration(
  input: RequestGenerationInput,
): Promise<CreatedGenerationRequest> {
  const { data, error } = await apiClient.POST('/generation-requests', {
    body: {
      workflow_name: input.workflowName,
      structured_output: input.structuredOutput,
      tool_use: input.toolUse,
      streaming: input.streaming,
      min_context_window: input.minContextWindow,
      requires_vision: input.requiresVision,
      requires_deterministic_seed: input.requiresDeterministicSeed,
    },
  })

  if (error) {
    throw toApiError(error)
  }

  return { ...data, status: data.status as GenerationStatus }
}

export async function getGenerationRequest(generationRequestId: string): Promise<GenerationRequest> {
  const { data, error } = await apiClient.GET('/generation-requests/{generationRequestId}', {
    params: { path: { generationRequestId } },
  })

  if (error) {
    throw toApiError(error)
  }

  return { ...data, status: data.status as GenerationStatus }
}
