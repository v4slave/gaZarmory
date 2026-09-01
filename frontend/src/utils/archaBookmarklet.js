const SLOTS = ['Костюм','Голова','Нагрудник','Пояс','Наручи','Перчатки','Плащ','Поножи','Обувь','Бельё','Ожерелье','Серьга 1','Серьга 2','Кольцо 1','Кольцо 2','Основное оружие','Левая рука','Лук','Музыкальный инструмент']

function collectArchaGear(armoryOrigin, slots) {
  if (location.hostname !== 'archa.ge') {
    alert('Откройте билд на archa.ge и нажмите эту закладку ещё раз.')
    return
  }
  const asset = (src, type) => Number((src?.match(new RegExp(`/${type}/(\\d+)\\.jpg`)) ?? [])[1] ?? 0)
  const gradeName = src => ((src?.match(/item_grade_([A-Za-z]+)\.png/) ?? [])[1] ?? '').toLowerCase()
  const items = [...document.querySelectorAll('.aa-itemslot')].slice(0, 19).map((element, index) => {
    const image = element.querySelector(':scope > img:not([class])')
    const grade = element.querySelector('.aa-gradecorner')
    const itemId = asset(image?.getAttribute('src'), 'items')
    const html = grade?.getAttribute('data-bs-content') ?? ''
    const heading = html.match(/<div class=['"]col-9[^'"]*['"]>\s*(.*?)\s*<br\s*\/?>\s*(.*?)\s*<\/div>/is)
    if (!itemId || !heading) return null
    const box = document.createElement('div')
    box.innerHTML = html
    const text = node => node?.textContent?.trim() ?? ''
    const lines = selector => [...box.querySelectorAll(selector)].map(text).filter(Boolean)
    const runeNode = box.querySelector('.rune-stat')
    const runeImage = box.querySelector('.aa-popover-rune img:not(.aa-popover-rune-grade)')
    const runeGrade = box.querySelector('.aa-popover-rune-grade')
    const gems = [...box.querySelectorAll('.gem-stat')].map(node => {
      const row = node.closest('.d-flex')
      return { text: text(node), id: asset(row?.querySelector('.aa-gem-slot-icon')?.getAttribute('src'), 'gems'), grade: gradeName(row?.querySelector('.aa-gem-slot-grade')?.getAttribute('src')) }
    }).filter(gem => gem.text && gem.id)
    return {
      slot: slots[index], name: text(Object.assign(document.createElement('span'), { innerHTML: heading[2] })),
      quality: text(Object.assign(document.createElement('span'), { innerHTML: heading[1] })),
      grade: gradeName(grade?.getAttribute('src')), item_id: itemId,
      stats: lines('.item-stats-block p'),
      rune: runeNode && runeImage ? { text: text(runeNode), id: asset(runeImage.getAttribute('src'), 'runes'), grade: gradeName(runeGrade?.getAttribute('src')) } : null,
      gems, synthesis: lines('.synth-stat'),
    }
  }).filter(Boolean)
  if (!items.length) {
    alert('Экипировка не найдена. Дождитесь полной загрузки билда.')
    return
  }
  const bytes = new TextEncoder().encode(JSON.stringify({ source_url: location.href, items }))
  let binary = ''
  bytes.forEach(byte => { binary += String.fromCharCode(byte) })
  location.href = `${armoryOrigin}/gear-import#${btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')}`
}

export function buildArchaBookmarklet(armoryOrigin) {
  return `javascript:(${collectArchaGear.toString()})(${JSON.stringify(armoryOrigin)},${JSON.stringify(SLOTS)})`
}
