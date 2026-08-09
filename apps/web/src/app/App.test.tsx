// Harness proof: Vitest + React Testing Library render and query correctly,
// exercising the real router end to end.
import { beforeEach, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import App from './App'

describe('App', () => {
  beforeEach(() => {
    localStorage.clear()
    window.history.pushState({}, '', '/')
  })

  it('redirects an unauthenticated visitor from the home page to login', async () => {
    render(<App />)

    expect(await screen.findByRole('heading', { name: /log in/i })).toBeInTheDocument()
  })
})
