# REST API

Актуальный API — Laravel routes в `backend/routes/api.php`. На дату 2026-09-10 зарегистрировано 122 web/API routes. `PROJECT.md` хранит исходную спецификацию и не является полным контрактом текущей реализации.

## Общие правила

- JSON endpoints используют префикс `/api`;
- авторизация — cookie session через Sanctum, state-changing запросы защищены CSRF;
- все guild endpoints требуют authenticated user, большинство — ещё и привязанного активного персонажа;
- язык ошибок определяется `Accept-Language: ru|en`, fallback — русский;
- validation/state errors обычно возвращают `422`, запрет — `403`, заблокированное завершённое состояние отдельных activity-операций — `409`;
- списки с потенциальным ростом используют Laravel pagination metadata;
- фильтры передаются query parameters, sorting принимается только из allowlist;
- общий rate limit authenticated API — 60 запросов/мин на пользователя.

## Авторизация и текущий пользователь

- `GET /auth/discord`, `GET /auth/discord/callback` — Discord OAuth;
- `GET /api/me`, `POST /api/logout` — текущая сессия;
- `POST /api/me/discord-profile/sync` — обновление Discord-профиля, `6/min`;
- `GET /api/me/player-options`, `GET /api/me/onboarding-options` — варианты привязки/вступления;
- `POST /api/me/player` — заявка на существующего персонажа;
- `POST /api/me/player/create` — создание персонажа и заявки;
- `PATCH /api/me/player/{nickname|class|profile}` — самостоятельное редактирование профиля;
- `POST /api/me/player/archa-gear` — импорт билда `archa.ge`, `6/min`.

## Состав и конст-пати

- resource `/api/players` — просмотр и управление составом;
- `/api/players/{player}/{activities|earnings|gear-score-history}` — paginated history;
- `/api/players/{player}/profile` и `/character-render[...]` — developer/profile media operations;
- `PUT /api/players/{player}/group|user`, `POST /activate`, `DELETE /permanent` — управление связями и lifecycle;
- resource `/api/groups` — конст-пати;
- `/api/groups/{group}/squads[...]` — пятёрки внутри консты;
- `GET /api/roster-readiness` — ГС, оснащение и дельты;
- `GET /api/attendance-analytics[/export]` — посещаемость и CSV/XLSX.

`DELETE /api/players/{player}` деактивирует запись. Permanent delete разрешён только для непривязанного игрока без финансовой/активностной истории.

## Активности и лут

- resource `/api/activity-definitions` и `/icon` — справочник праймов;
- resource `/api/activities` — журнал активностей;
- `/api/activities/{activity}/players[...]` — состав и индивидуальные коэффициенты;
- `POST /api/activities/{activity}/participant-scan` — OCR скриншота участников;
- `/api/activities/{activity}/loot[...]` — позиции лута;
- `/api/activities/{activity}/loot-imports` и `/api/loot-imports[...]` — draft/confirm импорт CSV/XLS/XLSX;
- `/complete`, `/reopen`, `/calculate-prime` — переходы расчёта.

Расчёт прайма создаёт snapshots начислений из оценочной стоимости лута, но не пополняет золотой ledger. Повторное изменение рассчитанных/оплаченных данных ограничено состоянием и audit log.

## Казна, выплаты и аукционы

- `GET /api/treasury`, `/items`, `/issue-options` — агрегаты и склад;
- `POST /api/treasury/transactions` — ручная операция с золотом;
- `POST /api/treasury/items/{item}/issue|sell` — выдача/продажа;
- `GET /api/earnings/pending` — ожидающие начисления;
- `/api/payouts`, `/api/payouts-preview`, `/calculate`, `/pay-players`, `/complete`, `/cancel`, `/export` — выплаты;
- `/api/auctions`, `/start`, `/bid`, `/finish`, `/cancel`, `/archive`, `/active-count` — аукционы;
- resource `/api/loot-catalog` — справочник лута.

API намеренно не предоставляет endpoint изменения `payout_players.amount`: сумма определяется включёнными начислениями или указанным distribution amount. Аукцион фиксирует курс жетона при старте; proxy bids хранят максимумы отдельно от видимой ставки.

## Dashboard, media и администрирование

- `GET /api/dashboard` — агрегированный read endpoint;
- `/api/media`, `/metadata`, `/{post}/file|reaction` — медиалента;
- `/api/notifications`, `/read-all`, `/{notification}/read` — центр уведомлений;
- `/api/admin/users[...]` — пользователи и роли;
- `/api/admin/player-link-requests[...]` — заявки;
- `GET /api/admin/audit-logs` — audit log;
- `GET /api/admin/settings`, `PATCH /economy` — integrations/economy state;
- `GET /api/financial-reconciliation` — read-only сверка ledger и snapshots.

## Публичные asset endpoints

- `GET /api/archa-gear/items/{itemId}`;
- `GET /api/archa-gear/assets/{items|runes|gems}/{assetId}`.

Они проксируют JPEG с `archa.ge`, кэшируют ответ на сутки и ограничены `120/min` на IP. Связанные риски и рекомендации приведены в [`TECHNICAL_AUDIT.md`](TECHNICAL_AUDIT.md).
