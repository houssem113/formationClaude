// Message d'attente pour un 429 (Retry-After en secondes).
export function tooManyRequestsMessage(err) {
  return err.retryAfter
    ? `Trop de tentatives. Réessayez dans ${err.retryAfter} seconde(s).`
    : 'Trop de tentatives. Réessayez dans un instant.'
}

// Message générique pour une erreur API inattendue.
export function genericMessage(err) {
  if (err.status === 429) return tooManyRequestsMessage(err)
  if (err.code === 'NETWORK_ERROR') return err.message
  return 'Une erreur est survenue. Veuillez réessayer.'
}
