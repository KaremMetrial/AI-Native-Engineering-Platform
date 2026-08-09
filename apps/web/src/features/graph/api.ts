import { apiClient } from '../../shared/api/client'
import { toApiError } from '../../shared/api/errors'

export interface Project {
  id: string
  name: string
  status: string
}

export interface ArtifactSummary {
  id: string
  project_id: string
  type: string
  status: string
  current_version_id: string | null
  version_count: number
}

export interface Lineage {
  model: string | null
  prompt_version: string | null
  input_version_ids: string[]
  tokens: number | null
  cost: number | null
}

export interface ArtifactVersion {
  id: string
  version_number: number
  content: string
  lineage: Lineage
  created_by: string
  created_at: string
}

export interface ArtifactDetail {
  id: string
  project_id: string
  type: string
  status: string
  current_version_id: string | null
  versions: ArtifactVersion[]
}

export type LinkType =
  'derives_from' | 'satisfies' | 'implements' | 'verifies' | 'supersedes' | 'references'

export interface ArtifactLink {
  id: string
  from_version_id: string
  to_version_id: string
  link_type: LinkType
  created_by: string
}

export interface ArtifactVersionLinks {
  outgoing: ArtifactLink[]
  incoming: ArtifactLink[]
}

export type ApprovalDecision = 'approved' | 'rejected'

export interface Approval {
  id: string
  artifact_version_id: string
  approved_by: string
  decision: ApprovalDecision
  comment: string | null
  created_at: string
}

// The three "created" types below are deliberately narrower than their
// GET-list counterparts above: the backend's write controllers return only
// a creation confirmation (id + the fields that make it identifiable), not
// the full resource -- callers that need the full shape re-fetch it via
// the corresponding GET.
export interface CreatedArtifactVersion {
  id: string
  artifact_id: string
  version_number: number
}

export interface CreatedArtifactLink {
  id: string
  from_version_id: string
  to_version_id: string
  link_type: LinkType
}

export interface CreatedApproval {
  id: string
  artifact_version_id: string
  decision: ApprovalDecision
}

export async function listProjects(): Promise<Project[]> {
  const { data, error } = await apiClient.GET('/projects')

  if (error) {
    throw toApiError(error)
  }

  // Scramble can't statically infer the shape of a hand-built
  // array_map(...) response (see ListProjectsController), so the
  // generated type is `unknown[]`; the actual shape is asserted by
  // ListProjectsTest.
  return data.projects as Project[]
}

export async function createProject(name: string): Promise<Project> {
  const { data, error } = await apiClient.POST('/projects', { body: { name } })

  if (error) {
    throw toApiError(error)
  }

  return data
}

export async function getProject(projectId: string): Promise<Project> {
  const { data, error } = await apiClient.GET('/projects/{projectId}', {
    params: { path: { projectId } },
  })

  if (error) {
    throw toApiError(error)
  }

  return data
}

export async function listProjectArtifacts(projectId: string): Promise<ArtifactSummary[]> {
  const { data, error } = await apiClient.GET('/projects/{projectId}/artifacts', {
    params: { path: { projectId } },
  })

  if (error) {
    throw toApiError(error)
  }

  // See listProjects: shape asserted by ListProjectArtifactsTest.
  return data.artifacts as ArtifactSummary[]
}

export interface CreateArtifactInput {
  projectId: string
  type: string
  content: string
}

export async function createArtifact(input: CreateArtifactInput): Promise<ArtifactSummary> {
  const { data, error } = await apiClient.POST('/artifacts', {
    body: { project_id: input.projectId, type: input.type, content: input.content },
  })

  if (error) {
    throw toApiError(error)
  }

  return { ...data, project_id: input.projectId, version_count: 1 }
}

export async function getArtifact(artifactId: string): Promise<ArtifactDetail> {
  const { data, error } = await apiClient.GET('/artifacts/{artifactId}', {
    params: { path: { artifactId } },
  })

  if (error) {
    throw toApiError(error)
  }

  // See listProjects: versions' shape asserted by GetArtifactTest.
  return { ...data, versions: data.versions as ArtifactVersion[] }
}

export async function createArtifactVersion(
  artifactId: string,
  content: string,
): Promise<CreatedArtifactVersion> {
  const { data, error } = await apiClient.POST('/artifacts/{artifactId}/versions', {
    params: { path: { artifactId } },
    body: { content },
  })

  if (error) {
    throw toApiError(error)
  }

  // Scramble infers version_number as string for this one hand-built
  // response (App\Graph\Domain\ArtifactVersion::$versionNumber is a real
  // int; CreateArtifactVersionTest asserts it as a JSON number). Number()
  // is a safe, idempotent conversion regardless of which one Scramble got
  // wrong.
  return { ...data, version_number: Number(data.version_number) }
}

export interface LinkArtifactVersionsInput {
  fromVersionId: string
  toVersionId: string
  linkType: LinkType
}

export async function linkArtifactVersions(
  input: LinkArtifactVersionsInput,
): Promise<CreatedArtifactLink> {
  const { data, error } = await apiClient.POST('/artifact-links', {
    body: {
      from_version_id: input.fromVersionId,
      to_version_id: input.toVersionId,
      link_type: input.linkType,
    },
  })

  if (error) {
    throw toApiError(error)
  }

  return data as CreatedArtifactLink
}

export async function listArtifactVersionLinks(versionId: string): Promise<ArtifactVersionLinks> {
  const { data, error } = await apiClient.GET('/artifact-versions/{versionId}/links', {
    params: { path: { versionId } },
  })

  if (error) {
    throw toApiError(error)
  }

  // See listProjects: shape asserted by ListArtifactVersionLinksTest.
  return { outgoing: data.outgoing as ArtifactLink[], incoming: data.incoming as ArtifactLink[] }
}

export interface ApproveArtifactVersionInput {
  versionId: string
  decision: ApprovalDecision
  comment?: string | undefined
}

export async function approveArtifactVersion(
  input: ApproveArtifactVersionInput,
): Promise<CreatedApproval> {
  const { data, error } = await apiClient.POST('/artifact-versions/{versionId}/approvals', {
    params: { path: { versionId: input.versionId } },
    body: { decision: input.decision, comment: input.comment ?? null },
  })

  if (error) {
    throw toApiError(error)
  }

  return data as CreatedApproval
}

export async function listApprovals(versionId: string): Promise<Approval[]> {
  const { data, error } = await apiClient.GET('/artifact-versions/{versionId}/approvals', {
    params: { path: { versionId } },
  })

  if (error) {
    throw toApiError(error)
  }

  // See listProjects: shape asserted by ListApprovalsTest.
  return data.approvals as Approval[]
}
