# REST API

Контракты из `PROJECT.md` сохраняются. Общие правила: JSON, `/api`, session auth через Sanctum, pagination metadata, validation errors в Laravel формате `422`, запрет `403`, конфликт состояния `409`.

Дополнения, необходимые для явно описанных возможностей:

- `GET/POST/PUT/DELETE /api/activity-definitions[/{id}]` — справочник событий (leader/officer для записи).
- `PUT /api/admin/users/{user}/role` — роли, только Guild Leader.
- `PUT /api/admin/players/{player}/user` — linking Discord user, leader/officer.
- `GET /api/audit-logs` — только Guild Leader.
- `GET /api/dashboard` — агрегированный read endpoint.

Фильтры коллекций передаются query parameters. Sorting принимается только из allowlist. Для ставок, расчёта, завершения auction/payout повторный запрос возвращает текущее состояние либо `409`, но никогда не повторяет финансовый side effect.

`DELETE /api/players/{id}` выполняет подтверждённое логическое удаление: устанавливает `is_active=false`, не удаляя Player и связанную историю.
