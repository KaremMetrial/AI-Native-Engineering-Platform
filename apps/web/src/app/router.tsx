import { createBrowserRouter } from 'react-router-dom'
import { Layout } from './Layout'
import { HomePage } from './HomePage'

// Route tree lives in one place, extended as each feature ships (D-12:
// features are bounded contexts by capability -- routing composes them,
// it does not own them). See ../features/README.md for the convention
// each feature follows when it adds its own routes here.
export const router = createBrowserRouter([
  {
    element: <Layout />,
    children: [{ index: true, element: <HomePage /> }],
  },
])
