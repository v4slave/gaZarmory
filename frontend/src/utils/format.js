import { getLocale, translate } from '../i18n.js'

export const localeTag = () => getLocale() === 'en' ? 'en-US' : 'ru-RU'

export const formatInteger = value => new Intl.NumberFormat(localeTag(), {
  maximumFractionDigits: 0,
}).format(Number(value) || 0)

export const formatDecimal = (value, options = {}) => new Intl.NumberFormat(localeTag(), options)
  .format(Number(value) || 0)

export const formatDate = (value, options = {}) => value
  ? new Intl.DateTimeFormat(localeTag(), { timeZone: 'Europe/Moscow', ...(Object.keys(options).length ? options : { dateStyle: 'short' }) }).format(new Date(value))
  : '—'

export const formatDateTime = (value, options = {}) => value
  ? new Intl.DateTimeFormat(localeTag(), { timeZone: 'Europe/Moscow', ...(Object.keys(options).length ? options : { dateStyle: 'short', timeStyle: 'short' }) }).format(new Date(value))
  : '—'

export const formatMoscowDateTimeInput = (value = new Date()) => {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Europe/Moscow', year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  }).formatToParts(new Date(value))
  const part = type => parts.find(item => item.type === type)?.value
  return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`
}

export const moscowLocalToIso = value => new Date(`${value}:00+03:00`).toISOString()

export const formatGold = value => `${formatInteger(value)} ${translate('золота')}`
export const dataAge = value => {
  if (!value) return translate('нет данных')
  const seconds = Math.max(0, Math.round((Date.now() - new Date(value).getTime()) / 1000))
  if (seconds < 10) return translate('только что')
  if (seconds < 60) return getLocale() === 'en' ? `${seconds} sec ago` : `${seconds} сек. назад`
  const minutes = Math.floor(seconds / 60)
  return getLocale() === 'en' ? `${minutes} min ago` : `${minutes} мин. назад`
}
