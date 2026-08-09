import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { type SubmitEvent, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { z } from 'zod'
import { getErrorMessage } from '../../shared/api/errors'
import {
  type LinkType,
  approveArtifactVersion,
  createArtifactVersion,
  getArtifact,
  linkArtifactVersions,
  listApprovals,
  listArtifactVersionLinks,
} from './api'
import styles from '../../shared/ui/ListPage.module.css'

const linkTypes: LinkType[] = [
  'derives_from',
  'satisfies',
  'implements',
  'verifies',
  'supersedes',
  'references',
]
const defaultLinkType: LinkType = 'derives_from'

const addVersionSchema = z.object({
  content: z.string().trim().min(1, 'Content is required.'),
})

const linkSchema = z.object({
  toVersionId: z.uuid('Enter a valid version id.'),
  linkType: z.enum(linkTypes),
})

export function ArtifactPage() {
  const { artifactId } = useParams<{ artifactId: string }>()
  const queryClient = useQueryClient()

  if (artifactId === undefined) {
    throw new Error('ArtifactPage rendered without an artifactId route param.')
  }

  const artifactQueryKey = ['artifacts', artifactId] as const
  const artifact = useQuery({ queryKey: artifactQueryKey, queryFn: () => getArtifact(artifactId) })
  const currentVersionId = artifact.data?.current_version_id ?? null

  const linksQueryKey = ['artifact-versions', currentVersionId, 'links'] as const
  const links = useQuery({
    queryKey: linksQueryKey,
    queryFn: () => listArtifactVersionLinks(currentVersionId ?? ''),
    enabled: currentVersionId !== null,
  })

  const approvalsQueryKey = ['artifact-versions', currentVersionId, 'approvals'] as const
  const approvals = useQuery({
    queryKey: approvalsQueryKey,
    queryFn: () => listApprovals(currentVersionId ?? ''),
    enabled: currentVersionId !== null,
  })

  const [content, setContent] = useState('')
  const [versionFieldErrors, setVersionFieldErrors] = useState<Record<string, string[]>>({})
  const [versionFormError, setVersionFormError] = useState<string | null>(null)

  const addVersion = useMutation({
    mutationFn: (versionContent: string) => createArtifactVersion(artifactId, versionContent),
    onSuccess: async () => {
      setContent('')
      setVersionFieldErrors({})
      setVersionFormError(null)
      await queryClient.invalidateQueries({ queryKey: artifactQueryKey })
    },
    onError: (error) => {
      setVersionFormError(getErrorMessage(error))
    },
  })

  function handleAddVersion(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    const result = addVersionSchema.safeParse({ content })

    if (!result.success) {
      setVersionFieldErrors(z.flattenError(result.error).fieldErrors)
      setVersionFormError(null)
      return
    }

    setVersionFieldErrors({})
    setVersionFormError(null)
    addVersion.mutate(result.data.content)
  }

  const [toVersionId, setToVersionId] = useState('')
  const [linkType, setLinkType] = useState<LinkType>(defaultLinkType)
  const [linkFormError, setLinkFormError] = useState<string | null>(null)

  const addLink = useMutation({
    mutationFn: linkArtifactVersions,
    onSuccess: async () => {
      setToVersionId('')
      setLinkFormError(null)
      await queryClient.invalidateQueries({ queryKey: linksQueryKey })
    },
    onError: (error) => {
      setLinkFormError(getErrorMessage(error))
    },
  })

  function handleAddLink(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    if (currentVersionId === null) {
      return
    }

    const result = linkSchema.safeParse({ toVersionId, linkType })

    if (!result.success) {
      setLinkFormError(result.error.issues[0]?.message ?? 'Invalid link.')
      return
    }

    setLinkFormError(null)
    addLink.mutate({
      fromVersionId: currentVersionId,
      toVersionId: result.data.toVersionId,
      linkType: result.data.linkType,
    })
  }

  const [decision, setDecision] = useState<'approved' | 'rejected'>('approved')
  const [comment, setComment] = useState('')
  const [approvalFormError, setApprovalFormError] = useState<string | null>(null)

  const approve = useMutation({
    mutationFn: approveArtifactVersion,
    onSuccess: async () => {
      setComment('')
      setApprovalFormError(null)
      await queryClient.invalidateQueries({ queryKey: approvalsQueryKey })
    },
    onError: (error) => {
      setApprovalFormError(getErrorMessage(error))
    },
  })

  function handleApprove(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    if (currentVersionId === null) {
      return
    }

    setApprovalFormError(null)
    approve.mutate({
      versionId: currentVersionId,
      decision,
      comment: comment.trim() === '' ? undefined : comment.trim(),
    })
  }

  return (
    <div className={styles.page}>
      {artifact.isPending && <p>Loading artifact…</p>}
      {artifact.isError && <p className={styles.formError}>{getErrorMessage(artifact.error)}</p>}

      {artifact.isSuccess && (
        <>
          <Link className={styles.backLink} to={`/projects/${artifact.data.project_id}`}>
            ← Project
          </Link>
          <h1>
            {artifact.data.type} <span className={styles.status}>{artifact.data.status}</span>
          </h1>

          <div className={styles.section}>
            <h2 className={styles.sectionTitle}>Version history</h2>
            <ul className={styles.list}>
              {artifact.data.versions.map((version) => (
                <li key={version.id} className={styles.listItem}>
                  <span>
                    v{version.version_number}: {version.content}
                  </span>
                  {version.id === currentVersionId && (
                    <span className={styles.status}>current</span>
                  )}
                </li>
              ))}
            </ul>
            <form className={styles.form} onSubmit={handleAddVersion} noValidate>
              <textarea
                className={styles.textarea}
                aria-label="New version content"
                rows={3}
                value={content}
                onChange={(event) => {
                  setContent(event.target.value)
                }}
              />
              {versionFieldErrors.content !== undefined &&
                versionFieldErrors.content.length > 0 && (
                  <p className={styles.formError}>{versionFieldErrors.content[0]}</p>
                )}
              {versionFormError !== null && <p className={styles.formError}>{versionFormError}</p>}
              <button type="submit" disabled={addVersion.isPending}>
                {addVersion.isPending ? 'Adding…' : 'Add version'}
              </button>
            </form>
          </div>

          <div className={styles.section}>
            <h2 className={styles.sectionTitle}>Links (current version)</h2>
            {links.isPending && <p>Loading links…</p>}
            {links.isError && <p className={styles.formError}>{getErrorMessage(links.error)}</p>}
            {links.isSuccess &&
              links.data.outgoing.length === 0 &&
              links.data.incoming.length === 0 && <p className={styles.empty}>No links yet.</p>}
            {links.isSuccess && (
              <ul className={styles.list}>
                {links.data.outgoing.map((link) => (
                  <li key={link.id} className={styles.listItem}>
                    <span>
                      {link.link_type} → {link.to_version_id}
                    </span>
                  </li>
                ))}
                {links.data.incoming.map((link) => (
                  <li key={link.id} className={styles.listItem}>
                    <span>
                      {link.from_version_id} → {link.link_type} this
                    </span>
                  </li>
                ))}
              </ul>
            )}
            <form className={styles.form} onSubmit={handleAddLink} noValidate>
              <input
                className={styles.textarea}
                aria-label="Target version id"
                placeholder="Target version id"
                value={toVersionId}
                onChange={(event) => {
                  setToVersionId(event.target.value)
                }}
              />
              <select
                className={styles.select}
                aria-label="Link type"
                value={linkType}
                onChange={(event) => {
                  setLinkType(event.target.value as LinkType)
                }}
              >
                {linkTypes.map((type) => (
                  <option key={type} value={type}>
                    {type}
                  </option>
                ))}
              </select>
              {linkFormError !== null && <p className={styles.formError}>{linkFormError}</p>}
              <button type="submit" disabled={addLink.isPending || currentVersionId === null}>
                {addLink.isPending ? 'Linking…' : 'Link versions'}
              </button>
            </form>
          </div>

          <div className={styles.section}>
            <h2 className={styles.sectionTitle}>Approvals (current version)</h2>
            {approvals.isPending && <p>Loading approvals…</p>}
            {approvals.isError && (
              <p className={styles.formError}>{getErrorMessage(approvals.error)}</p>
            )}
            {approvals.isSuccess && approvals.data.length === 0 && (
              <p className={styles.empty}>No approvals yet.</p>
            )}
            {approvals.isSuccess && approvals.data.length > 0 && (
              <ul className={styles.list}>
                {approvals.data.map((item) => (
                  <li key={item.id} className={styles.listItem}>
                    <span>{item.comment ?? '—'}</span>
                    <span className={styles.status}>{item.decision}</span>
                  </li>
                ))}
              </ul>
            )}
            <form className={styles.form} onSubmit={handleApprove} noValidate>
              <select
                className={styles.select}
                aria-label="Decision"
                value={decision}
                onChange={(event) => {
                  setDecision(event.target.value === 'rejected' ? 'rejected' : 'approved')
                }}
              >
                <option value="approved">Approve</option>
                <option value="rejected">Reject</option>
              </select>
              <textarea
                className={styles.textarea}
                aria-label="Comment"
                rows={2}
                value={comment}
                onChange={(event) => {
                  setComment(event.target.value)
                }}
              />
              {approvalFormError !== null && (
                <p className={styles.formError}>{approvalFormError}</p>
              )}
              <button type="submit" disabled={approve.isPending || currentVersionId === null}>
                {approve.isPending ? 'Recording…' : 'Record decision'}
              </button>
            </form>
          </div>
        </>
      )}
    </div>
  )
}
