import type { RouteObject } from 'react-router-dom'
import { ProtectedRoute } from '../../shared/auth/ProtectedRoute'
import { ArtifactPage } from './ArtifactPage'
import { ProjectPage } from './ProjectPage'
import { ProjectsPage } from './ProjectsPage'

export const graphRoutes: RouteObject[] = [
  {
    path: 'projects',
    element: (
      <ProtectedRoute>
        <ProjectsPage />
      </ProtectedRoute>
    ),
  },
  {
    path: 'projects/:projectId',
    element: (
      <ProtectedRoute>
        <ProjectPage />
      </ProtectedRoute>
    ),
  },
  {
    path: 'artifacts/:artifactId',
    element: (
      <ProtectedRoute>
        <ArtifactPage />
      </ProtectedRoute>
    ),
  },
]
