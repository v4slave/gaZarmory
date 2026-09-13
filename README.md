# Armory AA

GAZ ARMORY — система управления гильдией ArcheAge. Backend на Laravel хранит состав, активности, начисления, казну, аукционы и аудит; Vue SPA предоставляет пользовательский и административный интерфейсы. Авторизация выполняется через Discord OAuth и cookie-сессию Sanctum.

## Возможности

- состав гильдии, конст-пати, пятёрки и заявки на привязку персонажа;
- профиль игрока, история ГС, оснащение, импорт билда с `archa.ge` и рендер персонажа;
- журнал праймов, OCR участников, лут и импорт таблиц;
- неизменяемые начисления, предварительный расчёт и проведение выплат;
- золотой ledger, склад, выдача/продажа предметов и финансовая сверка;
- аукционы с proxy bidding, резервированием и автопродлением;
- dashboard, посещаемость, readiness-отчёт, CSV/XLSX-экспорт;
- внутренняя медиалента, уведомления, Discord webhook и audit log;
- русский и английский интерфейс.

Экономическая модель разделяет оценочную стоимость лута и реальное золото. Оценка дропа создаёт начисления участникам, но не пополняет золотой баланс. Золото появляется после аукционной или внешней продажи; только оно доступно для выплаты. Рассчитанные суммы вручную не редактируются.

## Структура

- `backend/` — Laravel 12 API, доменные actions/services, очереди и тесты;
- `frontend/` — Vue 3, Router, Pinia, Axios, Vite и Vitest;
- `database/schema.sql` — справочная PostgreSQL DDL; источником изменений являются Laravel migrations;
- `deploy/` — Nginx, systemd, backup и deploy-шаблоны;
- `docs/` — архитектура, API, права, эксплуатация и пользовательские руководства;
- `PROJECT.md` — исходная продуктовая спецификация, а не актуальная инструкция запуска.

## Требования

- PHP 8.3+ и Composer 2;
- Node.js 22+ и npm;
- PostgreSQL;
- PHP extensions: `pdo_pgsql`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `intl`, `fileinfo`;
- Tesseract OCR с языками `rus` и `eng` для распознавания участников;
- Python venv с `rembg` и локальной моделью для удаления фона;
- Chromium/Playwright для fallback-импорта динамических страниц `archa.ge`.

OCR, `rembg` и Playwright нужны только соответствующим функциям; остальная система может работать без них.

## Локальный запуск

Создайте PostgreSQL-базу и пользователя, затем настройте backend:

```bash
cd backend
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run install-browser
php artisan serve
```

В другом терминале запустите frontend:

```bash
cd frontend
npm ci
cp .env.example .env
npm run dev
```

По умолчанию SPA доступен на `http://localhost:5173`, API — на `http://localhost:8000`. Для Discord OAuth заполните `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET` и добавьте callback `http://localhost:8000/auth/discord/callback` в Discord Developer Portal.

Очередь и scheduler при локальной проверке запускаются отдельно:

```bash
cd backend
php artisan queue:work
php artisan schedule:work
```

## Проверки

```bash
cd backend
composer validate --strict
composer audit --locked
php artisan test

cd ../frontend
npm audit
npm run check
```

CI дополнительно выполняет чистую миграцию PostgreSQL и сканирует полную Git-историю через Gitleaks. Текущее состояние проверок и приоритеты исправлений зафиксированы в [`docs/TECHNICAL_AUDIT.md`](docs/TECHNICAL_AUDIT.md).

## Документация

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — компоненты и транзакционные границы;
- [`docs/API.md`](docs/API.md) — группы endpoint и правила API;
- [`docs/PERMISSIONS.md`](docs/PERMISSIONS.md) — матрица ролей;
- [`docs/USER_GUIDE.md`](docs/USER_GUIDE.md) — пользовательские сценарии;
- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — production-развёртывание;
- [`docs/TECHNICAL_AUDIT.md`](docs/TECHNICAL_AUDIT.md) — безопасность, производительность и состояние качества.

## Production

Production-шаблоны находятся в `deploy/`. Пошаговая инструкция: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).
