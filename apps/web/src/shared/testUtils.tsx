import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import type { ReactElement } from 'react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'

/**
 * Renders `element` at `path` (a route pattern, e.g. `/projects/:id`),
 * navigated to `initialPath` (a concrete URL, e.g. `/projects/proj-1`), with
 * a `/` marker route so a test can assert "navigation home happened" by
 * looking for the marker rather than reaching into router internals.
 */
export function renderAtPath(element: ReactElement, path = '/start', initialPath: string = path) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialPath]}>
        <Routes>
          <Route path={path} element={element} />
          <Route path="/" element={<p>Landed on home</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

export function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

/**
 * apiClient's fetch option always receives a constructed Request (see
 * client.ts), whose .toString() is the unhelpful "[object Request]" --
 * this reads its actual URL, and still handles a plain string/URL for
 * mocks that don't go through the real client.
 */
export function urlOf(input: Request | string | URL): string {
  return typeof input === 'string' || input instanceof URL ? input.toString() : input.url
}
