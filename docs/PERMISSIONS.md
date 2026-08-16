# Матрица прав

| Операция | Guild Leader | Officer | Member |
|---|:---:|:---:|:---:|
| Просмотр roster/profiles/activities/auctions | ✓ | ✓ | ✓ |
| Ставка на активном аукционе | ✓ | ✓ | ✓ |
| Просмотр собственной выплаты | ✓ | ✓ | ✓ |
| CRUD players/groups/activities/attendance/loot | ✓ | ✓ | — |
| Казна, выдача предметов, auctions, payouts | ✓ | ✓ | только разрешённое чтение |
| Связать User с Player | ✓ | ✓ | — |
| Назначить `officer`/`member` | ✓ | — | — |
| Назначить `guild_leader` | ✓ | — | — |
| Настройки и audit log | ✓ | — | — |

Любое разрешение также требует authenticated session. Officer не может изменить пользователя, имеющего роль Guild Leader, через косвенный endpoint.

