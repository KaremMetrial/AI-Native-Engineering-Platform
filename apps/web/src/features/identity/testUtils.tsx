import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import type { ReactElement } from 'react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'

/**
 * Renders a page at `/start` inside a router with a `/landed` marker
 * route, so a test can assert "navigation happened" by looking for the
 * marker rather than reaching into router internals.
 */
export function renderAtRoute(element: ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/start']}>
        <Routes>
          <Route path="/start" element={element} />
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
