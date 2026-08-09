import { useMutation } from '@tanstack/react-query'
import { type SubmitEvent, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { getErrorMessage, getFieldErrors } from '../../shared/api/errors'
import { useRefreshSession } from '../../shared/auth/session'
import { setToken } from '../../shared/auth/token'
import { TextField } from '../../shared/ui/TextField'
import { register } from './api'
import styles from './AuthPage.module.css'

// Field-keyed by the wire format (snake_case, matching the backend's
// FormRequest field names) so client-side and server-side validation
// errors land in the same state without translation.
const registerSchema = z.object({
  tenant_name: z.string().trim().min(1, 'Organization name is required.').max(255),
  name: z.string().trim().min(1, 'Your name is required.').max(255),
  email: z.email('Enter a valid email address.').max(255),
  password: z.string().min(8, 'Password must be at least 8 characters.'),
})

export function RegisterPage() {
  const navigate = useNavigate()
  const refreshSession = useRefreshSession()
  const [tenantName, setTenantName] = useState('')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: register,
    onSuccess: async (token) => {
      setToken(token)
      await refreshSession()
      await navigate('/')
    },
    onError: (error) => {
      const errors = getFieldErrors(error)

      if (Object.keys(errors).length > 0) {
        setFieldErrors(errors)
        setFormError(null)
      } else {
        setFormError(getErrorMessage(error))
      }
    },
  })

  function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    const result = registerSchema.safeParse({
      tenant_name: tenantName,
      name,
      email,
      password,
    })

    if (!result.success) {
      setFieldErrors(z.flattenError(result.error).fieldErrors)
      setFormError(null)
      return
    }

    setFieldErrors({})
    setFormError(null)
    mutation.mutate({
      tenantName: result.data.tenant_name,
      name: result.data.name,
      email: result.data.email,
      password: result.data.password,
    })
  }

  return (
    <div className={styles.page}>
      <h1>Create your organization</h1>
      <form className={styles.form} onSubmit={handleSubmit} noValidate>
        <TextField
          label="Organization name"
          name="tenant_name"
          value={tenantName}
          onChange={(event) => {
            setTenantName(event.target.value)
          }}
          errors={fieldErrors.tenant_name}
        />
        <TextField
          label="Your name"
          name="name"
          value={name}
          onChange={(event) => {
            setName(event.target.value)
          }}
          errors={fieldErrors.name}
        />
        <TextField
          label="Email"
          name="email"
          type="email"
          value={email}
          onChange={(event) => {
            setEmail(event.target.value)
          }}
          errors={fieldErrors.email}
        />
        <TextField
          label="Password"
          name="password"
          type="password"
          value={password}
          onChange={(event) => {
            setPassword(event.target.value)
          }}
          errors={fieldErrors.password}
        />
        {formError !== null && <p className={styles.formError}>{formError}</p>}
        <button type="submit" disabled={mutation.isPending}>
          {mutation.isPending ? 'Creating…' : 'Create organization'}
        </button>
      </form>
      <p>
        Already have an account? <Link to="/login">Log in</Link>
      </p>
    </div>
  )
}
