import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { confirmEmailRequest } from '../api/auth'
import ResendForm from './ResendForm'
import { genericMessage } from './errors'

// Le token est à usage unique : on mutualise l'appel par token pour que le
// double effet de StrictMode ne le consomme qu'une fois.
const pending = new Map()
function confirmOnce(token) {
  if (!pending.has(token)) pending.set(token, confirmEmailRequest(token))
  return pending.get(token)
}

export default function ConfirmEmailPage() {
  const [params] = useSearchParams()
  const token = params.get('token')
  const [state, setState] = useState(token ? { type: 'loading' } : { type: 'INVALID_TOKEN' })

  useEffect(() => {
    if (!token) return
    let active = true
    confirmOnce(token)
      .then(() => active && setState({ type: 'success' }))
      .catch((err) => {
        if (!active) return
        if (err.code === 'INVALID_TOKEN' || err.code === 'TOKEN_EXPIRED') {
          setState({ type: err.code })
        } else if (err.code === 'VALIDATION_FAILED') {
          setState({ type: 'INVALID_TOKEN' })
        } else {
          setState({ type: 'error', text: genericMessage(err) })
        }
      })
    return () => {
      active = false
    }
  }, [token])

  return (
    <main>
      <h1>Confirmation de l&apos;email</h1>
      {state.type === 'loading' && <p>Confirmation en cours…</p>}
      {state.type === 'success' && (
        <>
          <p>Votre email est confirmé.</p>
          <Link to="/login">Se connecter</Link>
        </>
      )}
      {(state.type === 'INVALID_TOKEN' || state.type === 'TOKEN_EXPIRED') && (
        <>
          <p role="alert">
            {state.type === 'TOKEN_EXPIRED'
              ? 'Ce lien de confirmation a expiré.'
              : 'Ce lien de confirmation est invalide ou déjà utilisé.'}
          </p>
          <p>Recevez un nouveau lien :</p>
          <ResendForm />
        </>
      )}
      {state.type === 'error' && <p role="alert">{state.text}</p>}
    </main>
  )
}
