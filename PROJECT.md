# ArcheAge Guild Management System

## 1. Назначение

Внутренняя веб-система управления гильдией **ArcheAge**.

Основные задачи: - состав гильдии и распределение игроков по группам; -
профили игроков; - учет праймов, активностей и мини-активностей; - учет
посещаемости; - учет лута с боссов; - казна и инвентарь гильдии; -
выдача редкого дропа; - внутренний аукцион со ставками в золоте; -
автоматический расчет выплат за посещенные праймы; - история выплат; -
Discord OAuth2; - Discord API/бот и уведомления; - audit log.

Проект создается исключительно для ArcheAge.

## 2. Стек

### Backend

-   PHP 8.3+
-   Laravel
-   REST API
-   Laravel Sanctum / session authentication
-   Laravel Socialite + Discord OAuth2 provider
-   Laravel Policies / Gates
-   Laravel Events / Listeners / Queue
-   Redis при необходимости

### Frontend

-   JavaScript
-   Vue 3
-   Vue Router
-   Pinia
-   Axios
-   Vite

### Database

-   PostgreSQL

## 3. Архитектурные принципы

Backend --- единственный источник истины для игроков, групп,
активностей, посещаемости, лута, казны, аукционов, ставок, выплат и
ролей.

Критические финансовые расчеты выполняются только backend.

Все финансовые операции имеют историю. Завершенные финансовые snapshots
immutable. Административные изменения попадают в audit log.

## 4. Discord Authentication

Основной способ входа --- Discord OAuth2. Публичная регистрация
email/password в MVP не нужна.

Flow:

``` text
Войти через Discord
→ Discord OAuth2
→ Laravel callback
→ Discord User ID
→ поиск/создание User
→ проверка привязки к Player
→ Laravel session
→ Vue GET /api/me
```

Использовать Discord User ID, а не username, как внешний уникальный
идентификатор.

`users`:

``` text
id
discord_id UNIQUE
discord_username
discord_display_name nullable
discord_avatar nullable
role
created_at
updated_at
```

Player и User --- разные сущности. Игрок может существовать без
аккаунта:

``` text
players.user_id = NULL
```

Guild Leader или Officer связывает Discord User с существующим Player.

Endpoints:

``` text
GET  /auth/discord
GET  /auth/discord/callback
GET  /api/me
POST /api/logout
```

В будущем можно проверять членство на Discord-сервере гильдии.

## 5. Роли

``` text
guild_leader
officer
member
```

**Guild Leader:** полный доступ, включая роли, настройки и audit log.

**Officer:** игроки, группы, активности, посещаемость, лут, казна,
аукционы и выплаты. Не может назначать Guild Leader.

**Member:** просмотр состава/профилей/активностей, разрешенной
информации казны, аукционов, собственных выплат; может делать ставки.

Permissions всегда проверяются Laravel Policies/Gates.

## 6. Классы ArcheAge

``` text
melee  → Милик
archer → Лук
mage   → Маг
healer → Хил
bard   → Бард
tank   → Танк
```

## 7. Типы событий

``` text
prime
activity
mini_activity
```

**Prime** --- финансовая активность. Отмечаются участники, фиксируется
стоимость дропа в золоте, рассчитывается доля каждого участника и
создается ожидаемая выплата.

**Activity** --- учет события, посещаемости и редкого лута. Само
посещение не создает денежное начисление.

**Mini Activity** --- дополнительная активность для статистики/учета.
Само посещение не создает денежное начисление.

## 8. Навигация

``` text
/dashboard
/roster
/groups
/players/{id}
/activities
/activities/{id}
/treasury
/auctions
/auctions/{id}
/payouts
/payouts/{id}
/admin
```

## 9. Состав гильдии

`/roster`

Фильтры: Все, Милик, Лук, Маг, Хил, Бард, Танк, поиск по nickname.

Таблица:

  --------------------------------------------------------------------------------
  Никнейм    Класс         Активности   Мини-активности Последняя        Выплачено
                                                        активность    всего золота
  ---------- ---------- ------------- ----------------- ------------ -------------

  --------------------------------------------------------------------------------

Server-side sorting/filtering/pagination.

## 10. Группы

Игрок находится максимум в одной группе. Если `group_id = NULL`, он
автоматически отображается в разделе **Одиночки**. Отдельную группу Solo
в БД не создавать.

Guild Leader/Officer могут создавать, переименовывать и удалять группы,
перемещать игроков. При удалении группы игроки становятся одиночками.

## 11. Player

``` text
id
user_id nullable
group_id nullable
nickname UNIQUE
class
is_active
created_at
updated_at
```

Профиль: - никнейм; - класс; - количество посещенных праймов; -
количество мини-активностей; - общее посещение; - посещение праймов %; -
выплачено золота; - ожидаемая выплата; - последние посещения.

Агрегаты рассчитываются из первичных данных либо безопасно кешируются.

## 12. Посещаемость

Prime attendance:

``` text
visited_primes / total_primes_in_period * 100
```

Процент посещаемости --- статистика и **не используется как множитель
выплаты**.

Денежная выплата определяется только фактическим присутствием на
конкретном прайме.

## 13. Справочник событий

``` text
activity_definitions
id
name
type
is_active
created_at
updated_at
```

`type`: `prime`, `activity`, `mini_activity`.

## 14. Журнал активности

`/activities`

Фильтры: - дата с/по; - статус к выплате/выплачено; - вид; - конкретный
босс/событие; - nickname игрока.

Таблица:

  Дата   Название   Тип     Игроков Статус выплаты
  ------ ---------- ----- --------- ----------------

Для нефинансовых событий UI не должен показывать ложный payout status.

## 15. Участники

``` text
activity_players
id
activity_id
player_id
created_at
```

Constraint:

``` text
UNIQUE(activity_id, player_id)
```

## 16. Лут

``` text
activity_loot
id
activity_id
item_name
quantity
unit_price
created_by
created_at
updated_at
```

``` text
total_price = quantity * unit_price
```

Лут может поступать в инвентарь казны.

## 17. Ключевая формула прайма

Игрок получает выплату **только за посещенный прайм**.

Каждый прайм рассчитывается независимо:

``` text
raw_player_share = prime.gold_value / participants_count
player_share = floor(raw_player_share)
```

Пример:

``` text
gold_value = 20 000
participants_count = 60
raw_player_share = 333.333...
player_share = 333
distributed = 19 980
remainder = 20
```

Остаток остается в казне. Нельзя распределить больше `gold_value`.

Игрок, которого нет в `activity_players`, получает 0.

Ожидаемая выплата:

``` text
expected_payout =
SUM(player_share всех невыплаченных праймов,
    посещенных игроком)
```

## 18. Snapshot начислений прайма

``` text
prime_player_earnings
id
activity_id
player_id
nickname_snapshot
prime_gold_value_snapshot
participants_count_snapshot
player_share
status
payout_id nullable
created_at
```

Статусы:

``` text
pending
paid
cancelled
```

После выплаты исторический `player_share` immutable.

## 19. Выплаты

`/payouts`

Выплата объединяет невыплаченные начисления с праймов.

Показывать: - золото на расплит / общую сумму; - количество включенных
праймов; - участников; - период; - статус.

Таблица:

  --------------------------------------------------------------------------
  Ник              Посещение         Праймы   Мини-активности    Общая сумма
                   праймов %                                         выплаты
  ----------- -------------- -------------- ----------------- --------------

  --------------------------------------------------------------------------

Сумма игрока:

``` text
SUM(pending prime_player_earnings.player_share)
```

После завершения начисления получают `paid`.

`payout_players`:

``` text
id
payout_id
player_id
nickname_snapshot
prime_attendance_percentage_snapshot
primes_count
mini_activities_count
amount
status
paid_at
created_at
updated_at
```

Payout statuses:

``` text
draft
calculated
paid
cancelled
```

## 20. Казна

`/treasury`

Показывает: - общее золото; - стоимость инвентаря в эквиваленте
золота; - инвентарь; - последний дроп; - историю выдачи предметов; -
финансовые транзакции.

Золото и оценочную стоимость предметов отображать отдельно.

## 21. Выдача редкого дропа

Activity используется Guild Leader/Officer для учета редкого дропа.

При выдаче хранить: - дату; - предмет; - количество; - получателя; - кто
выдал; - исходную активность; - причину/комментарий.

Выдача уменьшает инвентарь и создает immutable item transaction.

## 22. Treasury Transactions

Типы:

``` text
prime_income
auction_income
payout
manual_income
manual_expense
adjustment
```

``` text
id
type
amount
description
related_entity_type
related_entity_id
created_by
created_at
```

Для золота не использовать float/double. Если золото целое --- BIGINT.

## 23. Аукцион

Guild Leader/Officer выставляет предмет из казны.

Лот: - предмет; - количество; - стартовая ставка; - минимальный шаг; -
дата/время окончания.

Статусы:

``` text
draft
active
finished
cancelled
```

Ставки:

``` text
auction_bids
id
auction_id
player_id
amount
created_at
```

История ставок immutable. Проверка ставок и победителя выполняется
backend. Использовать DB transaction/locking против race conditions.

При завершении в одной транзакции: 1. определить победителя; 2. списать
предмет; 3. создать item transaction; 4. добавить золото; 5. создать
treasury transaction; 6. завершить auction; 7. записать audit log.

## 24. Dashboard

Показывает агрегированные данные: - игроков; - групп; - одиночек; -
праймов; - активностей; - мини-активностей; - золота; - стоимости
инвентаря; - ожидающих выплат; - активных аукционов; - последних
событий/лута/выплат.

## 25. Предлагаемая схема БД

``` text
users
players
groups

activity_definitions
activities
activity_players
activity_loot
prime_player_earnings

treasury_items
treasury_item_transactions
treasury_transactions

auctions
auction_bids

payouts
payout_activities
payout_players

notifications
audit_logs
```

## 26. REST API

### Players / Groups

``` text
GET    /api/players
POST   /api/players
GET    /api/players/{id}
PUT    /api/players/{id}
DELETE /api/players/{id}
PUT    /api/players/{id}/group

GET    /api/groups
POST   /api/groups
PUT    /api/groups/{id}
DELETE /api/groups/{id}
```

### Activities

``` text
GET    /api/activities
POST   /api/activities
GET    /api/activities/{id}
PUT    /api/activities/{id}
DELETE /api/activities/{id}

POST   /api/activities/{id}/players
DELETE /api/activities/{id}/players/{playerId}

POST   /api/activities/{id}/loot
PUT    /api/activities/{id}/loot/{lootId}
DELETE /api/activities/{id}/loot/{lootId}

POST   /api/activities/{id}/calculate-prime
```

### Treasury

``` text
GET  /api/treasury
GET  /api/treasury/items
GET  /api/treasury/transactions
POST /api/treasury/transactions
POST /api/treasury/items/{id}/issue
```

### Auctions

``` text
GET  /api/auctions
POST /api/auctions
GET  /api/auctions/{id}
POST /api/auctions/{id}/start
POST /api/auctions/{id}/bid
POST /api/auctions/{id}/finish
POST /api/auctions/{id}/cancel
```

### Payouts

``` text
GET  /api/payouts
POST /api/payouts
GET  /api/payouts/{id}
POST /api/payouts/{id}/calculate
POST /api/payouts/{id}/complete
POST /api/payouts/{id}/cancel
```

## 27. Discord Integration

Отдельный `DiscordService`.

``` text
Domain Event
→ Listener
→ Queue
→ DiscordService
```

Уведомления: - создан/завершен прайм; - получен/выдан важный лут; -
создан аукцион; - новая максимальная ставка; - завершен аукцион; -
рассчитаны/завершены выплаты; - важные изменения казны.

Будущие bot-команды:

``` text
/player
/attendance
/auction
/balance
/treasury
```

## 28. Audit Log

``` text
audit_logs
id
user_id
action
entity_type
entity_id
old_values
new_values
ip_address
created_at
```

Логировать изменения игроков, групп, посещаемости, лута, казны, выдач,
аукционов, выплат, ролей и финансовых корректировок.

## 29. Security

Обязательно: - Discord OAuth2 state validation; - Laravel session
security; - CSRF; - auth middleware; - Policies/Gates; - backend
validation; - rate limiting; - secure cookies production; - DB
constraints; - secrets только в `.env`.

## 30. Backend Services

``` text
AttendanceService
PrimePayoutCalculator
PayoutService
TreasuryService
AuctionService
ActivityService
DiscordService
AuditService
```

Actions:

``` text
CreateActivity
CalculatePrimeShares
CompletePayout
PlaceAuctionBid
FinishAuction
IssueTreasuryItem
LinkDiscordUserToPlayer
```

## 31. Тестирование

Обязательно тестировать: - Discord authentication/linking; -
permissions; - duplicate attendance; - `20 000 / 60 = 333`, remainder
20; - отсутствие выплаты отсутствующему игроку; - несколько праймов; -
immutable earnings; - auction bid validation/concurrency; -
winner/treasury integration; - payout snapshot; - duplicate payment
prevention; - недостаток золота.

## 32. Этапы MVP

1.  **Foundation:** Laravel, Vue, PostgreSQL, Discord OAuth2, roles,
    Policies, migrations, seeders.
2.  **Guild:** players, roster, groups, solo, profiles, Discord linking.
3.  **Activities:** definitions, три типа событий, participants,
    attendance, filters.
4.  **Prime earnings:** gold value, shares, rounding, remainder, pending
    earnings.
5.  **Loot & Treasury:** inventory, transactions, rare loot issuing.
6.  **Auctions:** bidding, concurrency, finish, treasury integration.
7.  **Payouts:** batches, snapshots, confirmation, treasury expense.
8.  **Dashboard.**
9.  **Discord:** notifications, queues, bot/API.

## 33. Не делать в MVP

Не добавлять без отдельного требования: - другие MMORPG; - Gear Score /
Item Level / экипировку / BiS; - PvP statistics; - marketplace; -
публичные профили; - mobile app; - microservices; - Kubernetes; -
Elasticsearch; - универсальный MMO framework.

## 34. Критические инварианты

``` text
treasury gold >= 0
item quantity >= 0
UNIQUE(activity_id, player_id)
```

Prime: - деньги получает только участник; -
`player_share = floor(gold_value / participants_count)`; - остаток
остается в казне; - нельзя начислить больше `gold_value`; - начисление
нельзя оплатить дважды.

Auction: - backend validation; - transaction/locking; - нельзя завершить
дважды.

History: - деактивация Player не удаляет историю; - завершенные
финансовые snapshots immutable.

## 35. Приоритеты

``` text
1. Финансовая корректность
2. Корректность посещаемости
3. Сохранность истории
4. Authentication / Authorization / Security
5. Удобство Guild Leader / Officer
6. Производительность
7. Внешний вид
```

## 36. Первая задача Codex

1.  Прочитать весь `PROJECT.md`.
2.  Найти логические противоречия.
3.  Предложить окончательную ER-схему.
4.  Определить Laravel models/relationships.
5.  Определить Policies/permissions matrix.
6.  Определить REST API.
7.  Определить Vue pages/components/stores.
8.  Проверить финансовые invariants.
9.  Составить implementation plan.
10. После согласования начать Phase 1.

Если неопределенное правило влияет на деньги, историю или permissions
--- задать вопрос пользователю, а не придумывать правило.

## 37. Definition of Done MVP

MVP готов, когда можно пройти цикл:

``` text
Discord Login
→ связать User с Player
→ создать/распределить игроков
→ создать прайм
→ отметить 60 участников
→ указать 20 000 золота
→ начислить 333 каждому
→ оставить 20 остатка
→ создать Activity
→ добавить редкий предмет
→ предмет попадает в казну
→ выдать или выставить на аукцион
→ получить ставки
→ завершить аукцион
→ золото поступает в казну
→ сформировать payout из pending earnings
→ подтвердить выплату
→ обновить казну, профиль и историю
```
