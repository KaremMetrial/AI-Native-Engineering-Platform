import { apiClient } from '../../shared/api/client'
import { toApiError } from '../../shared/api/errors'

export interface RegisterInput {
  tenantName: string
  name: string
  email: string
  password: string
}

export interface LoginInput {
  email: string
  password: string
}

export async function register(input: RegisterInput): Promise<string> {
  const { data, error } = await apiClient.POST('/register', {
    body: {
      tenant_name: input.tenantName,
      name: input.name,
      email: input.email,
      password: input.password,
    },
  })

  if (error) {
    throw toApiError(error)
  }

  return data.token
}

export async function login(input: LoginInput): Promise<string> {
  const { data, error } = await apiClient.POST('/login', { body: input })

  if (error) {
    throw toApiError(error)
  }

  // The backend's success response only ever omits the token on a path
  // that also returns 401 (see LoginController); the schema types it as
  // nullable because the static analyzer that generated it can't see
  // that. Treated as a genuine failure rather than cast away.
  if (data.token === null) {
    throw new Error('Login did not return a token.')
  }

  return data.token
}
