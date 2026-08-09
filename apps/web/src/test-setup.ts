import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach } from 'vitest'

// vitest.config.ts sets globals: false, so React Testing Library's
// auto-cleanup (which relies on a global afterEach) never registers on
// its own -- without this, each render() leaks into the next test's DOM,
// producing "multiple elements found" failures that have nothing to do
// with the component under test.
afterEach(() => {
  cleanup()
})
