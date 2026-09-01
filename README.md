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
npm install
npm run install-browser
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

cd ../frontend
npm install
cp .env.example .env
npm run dev
```

Текущая реализация включает Discord OAuth, роли и Policies, состав и конст-пати, активности и импорт лута, казну, аукционы, автоматические выплаты, audit log, Discord-уведомления и SPA-интерфейс.

Экономическая модель разделяет оценочную стоимость лута и реальное золото. Оценка дропа основного или мини-прайма автоматически создаёт равные начисления участникам, но не пополняет золотой баланс. Золото появляется после аукционной или внешней продажи и только оно может быть использовано для выплаты. Ручное изменение рассчитанных сумм не допускается.

## Production

Production-шаблоны находятся в `deploy/`. Пошаговая инструкция: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).
