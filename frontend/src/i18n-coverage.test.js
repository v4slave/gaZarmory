import { readdirSync, readFileSync } from 'node:fs'
import { extname, join } from 'node:path'
import { describe, expect, it } from 'vitest'
import { hasEnglishTranslation } from './i18n.js'

function sourceFiles(directory) {
  return readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
    const path = join(directory, entry.name)
    if (entry.isDirectory()) return sourceFiles(path)
    return ['.js', '.vue'].includes(extname(entry.name)) && !entry.name.endsWith('.test.js') ? [path] : []
  })
}

describe('localization coverage', () => {
  it('does not hardcode the Russian Intl locale in interface code', () => {
    const offenders = sourceFiles(join(process.cwd(), 'src'))
      .filter(file => /(?:toLocale(?:String|DateString|TimeString)|Intl\.(?:NumberFormat|DateTimeFormat))\(['"]ru-RU['"]/.test(readFileSync(file, 'utf8')))

    expect(offenders).toEqual([])
  })

  it('has English translations for visible static Vue copy', () => {
    const offenders = []
    for (const file of sourceFiles(join(process.cwd(), 'src')).filter(path => path.endsWith('.vue'))) {
      const source = readFileSync(file, 'utf8')
      const template = source.match(/<template>([\s\S]*?)<\/template>/)?.[1] ?? ''
      const root = document.createElement('div')
      root.innerHTML = template
      const attributes = [...root.querySelectorAll('*')].flatMap(element =>
        ['aria-label','alt','placeholder','title']
          .map(name => element.getAttribute(name))
          .filter(value => value && /[А-Яа-яЁё]/.test(value)),
      )
      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)
      const textNodes = []
      while (walker.nextNode()) {
        const value = walker.currentNode.nodeValue
          .replace(/{{[^}]+}}/g, ' ')
          .replace(/\s+/g, ' ')
          .trim()
        if (/[А-Яа-яЁё]/.test(value)) textNodes.push(value)
      }

      for (const value of [...attributes, ...textNodes]) {
        if (!hasEnglishTranslation(value)) offenders.push(`${file}: ${value}`)
      }
    }

    expect(offenders).toEqual([])
  })

  it('has English translations for Cyrillic interface strings in scripts', () => {
    const offenders = []
    for (const file of sourceFiles(join(process.cwd(), 'src'))) {
      if (file.endsWith('i18n.js')) continue
      const source = readFileSync(file, 'utf8')
      const script = file.endsWith('.vue')
        ? (source.match(/<script setup>([\s\S]*?)<\/script>/)?.[1] ?? '')
        : source
      const strings = [
        ...script.matchAll(/'([^'\\\r\n]*(?:\\.[^'\\\r\n]*)*)'/g),
        ...script.matchAll(/"([^"\\\r\n]*(?:\\.[^"\\\r\n]*)*)"/g),
        ...script.matchAll(/`([^`\\]*(?:\\.[^`\\]*)*)`/g),
      ].map(match => match[1]).filter(value => /[А-Яа-яЁё]/.test(value))

      for (const value of strings) {
        const withoutExpressions = value.replace(/\$\{[^}]+}/g, ' ').replace(/\s+/g, ' ').trim()
        if (withoutExpressions && !hasEnglishTranslation(withoutExpressions)) {
          offenders.push(`${file}: ${withoutExpressions}`)
        }
      }
    }

    expect(offenders).toEqual([])
  })
})
