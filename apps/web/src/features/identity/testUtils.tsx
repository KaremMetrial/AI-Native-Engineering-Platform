import type { ReactElement } from 'react'
import { jsonResponse, renderAtPath, urlOf } from '../../shared/testUtils'

export { jsonResponse, urlOf }

export function renderAtRoute(element: ReactElement) {
  return renderAtPath(element)
}
