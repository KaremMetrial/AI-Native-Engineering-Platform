import { QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from 'react-router-dom'
import { queryClient } from './queryClient'
import { router } from './router'

// Root shell: providers and routing only. Nothing feature-specific
// belongs in this file (docs/delivery/11-repository-and-folder-strategy.md)
// -- see ../features/README.md.
function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>
  )
}

export default App
