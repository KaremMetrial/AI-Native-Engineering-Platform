import { Link } from 'react-router-dom'

export function HomePage() {
  return (
    <div>
      <h1>AI-Native Engineering Platform</h1>
      <p>
        <Link to="/projects">View projects</Link>
      </p>
      <p>
        <Link to="/discovery-sessions">View discovery sessions</Link>
      </p>
      <p>
        <Link to="/requirement-documents">View requirement documents</Link>
      </p>
    </div>
  )
}
