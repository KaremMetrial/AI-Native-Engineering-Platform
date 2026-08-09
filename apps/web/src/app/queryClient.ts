import { QueryClient } from '@tanstack/react-query'

// Defaults tuned for an authenticated internal tool, not a public site:
// data changes from the user's own actions far more often than it goes
// stale from someone else's, so a short staleTime avoids refetch storms
// on every navigation without risking visibly wrong data for long.
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      retry: 1,
    },
  },
})
