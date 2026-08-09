import type { RouteObject } from 'react-router-dom'
import { ProtectedRoute } from '../../shared/auth/ProtectedRoute'
import { RequirementDocumentPage } from './RequirementDocumentPage'
import { RequirementDocumentsPage } from './RequirementDocumentsPage'
import { RequirementPage } from './RequirementPage'

export const requirementsRoutes: RouteObject[] = [
  {
    path: 'requirement-documents',
    element: (
      <ProtectedRoute>
        <RequirementDocumentsPage />
      </ProtectedRoute>
    ),
  },
  {
    path: 'requirement-documents/:documentId',
    element: (
      <ProtectedRoute>
        <RequirementDocumentPage />
      </ProtectedRoute>
    ),
  },
  {
    path: 'requirements/:requirementId',
    element: (
      <ProtectedRoute>
        <RequirementPage />
      </ProtectedRoute>
    ),
  },
]
