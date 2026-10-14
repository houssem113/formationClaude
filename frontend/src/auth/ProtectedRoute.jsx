import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from './context'

export default function ProtectedRoute() {
  const { user, initializing } = useAuth()
  if (initializing) return <p>Chargement…</p>
  return user ? <Outlet /> : <Navigate to="/login" replace />
}
