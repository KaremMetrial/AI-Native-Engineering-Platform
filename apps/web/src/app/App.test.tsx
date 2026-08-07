// Harness proof: Vitest + React Testing Library render and query correctly.
import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import App from './App'

describe('App', () => {
  it('renders the platform shell', () => {
    render(<App />)
    expect(
      screen.getByRole('heading', { name: /ai-native engineering platform/i }),
    ).toBeInTheDocument()
  })
})
