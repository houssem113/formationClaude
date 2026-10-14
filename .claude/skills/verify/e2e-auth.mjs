#!/usr/bin/env node
// Parcours d'authentification complet contre la stack Docker (cf. docs/api-contract.md) :
// inscription -> email dans Mailpit -> lien de confirmation -> login -> refresh -> logout
// -> le refresh token révoqué ne marche plus.
//
// Usage : node .claude/skills/verify/e2e-auth.mjs
// Variables optionnelles : API_URL (http://localhost:8000/api), MAILPIT_URL (http://localhost:8025)
// Code de sortie : 0 si tout passe, 1 au premier échec.

const API_URL = (process.env.API_URL ?? 'http://localhost:8000/api').replace(/\/$/, '')
const MAILPIT_URL = (process.env.MAILPIT_URL ?? 'http://localhost:8025').replace(/\/$/, '')
const EMAIL = `verify+${Date.now()}@example.com`
const PASSWORD = 'verify123'

let stepNumber = 0

class StepError extends Error {}

function fail(message, details) {
  throw new StepError(details === undefined ? message : `${message}\n      ${JSON.stringify(details)}`)
}

async function step(title, fn) {
  stepNumber += 1
  const label = `${String(stepNumber).padStart(2)}. ${title}`
  try {
    const info = await fn()
    console.log(`OK    ${label}${info ? ` — ${info}` : ''}`)
    return info
  } catch (error) {
    console.log(`ÉCHEC ${label}\n      ${error.message}`)
    throw error
  }
}

async function api(method, path, { body, cookie, token } = {}) {
  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (cookie) headers.Cookie = `refresh_token=${cookie}`
  if (token) headers.Authorization = `Bearer ${token}`

  const response = await fetch(`${API_URL}${path}`, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  const text = await response.text()
  let data = null
  try {
    data = text ? JSON.parse(text) : null
  } catch {
    data = text
  }
  const setCookie = response.headers.getSetCookie().find((c) => c.startsWith('refresh_token='))
  return { status: response.status, data, setCookie }
}

function expectStatus(res, status, code) {
  if (res.status !== status || (code && res.data?.error?.code !== code)) {
    fail(`attendu ${status}${code ? ` ${code}` : ''}, reçu ${res.status}`, res.data)
  }
}

function refreshCookieValue(setCookie) {
  if (!setCookie) fail('pas de cookie refresh_token dans la réponse')
  const attributes = setCookie.toLowerCase()
  for (const expected of ['httponly', 'secure', 'samesite=strict', 'path=/api/auth']) {
    if (!attributes.includes(expected)) fail(`attribut « ${expected} » absent du cookie`, setCookie)
  }
  return setCookie.slice('refresh_token='.length).split(';')[0]
}

async function waitForEmail(to) {
  const query = encodeURIComponent(`to:"${to}"`)
  for (let attempt = 0; attempt < 20; attempt += 1) {
    const res = await fetch(`${MAILPIT_URL}/api/v1/search?query=${query}`)
    if (!res.ok) fail(`Mailpit répond ${res.status} (stack lancée ? ${MAILPIT_URL})`)
    const { messages } = await res.json()
    if (messages?.length) return messages[0]
    await new Promise((resolve) => setTimeout(resolve, 500))
  }
  fail(`aucun email pour ${to} dans Mailpit après 10 s (MAILER_DSN redirigé vers un vrai service dans backend/.env.local ?)`)
}

async function main() {
  console.log(`Parcours d'authentification — API ${API_URL}, Mailpit ${MAILPIT_URL}, compte ${EMAIL}\n`)

  await step('Inscription', async () => {
    const res = await api('POST', '/auth/register', { body: { email: EMAIL, password: PASSWORD } })
    expectStatus(res, 201)
    if (res.data.email !== EMAIL || res.data.isVerified !== false) fail('réponse inattendue', res.data)
    return `compte #${res.data.id} non confirmé`
  })

  await step('Login refusé tant que l\'email n\'est pas confirmé', async () => {
    const res = await api('POST', '/auth/login', { body: { email: EMAIL, password: PASSWORD } })
    expectStatus(res, 403, 'EMAIL_NOT_CONFIRMED')
  })

  let confirmUrl
  let mailpitId
  await step('Email de confirmation reçu dans Mailpit', async () => {
    const summary = await waitForEmail(EMAIL)
    mailpitId = summary.ID
    const message = await (await fetch(`${MAILPIT_URL}/api/v1/message/${mailpitId}`)).json()
    const match = /href="([^"]*\/confirm-email\?token=[^"]+)"/.exec(message.HTML ?? '')
    if (!match) fail('lien de confirmation introuvable dans l\'email')
    confirmUrl = new URL(match[1].replaceAll('&amp;', '&'))
    return `« ${summary.Subject} »`
  })

  await step('Clic sur le lien de confirmation', async () => {
    // Le lien ouvre la page React /confirm-email, qui envoie le token à l'API : on fait de même
    const page = await fetch(confirmUrl).catch(() => null)
    if (!page?.ok) fail(`la page ${confirmUrl.origin}${confirmUrl.pathname} ne répond pas (frontend lancé ?)`)
    const token = confirmUrl.searchParams.get('token')
    const res = await api('POST', '/auth/confirm-email', { body: { token } })
    expectStatus(res, 200)
    const reuse = await api('POST', '/auth/confirm-email', { body: { token } })
    expectStatus(reuse, 400, 'INVALID_TOKEN')
    return 'email confirmé, lien à usage unique'
  })

  let accessToken
  let refreshToken
  await step('Login', async () => {
    const res = await api('POST', '/auth/login', { body: { email: EMAIL, password: PASSWORD } })
    expectStatus(res, 200)
    if (!res.data.token || res.data.expiresIn !== 900 || res.data.user?.email !== EMAIL) fail('réponse inattendue', res.data)
    accessToken = res.data.token
    refreshToken = refreshCookieValue(res.setCookie)
    const me = await api('GET', '/me', { token: accessToken })
    expectStatus(me, 200)
    if (me.data.isVerified !== true) fail('/api/me : compte non vérifié', me.data)
    return 'JWT valide sur /api/me, cookie refresh_token posé'
  })

  await step('Refresh du token', async () => {
    const res = await api('POST', '/auth/refresh', { cookie: refreshToken })
    expectStatus(res, 200)
    if (!res.data.token) fail('pas de nouvel access token', res.data)
    const rotated = refreshCookieValue(res.setCookie)
    if (rotated === refreshToken) fail('le refresh token n\'a pas changé (rotation attendue)')
    const me = await api('GET', '/me', { token: res.data.token })
    expectStatus(me, 200)
    const reuse = await api('POST', '/auth/refresh', { cookie: refreshToken })
    expectStatus(reuse, 401, 'INVALID_REFRESH_TOKEN')
    refreshToken = rotated
    return 'nouveau JWT valide, ancien refresh token refusé'
  })

  await step('Logout', async () => {
    const res = await api('POST', '/auth/logout', { cookie: refreshToken })
    expectStatus(res, 204)
    if (!/max-age=0/i.test(res.setCookie ?? '')) fail('le cookie refresh_token n\'est pas effacé', res.setCookie)
  })

  await step('Refresh token inutilisable après logout', async () => {
    const res = await api('POST', '/auth/refresh', { cookie: refreshToken })
    expectStatus(res, 401, 'INVALID_REFRESH_TOKEN')
  })

  // Ménage : l'email de test n'encombre pas la boîte Mailpit
  await fetch(`${MAILPIT_URL}/api/v1/messages`, {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ IDs: [mailpitId] }),
  }).catch(() => {})

  console.log(`\nParcours complet OK (${stepNumber} étapes).`)
}

main().catch((error) => {
  if (!(error instanceof StepError)) {
    console.log(`\nErreur inattendue : ${error.cause?.code ?? ''} ${error.message}`)
    console.log('La stack est-elle lancée ? docker compose up -d --build')
  } else {
    console.log('\nParcours en ÉCHEC.')
  }
  process.exit(1)
})
