import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../auth/context'
import { genericMessage } from './errors'

export default function RegisterPage() {
  const { register } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [error, setError] = useState(null)
  const [done, setDone] = useState(false)
  const [loading, setLoading] = useState(false)

  async function handleSubmit(e) {
    e.preventDefault()
    setLoading(true)
    setError(null)
    setFieldErrors({})
    try {
      await register(email, password)
      setDone(true)
    } catch (err) {
      if (err.code === 'VALIDATION_FAILED') {
        const byField = {}
        for (const v of err.violations) byField[v.field] = v.message
        setFieldErrors(byField)
      } else if (err.code === 'EMAIL_ALREADY_USED') {
        setFieldErrors({ email: 'Un compte existe déjà avec cette adresse email.' })
      } else {
        setError(genericMessage(err))
      }
    } finally {
      setLoading(false)
    }
  }

  if (done) {
    return (
      <main>
        <h1>Vérifiez votre boîte mail</h1>
        <p>
          Un email de confirmation a été envoyé à {email}. Cliquez sur le lien pour activer votre
          compte.
        </p>
        <Link to="/login">Aller à la connexion</Link>
      </main>
    )
  }

  return (
    <main>
      <h1>Inscription</h1>
      <form onSubmit={handleSubmit}>
        <label>
          Email
          <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
        </label>
        {fieldErrors.email && <p role="alert">{fieldErrors.email}</p>}
        <label>
          Mot de passe
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoComplete="new-password"
            required
          />
        </label>
        {fieldErrors.password && <p role="alert">{fieldErrors.password}</p>}
        {error && <p role="alert">{error}</p>}
        <button type="submit" disabled={loading}>
          Créer mon compte
        </button>
      </form>
      <p>
        Déjà inscrit ? <Link to="/login">Se connecter</Link>
      </p>
    </main>
  )
}
