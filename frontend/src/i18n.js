import { readonly, ref } from 'vue'

const STORAGE_KEY = 'gaz-armory-locale'
const supported = ['ru', 'en']
const initial = supported.includes(localStorage.getItem(STORAGE_KEY))
  ? localStorage.getItem(STORAGE_KEY)
  : (navigator.language?.toLowerCase().startsWith('en') ? 'en' : 'ru')
const locale = ref(initial)

// Longer phrases are replaced first. This keeps the existing Russian templates as
// the source of truth while giving every current and future view a translation.
const translations = {
  'Т2 Левиафан': 'Leviathan T2', 'Т2 Кракен': 'Kraken T2', 'Т2 АГЛ': 'JMG T2',
  'АГЛ': 'JMG', 'Месания': 'Mesania', 'Ксанатос': 'Black dragon', 'Анталлон': 'Anthalon',
  'Калидис': 'Charybdis', 'Авиара': 'Thunderwing Titan', 'Калеиль': 'Nehiliya',
  'Кракен': 'Kraken', 'Левиафан': 'Leviathan', 'Кошка': 'Hanure',
  'Проверяем авторизацию…': 'Checking authentication…', 'Войти через Discord': 'Sign in with Discord',
  'ПЕРВЫЙ ВХОД': 'FIRST SIGN-IN', 'Заявка отправлена': 'Request sent',
  'Дождитесь подтверждения ГЛ или администратора.': 'Wait for approval from the guild leader or an administrator.',
  'Привяжите персонажа': 'Link your character',
  'Выберите персонажа и отправьте заявку. Разделы гильдии откроются после подтверждения.': 'Choose your character and submit a request. Guild sections will become available after approval.',
  'Выбрать персонажа': 'Choose character', 'Привязать игровой профиль': 'Link game profile',
  'Выберите своего персонажа. Заявку проверит ГЛ или администратор.': 'Choose your character. The request will be reviewed by the guild leader or an administrator.',
  'Свободных активных профилей не найдено.': 'No available active profiles found.',
  'Не удалось загрузить список персонажей.': 'Could not load the character list.',
  'Не удалось привязать игровой профиль.': 'Could not link the game profile.',
  'Основное': 'Main', 'Экономика': 'Economy', 'Дашборд': 'Dashboard', 'Состав': 'Roster',
  'Конст-пати': 'Static parties', 'Активности': 'Activities', 'Казна': 'Treasury', 'Аукционы': 'Auctions',
  'Нахрюк': 'Payouts', 'Готовность состава': 'Roster readiness', 'Посещаемость': 'Attendance',
  'Финансовая сверка': 'Financial reconciliation', 'Админка': 'Admin',
  'Управление гильдией': 'Guild management', 'Участник гильдии': 'Guild member',
  'Уведомления': 'Notifications', 'непрочитанных': 'unread', 'Прочитать все': 'Mark all as read',
  'Уведомлений пока нет.': 'No notifications yet.', 'привязать профиль': 'link profile',
  'Выйти': 'Sign out', 'Отправить заявку': 'Submit request', 'Отправка…': 'Submitting…',
  'Игровой никнейм': 'Character name', 'Выберите персонажа': 'Choose a character', 'Отмена': 'Cancel',
  'Нет доступа': 'Access denied', 'Страница не найдена': 'Page not found', 'Вернуться на дашборд': 'Back to dashboard',
  'У вас нет прав для просмотра этого раздела.': 'You do not have permission to view this section.',
  'Запрошенная страница не существует.': 'The requested page does not exist.',
  'Пользователи и роли': 'Users and roles', 'Заявки': 'Requests', 'Справочник активностей': 'Activity catalog',
  'Справочник лута': 'Loot catalog', 'Аудит': 'Audit log', 'Настройки экономики': 'Economy settings',
  'Discord и уведомления': 'Discord and notifications', 'Панель управления': 'Control panel',
  'Добавить': 'Add', 'Создать': 'Create', 'Редактировать': 'Edit', 'Удалить': 'Delete',
  'Сохранить': 'Save', 'Сохранение…': 'Saving…', 'Подтвердить': 'Confirm', 'Закрыть': 'Close',
  'Загрузка…': 'Loading…', 'Загружаем данные…': 'Loading data…', 'Повторить': 'Retry',
  'Поиск': 'Search', 'Никнейм': 'Nickname', 'Класс': 'Class', 'Роль': 'Role', 'Статус': 'Status',
  'Дата': 'Date', 'Название': 'Name', 'Описание': 'Description', 'Комментарий': 'Comment',
  'Количество': 'Quantity', 'Сумма': 'Amount', 'Баланс': 'Balance', 'Действия': 'Actions',
  'Все': 'All', 'Активные': 'Active', 'Завершённые': 'Completed', 'Черновик': 'Draft',
  'Запланировано': 'Scheduled', 'Завершено': 'Completed', 'Отменено': 'Cancelled',
  'Сбросить': 'Reset', 'Применить': 'Apply', 'Фильтры': 'Filters', 'Показать': 'Show',
  'Сегодня': 'Today', 'Неделя': 'Week', 'Месяц': 'Month', 'За всё время': 'All time',
  'Игрок': 'Player', 'Игроки': 'Players', 'Участники': 'Participants', 'Группа': 'Group',
  'Без консты': 'No static party', 'Все консты': 'All static parties', 'Все классы': 'All classes',
  'Милик': 'Melee', 'Лучник': 'Archer', 'Маг': 'Mage', 'Хил': 'Healer', 'Бард': 'Bard', 'Танк': 'Tank',
  'Средний ГС': 'Average GS', 'Полностью оснащены': 'Fully equipped', 'В выборке': 'In selection',
  'по текущему фильтру': 'with current filter', 'все отметки профиля': 'all profile items',
  'Оснащение заполнено полностью': 'Equipment profile is complete', 'Не хватает:': 'Missing:',
  'Любое оснащение': 'Any equipment', 'ГС от': 'GS min', 'ГС до': 'GS max',
  'Корабль': 'Ship', 'Облачко': 'Cloud', 'Олень': 'Deer', 'Ласты': 'Flippers',
  'Щит на свап': 'Swap shield', 'Пет на неуяз': 'Invulnerability pet',
  'Инвентарь': 'Inventory', 'Последний дроп': 'Latest drop', 'По активностям': 'By activity',
  'История движения предметов': 'Item transaction history', 'История золота': 'Gold history',
  'Последние 50 операций': 'Last 50 transactions', 'Предмет': 'Item', 'Операция': 'Transaction',
  'Получатель': 'Recipient', 'Провёл': 'Created by', 'Кол-во': 'Qty', 'Система': 'System',
  'Продать': 'Sell', 'Выдать': 'Issue', 'Продать предмет': 'Sell item', 'Выдать предмет': 'Issue item',
  'доступно': 'available', 'В резерве:': 'Reserved:', 'Инвентарь пока пуст.': 'Inventory is empty.',
  'Транзакции казны пока отсутствуют.': 'No treasury transactions yet.',
  'Операций с золотом пока нет.': 'No gold transactions yet.', 'Золото': 'Gold', 'жетоны': 'tokens',
  'Казна гильдии': 'Guild treasury', 'Списать золото': 'Withdraw gold', 'Добавить золото': 'Add gold',
  'Золото в казне / жетоны': 'Treasury gold / tokens', 'Дроп с РБ (экв. золота)': 'Boss loot (gold value)',
  'Оперативное состояние гильдии': 'Guild status at a glance', 'Фактический баланс': 'Actual balance',
  'Дроп с РБ / жетоны': 'Boss loot / tokens', 'Эквивалент в золоте': 'Gold equivalent',
  'Ожидаемый нахрюк / жетоны': 'Expected payout / tokens', 'Средний ГС гильдии': 'Average guild GS',
  'Активных аукционов': 'Active auctions', 'Состав по классам': 'Roster by class',
  'Весь состав →': 'Full roster →', 'Динамика казны': 'Treasury history',
  'Стоимость предметов': 'Item value', 'Динамика золота и стоимости предметов за 14 дней': 'Gold and item value over 14 days',
  'Операций казны пока нет.': 'No treasury transactions yet.', 'Ближайшие события': 'Upcoming events',
  'Топ-5 по посещаемости': 'Top 5 by attendance', 'Праймы за': 'Primes over', 'дней': 'days',
  'Посещений за этот период пока нет.': 'No attendance in this period yet.',
  'Последние активности': 'Recent activities', 'Все события →': 'All events →',
  'Основной прайм': 'Main prime', 'участн.': 'participants', 'Событий пока нет.': 'No events yet.',
  'квадр.': 'quad.', 'трлн': 'tn', 'млрд': 'bn', 'млн': 'm', 'тыс.': 'k',
  ' сек.': ' sec.', ' мин.': ' min.', ' ч.': ' hr.', ' д.': ' d.', 'Лук': 'Archer',
  'Аукцион': 'Auction', 'Ставка': 'Bid', 'Текущая ставка': 'Current bid', 'Сделать ставку': 'Place bid',
  'Победитель': 'Winner', 'Начало': 'Start', 'Окончание': 'End', 'Осталось': 'Remaining',
  'Прайм': 'Prime', 'Мини-прайм': 'Mini-prime', 'Активность': 'Activity', 'Событие': 'Event',
  'Начать': 'Start', 'Завершить': 'Complete', 'Участие': 'Attendance', 'Награда': 'Reward',
  'Выплата': 'Payout', 'Выплаты': 'Payouts', 'Рассчитать': 'Calculate', 'Пересчитать': 'Recalculate',
  'Профиль игрока': 'Player profile', 'Персонаж': 'Character', 'Оснащение и транспорт': 'Equipment and transport',
  'Только русские или латинские буквы, без пробелов, цифр и специальных символов': 'Russian or Latin letters only; no spaces, numbers, or special characters',
  'Дата регистрации': 'Registration date', 'Последняя активность': 'Last activity', 'Статистика': 'Statistics',
  'Да': 'Yes', 'Нет': 'No', 'Не указано': 'Not specified', 'нет данных': 'no data',
  'только что': 'just now', ' сек. назад': ' sec ago', ' мин. назад': ' min ago',
  'золота': 'gold', 'предметов': 'items', 'позиций': 'entries', 'игроков': 'players',
  'шт.': 'pcs.', 'за единицу': 'per unit', 'Создано': 'Created', 'Обновлено': 'Updated',
  'Успешно': 'Success', 'Ошибка': 'Error', 'Не удалось': 'Failed to', 'Пока ничего нет.': 'Nothing here yet.'
  ,'Аукцион гильдии': 'Guild auction', 'Архив побед': 'Winner archive', 'Добавить лот': 'Add lot',
  'активных лотов': 'active lots', 'Стартовая цена': 'Starting price', 'Цена выкупа': 'Buyout price',
  'Завершение:': 'Ends:', 'Ставок:': 'Bids:', 'Завершён': 'Completed', 'История ставок': 'Bid history',
  'Ставок пока нет.': 'No bids yet.', 'Минимальная ставка': 'Minimum bid', 'Ваша максимальная ставка, жетоны': 'Your maximum bid, tokens',
  'Система автоматически повышает цену только на необходимый шаг. Ваш максимум другим игрокам не показывается.': 'The system raises the price only by the required increment. Your maximum is hidden from other players.',
  'Подтвердить ставку': 'Confirm bid', 'Выберите предмет из справочника и укажите цену': 'Choose an item from the catalog and set its price',
  'Цена за единицу, золото': 'Unit price, gold', 'Выберите предмет': 'Choose item', 'Поиск предмета…': 'Search items…',
  'Полученный дроп': 'Loot received', 'Дроп ещё не добавлен.': 'No loot added yet.',
  'Импорт лута': 'Loot import', 'черновик до подтверждения': 'draft until confirmation',
  'Выберите таблицу лута': 'Choose a loot spreadsheet', 'Участники ещё не отмечены.': 'No participants selected yet.',
  'Событие': 'Event', 'Все события': 'All events', 'С даты': 'From date', 'По дату': 'To date',
  'Создать событие': 'Create event', 'Основные праймы гильдии': 'Guild main primes',
  'Тип': 'Type', 'Рассчитана': 'Calculated', 'Основной прайм': 'Main prime',
  'Игроки, классы и распределение по конст-пати': 'Players, classes, and static-party assignments',
  'Добавить игрока': 'Add player', 'Поиск по никнейму': 'Search by nickname', 'Игроки гильдии': 'Guild players',
  'Посещено праймов': 'Primes attended', 'Выплачено всего': 'Total paid', 'Сольники': 'Solo players',
  'Переименовать': 'Rename', 'Средний ГС': 'Average GS', 'Профиль игрока': 'Player profile',
  'Редактировать профиль': 'Edit profile', 'Праймы · 30 дней': 'Primes · 30 days',
  'Посещение праймов · 30 дней': 'Prime attendance · 30 days', 'Выплачено': 'Paid', 'Ожидается': 'Pending',
  'В наличии': 'Available', 'История начислений': 'Earnings history', 'Последние посещения': 'Recent attendance',
  'Новая платёжная ведомость': 'New payout statement', 'По периоду': 'By period',
  'Конкретные активности': 'Specific activities', 'Период с': 'Period from', 'Период по': 'Period to',
  'К выдаче': 'To pay', 'Баланс до': 'Balance before', 'Баланс после': 'Balance after',
  'Создать ведомость': 'Create statement', 'Подтвердить расчёт': 'Confirm calculation',
  'Зафиксировать прайм': 'Finalize prime', 'КОНТРОЛИРУЕМАЯ ОПЕРАЦИЯ': 'CONTROLLED OPERATION'
}

const entries = Object.entries(translations).sort((a, b) => b[0].length - a[0].length)
const originals = new WeakMap()
let observer

function translate(value) {
  if (locale.value === 'ru' || !value) return value
  return entries.reduce((text, [ru, en]) => text.split(ru).join(en), value)
}

function visit(root, restore = false) {
  const nodes = []
  if (root.nodeType === Node.TEXT_NODE) nodes.push(root)
  else nodes.push(...root.querySelectorAll('*'))
  for (const node of nodes) {
    if (node.nodeType === Node.TEXT_NODE) {
      if (restore) { if (originals.has(node)) node.nodeValue = originals.get(node) }
      else if (/[А-Яа-яЁё]/.test(node.nodeValue)) { originals.set(node, node.nodeValue); node.nodeValue = translate(node.nodeValue) }
      continue
    }
    for (const child of node.childNodes) if (child.nodeType === Node.TEXT_NODE && /[А-Яа-яЁё]/.test(child.nodeValue)) {
      originals.set(child, child.nodeValue)
      if (!restore) child.nodeValue = translate(originals.get(child))
    }
    for (const attr of ['title', 'placeholder', 'aria-label', 'alt']) if (node.hasAttribute?.(attr)) {
      const key = `data-i18n-${attr}`
      if (!node.hasAttribute(key) && /[А-Яа-яЁё]/.test(node.getAttribute(attr))) node.setAttribute(key, node.getAttribute(attr))
      if (node.hasAttribute(key)) node.setAttribute(attr, restore ? node.getAttribute(key) : translate(node.getAttribute(key)))
    }
  }
}

function renderLanguage() {
  if (!document.body) return
  observer?.disconnect()
  visit(document.body, locale.value === 'ru')
  document.documentElement.lang = locale.value
  observer?.observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['title', 'placeholder', 'aria-label', 'alt'] })
}

export function setLocale(value) {
  if (!supported.includes(value) || value === locale.value) return
  locale.value = value
  localStorage.setItem(STORAGE_KEY, value)
  renderLanguage()
  window.dispatchEvent(new CustomEvent('locale-changed', { detail: value }))
}

export function installI18n(app) {
  app.config.globalProperties.$t = translate
  app.config.globalProperties.$locale = readonly(locale)
  observer = new MutationObserver(mutations => {
    observer.disconnect()
    for (const mutation of mutations) {
      if (mutation.type === 'characterData') visit(mutation.target)
      else for (const node of mutation.addedNodes) if (node.nodeType === Node.ELEMENT_NODE || node.nodeType === Node.TEXT_NODE) visit(node)
    }
    observer.observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['title', 'placeholder', 'aria-label', 'alt'] })
  })
  app.mixin({ mounted: renderLanguage, updated: renderLanguage })
  document.documentElement.lang = locale.value
}

export function useLocale() { return { locale: readonly(locale), setLocale, t: translate } }
