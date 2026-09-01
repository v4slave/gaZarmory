# Production-развёртывание GAZ ARMORY

Инструкция рассчитана на один Linux VPS с Nginx, PHP-FPM, PostgreSQL и HTTPS. Frontend и API публикуются на одном домене — это упрощает cookies, CORS и Discord OAuth.

## 1. Требования

- Linux-сервер с SSH-доступом;
- домен с A/AAAA-записью на сервер;
- Nginx;
- PHP 8.3+ с расширениями `pgsql`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `intl`, `fileinfo`;
- Composer 2;
- Node.js с npm;
- системные библиотеки Chromium (устанавливаются командой Playwright ниже);
- PostgreSQL;
- Certbot или другой ACME-клиент для HTTPS.

Путь приложения в готовых конфигурациях: `/var/www/gaz-armory/current`. Домен-заглушка: `armory.example.com`.

## 2. База данных

Создайте отдельного пользователя и базу. Пароль не храните в истории shell.

```sql
CREATE ROLE armory_aa LOGIN PASSWORD 'СЛОЖНЫЙ_ПАРОЛЬ';
CREATE DATABASE armory_aa OWNER armory_aa ENCODING 'UTF8';
```

## 3. Код и окружение

```bash
sudo mkdir -p /var/www/gaz-armory
sudo chown -R "$USER":www-data /var/www/gaz-armory
git clone <URL_РЕПОЗИТОРИЯ> /var/www/gaz-armory/current
cd /var/www/gaz-armory/current
cp backend/.env.production.example backend/.env
cp frontend/.env.production.example frontend/.env.production
```

В `backend/.env` обязательно замените домен, данные PostgreSQL и Discord credentials. Затем:

```bash
composer install --working-dir=backend --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci --prefix backend
PLAYWRIGHT_BROWSERS_PATH=0 npx --prefix backend playwright install --with-deps chromium
php backend/artisan key:generate
npm ci --prefix frontend
npm run build --prefix frontend
php backend/artisan migrate --force
php backend/artisan storage:link
php backend/artisan optimize
sudo chown -R www-data:www-data backend/storage backend/bootstrap/cache
sudo chmod -R ug+rwX backend/storage backend/bootstrap/cache
```

Не переносите локальный `APP_KEY`, если production ещё не содержит зашифрованных Laravel-данных. После первого запуска сохраните production `.env` и `APP_KEY` в защищённом менеджере секретов.

## 4. Discord OAuth

В Discord Developer Portal добавьте точный Redirect URL:

```text
https://armory.example.com/auth/discord/callback
```

Тот же URL укажите в `DISCORD_REDIRECT_URI`. `FRONTEND_URL`, `APP_URL`, `SESSION_DOMAIN` и `SANCTUM_STATEFUL_DOMAINS` должны соответствовать публичному домену.

## 5. Nginx и HTTPS

```bash
sudo cp deploy/nginx/gaz-armory.conf /etc/nginx/sites-available/gaz-armory
sudo cp deploy/nginx/security-headers.conf /etc/nginx/snippets/gaz-armory-security.conf
sudo ln -s /etc/nginx/sites-available/gaz-armory /etc/nginx/sites-enabled/gaz-armory
sudo nginx -t
sudo systemctl reload nginx
```

Замените `armory.example.com` и при необходимости сокет PHP-FPM. Для Ubuntu 26.04 с PHP 8.5 это `php8.5-fpm.sock`. После проверки HTTP выпустите сертификат ACME/Let's Encrypt и включите перенаправление HTTP → HTTPS.

Не оставляйте приложение доступным по HTTP после выпуска сертификата. HTTPS-конфигурация должна передавать PHP заголовок `X-Forwarded-Proto: https`, а HTTP-server — отвечать перенаправлением `301` на тот же URI по HTTPS. Добавьте `Strict-Transport-Security: max-age=31536000; includeSubDomains` только после проверки HTTPS на основном домене и всех его поддоменах.

Шаблон ограничивает API до 120 запросов в минуту с одного IP, а OAuth и Sanctum — до 20. Если перед Nginx используется CDN или reverse proxy, сначала настройте `set_real_ip_from` и `real_ip_header`; иначе лимит будет применяться к адресу прокси, а не пользователя. Защитные заголовки находятся в отдельном snippet и должны оставаться подключёнными также в locations с собственным `add_header`, поскольку Nginx не наследует родительские `add_header` в таких блоках.

Для загрузки изображений в раздел «Контент» установите в `php.ini` для PHP-FPM значения `upload_max_filesize = 20M` и `post_max_size = 22M`, затем перезапустите PHP-FPM. Загрузка видео с устройства запрещена: ролики добавляются только ссылками на поддерживаемые платформы.

Проверка backend:

```bash
curl -fsS https://armory.example.com/up
```

## 6. Очередь и scheduler

Очередь отправляет Discord-уведомления. Scheduler каждую минуту закрывает истёкшие аукционы и выбирает максимальную ставку.

```bash
sudo cp deploy/systemd/gaz-armory-queue.service /etc/systemd/system/
sudo cp deploy/systemd/gaz-armory-scheduler.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now gaz-armory-queue gaz-armory-scheduler
sudo systemctl status gaz-armory-queue gaz-armory-scheduler
```

Журналы:

```bash
journalctl -u gaz-armory-queue -f
journalctl -u gaz-armory-scheduler -f
```

## 7. Резервные копии

Скрипт сохраняет PostgreSQL и `backend/storage/app/public`, затем удаляет копии старше 14 дней.

```bash
sudo mkdir -p /etc/gaz-armory /var/backups/gaz-armory
sudo cp deploy/backup.env.example /etc/gaz-armory/backup.env
sudo chmod 600 /etc/gaz-armory/backup.env
sudo chmod +x deploy/scripts/backup.sh deploy/scripts/deploy.sh
sudo cp deploy/cron/gaz-armory-backup /etc/cron.d/gaz-armory-backup
sudo chmod 644 /etc/cron.d/gaz-armory-backup
sudo deploy/scripts/backup.sh
```

Обязательно проверьте восстановление копии на отдельной тестовой базе:

```bash
pg_restore --clean --if-exists --no-owner --dbname=<ТЕСТОВАЯ_БАЗА> /var/backups/gaz-armory/database_*.dump
```

Локальная копия на том же VPS не защищает от потери сервера. Настройте последующую отправку архивов во внешнее объектное хранилище.

## 8. Последующие обновления

После получения новой версии кода:

```bash
cd /var/www/gaz-armory/current
git pull --ff-only
APP_DIR=/var/www/gaz-armory/current deploy/scripts/deploy.sh
```

Скрипт устанавливает production-зависимости, собирает frontend, включает maintenance mode на время миграции, очищает/создаёт Laravel-кэши и перезапускает worker/scheduler.

## 9. Проверка после публикации

- `/up` отвечает успешно;
- главная страница и изображения открываются по HTTPS;
- вход и выход через Discord работают;
- cookie имеет флаги `Secure` и `HttpOnly`;
- cookie-сессия зашифрована (`SESSION_ENCRYPT=true`);
- ответы содержат CSP, `X-Content-Type-Options`, `Referrer-Policy` и запрет framing;
- создаётся тестовая активность;
- очередь обрабатывает Discord-уведомление;
- истёкший тестовый аукцион закрывается scheduler;
- выполняется и восстанавливается резервная копия;
- `APP_DEBUG=false`, `.env` недоступен из браузера.
