import { useCallback, useEffect, useMemo, useState } from 'react'
import { refreshAccessToken, setAuthFailureHandler, setToken } from '../api/client'
import { loginRequest, logoutRequest, meRequest, registerRequest } from '../api/auth'
import { AuthContext } from './context'

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [initializing, setInitializing] = useState(true)

  // Restauration de session : refresh (cookie) puis /me.
  // refreshAccessToken est mutualisé : le double effet de StrictMode ne
  // consomme donc le refresh token (à usage unique) qu'une seule fois.
  useEffect(() => {
    let active = true
    refreshAccessToken()
      .then(() => meRequest())
      .then((me) => active && setUser(me))
      .catch(() => {
        setToken(null)
      })
      .finally(() => active && setInitializing(false))
    return () => {
      active = false
    }
  }, [])

  useEffect(() => {
    setAuthFailureHandler(() => setUser(null))
  }, [])

  const login = useCallback(async (email, password) => {
    const data = await loginRequest(email, password)
    setToken(data.token)
    setUser(await meRequest())
  }, [])

  const register = useCallback((email, password) => registerRequest(email, password), [])

  const logout = useCallback(async () => {
    try {
      await logoutRequest()
    } finally {
      setToken(null)
      setUser(null)
    }
  }, [])

  const value = useMemo(
    () => ({ user, initializing, login, logout, register }),
    [user, initializing, login, logout, register],
  )
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
