import { api } from './client'

export const registerRequest = (email, password) => api.post('/auth/register', { email, password })
export const confirmEmailRequest = (token) => api.post('/auth/confirm-email', { token })
export const resendConfirmationRequest = (email) => api.post('/auth/resend-confirmation', { email })
export const loginRequest = (email, password) => api.post('/auth/login', { email, password })
export const logoutRequest = () => api.post('/auth/logout')
export const meRequest = () => api.get('/me')
