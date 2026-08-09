import { apiClient } from '../../shared/api/client'
import { toApiError } from '../../shared/api/errors'

export type SessionStatus = 'in_progress' | 'completed'

export interface DiscoverySession {
  id: string
  project_id: string
  title: string
  status: SessionStatus
  completed_at: string | null
}

export interface Question {
  id: string
  session_id: string
  prompt: string
  sequence: number
}

export interface DiscoveryResponse {
  id: string
  question_id: string
  content: string
  responded_by: string
}

export interface Assumption {
  id: string
  session_id: string
  statement: string
}

export interface Constraint {
  id: string
  session_id: string
  statement: string
}

export async function listSessions(): Promise<DiscoverySession[]> {
  const { data, error } = await apiClient.GET('/discovery-sessions')

  if (error) {
    throw toApiError(error)
  }

  // Scramble can't statically infer the shape of a hand-built
  // array_map(...) response (see ListDiscoverySessionsController), so the
  // generated type is `unknown[]`; the actual shape is asserted by
  // ListDiscoverySessionsTest.
  return data.sessions as DiscoverySession[]
}

export interface StartSessionInput {
  projectId: string
  title: string
}

export async function startSession(input: StartSessionInput): Promise<DiscoverySession> {
  const { data, error } = await apiClient.POST('/discovery-sessions', {
    body: { project_id: input.projectId, title: input.title },
  })

  if (error) {
    throw toApiError(error)
  }

  return { ...data, status: data.status as SessionStatus, completed_at: null }
}

export async function getSession(sessionId: string): Promise<DiscoverySession> {
  const { data, error } = await apiClient.GET('/discovery-sessions/{sessionId}', {
    params: { path: { sessionId } },
  })

  if (error) {
    throw toApiError(error)
  }

  return { ...data, status: data.status as SessionStatus }
}

export interface CompletedSession {
  id: string
  status: SessionStatus
}

export async function completeSession(sessionId: string): Promise<CompletedSession> {
  const { data, error } = await apiClient.POST('/discovery-sessions/{sessionId}/complete', {
    params: { path: { sessionId } },
  })

  if (error) {
    throw toApiError(error)
  }

  return data as CompletedSession
}

export async function listQuestions(sessionId: string): Promise<Question[]> {
  const { data, error } = await apiClient.GET('/discovery-sessions/{sessionId}/questions', {
    params: { path: { sessionId } },
  })

  if (error) {
    throw toApiError(error)
  }

  // See listSessions: shape asserted by ListQuestionsTest.
  return data.questions as Question[]
}

export async function getQuestion(questionId: string): Promise<Question> {
  const { data, error } = await apiClient.GET('/discovery-questions/{questionId}', {
    params: { path: { questionId } },
  })

  if (error) {
    throw toApiError(error)
  }

  return data
}

export interface CreatedQuestion {
  id: string
  session_id: string
  prompt: string
  sequence: number
}

export async function addQuestion(sessionId: string, prompt: string): Promise<CreatedQuestion> {
  const { data, error } = await apiClient.POST('/discovery-sessions/{sessionId}/questions', {
    params: { path: { sessionId } },
    body: { prompt },
  })

  if (error) {
    throw toApiError(error)
  }

  return { ...data, sequence: Number(data.sequence) }
}

export async function listResponses(questionId: string): Promise<DiscoveryResponse[]> {
  const { data, error } = await apiClient.GET('/discovery-questions/{questionId}/responses', {
    params: { path: { questionId } },
  })

  if (error) {
    throw toApiError(error)
  }

  // See listSessions: shape asserted by ListResponsesTest.
  return data.responses as DiscoveryResponse[]
}

export interface CreatedResponse {
  id: string
  question_id: string
  content: string
}

export async function recordResponse(
  questionId: string,
  content: string,
): Promise<CreatedResponse> {
  const { data, error } = await apiClient.POST('/discovery-questions/{questionId}/responses', {
    params: { path: { questionId } },
    body: { content },
  })

  if (error) {
    throw toApiError(error)
  }

  return data
}

export async function listAssumptions(sessionId: string): Promise<Assumption[]> {
  const { data, error } = await apiClient.GET('/discovery-sessions/{sessionId}/assumptions', {
    params: { path: { sessionId } },
  })

  if (error) {
    throw toApiError(error)
  }

  // See listSessions: shape asserted by ListAssumptionsTest.
  return data.assumptions as Assumption[]
}

export interface CreatedAssumption {
  id: string
  session_id: string
  statement: string
}

export async function captureAssumption(
  sessionId: string,
  statement: string,
): Promise<CreatedAssumption> {
  const { data, error } = await apiClient.POST('/discovery-sessions/{sessionId}/assumptions', {
    params: { path: { sessionId } },
    body: { statement },
  })

  if (error) {
    throw toApiError(error)
  }

  return data
}

export async function listConstraints(sessionId: string): Promise<Constraint[]> {
  const { data, error } = await apiClient.GET('/discovery-sessions/{sessionId}/constraints', {
    params: { path: { sessionId } },
  })

  if (error) {
    throw toApiError(error)
  }

  // See listSessions: shape asserted by ListConstraintsTest.
  return data.constraints as Constraint[]
}

export interface CreatedConstraint {
  id: string
  session_id: string
  statement: string
}

export async function captureConstraint(
  sessionId: string,
  statement: string,
): Promise<CreatedConstraint> {
  const { data, error } = await apiClient.POST('/discovery-sessions/{sessionId}/constraints', {
    params: { path: { sessionId } },
    body: { statement },
  })

  if (error) {
    throw toApiError(error)
  }

  return data
}
