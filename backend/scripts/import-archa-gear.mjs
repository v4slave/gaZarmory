import { chromium } from 'playwright'

const url = process.argv[2]
if (!url) throw new Error('Build URL is required')

const slots = ['Костюм','Голова','Нагрудник','Пояс','Наручи','Перчатки','Плащ','Поножи','Обувь','Бельё','Ожерелье','Серьга 1','Серьга 2','Кольцо 1','Кольцо 2','Основное оружие','Левая рука','Лук','Музыкальный инструмент']
const browser = await chromium.launch({ headless: true, args: ['--disable-dev-shm-usage', '--no-sandbox'] })

try {
  const page = await browser.newPage({ locale: 'ru-RU' })
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 })
  await page.waitForFunction(() => [...document.querySelectorAll('.aa-itemslot .aa-gradecorner')].some(node => node.getAttribute('data-bs-content')), null, { timeout: 30000 })

  const items = await page.locator('.aa-itemslot').evaluateAll((elements, slotNames) => {
    const asset = (src, type) => Number((src?.match(new RegExp(`/${type}/(\\d+)\\.jpg`)) ?? [])[1] ?? 0)
    const gradeName = src => ((src?.match(/item_grade_([A-Za-z]+)\.png/) ?? [])[1] ?? '').toLowerCase()
    const text = node => node?.textContent?.trim() ?? ''

    return elements.slice(0, 19).map((element, index) => {
      const image = element.querySelector(':scope > img:not([class])')
      const grade = element.querySelector(':scope > .aa-gradecorner')
      const itemId = asset(image?.getAttribute('src'), 'items')
      const html = grade?.getAttribute('data-bs-content') ?? ''
      if (!itemId || !html) return null

      const box = document.createElement('div')
      box.innerHTML = html
      const heading = box.querySelector('.col-9')
      const headingParts = heading?.innerHTML.split(/<br\s*\/?>/i) ?? []
      const clean = value => {
        const node = document.createElement('span')
        node.innerHTML = value ?? ''
        return text(node)
      }
      const lines = selector => [...box.querySelectorAll(selector)].map(text).filter(Boolean)
      const runeNode = box.querySelector('.rune-stat')
      const runeImage = box.querySelector('.aa-popover-rune img:not(.aa-popover-rune-grade)')
      const runeGrade = box.querySelector('.aa-popover-rune-grade')
      const gems = [...box.querySelectorAll('.gem-stat')].map(node => {
        const row = node.closest('.d-flex')
        return {
          text: text(node),
          id: asset(row?.querySelector('.aa-gem-slot-icon')?.getAttribute('src'), 'gems'),
          grade: gradeName(row?.querySelector('.aa-gem-slot-grade')?.getAttribute('src')),
        }
      }).filter(gem => gem.text && gem.id)

      return {
        slot: slotNames[index],
        name: clean(headingParts.slice(1).join('<br>')),
        quality: clean(headingParts[0]),
        grade: gradeName(grade.getAttribute('src')),
        item_id: itemId,
        stats: lines('.item-stats-block p'),
        rune: runeNode && runeImage ? {
          text: text(runeNode),
          id: asset(runeImage.getAttribute('src'), 'runes'),
          grade: gradeName(runeGrade?.getAttribute('src')),
        } : null,
        gems,
        synthesis: lines('.synth-stat'),
      }
    }).filter(Boolean)
  }, slots)

  process.stdout.write(JSON.stringify(items))
} finally {
  await browser.close()
}
