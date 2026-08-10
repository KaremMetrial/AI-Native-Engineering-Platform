import type { RouteObject } from 'react-router-dom'
import { ProtectedRoute } from '../../shared/auth/ProtectedRoute'
import { GenerationRequestPage } from './GenerationRequestPage'
import { GenerationRequestsPage } from './GenerationRequestsPage'

export const aiOrchestrationRoutes: RouteObject[] = [
  {
    path: 'generation-requests',
    element: (
      <ProtectedRoute>
        <GenerationRequestsPage />
      </ProtectedRoute>
    ),
  },
  {
    path: 'generation-requests/:generationRequestId',
    element: (
      <ProtectedRoute>
        <GenerationRequestPage />
      </ProtectedRoute>
    ),
  },
]
