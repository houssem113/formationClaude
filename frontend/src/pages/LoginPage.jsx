import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/context'
import ResendForm from './ResendForm'
import { genericMessage } from './errors'

export default function LoginPage() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)
  const [notConfirmed, setNotConfirmed] = useState(false)
  const [loading, setLoading] = useState(false)

  if (user) return <Navigate to="/" replace />

  async function handleSubmit(e) {
    e.preventDefault()
    setLoading(true)
    setError(null)
    setNotConfirmed(false)
    try {
      await login(email, password)
      navigate('/', { replace: true })
    } catch (err) {
      if (err.code === 'INVALID_CREDENTIALS') {
        setError('Email ou mot de passe incorrect.')
      } else if (err.code === 'EMAIL_NOT_CONFIRMED') {
        setNotConfirmed(true)
        setError('Votre email n’est pas encore confirmé.')
      } else {
        setError(genericMessage(err))
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <main>
      <h1>Connexion</h1>
      <form onSubmit={handleSubmit}>
        <label>
          Email
          <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
        </label>
        <label>
          Mot de passe
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoComplete="current-password"
            required
          />
        </label>
        {error && <p role="alert">{error}</p>}
        <button type="submit" disabled={loading}>
          Se connecter
        </button>
      </form>
      {notConfirmed && <ResendForm initialEmail={email} />}
      <p>
        Pas de compte ? <Link to="/register">S&apos;inscrire</Link>
      </p>
    </main>
  )
}
