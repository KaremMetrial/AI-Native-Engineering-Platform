import { createBrowserRouter } from 'react-router-dom'
import { discoveryRoutes } from '../features/discovery/routes'
import { graphRoutes } from '../features/graph/routes'
import { identityRoutes } from '../features/identity/routes'
import { requirementsRoutes } from '../features/requirements/routes'
import { ProtectedRoute } from '../shared/auth/ProtectedRoute'
import { Layout } from './Layout'
import { HomePage } from './HomePage'

// Route tree lives in one place, extended as each feature ships (D-12:
// features are bounded contexts by capability -- routing composes them,
// it does not own them). See ../features/README.md for the convention
// each feature follows when it adds its own routes here.
//
// Identity's own routes (login/register) are deliberately outside
// ProtectedRoute -- everything else added here goes inside it as it
// ships, nested under Layout so it shares the header/nav shell.
export const router = createBrowserRouter([
  ...identityRoutes,
  {
    element: <Layout />,
    children: [
      {
        index: true,
        element: (
          <ProtectedRoute>
            <HomePage />
          </ProtectedRoute>
        ),
      },
      ...graphRoutes,
      ...discoveryRoutes,
      ...requirementsRoutes,
    ],
  },
])
