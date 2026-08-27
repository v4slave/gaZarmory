import { beforeEach, describe, expect, it } from 'vitest'
import { apiErrorMessage } from './notifications.js'
import { setLocale } from '../i18n.js'

describe('apiErrorMessage', () => {
  beforeEach(() => setLocale('ru'))
  it('prefers the first validation error', () => {
    expect(apiErrorMessage({ response: { status: 422, data: { errors: { amount: ['Сумма некорректна.'] } } } })).toBe('Сумма некорректна.')
  })

  it.each([
    [401, 'Сессия завершена. Войдите снова.'],
    [403, 'У вас недостаточно прав для этой операции.'],
    [409, 'Данные уже изменились. Обновите страницу и повторите действие.'],
    [429, 'Слишком много запросов. Подождите немного и повторите действие.'],
    [500, 'Сервер временно не может выполнить операцию. Попробуйте позже.'],
  ])('normalizes HTTP %s', (status, message) => {
    expect(apiErrorMessage({ response: { status, data: {} } })).toBe(message)
  })

  it('reports a network failure', () => {
    expect(apiErrorMessage({})).toContain('Нет соединения')
  })
})
