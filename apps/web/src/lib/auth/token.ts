const STORAGE_KEY = 'auth_token'

/**
 * The backend issues a plain Sanctum bearer token (LoginController,
 * RegisterController) -- not cookie-based stateful SPA auth -- so the
 * client is responsible for storing and attaching it itself.
 */
export function getToken(): string | null {
  return localStorage.getItem(STORAGE_KEY)
}

export function setToken(token: string): void {
  localStorage.setItem(STORAGE_KEY, token)
}

export function clearToken(): void {
  localStorage.removeItem(STORAGE_KEY)
}
