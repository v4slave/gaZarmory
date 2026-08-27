import { beforeEach, describe, expect, it } from 'vitest'
import { localizeBossName } from './locales/bossNames.js'
import { setLocale, translate } from './i18n.js'

describe('interface localization', () => {
  beforeEach(() => setLocale('ru'))

  it('preserves the official boss names', () => {
    expect(localizeBossName('АГЛ', 'en')).toBe('JMG')
    expect(localizeBossName('Ксанатос', 'en')).toBe('Black dragon')
    expect(localizeBossName('Т2 Левиафан', 'en')).toBe('Leviathan T2')
  })

  it('translates interpolated interface copy without corrupting words', () => {
    setLocale('en')
    expect(translate('Победитель: Taquela')).toBe('Winner: Taquela')
    expect(translate('Данные уже изменились. Обновите страницу и повторите действие.')).toBe('The data has already changed. Refresh the page and try again.')
    expect(translate('Да')).toBe('Yes')
  })

  it('does not translate user-generated names', () => {
    setLocale('en')
    expect(translate('Воры дкп')).toBe('Воры дкп')
  })
})
