import { beforeEach, describe, expect, it } from 'vitest'
import { setLocale } from '../i18n.js'
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
