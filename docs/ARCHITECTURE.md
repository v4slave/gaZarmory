# Архитектура ArcheAge Guild Management System

## Границы системы

Проект — модульный монолит: Laravel REST API является единственным источником истины, Vue 3 — отдельный SPA-клиент, PostgreSQL хранит первичные данные, immutable snapshots и очередь. File cache/session используются по умолчанию; Redis текущей конфигурацией не предусмотрен. Микросервисы не используются.

```text
Browser (Vue 3 + Pinia + Axios)
        │ session cookie + CSRF
        ▼
Laravel HTTP API
  Controllers → Form Requests/Policies → Actions/Services → Eloquent → PostgreSQL
                                      │
                                      └→ queued Jobs → DiscordService
```

## Backend

- `Http/Controllers` — HTTP-адаптация, orchestration и часть компактных read/CRUD-сценариев.
- `Http/Requests` и inline validation — валидация входных данных.
- `Policies` и `Gates` — проверка каждой операции; роль не проверяется во Vue как мера безопасности.
- `Actions` — один изменяющий use case (`CalculatePrimeShares`, `PlaceAuctionBid`, `CompletePayout`).
- `Services` — общая доменная логика и read-модели.
- `Models` — связи, casts и локальные scopes; критические переходы не прячутся в observers.
- `Jobs` — Discord-уведомления; database queue настроена с `after_commit=true`.

Финансовые команды выполняются в `DB::transaction()`. Изменяемые агрегаты блокируются через `SELECT ... FOR UPDATE`. Денежные значения — `BIGINT`, количества предметов — положительные целые. Snapshots начислений и завершённых выплат запрещено изменять на уровне приложения; ключевые ограничения продублированы в PostgreSQL.

Экономика разделяет два независимых показателя: оценочную стоимость инвентаря и фактический золотой баланс. Добавление и оценка лута создают предметы казны и snapshot-начисления участникам, но не создают золотую транзакцию. Золото появляется только после аукционной или внешней продажи. Выплаты формируются автоматически из pending earnings и списываются только с золотого ledger.

## Frontend

- страницы загружаются динамически через Vue Router;
- Pinia stores используются для auth, guild, activities, dialog и notifications state; часть страниц обращается к API напрямую;
- API-клиент централизован, отправляет cookies и сначала получает Sanctum CSRF cookie;
- права из `/api/me` управляют интерфейсом, но не заменяют backend Policies;
- сервер отвечает за фильтрацию, сортировку и пагинацию таблиц.

## Транзакционные границы

1. Расчёт прайма блокирует activity, проверяет состав и отсутствие существующих earnings, затем создаёт snapshots по оценочной стоимости лута и коэффициентам. Золотой ledger при этом не изменяется.
2. Выплата блокирует payout, treasury balance и включённые `pending` earnings; создаёт payout snapshots, переводит earnings в `paid`, списывает золото и пишет treasury/audit transactions.
3. Ставка блокирует auction и текущую максимальную ставку, после чего валидирует статус, deadline, участника и минимальный шаг.
4. Завершение аукциона блокирует auction и inventory item и атомарно создаёт item/gold transactions и audit log.

## Развёртывание

Предполагаются Laravel/PHP-FPM, отдельные queue worker и scheduler, статический Vue build, PostgreSQL, file cache/session, Tesseract OCR, а также опциональные Playwright и `rembg` для расширенных функций. Все secrets задаются через `.env`; production cookies — encrypted, `secure`, `httpOnly`, `sameSite=lax`.

Тяжёлые OCR и `rembg` операции пока выполняются синхронно в HTTP worker. Ограничения и план изоляции описаны в [`TECHNICAL_AUDIT.md`](TECHNICAL_AUDIT.md).
