import { createBrowserRouter } from 'react-router-dom'
import { identityRoutes } from '../features/identity/routes'
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
// ships.
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
    ],
  },
])
