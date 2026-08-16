# Armory AA

Старт реализации ArcheAge Guild Management System по требованиям `PROJECT.md`.

## Структура

- `backend/` — Laravel 12 REST API skeleton;
- `frontend/` — Vue 3 + Router + Pinia + Axios + Vite;
- `database/schema.sql` — окончательная PostgreSQL DDL;
- `docs/` — архитектура, ERD, permissions, API и открытые продуктовые решения.

## Локальный запуск

Требуются PHP 8.3+, Composer, Node.js и PostgreSQL.

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

cd ../frontend
npm install
cp .env.example .env
npm run dev
```

Текущий milestone включает foundation, Discord OAuth flow, Policies, Guild REST API, состав и управление конст-пати во Vue. Для OAuth нужны реальные Discord credentials; спорные финансовые правила намеренно не подменены фиктивными значениями.

## Production

Production-шаблоны находятся в `deploy/`. Пошаговая инструкция: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).
