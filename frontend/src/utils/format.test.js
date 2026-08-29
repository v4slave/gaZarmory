import { beforeEach, describe, expect, it } from 'vitest'
import { setLocale } from '../i18n.js'
import { formatDateTime, formatMoscowDateTimeInput, moscowLocalToIso } from './format.js'
import { dataAge, formatDate, formatGold, formatInteger } from './format.js'

describe('locale-aware formatting', () => {
  beforeEach(() => setLocale('ru'))

  it('formats numbers and units in Russian', () => {
    expect(formatInteger(12345)).toBe('12 345')
    expect(formatGold(12345)).toBe('12 345 золота')
  })

  it('formats numbers, dates and units in English', () => {
    setLocale('en')
    expect(formatInteger(12345)).toBe('12,345')
    expect(formatGold(12345)).toBe('12,345 gold')
    expect(formatDate('2026-08-19T12:00:00Z')).toMatch(/8\/19\/26/)
  })

  it('localizes relative time', () => {
    setLocale('en')
    expect(dataAge(new Date(Date.now() - 30_000).toISOString())).toMatch(/sec ago$/)
  })
})

describe('Moscow activity time', () => {
  it('uses Moscow time for display and datetime inputs', () => {
    expect(formatMoscowDateTimeInput('2026-08-29T07:00:00Z')).toBe('2026-08-29T10:00')
    expect(moscowLocalToIso('2026-08-29T10:00')).toBe('2026-08-29T07:00:00.000Z')
    expect(formatDateTime('2026-08-29T07:00:00Z')).toContain('10:00')
  })

  it('keeps the selected wall-clock time after a server round trip', () => {
    const submitted = moscowLocalToIso('2026-08-30T19:20')
    expect(formatMoscowDateTimeInput(submitted)).toBe('2026-08-30T19:20')
    expect(formatMoscowDateTimeInput('2026-08-30T19:20:00+03:00')).toBe('2026-08-30T19:20')
  })
})
