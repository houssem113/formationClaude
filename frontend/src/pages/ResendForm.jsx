import { useState } from 'react'
import { resendConfirmationRequest } from '../api/auth'
import { genericMessage } from './errors'

export default function ResendForm({ initialEmail = '' }) {
  const [email, setEmail] = useState(initialEmail)
  const [status, setStatus] = useState(null) // { type: 'ok' | 'error', text }
  const [loading, setLoading] = useState(false)

  async function handleSubmit(e) {
    e.preventDefault()
    setLoading(true)
    setStatus(null)
    try {
      const data = await resendConfirmationRequest(email)
      setStatus({ type: 'ok', text: data.message })
    } catch (err) {
      const text =
        err.code === 'VALIDATION_FAILED'
          ? (err.violations[0]?.message ?? err.message)
          : genericMessage(err)
      setStatus({ type: 'error', text })
    } finally {
      setLoading(false)
    }
  }

  return (
    <form onSubmit={handleSubmit}>
      <label>
        Email
        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
      </label>
      <button type="submit" disabled={loading}>
        Renvoyer l&apos;email de confirmation
      </button>
      {status && <p role={status.type === 'error' ? 'alert' : 'status'}>{status.text}</p>}
    </form>
  )
}
