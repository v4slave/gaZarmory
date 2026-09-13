import axios from 'axios'
import { getLocale } from './i18n.js'

const localApiUrl = `${window.location.protocol}//${window.location.hostname}:8000`
const apiBaseUrl = import.meta.env.VITE_API_URL ?? localApiUrl
const safeMethods = new Set(['get', 'head', 'options'])

const csrfClient = axios.create({
  baseURL: apiBaseUrl,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

let csrfCookieRequest = null

export function ensureCsrfCookie({ refresh = false } = {}) {
  if (refresh) csrfCookieRequest = null
  csrfCookieRequest ??= csrfClient.get('/sanctum/csrf-cookie').catch(error => {
    csrfCookieRequest = null
    throw error
  })
  return csrfCookieRequest
}

export const api = axios.create({
  baseURL: apiBaseUrl,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use(config => {
  config.headers['Accept-Language'] = getLocale()
  if (safeMethods.has(config.method?.toLowerCase())) return config
  return ensureCsrfCookie().then(() => config)
})

api.interceptors.response.use(undefined, async error => {
  const config = error.config
  if (error.response?.status !== 419 || !config || config._csrfRetry) throw error

  config._csrfRetry = true
  await ensureCsrfCookie({ refresh: true })
  return api.request(config)
})
