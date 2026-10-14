const API_URL = import.meta.env.VITE_API_URL

// Access token : en mémoire uniquement (jamais de localStorage).
let accessToken = null
export const getToken = () => accessToken
export const setToken = (token) => {
  accessToken = token
}

// Appelé quand le refresh échoue définitivement (session perdue).
let authFailureHandler = () => {}
export const setAuthFailureHandler = (fn) => {
  authFailureHandler = fn
}

export class ApiError extends Error {
  constructor({ status, code, message, violations, retryAfter }) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.violations = violations ?? []
    this.retryAfter = retryAfter ?? null
  }
}

const REFRESHABLE_CODES = ['TOKEN_EXPIRED', 'TOKEN_INVALID', 'TOKEN_MISSING']

async function parseError(response) {
  let error = {}
  try {
    const body = await response.json()
    error = body.error ?? {}
  } catch {
    // corps absent ou non JSON
  }
  const header = response.headers.get('Retry-After')
  const retryAfter = header && !Number.isNaN(Number(header)) ? Number(header) : null
  return new ApiError({
    status: response.status,
    code: error.code ?? 'UNKNOWN_ERROR',
    message: error.message ?? 'Une erreur est survenue.',
    violations: error.violations,
    retryAfter,
  })
}

async function send(method, path, body, withAuth) {
  const headers = {}
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (withAuth && accessToken) headers.Authorization = `Bearer ${accessToken}`
  let response
  try {
    response = await fetch(`${API_URL}${path}`, {
      method,
      headers,
      credentials: 'include',
      body: body !== undefined ? JSON.stringify(body) : undefined,
    })
  } catch {
    throw new ApiError({
      status: 0,
      code: 'NETWORK_ERROR',
      message: 'Impossible de joindre le serveur.',
    })
  }
  if (!response.ok) throw await parseError(response)
  return response.status === 204 ? null : response.json()
}

// Une seule promesse de refresh en vol, partagée par tous les appelants.
let refreshPromise = null
export function refreshAccessToken() {
  if (!refreshPromise) {
    refreshPromise = send('POST', '/auth/refresh', undefined, false)
      .then((data) => {
        accessToken = data.token
        return data
      })
      .finally(() => {
        refreshPromise = null
      })
  }
  return refreshPromise
}

async function request(method, path, body) {
  try {
    return await send(method, path, body, true)
  } catch (err) {
    if (!(err.status === 401 && REFRESHABLE_CODES.includes(err.code))) throw err
    try {
      await refreshAccessToken()
    } catch (refreshErr) {
      if (refreshErr.status === 401 && refreshErr.code === 'INVALID_REFRESH_TOKEN') {
        accessToken = null
        authFailureHandler()
      }
      throw refreshErr
    }
    // Rejeu unique de la requête d'origine.
    return send(method, path, body, true)
  }
}

export const api = {
  get: (path) => request('GET', path),
  post: (path, body) => request('POST', path, body),
}
