import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('DashboardPage source integrity', () => {
  it('imports the date-time formatter used by upcoming events', () => {
    const source = readFileSync(join(process.cwd(), 'src/pages/DashboardPage.vue'), 'utf8')

    expect(source).toMatch(/import\s*\{[^}]*\bformatDateTime\b[^}]*}\s*from\s*['"]\.\.\/utils\/format\.js['"]/)
    expect(source).toContain('formatDateTime(event.starts_at')
  })

  it('shows the dashboard attribution at the top of the page', () => {
    const source = readFileSync(join(process.cwd(), 'src/pages/DashboardPage.vue'), 'utf8')

    expect(source).toContain('Да, спиздили. Потому что интерфейс классный и дашборд, а сама идея пришла от армори таргетов')
  })
})
