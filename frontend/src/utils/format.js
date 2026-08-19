const dateTime = new Intl.DateTimeFormat('ru-RU', { dateStyle: 'short', timeStyle: 'short' })
const integer = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 })
export const formatDateTime = value => value ? dateTime.format(new Date(value)) : '—'
export const formatGold = value => `${integer.format(Number(value) || 0)} золота`
export const dataAge = value => {
  if (!value) return 'нет данных'
  const seconds = Math.max(0, Math.round((Date.now() - new Date(value).getTime()) / 1000))
  if (seconds < 10) return 'только что'
  if (seconds < 60) return `${seconds} сек. назад`
  return `${Math.floor(seconds / 60)} мин. назад`
}
