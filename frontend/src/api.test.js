import { beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => {
  const clients = []
  const makeClient = () => {
    const handlers = { request: [], response: [] }
    return {
      handlers,
      get: vi.fn().mockResolvedValue({ status: 204 }),
      request: vi.fn().mockResolvedValue({ status: 200 }),
      interceptors: {
        request: { use: vi.fn(handler => handlers.request.push(handler)) },
        response: { use: vi.fn((success, failure) => handlers.response.push({ success, failure })) },
      },
    }
  }
  return { clients, makeClient }
})

vi.mock('axios', () => ({
  default: { create: vi.fn(() => {
    const client = mocks.makeClient()
    mocks.clients.push(client)
    return client
  }) },
}))

describe('API CSRF protection', () => {
  beforeEach(() => vi.clearAllMocks())

  it('loads a CSRF cookie before a state-changing request', async () => {
    await import('./api.js')
    const [csrfClient, apiClient] = mocks.clients

    const config = { method: 'post', headers: {} }
    await apiClient.handlers.request[0](config)

    expect(csrfClient.get).toHaveBeenCalledWith('/sanctum/csrf-cookie')
  })

  it('refreshes the CSRF cookie and retries once after a 419 response', async () => {
    const { api } = await import('./api.js')
    const [csrfClient, apiClient] = mocks.clients
    const config = { method: 'post', headers: {} }

    await apiClient.handlers.response[0].failure({ response: { status: 419 }, config })

    expect(csrfClient.get).toHaveBeenCalledWith('/sanctum/csrf-cookie')
    expect(api.request).toHaveBeenCalledWith(expect.objectContaining({ _csrfRetry: true }))
  })
})
