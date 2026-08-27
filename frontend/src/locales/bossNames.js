export const bossNames = Object.freeze({
  'АГЛ': 'JMG',
  'Месания': 'Mesania',
  'Ксанатос': 'Black dragon',
  'Анталлон': 'Anthalon',
  'Калидис': 'Charybdis',
  'Авиара': 'Thunderwing Titan',
  'Калеиль': 'Nehiliya',
  'Кракен': 'Kraken',
  'Левиафан': 'Leviathan',
  'Кошка': 'Hanure',
  'Т2 АГЛ': 'JMG T2',
  'Т2 Кракен': 'Kraken T2',
  'Т2 Левиафан': 'Leviathan T2',
})

export function localizeBossName(name, locale) {
  return locale === 'en' ? bossNames[name] ?? name : name
}
