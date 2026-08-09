import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../api/client'
import { clearToken, getToken } from './token'

export const sessionQueryKey = ['session'] as const

export interface AuthenticatedSession {
  status: 'authenticated'
  user: { id: string; name: string; email: string }
  tenantId: string
  role: string
}

export interface UnauthenticatedSession {
  status: 'unauthenticated'
}

export type Session = AuthenticatedSession | UnauthenticatedSession

/**
 * No token stored means no network call -- an unauthenticated visitor
 * hitting a protected route should not need a round trip to learn what
 * the presence of a token already tells us locally.
 */
async function fetchSession(): Promise<Session> {
  if (getToken() === null) {
    return { status: 'unauthenticated' }
  }

  const { data, error } = await apiClient.GET('/me')

  if (error || data.tenant_id === null) {
    return { status: 'unauthenticated' }
  }

  return {
    status: 'authenticated',
    user: data.user,
    tenantId: data.tenant_id,
    role: data.role,
  }
}

export function useSession() {
  return useQuery({
    queryKey: sessionQueryKey,
    queryFn: fetchSession,
    staleTime: Infinity,
    retry: false,
  })
}

/**
 * Called after login/register succeed (a token was just stored) or after
 * logout (the token was just cleared) -- either way, the cached session
 * must be recomputed from the new token state, not just marked stale.
 */
export function useRefreshSession() {
  const queryClient = useQueryClient()

  return () => queryClient.invalidateQueries({ queryKey: sessionQueryKey })
}

/**
 * Used from the app shell, not a specific feature -- logging out is
 * available everywhere a session exists, not owned by Identity's pages.
 */
export function useLogout() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async () => {
      // Best-effort: even if the token was already invalid, the local
      // state must still end up logged out.
      await apiClient.POST('/logout')
    },
    onSettled: async () => {
      clearToken()
      await queryClient.invalidateQueries({ queryKey: sessionQueryKey })
    },
  })
}
