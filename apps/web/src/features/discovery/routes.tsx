import type { RouteObject } from 'react-router-dom'
import { ProtectedRoute } from '../../shared/auth/ProtectedRoute'
import { DiscoveryQuestionPage } from './DiscoveryQuestionPage'
import { DiscoverySessionPage } from './DiscoverySessionPage'
import { DiscoverySessionsPage } from './DiscoverySessionsPage'

export const discoveryRoutes: RouteObject[] = [
  {
    path: 'discovery-sessions',
    element: (
      <ProtectedRoute>
        <DiscoverySessionsPage />
      </ProtectedRoute>
    ),
  },
  {
    path: 'discovery-sessions/:sessionId',
    element: (
      <ProtectedRoute>
        <DiscoverySessionPage />
      </ProtectedRoute>
    ),
  },
  {
    path: 'discovery-questions/:questionId',
    element: (
      <ProtectedRoute>
        <DiscoveryQuestionPage />
      </ProtectedRoute>
    ),
  },
]
