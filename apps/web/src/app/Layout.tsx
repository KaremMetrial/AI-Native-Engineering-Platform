import { Outlet } from 'react-router-dom'
import styles from './Layout.module.css'

// Shared chrome around every route. Feature-specific navigation (once a
// user is authenticated) lands here incrementally as each feature ships
// -- see ../features/README.md.
export function Layout() {
  return (
    <div className={styles.shell}>
      <header className={styles.header}>
        <span className={styles.brand}>AI-Native Engineering Platform</span>
      </header>
      <main className={styles.main}>
        <Outlet />
      </main>
    </div>
  )
}
