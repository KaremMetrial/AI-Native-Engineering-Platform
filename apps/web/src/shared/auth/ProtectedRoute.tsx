import type { ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { useSession } from './session'

/**
 * Gates a route on an authenticated session. Deliberately renders
 * nothing (not a spinner) while the session query is in flight -- most
 * navigations resolve from cache instantly (staleTime: Infinity), so a
 * flash of loading UI would be more distracting than a brief blank
 * frame for the rare cold-load case.
 */
export function ProtectedRoute({ children }: { children: ReactNode }) {
  const session = useSession()

  if (session.isPending) {
    return null
  }

  if (session.data?.status !== 'authenticated') {
    return <Navigate to="/login" replace />
  }

  return children
}
