import { defineStore } from 'pinia'

let nextId = 1

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({ items: [] }),
  actions: {
    show(message, type = 'info', timeout = 4000) {
      if (!message) return null
      const id = nextId++
      this.items.push({ id, message, type })
      if (timeout > 0) window.setTimeout(() => this.dismiss(id), timeout)
      return id
    },
    success(message) { return this.show(message, 'success') },
    error(message) { return this.show(message, 'error', 6500) },
    warning(message) { return this.show(message, 'warning', 5500) },
    dismiss(id) { this.items = this.items.filter(item => item.id !== id) },
  },
})

export function apiErrorMessage(error, fallback = 'Операция не выполнена.') {
  const validation = Object.values(error?.response?.data?.errors ?? {}).flat()[0]
  if (validation) return validation
  if (error?.response?.data?.message) return error.response.data.message
  if (error?.code === 'ECONNABORTED') return 'Сервер не успел ответить. Попробуйте ещё раз.'
  if (!error?.response) return 'Нет соединения с сервером. Проверьте, запущен ли backend.'
  return fallback
}
