import { defineStore } from 'pinia'
import { translate, translateExact } from '../i18n.js'

let nextId = 1

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({ items: [] }),
  actions: {
    show(message, type = 'info', timeout = 4000) {
      if (!message) return null
      const id = nextId++
      this.items.push({ id, message: translate(message), type })
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
  if (validation) return translateExact(validation)
  if (error?.code === 'ECONNABORTED') return translate('Сервер не успел ответить. Попробуйте ещё раз.')
  if (!error?.response) return translate('Нет соединения с сервером. Проверьте, запущен ли backend.')
  const status = error.response.status
  const serverMessage = error.response.data?.message
  if (serverMessage && !['Unauthenticated.', 'This action is unauthorized.'].includes(serverMessage)) return translateExact(serverMessage)
  if (status === 401) return translate('Сессия завершена. Войдите снова.')
  if (status === 403) return translate('У вас недостаточно прав для этой операции.')
  if (status === 409) return translate('Данные уже изменились. Обновите страницу и повторите действие.')
  if (status === 429) return translate('Слишком много запросов. Подождите немного и повторите действие.')
  if (status >= 500) return translate('Сервер временно не может выполнить операцию. Попробуйте позже.')
  return translate(fallback)
}
