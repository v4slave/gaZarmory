# Архитектура ArcheAge Guild Management System

## Границы системы

Проект — модульный монолит: Laravel REST API является единственным источником истины, Vue 3 — отдельный SPA-клиент, PostgreSQL хранит первичные данные и immutable snapshots, Redis опционален для очередей и rate limiting. Микросервисы для MVP не используются.

```text
Browser (Vue 3 + Pinia + Axios)
        │ session cookie + CSRF
        ▼
Laravel HTTP API
  Controllers → Form Requests → Actions/Services → Eloquent → PostgreSQL
                         │
                         └→ Domain Events → queued Listeners → DiscordService
```

## Backend

- `Http/Controllers` — только HTTP-адаптация, без финансовой логики.
- `Http/Requests` — валидация входных данных.
- `Policies` и `Gates` — проверка каждой операции; роль не проверяется во Vue как мера безопасности.
- `Actions` — один изменяющий use case (`CalculatePrimeShares`, `PlaceAuctionBid`, `CompletePayout`).
- `Services` — общая доменная логика и read-модели.
- `Models` — связи, casts и локальные scopes; критические переходы не прячутся в observers.
- `Events/Listeners/Jobs` — уведомления после успешного commit (`afterCommit`).

Финансовые команды выполняются в `DB::transaction()`. Изменяемые агрегаты блокируются через `SELECT ... FOR UPDATE`. Денежные значения — `BIGINT`, количества предметов — положительные целые. Snapshots начислений и завершённых выплат запрещено изменять на уровне приложения; ключевые ограничения продублированы в PostgreSQL.

## Frontend

- страницы соответствуют маршрутам из спецификации;
- Pinia stores разделены по доменам (`auth`, `players`, `activities`, `treasury`, `auctions`, `payouts`);
- API-клиент централизован, отправляет cookies и сначала получает Sanctum CSRF cookie;
- права из `/api/me` управляют интерфейсом, но не заменяют backend Policies;
- сервер отвечает за фильтрацию, сортировку и пагинацию таблиц.

## Транзакционные границы

1. Расчёт прайма блокирует activity, проверяет тип `prime`, непустой состав и отсутствие существующих active earnings, затем одним bulk insert создаёт snapshots.
2. Выплата блокирует payout, treasury balance и включённые `pending` earnings; создаёт payout snapshots, переводит earnings в `paid`, списывает золото и пишет treasury/audit transactions.
3. Ставка блокирует auction и текущую максимальную ставку, после чего валидирует статус, deadline, участника и минимальный шаг.
4. Завершение аукциона блокирует auction и inventory item и атомарно создаёт item/gold transactions и audit log.

## Развёртывание

Предполагаются один Laravel runtime/queue worker, статический Vue build, PostgreSQL и при необходимости Redis. Все secrets задаются через `.env`; production cookies — `secure`, `httpOnly`, `sameSite=lax` (или более строго после проверки OAuth flow).

