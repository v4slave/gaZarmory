import { readonly, ref } from 'vue'
import { createI18n } from 'vue-i18n'
import { bossNames } from './locales/bossNames.js'

const STORAGE_KEY = 'gaz-armory-locale'
const supported = ['ru', 'en']
const initial = supported.includes(localStorage.getItem(STORAGE_KEY))
  ? localStorage.getItem(STORAGE_KEY)
  : (navigator.language?.toLowerCase().startsWith('en') ? 'en' : 'ru')
const locale = ref(initial)

// Russian copy is kept as the legacy lookup key while views are migrated to
// vue-i18n. Replacements are bounded, so a short label such as "Да" can never
// corrupt a longer word such as "Данные".
const translations = {
  'Проверяем авторизацию…': 'Checking authentication…', 'Войти через Discord': 'Sign in with Discord',
  'ПЕРВЫЙ ВХОД': 'FIRST SIGN-IN', 'Заявка отправлена': 'Request sent',
  'Дождитесь подтверждения ГЛ или администратора.': 'Wait for approval from the guild leader or an administrator.',
  'Статус проверяется автоматически — доступ откроется без обновления страницы.': 'The status is checked automatically; access will open without refreshing the page.',
  'Привяжите персонажа': 'Link your character',
  'Выберите персонажа и отправьте заявку. Разделы гильдии откроются после подтверждения.': 'Choose your character and submit a request. Guild sections will become available after approval.',
  'Выбрать персонажа': 'Choose character', 'Привязать игровой профиль': 'Link game profile',
  'Выберите своего персонажа. Заявку проверит ГЛ или администратор.': 'Choose your character. The request will be reviewed by the guild leader or an administrator.',
  'Свободных активных профилей не найдено.': 'No available active profiles found.',
  'Не удалось загрузить список персонажей.': 'Could not load the character list.',
  'Не удалось привязать игровой профиль.': 'Could not link the game profile.',
  'Основное': 'Main', 'Экономика': 'Economy', 'Управление': 'Management', 'Дашборд': 'Dashboard', 'Состав': 'Roster',
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
  'Загрузка…': 'Loading…', 'Загружаем данные…': 'Loading data…', 'Не удалось загрузить данные': 'Could not load data', 'Здесь пока ничего нет': 'Nothing here yet', 'Повторить': 'Retry',
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
  'Да, спиздили. Потому что интерфейс классный и дашборд, а сама идея пришла от армори таргетов': 'Yes, we ripped it off. Because the interface and dashboard are great, while the idea itself came from Armory Targets',
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
  'Все события': 'All events', 'С даты': 'From date', 'По дату': 'To date',
  'Создать событие': 'Create event', 'Основные праймы гильдии': 'Guild main primes',
  'Тип': 'Type', 'Рассчитана': 'Calculated',
  'Игроки, классы и распределение по конст-пати': 'Players, classes, and static-party assignments',
  'Добавить игрока': 'Add player', 'Поиск по никнейму': 'Search by nickname', 'Игроки гильдии': 'Guild players',
  'Посещено праймов': 'Primes attended', 'Выплачено всего': 'Total paid', 'Сольники': 'Solo players',
  'Переименовать': 'Rename',
  'Редактировать профиль': 'Edit profile', 'Праймы · 30 дней': 'Primes · 30 days',
  'Посещение праймов · 30 дней': 'Prime attendance · 30 days', 'Выплачено': 'Paid', 'Ожидается': 'Pending',
  'В наличии': 'Available', 'История начислений': 'Earnings history', 'Последние посещения': 'Recent attendance',
  'Новая платёжная ведомость': 'New payout statement', 'По периоду': 'By period',
  'Конкретные активности': 'Specific activities', 'Период с': 'Period from', 'Период по': 'Period to',
  'К выдаче': 'To pay', 'Баланс до': 'Balance before', 'Баланс после': 'Balance after',
  'Создать ведомость': 'Create statement', 'Подтвердить расчёт': 'Confirm calculation',
  'Зафиксировать прайм': 'Finalize prime', 'КОНТРОЛИРУЕМАЯ ОПЕРАЦИЯ': 'CONTROLLED OPERATION',
  'Для подтверждения введите': 'Type to confirm:', 'Подтвердите действие': 'Confirm action',
  'Введите значение': 'Enter a value', 'Значение': 'Value'
  ,'Новое событие': 'New event', 'Выберите из справочника': 'Choose from catalog', 'Дата и время': 'Date and time',
  'Черновик активности создан.': 'Activity draft created.', 'Не удалось создать событие.': 'Could not create the event.',
  'Событие создано, но страницу не удалось открыть. Обновите журнал активностей.': 'The event was created, but its page could not be opened. Refresh the activity log.',
  'Разделы админки': 'Admin sections', 'Аватар': 'Avatar', 'Закрыть уведомление': 'Dismiss notification',
  'Новый игрок': 'New player', 'Не удалось сохранить игрока.': 'Could not save the player.',
  'Файл не должен превышать 10 МБ.': 'The file must not exceed 10 MB.', 'Обработка заняла слишком много времени. Проверьте таблицу.': 'Processing took too long. Check the spreadsheet.',
  'Не удалось импортировать таблицу.': 'Could not import the spreadsheet.', 'Проверьте значения строки.': 'Check the row values.',
  'Подтвердить импорт? Предметы поступят в казну, а транзакции станут immutable.': 'Confirm import? The items will be added to the treasury and the transactions will become immutable.',
  'Не удалось подтвердить импорт.': 'Could not confirm the import.', 'Подтверждён': 'Confirmed', 'Создать черновик': 'Create draft',
  'Файл:': 'File:', 'Строк:': 'Rows:', 'Стоимость:': 'Value:', 'Строка': 'Row', 'Цена за единицу': 'Unit price',
  'Готово': 'Done', 'Проверьте данные.': 'Review the data.', 'После подтверждения предметы и цены попадут в казну.': 'After confirmation, the items and prices will be added to the treasury.',
  'Подтвердить в казну': 'Confirm to treasury', 'Не удалось загрузить справочник лута.': 'Could not load the loot catalog.',
  'Лут добавлен в активность.': 'Loot added to the activity.', 'Не удалось добавить лут.': 'Could not add loot.',
  'Добавить лут': 'Add loot', 'Предметы не найдены.': 'No items found.', 'Добавление…': 'Adding…',
  'Справочник пуст. Сначала добавьте предметы в разделе администрирования.': 'The catalog is empty. Add items in the admin section first.',
  'GAZ ARMORY · ЖУРНАЛ': 'GAZ ARMORY · LOG', 'GAZ ARMORY · СОБЫТИЕ': 'GAZ ARMORY · ACTIVITY',
  'GAZ ARMORY · ОБЗОР': 'GAZ ARMORY · OVERVIEW', 'GAZ ARMORY · ГИЛЬДИЯ': 'GAZ ARMORY · GUILD',
  'GAZ ARMORY · УПРАВЛЕНИЕ': 'GAZ ARMORY · MANAGEMENT', 'GAZ ARMORY · ФИНАНСОВЫЙ КОНТРОЛЬ': 'GAZ ARMORY · FINANCIAL CONTROL',
  'ЭКОНОМИКА · АУКЦИОН #': 'ECONOMY · AUCTION #', 'Экономика · аукцион': 'Economy · auction',
  'ЭКОНОМИКА · НАХРЮК': 'ECONOMY · PAYOUT', 'ЭКОНОМИКА · ВЕДОМОСТЬ': 'ECONOMY · STATEMENT',
  'ЭКОНОМИКА · КАЗНА': 'ECONOMY · TREASURY', 'ПРОФИЛЬ ИГРОКА': 'PLAYER PROFILE',
  'АДМИНКА': 'ADMIN', 'КОНСТ-ПАТИ': 'STATIC PARTIES', 'КОНСТ-ПАТИ · СОСТАВ': 'STATIC PARTY · ROSTER',
  'участников': 'participants', 'стоимость лута': 'loot value', 'на участника': 'per participant',
  'Нахрюк рассчитан: каждому': 'Payout calculated: each player receives', 'К распределению: оценки лута': 'To distribute: estimated loot value',
  'Участников:': 'Participants:', 'События и единые изображения для истории посещений': 'Activities and shared images for attendance history',
  'Справочник пуст': 'The catalog is empty', 'Действие, сущность или пользователь': 'Action, entity, or user',
  'Дата с': 'Date from', 'Дата по': 'Date to', 'Неизменяемый журнал действий пользователей': 'Immutable user activity log',
  'записей': 'entries', 'Все действия': 'All actions', 'Пользователь': 'User', 'Действие': 'Action', 'Сущность': 'Entity',
  'Состояние авторизации, webhook и внутреннего центра': 'Authentication, webhook, and notification-center status',
  'Проверено': 'Checked', 'Проверить': 'Check', 'Предметы, доступные при заведении дропа': 'Items available when adding loot',
  'Предметов пока нет': 'No items yet', 'Добавьте первый предмет ниже.': 'Add the first item below.',
  'Добавить предмет': 'Add item', 'Редкость': 'Rarity', 'Иконка': 'Icon',
  'Привязка Discord-пользователей к персонажам': 'Link Discord users to characters', 'хочет привязать': 'wants to link',
  'Отклонить': 'Reject', 'Новых заявок нет': 'No new requests', 'Все запросы на привязку обработаны.': 'All link requests have been processed.',
  'Поиск по Discord или персонажу': 'Search by Discord or character', 'Управление аккаунтами и минимально необходимыми правами': 'Account and least-privilege access management',
  'Всего:': 'Total:', 'Профиль не привязан': 'Profile not linked', 'Права доступа': 'Access permissions',
  'Отвязать Discord': 'Unlink Discord', 'Ликвидировать': 'Retire', 'Восстановить': 'Restore', 'Удалить пользователя': 'Delete user',
  'Пользователи не найдены': 'No users found', 'Измените строку поиска.': 'Change the search query.', 'Назад': 'Previous', 'из': 'of', 'Далее': 'Next',
  'ПЕРЕДАЧА РОЛИ': 'ROLE TRANSFER', 'Передать права ГЛ?': 'Transfer guild leader permissions?', 'Новым ГЛ станет': 'The new guild leader will be',
  'После подтверждения роль ГЛ будет снята с вашего аккаунта. Остальные назначенные вам роли сохранятся.': 'After confirmation, the Guild Leader role will be removed from your account. Your other assigned roles will remain.',
  'Передать роль ГЛ': 'Transfer Guild Leader role', 'Период': 'Period', 'Всё время': 'All time', 'Конста': 'Static party',
  'Все праймы': 'All primes', 'Динамика игрока': 'Player trend', 'Страницы истории ставок': 'Bid history pages',
  'шт. · до МСК': 'pcs. · until MSK', 'К списку лотов': 'Back to lots', 'Ставок пока нет': 'No bids yet',
  'Минимум жет. · шаг жет. · автопродление мин.': 'Minimum tokens · step tokens · auto-extension min.',
  'Установить максимум': 'Set maximum', 'Завершён без ставок': 'Completed without bids', 'Лот отменён': 'Lot cancelled',
  'Приём ставок завершён': 'Bidding has ended', 'Отменить': 'Cancel', 'Запустить': 'Start',
  'Минимальный шаг, жетоны': 'Minimum step, tokens', 'Завершение': 'End time',
  'Архив побед и расходов': 'Wins and spending archive', 'побед': 'wins', 'Лот': 'Lot', 'Итог': 'Result',
  '· прайм. · мини': '· primes · mini', 'PL · лидер конст-пати': 'PL · static-party leader',
  'Посещённые праймы и мини-праймы': 'Attended primes and mini-primes', 'В конст-пати пока никого нет': 'No one is in this static party yet',
  'Активных сольников нет': 'No active solo players', 'Переименовать группу': 'Rename group',
  'Введите новое название для « ».': 'Enter a new name for “”.', 'Новое название': 'New name',
  'Поиск по названию…': 'Search by title…', 'Добавьте немного контекста…': 'Add some context…', 'Изображения': 'Images',
  '★ Избранное': '★ Favorites', 'Здесь пока тихо': 'Nothing here yet', 'Добавьте первое видео или изображение.': 'Add the first video or image.',
  'Добавить публикацию': 'Add post', 'Новая публикация': 'New post', 'Добавить в медиатеку': 'Add to media library',
  'Файл с устройства': 'File from device', 'получаем с платформы…': 'fetching from platform…', '(необязательно)': '(optional)',
  'Открыть на ↗': 'Open on ↗', 'Распределите участников по 5 человек.': 'Arrange participants into groups of 5.',
  '+ Добавить группу': '+ Add group', 'Загружаем состав…': 'Loading roster…', 'Создать нахрюк': 'Create payout',
  'Ник': 'Nickname', 'Праймы': 'Primes', 'История ведомостей': 'Statement history', 'Начислений за праймы пока нет.': 'No prime earnings yet.',
  'Посещений праймов пока нет.': 'No prime attendance yet.', 'ГС': 'GS',
  'Навсегда удалить непривязанного персонажа': 'Permanently delete an unlinked character',
  'По выбранным условиям игроков не найдено.': 'No players match the selected filters.',
  'Закрыть информацию о предмете': 'Close item details', 'Доступно:': 'Available:', 'Дропа из активностей пока нет.': 'No activity loot yet.',
  'Подтвердить импорт?': 'Confirm import?', 'Предметы поступят в казну, а созданные транзакции больше нельзя будет изменить.': 'The items will be added to the treasury, and the created transactions can no longer be changed.',
  'Не удалось добавить участников.': 'Could not add participants.', 'Удалить лут из активности?': 'Remove loot from the activity?',
  'Лут удалён из активности и казны.': 'Loot was removed from the activity and treasury.', 'Не удалось удалить лут.': 'Could not remove loot.',
  'Исправить стоимость': 'Correct value', 'Стоимость единицы': 'Unit value', 'Сохранить стоимость': 'Save value',
  'Стоимость должна быть целым неотрицательным числом.': 'The value must be a non-negative integer.',
  'Стоимость предмета исправлена.': 'Item value corrected.', 'Не удалось изменить стоимость.': 'Could not change the value.',
  'Начисления отменены. Исправьте данные и выполните расчёт заново.': 'Earnings were cancelled. Correct the data and calculate again.',
  'Не удалось открыть активность для исправления.': 'Could not open the activity for correction.',
  'Нахрюк рассчитан, начисления сформированы.': 'Payout calculated and earnings created.', 'Не удалось рассчитать нахрюк.': 'Could not calculate the payout.',
  'Не удалось сохранить черновик.': 'Could not save the draft.', 'Удалить черновик активности?': 'Delete the activity draft?',
  'Черновик будет удалён. Активность с лутом удалить нельзя.': 'The draft will be deleted. An activity with used loot cannot be deleted.',
  'Черновик с лутом удалить нельзя.': 'A draft with used loot cannot be deleted.', 'Добавлено участников: .': 'Participants added: .',
  '« » будет удалён, остаток в казне уменьшится на шт.': '“ ” will be removed, and treasury stock will decrease by pcs.',
  'Укажите новую стоимость одной единицы « ».': 'Enter a new unit value for “ ”.',
  'Зафиксировать прайм: участников получат по . Всего начислено , остаток оценки . Золотой баланс казны не изменится.': 'Finalize the prime: participants will receive each. Total earnings , undistributed estimate . The treasury gold balance will not change.',
  'Не удалось загрузить активности.': 'Could not load activities.', 'Не удалось загрузить изображение.': 'Could not upload the image.',
  'Удалить изображение?': 'Delete the image?', 'Все активности « » останутся без картинки.': 'All “ ” activities will be left without an image.',
  'БЛИЖАЙШИЕ СОБЫТИЯ': 'UPCOMING EVENTS', 'БЛИЖАЙШЕЕ СОБЫТИЕ': 'NEXT EVENT', 'Ближайших событий нет.': 'No upcoming events.', 'Неделя гильдии': 'Guild week', 'Расписание': 'Schedule', 'Экспериментальный интерфейс': 'Experimental interface', 'Не удалось загрузить дашборд.': 'Failed to load dashboard.',
  'Не удалось загрузить аудит.': 'Could not load the audit log.', 'Не удалось загрузить настройки.': 'Could not load settings.',
  'Не удалось обновить стоимость жетона.': 'Could not update the token value.', 'Не удалось проверить интеграции.': 'Could not check integrations.',
  'Не удалось загрузить лут.': 'Could not load loot.', 'Не удалось добавить предмет.': 'Could not add the item.',
  'Не удалось изменить редкость.': 'Could not change the rarity.',
  'Discord-аккаунты, персонажи и права доступа': 'Discord accounts, characters, and access permissions',
  'Подтверждение привязки персонажей': 'Character link approvals', 'Справочник событий и изображения': 'Activity catalog and images',
  'Предметы и их иконки': 'Items and their icons', 'Журнал действий и изменений': 'Action and change log',
  'Стоимость жетона и финансовая конфигурация': 'Token value and financial configuration',
  'Состояние интеграций и доставки': 'Integration and delivery status', 'Не удалось загрузить заявки.': 'Could not load requests.',
  'Не удалось обработать заявку.': 'Could not process the request.', 'Не удалось загрузить пользователей.': 'Could not load users.',
  'Не удалось изменить права.': 'Could not change permissions.', 'Отвязать': 'Unlink',
  'Ликвидировать персонажа?': 'Retire the character?', 'Восстановить персонажа?': 'Restore the character?',
  'Персонаж « » и вся история сохранятся.': 'The character “ ” and all history will be preserved.',
  '« » исчезнет из активного состава, история сохранится.': '“ ” will be removed from the active roster while history is preserved.',
  '« » вернётся в активный состав.': '“ ” will return to the active roster.',
  'Discord-пользователь @ будет удалён, персонаж и история сохранятся.': 'Discord user @ will be deleted while the character and history are preserved.',
  'Не удалось загрузить аналитику посещаемости.': 'Could not load attendance analytics.', 'Не удалось сформировать экспорт.': 'Could not generate the export.',
  'Не удалось загрузить лот.': 'Could not load the lot.', 'Ставка принята.': 'Bid accepted.', 'Ставка отклонена.': 'Bid rejected.',
  'Аукцион завершён.': 'Auction completed.', 'Не удалось завершить аукцион.': 'Could not complete the auction.',
  'Отменить аукцион?': 'Cancel the auction?', 'Лот будет закрыт, а предмет вернётся в свободный остаток.': 'The lot will be closed and the item returned to available stock.',
  'Отменить лот': 'Cancel lot', 'Лот отменён, предмет возвращён в казну.': 'Lot cancelled and item returned to the treasury.',
  'Не удалось отменить лот.': 'Could not cancel the lot.', 'Активен': 'Active', 'Отменён': 'Cancelled',
  'Участники могут делать ставки до указанного времени': 'Participants can bid until the specified time',
  'Лот виден только управляющим и ещё не принимает ставки': 'The lot is visible only to managers and is not accepting bids yet',
  'Победитель определён, предмет списан, эквивалент жетонов зачислен в казну': 'The winner is determined, the item is written off, and the token equivalent is credited to the treasury',
  'Лот отменён, резерв предмета освобождён': 'The lot is cancelled and the item reservation is released',
  'Не удалось сохранить лот.': 'Could not save the lot.', 'Не удалось запустить лот.': 'Could not start the lot.',
  'Не удалось загрузить аукцион.': 'Could not load the auction.', 'Не удалось загрузить историю ставок.': 'Could not load bid history.',
  'Не удалось сделать ставку.': 'Could not place the bid.',
  'Баланс по движениям': 'Movement balance', 'Текущий баланс': 'Current balance', 'Транзакций': 'Transactions',
  'Предметов': 'Items', 'Движений': 'Movements', 'Нахрюков': 'Payouts', 'Начислений': 'Earnings',
  'Предметов в резерве': 'Reserved items', 'Не удалось выполнить финансовую сверку.': 'Could not run financial reconciliation.',
  'Удалить конст-пати?': 'Delete the static party?', 'Игроки из « » станут одиночками.': 'Players from “ ” will become solo players.',
  'Не удалось загрузить медиатеку.': 'Could not load the media library.', 'Не удалось добавить публикацию.': 'Could not add the post.',
  'Удалить публикацию?': 'Delete the post?', '« » исчезнет из раздела контента.': '“ ” will be removed from the content section.',
  'Не удалось загрузить состав пятёрок.': 'Could not load the squad roster.', 'Не удалось создать группу.': 'Could not create the group.',
  'Не удалось переименовать группу.': 'Could not rename the group.', 'Удалить группу?': 'Delete the group?',
  'Не удалось изменить группу.': 'Could not change the group.', 'Игроки из « » останутся в КП без распределения.': 'Players from “ ” will remain in the static party without a squad assignment.',
  'Выплачен': 'Paid', 'Период создан, начисления ещё не зафиксированы': 'The period is created; earnings are not fixed yet',
  'Состав и суммы зафиксированы, золото ещё не списано': 'Roster and amounts are fixed; gold has not been withdrawn yet',
  'Золото списано из казны, начисления закрыты': 'Gold was withdrawn from the treasury and earnings were closed',
  'Выплата отменена, начисления освобождены': 'The payout was cancelled and earnings released',
  'Подтвердить выплату?': 'Confirm payment?', 'Золото будет отмечено фактически выданным всем ожидающим участникам.': 'Gold will be marked as actually issued to all pending participants.',
  'Отменить выплату?': 'Cancel the payout?', 'Связанные начисления снова станут доступны для будущей ведомости.': 'Linked earnings will become available for a future statement again.',
  'Отменить выплату': 'Cancel payout', 'Рассчитать выплату?': 'Calculate the payout?',
  'Состав и суммы ведомости будут зафиксированы.': 'The statement roster and amounts will be fixed.',
  'Черновик ведомости будет удалён.': 'The statement draft will be deleted.', 'Не удалось удалить черновик.': 'Could not delete the draft.',
  'Выдача не отмечена.': 'Payment was not recorded.', 'Золото будет отмечено фактически выданным выбранным игрокам: .': 'Gold will be marked as actually issued to the selected players: .',
  'Не удалось загрузить начисления.': 'Could not load earnings.', 'Не удалось загрузить историю выплат.': 'Could not load payout history.',
  'Не удалось рассчитать предварительную выплату.': 'Could not calculate the payout preview.',
  'Фуксория': 'Fuchsia', 'Махаон': 'Swallowtail', 'Таре': 'Tape',
  'Не удалось загрузить профиль.': 'Could not load the profile.', 'Не удалось сохранить профиль.': 'Could not save the profile.',
  'игрок': 'player', 'игрока': 'players', 'Удалить навсегда': 'Delete permanently', 'Не удалось удалить персонажа.': 'Could not delete the character.',
  'Персонаж « » будет удалён без возможности восстановления. Исторические записи блокируют удаление автоматически.': 'The character “ ” will be permanently deleted. Historical records automatically prevent deletion.',
  'Персонаж « » удалён.': 'Character “ ” deleted.', 'Не удалось загрузить готовность состава.': 'Could not load roster readiness.',
  'Поступление лута': 'Loot income', 'Выдача игроку': 'Issued to player', 'Резерв аукциона': 'Auction reservation',
  'Снятие резерва': 'Reservation released', 'Продажа на аукционе': 'Auction sale', 'Ручная продажа': 'Manual sale',
  'Корректировка': 'Adjustment', 'Выплата нахрюка': 'Payout payment', 'Продажа вне гильдии / пополнение': 'External sale / deposit',
  'Ручной расход': 'Manual expense', 'Не удалось загрузить казну.': 'Could not load the treasury.',
  'Продажа проведена, золото зачислено в казну.': 'Sale completed and gold credited to the treasury.',
  'Не удалось провести продажу.': 'Could not complete the sale.', 'Золото добавлено в казну.': 'Gold added to the treasury.',
  'Золото списано из казны.': 'Gold withdrawn from the treasury.', 'Не удалось провести операцию.': 'Could not complete the operation.',
  'Не удалось загрузить список получателей.': 'Could not load the recipient list.', 'Предмет выдан игроку.': 'Item issued to the player.',
  'Не удалось выдать предмет.': 'Could not issue the item.',
  'ОШИБКА 403': 'ERROR 403', 'Недостаточно прав': 'Insufficient permissions',
  'Этот раздел недоступен для вашей роли. Если доступ действительно нужен, обратитесь к ГЛ.': 'This section is unavailable for your role. Contact the guild leader if you need access.',
  'ОШИБКА 404': 'ERROR 404', 'Возможно, ссылка устарела или объект был удалён.': 'The link may be outdated or the item may have been deleted.',
  'Открыть меню': 'Open menu', 'Закрыть меню': 'Close menu', 'Язык интерфейса': 'Interface language',
  'Хомяк GAZ ARMORY': 'GAZ ARMORY hamster', 'Войдите через Discord, чтобы загрузить состав.': 'Sign in with Discord to load the roster.',
  'Backend недоступен. Запустите Laravel API.': 'Backend is unavailable. Start the Laravel API.', 'Backend недоступен.': 'Backend is unavailable.',
  'Не удалось загрузить конст-пати.': 'Could not load static parties.', 'Не удалось загрузить справочник событий.': 'Could not load the event catalog.',
  'Не удалось загрузить активность.': 'Could not load the activity.', 'Операция не выполнена.': 'Operation failed.',
  'Сервер не успел ответить. Попробуйте ещё раз.': 'The server timed out. Try again.', 'Нет соединения с сервером. Проверьте, запущен ли backend.': 'Cannot connect to the server. Check that the backend is running.',
  'Все активные игроки уже добавлены.': 'All active players have already been added.',
  'Цена': 'Price', 'Удалить лут из активности и уменьшить остаток казны': 'Remove loot from the activity and reduce the treasury balance',
  'Исправить завершённую активность': 'Correct completed activity', 'Причина исправления': 'Reason for correction',
  'Например: ошибочно отмечен участник или неверно указана стоимость предмета': 'For example: an incorrect participant or item price',
  'Причина и ваш аккаунт сохранятся в журнале аудита. После выплаты операция запрещена.': 'The reason and your account will be saved in the audit log. This action is unavailable after payout.',
  'Pending-начисления будут удалены, а активность снова станет доступна для редактирования. После исправления участников или стоимости выполните расчёт повторно.': 'Pending earnings will be removed and the activity will become editable again. Recalculate after correcting participants or item values.',
  'Отмена начислений…': 'Reverting earnings…', 'Отменить начисления и исправить': 'Revert earnings and correct',
  'Загрузка выплаты…': 'Loading payout…', 'Продолжить и рассчитать': 'Continue and calculate', 'Удалить черновик': 'Delete draft',
  'Выдать всем ожидающим': 'Pay all pending', 'Общая сумма / жетоны': 'Total amount / tokens', 'Создал': 'Created by',
  'Рассчитан': 'Calculated', 'Платёжная ведомость': 'Payout statement', 'Фильтры применяются и к экспорту': 'Filters also apply to exports',
  'Все статусы': 'All statuses', 'Ожидает': 'Pending', 'Выбрать ожидающих': 'Select pending', 'Снять выбор': 'Clear selection',
  'Выбрано:': 'Selected:', 'Отметить фактически выданными': 'Mark as paid', 'Посещение праймов': 'Prime attendance',
  'Участники не найдены.': 'No participants found.', 'Включённые праймы': 'Included primes', 'Праймы появятся после расчёта.': 'Primes will appear after calculation.',
  'Завершённая выплата защищена от изменений.': 'Completed payouts are protected from changes.', 'Вернуться к выплатам': 'Back to payouts',
  'Не удалось загрузить выплату.': 'Could not load the payout.', 'У вас нет доступа к этой выплате.': 'You do not have access to this payout.',
  'Ожидаемые начисления': 'Pending earnings', 'Текущий незакрытый период': 'Current open period', 'Поиск по нику…': 'Search by nickname…',
  '% посещения': 'Attendance %', 'Общая сумма': 'Total amount', 'Начислений пока нет.': 'No earnings yet.',
  'Все свободные начисления за даты': 'All available earnings within the date range', 'Выберите нужные праймы вручную': 'Select the required primes manually',
  'Рассчитываем выплату…': 'Calculating payout…', 'Активностей': 'Activities', 'Игроков': 'Players',
  'В казне недостаточно реального золота.': 'There is not enough actual gold in the treasury.', 'В выборке нет свободных начислений.': 'There are no available earnings in the selection.',
  'Создание фиксирует ведомость, но не списывает золото. Фактическая выдача отмечается на следующем экране.': 'Creating the statement locks it but does not withdraw gold. Actual payments are recorded on the next screen.',
  'Создание…': 'Creating…', 'Ведомость создана. Подтвердите фактическую выдачу.': 'Statement created. Confirm the actual payment.',
  'Не удалось создать ведомость.': 'Could not create the statement.', 'Начисления за фактически посещённые праймы': 'Earnings for primes actually attended',
  'На расплит / жетоны': 'For distribution / tokens', 'Поиск по никнейму…': 'Search by nickname…',
  'Переименовать конст-пати': 'Rename static party', 'Новое название конст-пати': 'New static-party name',
  'Создать конст-пати': 'Create static party', 'Название новой конст-пати': 'New static-party name',
  'Фактическая сумма продажи, золото': 'Actual sale amount, gold',
  'Будет списано': 'Will be removed:', 'и зачислено': 'and credited:', 'Подтвердить продажу': 'Confirm sale',
  'Выберите игрока': 'Choose player', 'Исходная активность': 'Source activity',
  'Без привязки к активности': 'No linked activity', 'Причина / комментарий': 'Reason / comment', 'За что выдан предмет': 'Reason for issuing the item',
  'Операция сохранится в истории и уменьшит остаток на': 'The operation will be saved in history and reduce the balance by', 'Подтвердить выдачу': 'Confirm issue',
  'Сумма, золото': 'Amount, gold', 'Основание операции': 'Transaction reason', 'Например: взнос участника или покупка расходников': 'For example: member contribution or consumables purchase',
  'Текущий баланс:': 'Current balance:', 'Операция сохранится в финансовой истории.': 'The operation will be saved in financial history.',
  'золото': 'gold', 'Загрузка': 'Loading', 'Контент': 'Content',
  'Загружаем журнал активностей…': 'Loading activity log…', 'Созданные праймы появятся в этом журнале.': 'Created primes will appear in this log.',
  'Убрать участника?': 'Remove participant?', 'будет исключён из этой активности.': 'will be removed from this activity.', 'Убрать': 'Remove',
  'до': 'before', 'после': 'after', 'Стоимость жетона обновлена.': 'Token value updated.', 'Обновить': 'Refresh',
  'Стоимость жетона': 'Token value', 'Изменено:': 'Updated:', 'Стоимость одного жетона, золото': 'Value of one token, gold',
  'Изменение цены не двигает золото и не создаёт финансовую транзакцию — меняется только эквивалент в жетонах. Уже запущенные аукционы сохраняют зафиксированный при старте курс.': 'Changing the rate does not move gold or create a financial transaction; it only changes the token equivalent. Active auctions keep the rate recorded when they started.',
  'Обычный': 'Common', 'Необычный': 'Uncommon', 'Редкий': 'Rare', 'Уникальный': 'Unique', 'Эпический': 'Epic', 'Легендарный': 'Legendary',
  'Реликвия': 'Relic', 'Эпоха чудес': 'Wonders', 'Эпоха сказаний': 'Tales', 'Эпоха легенд': 'Legends', 'Эпоха мифов': 'Mythic', 'Эпоха Двенадцати': 'Eternal',
  'Убрать предмет из справочника?': 'Remove item from catalog?', 'больше нельзя будет выбрать для нового лута. Остатки и история сохранятся.': 'will no longer be available for new loot. Existing inventory and history will remain.',
  'ГЛ': 'Guild leader', 'Микро-ГЛ': 'Micro guild leader', 'Разработчик': 'Developer', 'Участник': 'Member',
  'У пользователя должна остаться хотя бы одна роль.': 'The user must keep at least one role.', 'Никогда': 'Never', 'нед.': 'wk',
  'GAZ ARMORY · РУКОВОДИТЕЛЯМ': 'GAZ ARMORY · MANAGEMENT', 'Аналитика посещаемости': 'Attendance analytics',
  'Готовим…': 'Preparing…', 'Экспорт CSV': 'Export CSV', 'Экспорт XLSX': 'Export XLSX',
  'Собираем статистику посещаемости…': 'Compiling attendance statistics…', 'Динамика посещаемости игрока': 'Player attendance trend',
  'Доля посещённых праймов по дням или неделям': 'Share of attended primes by day or week',
  'Для выбранного игрока пока нет доступных праймов.': 'No eligible primes are available for the selected player yet.',
  'Подвести итог': 'Finalize', 'Сервер не ответил. Проверьте соединение и попробуйте снова.': 'The server did not respond. Check your connection and try again.',
  'Загружаем аукционы…': 'Loading auctions…', 'Активных лотов нет': 'No active lots', 'Новые и недавно завершённые лоты появятся здесь.': 'New and recently completed lots will appear here.',
  'Текущая цена': 'Current price', 'До конца:': 'Time left:', 'Лидер': 'Leader', 'лидер': 'leader', 'авто': 'auto',
  'Страницы аукционов': 'Auction pages', 'Автопродление': 'Auto-extension', 'На 2–5 минут при ставке перед закрытием': 'By 2–5 minutes when a bid is placed near closing.',
  'Расхождений нет': 'No discrepancies', 'Требует внимания': 'Needs attention', 'Критическое расхождение': 'Critical discrepancy',
  'Проверяем журналы движений, остатки и связанные операции…': 'Checking ledgers, balances, and linked transactions…',
  'Результат сверки': 'Reconciliation result', 'Проверено:': 'Checked:', 'пройдено': 'passed', 'замечаний': 'issues', 'критических': 'critical',
  'Открыть →': 'Open →', 'Проверка пройдена, расхождений не найдено': 'Check passed; no discrepancies found',
  'Загружаем конст-пати…': 'Loading static parties…', 'Ссылка': 'Link', 'Файл': 'File', 'Новые': 'Newest', 'Популярные': 'Popular',
  'Загружаем медиатеку…': 'Loading media library…', 'Видео': 'Video', 'Изображение': 'Image',
  'Ссылка на видео или изображение': 'Video or image URL', 'Выбрать изображение': 'Choose image', 'До 20 МБ · JPG, PNG, GIF или WebP': 'Up to 20 MB · JPG, PNG, GIF, or WebP',
  'Загружаем ожидаемые начисления…': 'Loading pending earnings…', 'Начислений пока нет': 'No earnings yet', 'Свободные начисления появятся после расчёта праймов.': 'Available earnings will appear after primes are calculated.',
  'Загружаем историю ведомостей…': 'Loading statement history…', 'Ведомостей пока нет': 'No statements yet', 'Созданные ведомости выплат появятся здесь.': 'Created payout statements will appear here.',
  'Страницы истории выплат': 'Payout history pages', 'Изменений пока нет': 'No changes yet', 'с прошлого обновления': 'since the previous update',
  'Профиль персонажа сохранён.': 'Character profile saved.', 'Безвозвратно удалить персонажа?': 'Permanently delete character?', 'Страницы состава': 'Roster pages',
  'Загружаем готовность состава…': 'Loading roster readiness…', 'Раздел подготовлен для следующего этапа реализации.': 'This section is ready for the next implementation stage.',
  'Продажа предмета вне аукциона': 'Item sold outside the auction', 'Пятёрки КП': 'Party squads',
  'Сессия завершена. Войдите снова.': 'Your session has ended. Sign in again.', 'У вас недостаточно прав для этой операции.': 'You do not have permission for this operation.',
  'Данные уже изменились. Обновите страницу и повторите действие.': 'The data has already changed. Refresh the page and try again.',
  'Слишком много запросов. Подождите немного и повторите действие.': 'Too many requests. Wait a moment and try again.',
  'Сервер временно не может выполнить операцию. Попробуйте позже.': 'The server is temporarily unable to complete the operation. Try again later.'
}

Object.assign(translations, bossNames)

const vueI18n = createI18n({
  legacy: false,
  locale: initial,
  fallbackLocale: 'ru',
  messages: { ru: {}, en: translations },
  missingWarn: false,
  fallbackWarn: false,
})

const entries = Object.entries(translations).sort((a, b) => b[0].length - a[0].length)
const phraseUnsafeKeys = new Set(['Не удалось'])

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

const phraseEntries = entries
  .filter(([source]) => !phraseUnsafeKeys.has(source) && source.trim().length > 1)
  .map(([source, target]) => {
    const trimmed = source.trim()
    const leftBoundary = /^[\p{L}\p{N}]/u.test(trimmed) ? '(?<![\\p{L}\\p{N}])' : ''
    const rightBoundary = /[\p{L}\p{N}]$/u.test(trimmed) ? '(?![\\p{L}\\p{N}])' : ''
    return [new RegExp(`${leftBoundary}${escapeRegExp(trimmed)}${rightBoundary}`, 'gu'), target]
  })
const originals = new WeakMap()
let observer

export function translate(value) {
  if (locale.value === 'ru' || !value) return value
  return translateToEnglish(value)
}

function translateToEnglish(value) {
  const leading = value.match(/^\s*/)?.[0] ?? ''
  const trailing = value.match(/\s*$/)?.[0] ?? ''
  const source = value.trim()
  if (Object.hasOwn(translations, source)) return `${leading}${translations[source]}${trailing}`

  // Vue joins static labels and interpolated values into one text node. Known
  // phrases inside such nodes are translated only at Unicode word boundaries.
  return phraseEntries.reduce(
    (text, [pattern, target]) => text.replace(pattern, target),
    value,
  )
}

export function hasEnglishTranslation(value) {
  return typeof value !== 'string' || !/[А-Яа-яЁё]/.test(translateToEnglish(value))
}

export function translateExact(value) {
  if (locale.value === 'ru' || typeof value !== 'string' || !value) return value
  const leading = value.match(/^\s*/)?.[0] ?? ''
  const trailing = value.match(/\s*$/)?.[0] ?? ''
  const source = value.trim()
  return Object.hasOwn(translations, source) ? `${leading}${translations[source]}${trailing}` : value
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
  vueI18n.global.locale.value = value
  localStorage.setItem(STORAGE_KEY, value)
  renderLanguage()
  window.dispatchEvent(new CustomEvent('locale-changed', { detail: value }))
}

export function installI18n(app) {
  app.use(vueI18n)
  app.config.globalProperties.$legacyT = translate
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

export function getLocale() { return locale.value }
export function useLocale() { return { locale: readonly(locale), setLocale, t: translate } }
