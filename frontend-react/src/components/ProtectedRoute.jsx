import { Navigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

// Ports app.py's page_login_required (redirect to /login) and
// page_admin_required (redirect to /dashboard) decorators.
export default function ProtectedRoute({ children, adminOnly = false }) {
  const { user, loading } = useAuth()

  if (loading) {
    return null
  }
  if (!user) {
    return <Navigate to="/login" replace />
  }
  if (adminOnly && user.role !== 'admin') {
    return <Navigate to="/dashboard" replace />
  }
  return children
}
