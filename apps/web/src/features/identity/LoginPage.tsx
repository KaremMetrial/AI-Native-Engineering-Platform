import { useMutation } from '@tanstack/react-query'
import { type SubmitEvent, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { getErrorMessage, getFieldErrors } from '../../shared/api/errors'
import { useRefreshSession } from '../../shared/auth/session'
import { setToken } from '../../shared/auth/token'
import { login } from './api'
import { TextField } from './TextField'
import styles from './AuthPage.module.css'

const loginSchema = z.object({
  email: z.email('Enter a valid email address.'),
  password: z.string().min(1, 'Password is required.'),
})

export function LoginPage() {
  const navigate = useNavigate()
  const refreshSession = useRefreshSession()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: login,
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

    const result = loginSchema.safeParse({ email, password })

    if (!result.success) {
      setFieldErrors(z.flattenError(result.error).fieldErrors)
      setFormError(null)
      return
    }

    setFieldErrors({})
    setFormError(null)
    mutation.mutate(result.data)
  }

  return (
    <div className={styles.page}>
      <h1>Log in</h1>
      <form className={styles.form} onSubmit={handleSubmit} noValidate>
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
          {mutation.isPending ? 'Logging in…' : 'Log in'}
        </button>
      </form>
      <p>
        Need an organization? <Link to="/register">Create one</Link>
      </p>
    </div>
  )
}
