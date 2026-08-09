import { apiClient } from '../../shared/api/client'
import { toApiError } from '../../shared/api/errors'

export type DocumentType = 'brd' | 'srs'
export type DocumentStatus = 'draft' | 'approved'
export type RequirementStatus = 'draft' | 'approved'

export interface RequirementDocument {
  id: string
  project_id: string
  type: DocumentType
  title: string
  status: DocumentStatus
}

export interface Requirement {
  id: string
  document_id: string
  text: string
  acceptance_criteria: string[]
  status: RequirementStatus
}

export async function listDocuments(): Promise<RequirementDocument[]> {
  const { data, error } = await apiClient.GET('/requirement-documents')

  if (error) {
    throw toApiError(error)
  }

  // Scramble can't statically infer the shape of a hand-built
  // array_map(...) response (see ListRequirementDocumentsController), so
  // the generated type is `unknown[]`; the actual shape is asserted by
  // ListRequirementDocumentsTest.
  return data.documents as RequirementDocument[]
}

export interface CreateDocumentInput {
  projectId: string
  type: DocumentType
  title: string
}

export async function createDocument(input: CreateDocumentInput): Promise<RequirementDocument> {
  const { data, error } = await apiClient.POST('/requirement-documents', {
    body: { project_id: input.projectId, type: input.type, title: input.title },
  })

  if (error) {
    throw toApiError(error)
  }

  return { ...data, type: data.type as DocumentType, status: data.status as DocumentStatus }
}

export async function getDocument(documentId: string): Promise<RequirementDocument> {
  const { data, error } = await apiClient.GET('/requirement-documents/{documentId}', {
    params: { path: { documentId } },
  })

  if (error) {
    throw toApiError(error)
  }

  return { ...data, type: data.type as DocumentType, status: data.status as DocumentStatus }
}

export interface ApprovedDocument {
  id: string
  status: DocumentStatus
}

export async function approveDocument(documentId: string): Promise<ApprovedDocument> {
  const { data, error } = await apiClient.POST('/requirement-documents/{documentId}/approve', {
    params: { path: { documentId } },
  })

  if (error) {
    throw toApiError(error)
  }

  return data as ApprovedDocument
}

export async function listRequirements(documentId: string): Promise<Requirement[]> {
  const { data, error } = await apiClient.GET('/requirement-documents/{documentId}/requirements', {
    params: { path: { documentId } },
  })

  if (error) {
    throw toApiError(error)
  }

  // See listDocuments: shape asserted by ListRequirementsTest.
  return data.requirements as Requirement[]
}

export interface AddRequirementInput {
  documentId: string
  text: string
  acceptanceCriteria: string[]
}

export interface CreatedRequirement {
  id: string
  document_id: string
  text: string
  status: RequirementStatus
}

export async function addRequirement(input: AddRequirementInput): Promise<CreatedRequirement> {
  const { data, error } = await apiClient.POST('/requirement-documents/{documentId}/requirements', {
    params: { path: { documentId: input.documentId } },
    body: { text: input.text, acceptance_criteria: input.acceptanceCriteria },
  })

  if (error) {
    throw toApiError(error)
  }

  return { ...data, status: data.status as RequirementStatus }
}

export async function getRequirement(requirementId: string): Promise<Requirement> {
  const { data, error } = await apiClient.GET('/requirements/{requirementId}', {
    params: { path: { requirementId } },
  })

  if (error) {
    throw toApiError(error)
  }

  return {
    ...data,
    acceptance_criteria: data.acceptance_criteria as string[],
    status: data.status as RequirementStatus,
  }
}

export interface ApprovedRequirement {
  id: string
  status: RequirementStatus
}

export async function approveRequirement(requirementId: string): Promise<ApprovedRequirement> {
  const { data, error } = await apiClient.POST('/requirements/{requirementId}/approve', {
    params: { path: { requirementId } },
  })

  if (error) {
    throw toApiError(error)
  }

  return data as ApprovedRequirement
}
