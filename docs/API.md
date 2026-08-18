# REST API

Контракты из `PROJECT.md` сохраняются. Общие правила: JSON, `/api`, session auth через Sanctum, pagination metadata, validation errors в Laravel формате `422`, запрет `403`, конфликт состояния `409`.

Дополнения, необходимые для явно описанных возможностей:

- `GET/POST/PUT/DELETE /api/activity-definitions[/{id}]` — справочник событий (leader/officer для записи).
- `PATCH /api/admin/users/{user}/roles` — роли, только Guild Leader/Developer согласно серверной политике.
- `PUT /api/players/{player}/user` — linking Discord user, управляющие роли.
- `GET /api/admin/audit-logs` — только Guild Leader/Developer.
- `GET /api/dashboard` — агрегированный read endpoint.

Фильтры коллекций передаются query parameters. Sorting принимается только из allowlist. Для ставок, расчёта, завершения auction/payout повторный запрос возвращает текущее состояние либо `409`, но никогда не повторяет финансовый side effect.

Расчёт основного или мини-прайма создаёт неизменяемые начисления из оценочной стоимости лута, но не пополняет золотой баланс. API намеренно не предоставляет endpoint изменения `payout_players.amount`: сумма полностью определяется включёнными начислениями.

`DELETE /api/players/{id}` выполняет подтверждённое логическое удаление: устанавливает `is_active=false`, не удаляя Player и связанную историю.
