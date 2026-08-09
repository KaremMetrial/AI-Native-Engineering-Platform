import type { InputHTMLAttributes } from 'react'
import styles from './TextField.module.css'

interface TextFieldProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string
  name: string
  errors?: string[] | undefined
}

export function TextField({ label, name, errors, ...inputProps }: TextFieldProps) {
  const errorId = `${name}-error`

  return (
    <div className={styles.field}>
      <label className={styles.label} htmlFor={name}>
        {label}
      </label>
      <input
        id={name}
        name={name}
        className={styles.input}
        aria-invalid={errors !== undefined && errors.length > 0}
        aria-describedby={errors !== undefined && errors.length > 0 ? errorId : undefined}
        {...inputProps}
      />
      {errors !== undefined && errors.length > 0 && (
        <p id={errorId} className={styles.error}>
          {errors[0]}
        </p>
      )}
    </div>
  )
}
