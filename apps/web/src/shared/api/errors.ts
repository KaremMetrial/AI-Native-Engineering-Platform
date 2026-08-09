/**
 * Every endpoint's error responses share Laravel's shape: always a
 * `message`, and a `422` additionally carries `errors` (field name ->
 * list of messages). Cross-cutting because every feature's forms need
 * it, not just Identity's.
 *
 * openapi-fetch's `error` value is the raw parsed JSON body, not an
 * `Error` -- wrapped in ApiError at the call site (see
 * features/identity/api.ts) so callers always throw and catch real
 * Error instances (@typescript-eslint/only-throw-error), while still
 * carrying the structured field errors forms need.
 */

export class ApiError extends Error {
  readonly fieldErrors: Record<string, string[]>

  constructor(message: string, fieldErrors: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.fieldErrors = fieldErrors
  }
}

function hasMessage(value: unknown): value is { message: string } {
  return (
    typeof value === 'object' &&
    value !== null &&
    'message' in value &&
    typeof value.message === 'string'
  )
}

function hasFieldErrors(value: unknown): value is { errors: Record<string, string[]> } {
  return typeof value === 'object' && value !== null && 'errors' in value
}

export function toApiError(raw: unknown, fallbackMessage = 'Something went wrong.'): ApiError {
  const message = hasMessage(raw) ? raw.message : fallbackMessage
  const fieldErrors = hasFieldErrors(raw) ? raw.errors : {}

  return new ApiError(message, fieldErrors)
}

export function getErrorMessage(error: unknown, fallback = 'Something went wrong.'): string {
  if (error instanceof Error) {
    return error.message
  }

  return fallback
}

export function getFieldErrors(error: unknown): Record<string, string[]> {
  if (error instanceof ApiError) {
    return error.fieldErrors
  }

  return {}
}
