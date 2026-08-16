import axios from 'axios'

const localApiUrl = `${window.location.protocol}//${window.location.hostname}:8000`

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? localApiUrl,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})
