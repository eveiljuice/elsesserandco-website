# FEATURES.md — Elsesser & Co.

Полный список фич сайта, их файлы, эндпоинты, таблицы БД и точки расширения.
Документ для агентов и разработчиков, чтобы не лазить по 140 файлам.

> Версия: **v2.1** (май 2026)
> Стек: PHP 8.1, MySQL 8.0, Vanilla JS, без фреймворков, без Composer.

---

## Условные обозначения

| Символ | Значение |
|--------|----------|
| ✅ | Готово и работает |
| 🟡 | Готово, требует ручной настройки (ключи в `.env`, миграции) |
| 🔧 | Готово частично / есть TODO |
| ⏳ | Запланировано, не реализовано |

---

## 1. Каталог недвижимости

### 1.1 Готовое жильё ✅
- **URL:** `/properties.php?category=sale` или `?category=rent`
- **ЧПУ:** `/properties` (через `.htaccess`)
- **Файлы:** `properties.php` (логика + view), `js/filters.js`
- **Фильтры:** район, комнаты, цена, площадь, этаж, тип дома, ремонт, метро, поиск, сортировка
- **Таблицы:** `properties`, `property_images`, `ekb_districts`, `favorites`
- **Точки расширения:** добавить новый фильтр → дописать GET-параметр в `properties.php:13-32` и WHERE в `properties.php:35-103`.

### 1.2 Карточка объекта ✅
- **URL:** `/property.php?id={id}` / ЧПУ: `/property/{id}-slug`
- **Файл:** `property.php` (~1400 строк — содержит логику, HTML, inline-стили)
- **Что внутри:**
  - Галерея + удобства + рейтинг + отзывы + контакты агента
  - Похожие объекты (взвешенный similarity_score: район×3 + комнаты×2 + цена ±25%×2)
  - Ипотечный калькулятор (для category=sale, см. §4)
  - JSON-LD `Residence`/`Apartment` + Open Graph (см. §11)
  - Счётчик `views_count` инкрементируется при каждом GET
- **Таблицы:** `properties`, `property_images`, `amenities`, `property_amenities`, `reviews`, `users` (для агента), `ekb_districts`

### 1.3 Новостройки (ЖК) ✅
- **URL:** `/new-buildings.php`, `/new-building.php?id={id}` / ЧПУ: `/new-building/{id}-slug`
- **Файлы:** `new-buildings.php`, `new-building.php`
- **Таблицы:** `new_buildings`, `new_building_images`, `new_building_layouts`, `developers`
- **Точка расширения:** для застройщиков есть отдельный flow регистрации `register-developer.php`.

### 1.4 Сравнение объектов 🔧
- **URL:** `/compare.php`
- **Файл:** `compare.php`, `js/compare.js`
- **TODO:** нет кнопки «добавить в сравнение» на карточках. Реализовать аналогично favorites — добавить кнопку рядом с сердечком + localStorage-стейт.

---

## 2. Авторизация и пользователи

### 2.1 Регистрация (email + пароль) ✅
- **URL:** `/register.php`
- **Файлы:** `register.php` (view), `includes/auth/register.php` (логика)
- **Валидация:** email, имя/фамилия (≥2 символа), пароль (≥8 + буквы + цифры), телефон (опционально)
- **CSRF:** через `validateCSRFToken()` (см. §2.6)
- **После регистрации:** автологин + автоотправка письма подтверждения (см. §2.5)

### 2.2 Вход ✅
- **URL:** `/login.php`
- **Файлы:** `login.php` (view), `includes/auth/login.php` (логика)
- **Особенности:**
  - `session_regenerate_id(true)` против session fixation
  - `sleep(1)` после неверного пароля (слабая защита от brute force, см. бэклог)
  - Role-based редирект: admin → `/admin/`, agent → `/agent/`, user → `/dashboard.php`
  - Безопасный redirect-параметр (только relative URLs)

### 2.3 Восстановление пароля 🟡 *(v2.1)*
- **URL:** `/forgot-password.php`, `/reset-password.php?token={token}`
- **Файлы:** `forgot-password.php`, `reset-password.php`, `includes/auth/password_reset.php`
- **Таблица:** `password_resets` (миграция `020_auth_extensions.sql`)
- **Особенности:**
  - Токен живёт 1 час
  - Храним `sha256(token)`, не raw
  - Не палим существование email (всегда отвечаем «отправили»)
  - При успешном сбросе обнуляем `failed_login_attempts`
- **Требует:** SMTP в `.env` (`MAIL_TRANSPORT=smtp` + хост/пароль) или `MAIL_TRANSPORT=log` для dev.

### 2.4 OAuth: VK / Яндекс / Google 🟡 *(v2.1)*
- **URLs:**
  - Старт: `/oauth/{vk,yandex,google}/start.php`
  - Callback: `/oauth/{vk,yandex,google}/callback.php`
- **Файлы:** `oauth/*/start.php`, `oauth/*/callback.php`, `includes/auth/oauth_helper.php`
- **Логика:** `OAuthHelper::loginOrRegister()`:
  1. Ищем по `(oauth_provider, oauth_id)` — логин.
  2. Если нет — ищем по email и привязываем OAuth.
  3. Если нет email — создаём нового юзера с `<provider>_<id>@oauth.local`.
- **State CSRF:** `OAuthHelper::generateState()` + `consumeState()` хранят state в сессии 10 мин.
- **Таблицы:** `users.oauth_provider`, `users.oauth_id` (uniq index)
- **Требует в `.env`:** `VK_CLIENT_ID/SECRET`, `YANDEX_CLIENT_ID/SECRET`, `GOOGLE_CLIENT_ID/SECRET` + redirect_uri
- **Кнопки** автоматически скрываются на login/register, если соответствующий `CLIENT_ID` пуст.

### 2.5 Telegram Login Widget 🟡 *(v2.1)*
- **URL:** виджет встроен в `login.php` и `register.php` (рендерится клиентом)
- **Callback:** `/oauth/telegram/callback.php`
- **Файл:** `oauth/telegram/callback.php`
- **Защита подписи:** HMAC-SHA256 на `data_check_string`, secret = `sha256(bot_token)`
- **auth_date:** проверяется на ≤24 ч
- **Таблицы:** `users.telegram_id` (BIGINT, uniq), `users.telegram_username`
- **Требует в `.env`:** `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME` + `/setdomain` у @BotFather

### 2.6 Подтверждение email ✅ *(v2.1)*
- **URL:** `/verify-email.php?token={token}`
- **Файлы:** `verify-email.php`, `includes/auth/email_verification.php`
- **API:** `POST /php/auth/resend_verification.php` (повторная отправка для текущего юзера)
- **Поля БД:** `users.email_verified_at`, `users.email_verification_token`, `users.email_verification_expires_at`
- **Токен:** sha256-хеш, действует 24 часа
- **Автоотправка:** при `register.php` (новый юзер) и через OAuth (`email_verified_at = NOW()` сразу).
- **TODO:** показать в `dashboard.php` баннер «подтвердите email» с кнопкой повторной отправки.

### 2.7 CSRF + Session ✅
- **Файл:** `includes/auth/check_auth.php`
- **Функции:** `isLoggedIn()`, `requireLogin()`, `requireAdmin()`, `requireAgent()`, `getUserData()`, `generateCSRFToken()`, `validateCSRFToken()`
- **Cookie:** `HttpOnly`, `SameSite=Lax`, `Secure` (если HTTPS), `session.use_strict_mode=1`
- **Idle timeout:** 2 часа (`SESSION_IDLE_TIMEOUT`) — автоматический logout с редиректом на `/login.php?timeout=1`
- **CSRF-токен:** хранится в `$_SESSION['csrf_token']`, проверяется через `hash_equals()`
- **TODO:** добавить CSRF в JSON-эндпоинты `/php/favorites/*`, `/php/chat/send_message.php` (читать из `X-CSRF-Token` header).

### 2.8 Роли (user / agent / admin) ✅
- **ENUM:** `users.role`
- **Защита:**
  - `requireAdmin()` → `/admin/*`
  - `requireAgent()` → `/agent/*` (agent или admin)
  - `requireLogin()` → личные кабинеты
- **Дашборды:**
  - User → `/dashboard.php`
  - Agent → `/agent/dashboard.php` + sidebar (`/agent/includes/agent-sidebar.php`)
  - Admin → `/admin/index.php` + sidebar (`/admin/includes/admin-sidebar.php`)

### 2.9 Регистрация застройщика / агентства ✅
- **URL:** `/register-developer.php`, `/register-agency.php`
- **Файлы:** `includes/auth/register_developer.php`, `includes/auth/register_agency.php`
- **Модерация:** через `/admin/developer-applications.php`

---

## 3. Избранное и взаимодействие

### 3.1 Favorites ✅
- **JS:** `js/favorites.js` (`toggleFavorite`, `addFavorite`, `removeFavorite`, toast-уведомления)
- **API:** `POST /includes/favorites/{toggle,add,remove}.php` (JSON-body)
- **Таблица:** `favorites (user_id, property_id)` с unique-индексом
- **Точка расширения:** счётчик избранного выводится в header — JS обновляет через `updateFavoritesCount()`.

### 3.2 Чат ✅
- **URL:** `/chat.php?user={agent_id}&property={property_id}`
- **Файлы:** `chat.php`, `js/chat.js`, `css/chat.css`
- **API:**
  - `POST /php/chat/send_message.php` — отправка (JSON body)
  - `GET  /php/chat/get_messages.php?user_id&last_id&limit` — получение
  - `POST /php/chat/mark_read.php` — пометить прочитанными
- **Реализация:** polling каждые 3 сек (см. `js/chat.js:30`). При hidden tab — пауза.
- **Защита:** rate-limit 10 сообщений/мин в `send_message.php`, длина ≤2000 симв.
- **Push-уведомления:** автоматически отправляются получателю через `Notifier::push()` (см. §6.2).
- **TODO:** заменить polling на SSE если нагрузка вырастет (см. бэклог).

### 3.3 Заявки (Inquiries) ✅
- **URL:** форма на `property.php` + контактная форма
- **Логика:** `includes/inquiries/submit.php`
- **Таблица:** `inquiries` (status: new / contacted / completed / spam)
- **Админка:** `/admin/inquiries.php`, агенту — `/agent/requests.php`

### 3.4 Просмотры (Viewings, календарь) ✅
- **URL:** `/agent/calendar.php`
- **Таблица:** `viewings (user_id, agent_id, property_id, scheduled_at, status)`

### 3.5 Отзывы ✅
- **API:** `POST /php/reviews/add_review.php`, `GET /php/reviews/get_reviews.php`
- **JS:** `js/reviews.js` (звёздный рейтинг)
- **Модерация:** `/admin/moderate_reviews.php`
- **Таблица:** `reviews (user_id, property_id, agent_id, rating, comment, is_approved)`

---

## 4. Ипотечный калькулятор ✅ *(v2.1)*

- **Где:** секция на `property.php` (только для `category=sale`)
- **Файл:** `js/mortgage.js`
- **Формула:** аннуитет `P = S × (i × (1+i)^n) / ((1+i)^n - 1)`
  - `S` = цена − первый взнос
  - `i` = годовая ставка / 12 / 100
  - `n` = срок в месяцах
- **Inputs:** стоимость, % взноса (range), % ставки (range), срок (range)
- **Outputs:** ежемесячный платёж, общая сумма, переплата
- **CSS:** в `css/style.css` блок `.mortgage*`

---

## 5. Поиск и автокомплит

### 5.1 Полнотекстовый поиск ✅
- **Endpoint:** `GET /php/search/autocomplete.php?q={query}&type={sale|rent}`
- **Источники:** properties (LIKE по title/street/location/building/description + district name), districts, new_buildings
- **Индексы:** FULLTEXT `idx_search` в таблице `properties`
- **JS:** `js/autocomplete.js`
- **Подключение:** добавь `data-autocomplete` к любому `<input>` — обвяжется автоматически.
- **Прикреплён** к hero search на `index.php`.

### 5.2 Расширенный поиск ✅
- На `properties.php` — все фильтры через GET.
- **TODO:** AJAX-фильтры + History API (сейчас полный релоад страницы).

---

## 6. PWA + Web Push *(v2.1)*

### 6.1 PWA ✅ 🟡
- **Файлы:** `manifest.webmanifest`, `sw.js`, `offline.html`, `js/pwa.js`
- **Стратегии кеширования в SW:**
  - HTML → network-first + fallback на `/offline.html`
  - CSS/JS/шрифты → stale-while-revalidate
  - Картинки → cache-first (лимит 60 файлов)
  - `/php/`, `/includes/`, `/admin/`, `/agent/`, `/oauth/` → не кешируются
- **Установка:** Chrome/Edge показывает «Install» при выполнении PWA criteria.
- **TODO:** заменить `favicon.png` на нормальные иконки 192/512 maskable.

### 6.2 Web Push 🟡 *(v2.1)*
- **Файлы:**
  - `includes/push/WebPush.php` — нативный VAPID + aes128gcm (без зависимостей)
  - `includes/push/Notifier.php` — высокоуровневая отправка с очисткой битых подписок (410/404)
  - `php/push/{subscribe,unsubscribe}.php` — API
  - `js/pwa.js::EcoPush.enable()/disable()` — клиент
- **Таблица:** `push_subscriptions (user_id, endpoint, p256dh_key, auth_key, user_agent)` — миграция `021_web_push_subscriptions.sql`
- **VAPID keys:** генерация — `npx web-push generate-vapid-keys`, кладём в `.env`
- **Где триггерится:**
  - `php/chat/send_message.php` → push получателю при новом сообщении
- **TODO:** триггеры на:
  - Новая заявка → агент
  - Новый объект, подходящий под сохранённый поиск → пользователь (требует saved_searches, ⏳)
  - Понижение цены избранного объекта → пользователь

---

## 7. Аналитика цен ✅ *(v2.1)*

- **URL:** `/analytics.php?category={sale|rent}`
- **Файл:** `analytics.php` (логика + Chart.js)
- **Что считается:**
  - Средняя цена за м² по районам (с min/max/диапазон)
  - Тренд за 12 месяцев (line chart)
  - Распределение по комнатам (bar chart с двумя осями)
- **Источник:** агрегации по `properties` (без отдельной аналитической таблицы)
- **TODO:**
  - Кеш на 1 час (сейчас считаем при каждом запросе)
  - Сравнение нескольких районов
  - Прогноз цены через scikit-learn / простой OLS (за рамками PHP)

---

## 8. SEO

### 8.1 sitemap.xml ✅ *(v2.1)*
- **URL:** `/sitemap.xml` (через `.htaccess` редиректится на `sitemap.php`)
- **Файл:** `sitemap.php`
- **Включает:** главные страницы + все available properties + active new_buildings
- **Лимит:** 5000 properties, 2000 ЖК (поднять можно)

### 8.2 robots.txt ✅
- **Файл:** `robots.txt`
- Блокирует /admin/, /agent/, /includes/, /php/, /oauth/, dashboard.php, chat.php и т.д.
- Разрешает индексировать каталог, отдельные объекты, новостройки, аналитику.
- `Crawl-delay: 1` для YandexBot.

### 8.3 JSON-LD Schema.org ✅ *(v2.1)*
- **Где:** `property.php`
- **Schema:** `Residence` или `Apartment` (зависит от listing_type)
- **Поля:** name, description, image[], numberOfRooms, floorSize (м² = MTK), address (PostalAddress), offers (Offer с RUB и LeaseOut/Sell), geo (если есть lat/lng), aggregateRating
- **Open Graph:** дублирует key поля для шаринга в соцсети.

### 8.4 Canonical URLs ✅
- На `property.php`, `analytics.php` — `<link rel="canonical">`
- **TODO:** на `properties.php` (с учётом параметров фильтра).

### 8.5 ЧПУ URLs ✅
- `.htaccess` правила: `/property/{id}` → `property.php?id={id}` (slug опционален)
- **TODO:** реально использовать `slug` колонку (миграция `006_add_property_slug.sql`) в ссылках.

---

## 9. Email

### 9.1 Mailer ✅ *(v2.1)*
- **Файл:** `includes/email/Mailer.php`
- **Транспорты:** `smtp` (нативный SMTP-клиент через fsockopen + AUTH LOGIN + TLS), `mail` (default), `log` (в `logs/mail.log`)
- **Конфиг:** через `.env` (`MAIL_TRANSPORT`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`)
- **Wrapper:** все письма получают единый HTML-шаблон с шапкой и футером.

### 9.2 Уведомления ✅
- **Где используется:**
  - `password_reset.php` → ссылка восстановления
  - `email_verification.php` → ссылка подтверждения
  - `php/chat/send_message.php` → если есть `send_notification.php::sendMessageNotification`
- **Legacy:** `php/email/send_notification.php` — старая логика. Постепенно мигрируем на `Mailer`.

### 9.3 Рассылка ✅
- **API:** `POST /php/newsletter/subscribe.php`
- **Таблица:** `newsletter_subscribers`
- **Форма:** футер на главной (`index.php`)

---

## 10. Админка и кабинет агента

### 10.1 Админка ✅
- **URL:** `/admin/`
- **Защита:** `requireAdmin()`
- **Разделы:**
  - `/admin/index.php` — дашборд со статистикой (3 запроса вместо 7 после v2.1)
  - `/admin/properties.php` + `/admin/property-edit.php` — объекты
  - `/admin/new-buildings.php` + `/admin/new-building-edit.php` — ЖК
  - `/admin/developers.php` + `/admin/developer-applications.php` — застройщики
  - `/admin/agencies.php` — агентства
  - `/admin/districts.php` — справочник районов
  - `/admin/moderate_reviews.php` — модерация отзывов
  - `/admin/inquiries.php` — заявки
- **Шаблонные include:** `admin/includes/admin-header.php`, `admin-sidebar.php`
- **CSS:** `css/admin.css`

### 10.2 Кабинет агента ✅
- **URL:** `/agent/`
- **Защита:** `requireAgent()`
- **Разделы:**
  - `/agent/dashboard.php`
  - `/agent/add-property.php` / `edit-property.php` (drag&drop загрузка → ⏳)
  - `/agent/new-buildings.php` / `new-building-edit.php`
  - `/agent/calendar.php` — расписание показов
  - `/agent/requests.php` — входящие заявки
  - `/agent/reviews.php` — отзывы по агенту
  - `/agent/developers.php` / `districts.php` — справочники
- **Include:** `agent/includes/agent-header.php`, `agent-sidebar.php`
- **CSS:** `css/agent-dashboard.css`

---

## 11. Безопасность

| Угроза | Защита | Файл |
|--------|--------|------|
| SQL Injection | Prepared statements везде | все *.php |
| XSS | `escape()` (= `htmlspecialchars`) на выводе. **Не** на ввод (после фикса v2.1) | `includes/config/database.php` |
| CSRF (form POST) | `generateCSRFToken()` / `validateCSRFToken()` | `includes/auth/check_auth.php` |
| CSRF (JSON API) | `SameSite=Lax` + 🔧 TODO: X-CSRF-Token header в favorites/chat | — |
| Session fixation | `session_regenerate_id(true)` после login | `includes/auth/login.php` |
| Session hijacking | `cookie_httponly`, `cookie_secure` (на HTTPS), `cookie_samesite=Lax`, `use_strict_mode` | `includes/auth/check_auth.php` |
| Idle hijack | Idle timeout 2 часа | `includes/auth/check_auth.php` |
| Brute force login | `sleep(1)` после ошибки + 🔧 TODO: `users.failed_login_attempts` + `locked_until` (поля уже в миграции 020) | `includes/auth/login.php` |
| Password reuse | `password_hash(BCRYPT, cost=12)` + reset через email | везде |
| Open redirect | safe-relative-URL check (`/`-prefix без `//`) | `login.php`, `oauth_helper.php` |
| File upload | MIME + ext check + uniqid filenames | `agent/add-property.php` |
| Clickjacking | `X-Frame-Options: SAMEORIGIN` | `.htaccess` |
| Sniffing | `X-Content-Type-Options: nosniff`, HSTS на HTTPS | `.htaccess` |

---

## 12. Конфигурация и инфраструктура

### 12.1 `.env` ✅
- **Файлы:** `.env.example` (в репо), `.env`/`.env.local` (gitignored)
- **Хелпер:** `includes/config/Config.php`
  - `Config::get('KEY', $default)` — с поддержкой `${OTHER_VAR}` интерполяции
  - `Config::bool()`, `Config::isProd()`, `Config::appUrl()`
- **Группы:** App, DB, SMTP, OAuth (VK/Yandex/Google), Telegram, VAPID

### 12.2 .htaccess ✅ *(v2.1)*
- mod_rewrite: ЧПУ, sitemap.xml → sitemap.php
- mod_deflate: gzip для text/css/js/json/xml/svg
- mod_expires: 1 год для картинок, 1 месяц для CSS/JS
- mod_headers: X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS
- `Service-Worker-Allowed: /` для `sw.js`

### 12.3 Миграции БД 🟡
- **Папка:** `database/migrations/`
- **Старые:** `006_add_property_slug.sql`, `010_property_history.sql`, `011_analytics_events.sql`
- **Новые (v2.1):**
  - `020_auth_extensions.sql` — email verification, OAuth поля, password_resets
  - `021_web_push_subscriptions.sql`
- **Применение:**
  ```bash
  mysql -u root realestate_db < database/migrations/020_auth_extensions.sql
  mysql -u root realestate_db < database/migrations/021_web_push_subscriptions.sql
  ```
- **TODO:** простой migration runner с таблицей `migrations(name, applied_at)`.

### 12.4 Логи ✅
- `logs/contacts.log` — контактные формы
- `logs/inquiries.log` — заявки
- `logs/mail.log` — письма (только при `MAIL_TRANSPORT=log`)
- PHP: `error_log()` в стандартный лог OSPanel/Apache

---

## 13. JavaScript модули

| Файл | Назначение | Зависит от |
|------|-----------|------------|
| `js/navigation.js` | Гамбургер-меню, sticky header | — |
| `js/main.js` | Общие init'ы | — |
| `js/favorites.js` | Toggle избранного, toast | `/includes/favorites/*` |
| `js/chat.js` | Polling-чат | `/php/chat/*` |
| `js/filters.js` | Фильтры на `properties.php` | — |
| `js/form.js` | Валидация форм | — |
| `js/reviews.js` | Звёзды рейтинга | `/php/reviews/*` |
| `js/compare.js` | Сравнение | — |
| `js/autocomplete.js` | **(v2.1)** Поисковый автокомплит | `/php/search/autocomplete.php` |
| `js/mortgage.js` | **(v2.1)** Ипотечный калькулятор | — |
| `js/pwa.js` | **(v2.1)** Регистрация SW + Web Push | `/sw.js`, `/php/push/*` |

---

## 14. Бэклог / запланированные фичи ⏳

### Высокий приоритет
- **Saved searches + email-алерты** (#3) — таблица `saved_searches` + cron, отправляет дайджест новых объектов под критерии.
- **2FA для агентов/админов** (#14) — TOTP, поле `users.totp_secret`.
- **Карты Яндекс на property.php / map view для properties.php** (#4/#5) — `<script src="api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=...">`.

### Средний приоритет
- **Импорт ЦИАН / Авито (XML-feed по cron)** (#8)
- **Drag&drop загрузка фотографий** в agent/add-property.php
- **ImageProcessor** — генерация 400×300 / 800×600 thumbs + WebP (метод `getImageThumb` в `database.php` ссылается на пути, которых пока никто не создаёт)
- **AJAX-фильтры + History API** на `properties.php`
- **SSE для чата** вместо polling (когда нагрузка вырастет)
- **CSRF на JSON-эндпоинты** — `X-CSRF-Token` из мета-тега

### Низкий приоритет
- **PHPMailer / Symfony Mailer через Composer** — заменить нативный SMTP-клиент в `Mailer.php`
- **Calc расходов на содержание квартиры** (ЖКХ + налог) (#18)
- **Telegram-бот для агентов** (#9) — уведомления о новых заявках через `python-telegram-bot` или PHP-curl
- **PWA install prompt UI** + кнопка «получать уведомления» в дашборде
- **Migration runner** с трекингом применённых миграций
- **Тесты** (PHPUnit + Pest для смоук-тестов критичных эндпоинтов)

---

## 15. Быстрый старт после клонирования

```bash
# 1. БД
mysql -u root < database/create_database.sql
mysql -u root realestate_db < database/migrations/020_auth_extensions.sql
mysql -u root realestate_db < database/migrations/021_web_push_subscriptions.sql

# 2. .env
cp .env.example .env
# отредактировать DB_*, MAIL_*, опционально VK/YANDEX/GOOGLE/TELEGRAM/VAPID

# 3. (опционально) VAPID для Web Push
npx web-push generate-vapid-keys
# в .env: VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY

# 4. OSPanel → запустить проект, открыть http://elsesserandco-site.local
# 5. Sanity check
php test_db.php
```

### Тестовые аккаунты

| Роль | Email | Пароль |
|------|-------|--------|
| Admin | admin@elsesserandco.com | password123 |
| Agent | agent@elsesserandco.com | password123 |
| User | user@example.com | password123 |

---

## 15.1 CI/CD на VPS *(v2.1)*

Автодеплой настроен через **GitHub Actions → SSH → `git pull`** на VPS.
Workflow: `.github/workflows/deploy.yml`.
Триггеры: `push` в `main` + ручной запуск (`workflow_dispatch`).

### Что делает workflow (6 шагов)
1. SSH в VPS под deploy-юзером.
2. `mysqldump` БД → `/var/backups/elsesserandco/db-<timestamp>.sql.gz` (ротация 14 дней).
3. `git fetch && git reset --hard origin/main` + `git clean -fd` с исключениями для `.env`, `logs/`, `cache/`, `session/`, `uploads/`, `images/uploads/`.
4. `php database/migrate.php` — идемпотентно накатывает новые миграции.
5. Чинит права: владелец `deploy:www-data`, директории 775, файлы 664, `.env` 600. Папки на запись (`logs/`, `cache/`, `uploads/`) — 775 рекурсивно.
6. `systemctl reload php8.1-fpm` (если активен) + `systemctl reload apache2` — для сброса opcache.

Дополнительно: smoke-test (GET `/`, ожидаем 200/301/302).

### Подготовка VPS (один раз)

```bash
# 1. Превращаем существующую папку в git-репо (если ещё не)
cd /var/www/elsesserandco-site.local
sudo -u www-data git init
sudo -u www-data git remote add origin https://github.com/eveiljuice/elsesserandco-website.git
sudo -u www-data git fetch origin
sudo -u www-data git reset --hard origin/main   # ⚠ затирает несохранённые локальные правки

# 2. Deploy-юзер
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG www-data deploy
sudo chown -R deploy:www-data /var/www/elsesserandco-site.local

# 3. SSH-ключ для GitHub Actions
sudo -u deploy mkdir -p /home/deploy/.ssh
sudo -u deploy ssh-keygen -t ed25519 -f /home/deploy/.ssh/github_actions -N ""
sudo -u deploy bash -c 'cat /home/deploy/.ssh/github_actions.pub >> /home/deploy/.ssh/authorized_keys'
sudo -u deploy chmod 600 /home/deploy/.ssh/authorized_keys
sudo cat /home/deploy/.ssh/github_actions   # ← в Secret VPS_SSH_KEY

# 4. Sudoers (без пароля только на нужные команды)
sudo tee /etc/sudoers.d/deploy <<'EOF'
deploy ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.1-fpm
deploy ALL=(ALL) NOPASSWD: /bin/systemctl reload apache2
deploy ALL=(ALL) NOPASSWD: /bin/systemctl is-active php8.1-fpm
deploy ALL=(ALL) NOPASSWD: /bin/chown -R deploy\:www-data /var/www/elsesserandco-site.local*
deploy ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/backups/elsesserandco
deploy ALL=(ALL) NOPASSWD: /bin/chown deploy\:deploy /var/backups/elsesserandco
deploy ALL=(ALL) NOPASSWD: /bin/chmod -R 775 /var/www/elsesserandco-site.local/*
EOF
sudo chmod 440 /etc/sudoers.d/deploy

# 5. .env на VPS (НЕ в git, лежит только локально на сервере)
sudo -u deploy cp .env.example .env
sudo -u deploy nano .env   # заполнить REALESTATE_DB_*, MAIL_*, APP_URL и т.д.
sudo chmod 600 .env

# 6. Первый прогон миграционного runner-а: пометить уже накатанные SQL
cd /var/www/elsesserandco-site.local
sudo -u deploy php database/migrate.php --mark 006_add_property_slug.sql
sudo -u deploy php database/migrate.php --mark 010_property_history.sql
sudo -u deploy php database/migrate.php --mark 011_analytics_events.sql
# Если 020/021 ещё НЕ накатаны на проде — НЕ помечай, дай runner-у их применить:
sudo -u deploy php database/migrate.php   # применит 020 и 021
```

### GitHub Secrets (Settings → Secrets → Actions)

| Secret | Значение |
|--------|----------|
| `VPS_HOST` | IP или домен VPS |
| `VPS_USER` | `deploy` |
| `VPS_PORT` | `22` (или твой нестандартный) |
| `VPS_SSH_KEY` | приватный ключ из `/home/deploy/.ssh/github_actions` (целиком, включая BEGIN/END) |
| `VPS_PATH` | `/var/www/elsesserandco-site.local` |
| `SMOKE_URL` | (опц.) `elsesserandco.com` — если домен ≠ `VPS_HOST` |

### Ручной запуск
GitHub → Actions → **Deploy to VPS** → Run workflow → выбрать `main`.

### Откат
```bash
# На VPS
cd /var/www/elsesserandco-site.local
git log --oneline -10                       # найти нужный коммит
git reset --hard <commit-hash>
sudo systemctl reload php8.1-fpm
sudo systemctl reload apache2
# БД при необходимости — из бэкапа:
gunzip < /var/backups/elsesserandco/db-YYYY-MM-DD-HHMMSS.sql.gz | mysql -u root realestate_db
```

### Миграционный runner
- **Файл:** `database/migrate.php`
- **Команды:**
  - `php database/migrate.php` — применить непрокаченные
  - `php database/migrate.php --status` — список применённых/нет
  - `php database/migrate.php --mark <file.sql>` — пометить применённой без выполнения
- **Таблица:** `migrations (name PK, applied_at)` — создаётся автоматически при первом запуске.
- **Транзакция** на каждую миграцию (rollback при ошибке).

---

## 16. Карта файлов

```
.
├── /admin/                Админ-панель (requireAdmin)
├── /agent/                Кабинет агента (requireAgent)
├── /oauth/                v2.1: VK / Yandex / Google / Telegram (start.php + callback.php)
├── /includes/
│   ├── auth/              login, register, check_auth, password_reset, email_verification, oauth_helper
│   ├── config/            database.php, Config.php
│   ├── email/             Mailer.php
│   ├── push/              WebPush.php, Notifier.php  (v2.1)
│   ├── favorites/         toggle/add/remove (canonical)
│   ├── inquiries/         submit
│   ├── repository/        PropertyRepository.php (заявлен, не реализован)
│   ├── upload/            ImageProcessor.php (заявлен, не реализован)
│   ├── seo/               SeoHelper.php (заявлен, не реализован)
│   ├── analytics/         Analytics.php (заявлен, не реализован)
│   ├── audit/             AuditLog.php (заявлен, не реализован)
│   ├── validation/        Validator.php (заявлен, не реализован)
│   ├── footer.php, mobile-menu.php
│   └── email/templates/   HTML-шаблоны (legacy)
├── /php/                  API endpoints
│   ├── auth/              resend_verification.php
│   ├── chat/              send_message, get_messages, mark_read
│   ├── push/              subscribe, unsubscribe  (v2.1)
│   ├── reviews/           add_review, get_reviews
│   ├── search/            autocomplete
│   ├── newsletter/        subscribe
│   ├── favorites/         (удалены в v2.1 — каноничные в /includes/favorites)
│   └── email/             send_notification (legacy)
├── /css/                  reset, variables, style, responsive, admin, agent-dashboard, auth, chat, dashboard
├── /js/                   см. §13
├── /database/             create_database.sql + миграции
├── /images/               favicon.png, лого, hero
├── /logs/                 contacts.log, inquiries.log, mail.log
├── /prompts/              phase1-4.md (история разработки)
├── /.cursor/              AGENTS.md, commands/
│
├── index.php              Главная
├── properties.php         Каталог
├── property.php           Карточка объекта (1400+ строк — кандидат на сплит)
├── new-buildings.php      Каталог ЖК
├── new-building.php       Карточка ЖК
├── analytics.php          v2.1: аналитика цен
├── login.php / register.php / logout.php
├── forgot-password.php / reset-password.php / verify-email.php  (v2.1)
├── register-developer.php / register-agency.php
├── dashboard.php          Личный кабинет user
├── chat.php               Чат
├── favorites.php          Список избранного
├── compare.php            Сравнение
├── about.html / contact.html
├── sitemap.php            v2.1: sitemap
├── robots.txt             v2.1
├── manifest.webmanifest   v2.1: PWA
├── sw.js                  v2.1: Service Worker
├── offline.html           v2.1: PWA offline fallback
├── .htaccess              v2.1: rewrite + headers + compression
├── .env / .env.example
├── test_db.php            Диагностика БД
└── FEATURES.md            ← вы здесь
```
