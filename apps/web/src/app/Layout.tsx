import { Outlet } from 'react-router-dom'
import { useSession, useLogout } from '../shared/auth/session'
import styles from './Layout.module.css'

// Shared chrome around every route inside ProtectedRoute. Feature-specific
// navigation lands here incrementally as each feature ships -- see
// ../features/README.md.
export function Layout() {
  const session = useSession()
  const logout = useLogout()

  return (
    <div className={styles.shell}>
      <header className={styles.header}>
        <span className={styles.brand}>AI-Native Engineering Platform</span>
        {session.data?.status === 'authenticated' && (
          <div className={styles.account}>
            <span>{session.data.user.email}</span>
            <button
              type="button"
              className={styles.logoutButton}
              onClick={() => {
                logout.mutate()
              }}
              disabled={logout.isPending}
            >
              Log out
            </button>
          </div>
        )}
      </header>
      <main className={styles.main}>
        <Outlet />
      </main>
    </div>
  )
}
