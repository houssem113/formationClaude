import { useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/context'

export default function HomePage() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  async function handleLogout() {
    try {
      await logout()
    } finally {
      navigate('/login', { replace: true })
    }
  }

  return (
    <main>
      <h1>Accueil</h1>
      <p>Connecté en tant que {user.email}</p>
      <p>Rôles : {user.roles.join(', ')}</p>
      <button onClick={handleLogout}>Se déconnecter</button>
    </main>
  )
}
