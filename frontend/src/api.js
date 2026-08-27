import axios from 'axios'
import { getLocale } from './i18n.js'

const localApiUrl = `${window.location.protocol}//${window.location.hostname}:8000`

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? localApiUrl,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use(config => {
  config.headers['Accept-Language'] = getLocale()
  return config
})
