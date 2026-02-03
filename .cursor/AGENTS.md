# AGENTS.md

## Project Overview
**Elsesser & Co. Real Estate Website** — полнофункциональный сайт агентства недвижимости для Екатеринбурга с системой управления объектами, пользователями и взаимодействием клиент-агент.

- **Tech Stack**: PHP 8.1, MySQL 8.0, Vanilla JavaScript, CSS3
- **Database**: `realestate_db` (MySQL с PDO)
- **Server**: OSPanel (локальная разработка на Windows)
- **Architecture**: MVC-подобная структура с разделением на includes/php директории

### Основные модули:
- 🏠 **Готовое жильё (Продажа)** — каталог квартир на продажу в Екатеринбурге
- 🔑 **Готовое жильё (Аренда)** — каталог квартир в аренду
- 🏗️ **Новостройки (ЖК)** — каталог жилых комплексов от застройщиков
- 👥 **Multi-role System** — user, agent, admin с разными правами доступа
- 💬 **Chat** — внутренний мессенджер между клиентами и агентами
- ⭐ **Favorites** — избранные объекты пользователей
- 📊 **Dashboards** — личные кабинеты для каждой роли
- 📝 **Inquiries & Viewings** — заявки на просмотры и запросы
- ⭐ **Reviews** — система отзывов с модерацией

---

## Dev Environment Tips

### Требования:
- **PHP**: 7.4+ (рекомендуется 8.0+)
- **MySQL**: 5.7+ или MariaDB 10.3+
- **Web Server**: Apache 2.4+ (с mod_rewrite)
- **OSPanel** или аналогичный локальный сервер

### Setup Commands:

```bash
# 1. Клонировать проект в папку OSPanel domains
cd C:\OSPanel\domains\
git clone <repo> elsesserandco-site

# 2. Импортировать базу данных
# Открыть phpMyAdmin или командой:
mysql -u root < database/create_database.sql

# 3. Настроить database.php (если нужно)
# Файл: includes/config/database.php
# DB_HOST = 'localhost'
# DB_NAME = 'realestate_db'
# DB_USER = 'root'
# DB_PASS = ''

# 4. Запустить OSPanel и открыть:
http://elsesserandco-site/
```

### Структура проекта:
```
├── /admin/           # Админ-панель (роль: admin)
├── /agent/           # Кабинет агента (роль: agent)
├── /includes/        # Backend логика (auth, config, favorites, inquiries)
├── /php/             # Дублирующая структура для API endpoints
├── /css/             # Стили (reset, variables, style, responsive, роль-специфичные)
├── /js/              # Клиентские скрипты (navigation, favorites, chat, filters)
├── /images/          # Статические изображения (логотипы, команда, свойства)
├── /database/        # SQL скрипты (создание БД, миграции)
├── *.php             # Публичные страницы (index, properties, property, login, etc.)
└── *.html            # Статические страницы (about, contact)
```

---

## Build & Run Commands

### Запуск локального сервера:
```bash
# OSPanel: запустить через GUI
# Или использовать встроенный PHP сервер (не рекомендуется):
php -S localhost:8000
```

### Проверка PHP синтаксиса:
```bash
# Рекурсивная проверка всех PHP файлов
find . -name "*.php" -exec php -l {} \;
```

### База данных:
```bash
# Создать/пересоздать БД
mysql -u root < database/create_database.sql

# Обновить валюту на RUB
mysql -u root realestate_db < database/update_currency_to_rub.sql

# Исправить поисковый индекс (если поиск не работает)
mysql -u root realestate_db < database/fix_search_index.sql

# Информация о единицах площади (м² vs sqft)
# См. database/area_sqft_info.sql
```

### Логи:
- **Contacts**: `logs/contacts.log`
- **Inquiries**: `logs/inquiries.log`
- **PHP Errors**: проверять в OSPanel logs или `error_log()`

---

## Testing Instructions

### Тестовые учетные записи:
```
Admin:
  Email: admin@elsesserandco.com
  Password: password123

Agent:
  Email: agent@elsesserandco.com
  Password: password123

User:
  Email: user@example.com
  Password: password123
```

### Manual Testing Flow:

#### 1. Публичная часть:
- [ ] Открыть `index.php` — hero, поиск, featured properties, районы
- [ ] Перейти в `properties.php` — фильтры (тип, цена, спальни, площадь)
- [ ] Открыть `property.php?id=1` — детальная карточка, галерея, удобства
- [ ] Проверить `about.html` и `contact.html` — статические страницы
- [ ] Зарегистрироваться через `register.php`
- [ ] Войти через `login.php`

#### 2. User Dashboard (`dashboard.php`):
- [ ] Проверить личную информацию, избранное
- [ ] Добавить/удалить избранное (JS: favorites.js)
- [ ] Сравнить объекты через `compare.php`
- [ ] Отправить сообщение агенту через `chat.php`

#### 3. Agent Dashboard (`agent/dashboard.php`):
- [ ] Создать объект через `agent/add-property.php`
- [ ] Редактировать объект через `agent/edit-property.php`
- [ ] Проверить календарь просмотров `agent/calendar.php`
- [ ] Ответить на запросы в `agent/requests.php`

#### 4. Admin Panel (`admin/index.php`):
- [ ] Управлять пользователями
- [ ] Модерировать объекты в `admin/properties.php`
- [ ] Модерировать отзывы в `admin/moderate_reviews.php`
- [ ] Просмотреть заявки в `admin/inquiries.php`

#### 5. API Endpoints (AJAX):
- [ ] `/php/favorites/toggle.php` — добавить/удалить избранное
- [ ] `/php/chat/send_message.php` — отправка сообщений
- [ ] `/php/reviews/add_review.php` — добавление отзыва
- [ ] `/php/newsletter/subscribe.php` — подписка на рассылку

### CI/CD:
- **Нет автоматизированных тестов** (ручное тестирование)
- При добавлении новых функций тестировать все роли (user/agent/admin)

---

## Code Style Guidelines

### PHP:
- **File Encoding**: UTF-8 without BOM
- **PHP Tags**: Всегда `<?php`, закрывающий тег `?>` только если нужен HTML после
- **Naming**:
  - Functions: `camelCase()` (e.g., `getUserData()`, `formatPrice()`)
  - Variables: `$snake_case` или `$camelCase` (смешанно в проекте)
  - Classes: `PascalCase` (если будут добавлены)
- **Database**:
  - Всегда использовать prepared statements (PDO)
  - `getDBConnection()` для получения синглтон-соединения
  - `escape()` для вывода в HTML
  - `formatPrice()` для форматирования цен в RUB
- **Security**:
  - Всегда проверять `$_SESSION['user_id']` для защищенных страниц
  - Использовать `password_hash()` / `password_verify()` для паролей
  - Экранировать все выводы в HTML через `htmlspecialchars()`

### CSS:
- **Методология**: BEM-подобная (block__element--modifier)
- **Variables**: CSS Custom Properties в `css/variables.css`
- **Breakpoints**:
  - Mobile: 576px
  - Tablet: 768px
  - Desktop: 992px
  - Large: 1200px
- **Files**:
  - `reset.css` — сброс браузерных стилей
  - `variables.css` — CSS переменные (цвета, spacing, шрифты)
  - `style.css` — основные стили сайта
  - `responsive.css` — медиа-запросы
  - `admin.css`, `agent-dashboard.css`, `auth.css`, `chat.css`, `dashboard.css` — роль-специфичные стили

### JavaScript:
- **Style**: Vanilla JS, ES6+ (стрелочные функции, async/await, fetch)
- **Naming**:
  - Variables/Functions: `camelCase`
  - Constants: `UPPER_SNAKE_CASE`
- **Files**:
  - `navigation.js` — мобильное меню, header scroll
  - `favorites.js` — добавление/удаление избранного
  - `chat.js` — real-time сообщения
  - `filters.js` — фильтрация объектов на странице properties
  - `form.js` — валидация форм
  - `reviews.js` — система рейтингов
- **API Requests**: использовать `fetch()` с обработкой ошибок
- **Event Handlers**: использовать делегирование событий где возможно

### Database:
- **Tables**: `snake_case` (e.g., `property_images`, `newsletter_subscribers`)
- **Columns**: `snake_case`
- **Foreign Keys**: всегда с `ON DELETE CASCADE` или `SET NULL`
- **Indexes**: добавлять для часто используемых WHERE/JOIN колонок

---

## Git & PR Instructions

### Branch Naming:
```
feature/описание-фичи
bugfix/описание-бага
hotfix/критическая-проблема
refactor/что-рефакторим
```

Примеры:
- `feature/add-property-comparison`
- `bugfix/fix-favorite-toggle`
- `hotfix/fix-login-redirect`

### Commit Message Format:
```
<type>: <краткое описание>

[опционально] Детальное описание изменений
```

**Types**:
- `feat` — новая функциональность
- `fix` — исправление бага
- `refactor` — рефакторинг без изменения функциональности
- `style` — изменения в CSS/стилях
- `docs` — обновление документации
- `db` — изменения в структуре БД

Примеры:
```
feat: добавлена страница сравнения объектов (compare.php)

fix: исправлен баг с удалением из избранного при неавторизованном пользователе

db: добавлена таблица viewings для календаря просмотров

style: обновлен дизайн карточек объектов на мобильных
```

### Pre-commit Checks:
- [ ] Проверить PHP синтаксис: `php -l file.php`
- [ ] Проверить SQL инъекции (использовать prepared statements)
- [ ] Протестировать измененную функциональность вручную
- [ ] Проверить responsive design (если менял CSS)
- [ ] Убедиться, что нет `console.log()` или `var_dump()` в коде

### Code Review Checklist:
- [ ] Код соответствует стилю проекта
- [ ] Нет SQL инъекций и XSS уязвимостей
- [ ] Все пользовательские данные экранированы
- [ ] Проверены права доступа для ролей
- [ ] Нет дублирующегося кода (DRY principle)
- [ ] Добавлены комментарии для сложной логики
- [ ] Обновлены SQL миграции (если изменялась БД)

---

## Security & Best Practices

### Authentication & Authorization:
- **Session Management**:
  - `includes/auth/check_auth.php` — проверка авторизации
  - Всегда вызывать `session_start()` в начале защищенных страниц
  - Хранить `user_id`, `role`, `email` в сессии
- **Role Checks**:
  ```php
  // Для агентов
  if ($user['role'] !== 'agent') {
      header('Location: /dashboard.php');
      exit;
  }
  
  // Для админов
  if ($user['role'] !== 'admin') {
      http_response_code(403);
      die('Access denied');
  }
  ```

### Input Validation:
```php
// Всегда проверять входные данные
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
$price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);

// Для строк использовать trim() и валидацию длины
$name = trim($_POST['name']);
if (strlen($name) < 2 || strlen($name) > 100) {
    die('Invalid name length');
}

// ⚠️ ВАЖНО: ENUM поля должны быть NULL, а не пустой строкой
// Пустая строка из формы (value="") НЕ становится null с ??
$roomsType = ($_POST['rooms_type'] ?? null) ?: null;  // ✅ ПРАВИЛЬНО
$renovation = ($_POST['renovation'] ?? null) ?: null;  // ✅ ПРАВИЛЬНО

// ❌ НЕПРАВИЛЬНО (вызовет "Data truncated" для ENUM)
$roomsType = $_POST['rooms_type'] ?? null;  // пустая строка станет ''
```

### SQL Injection Prevention:
```php
// ✅ ПРАВИЛЬНО — Prepared Statements
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

// ❌ НЕПРАВИЛЬНО — Прямая подстановка
$query = "SELECT * FROM users WHERE email = '$email'"; // НЕ ДЕЛАТЬ ТАК!
```

### XSS Prevention:
```php
// ✅ ПРАВИЛЬНО — Экранирование
echo escape($property['title']); // использует htmlspecialchars()

// ❌ НЕПРАВИЛЬНО — Прямой вывод
echo $property['title']; // НЕ ДЕЛАТЬ ТАК!
```

### File Uploads:
```php
// Проверять И MIME type И расширение (для надежности)
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
$allowedExtensions = ['jpg', 'jpeg', 'jfif', 'jpe', 'png', 'webp'];

$fileType = strtolower($_FILES['image']['type']);
$extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

if (!in_array($fileType, $allowedMimeTypes) && !in_array($extension, $allowedExtensions)) {
    die('Invalid file type');
}

// Конвертировать JFIF в JPG для совместимости
if ($extension === 'jfif') {
    $extension = 'jpg';
}

// Генерировать уникальные имена файлов
$newName = 'property_' . $id . '_' . uniqid() . '_' . time() . '.' . $extension;
```

### Performance Considerations:
- **Pagination**: использовать `LIMIT` и `OFFSET` для больших списков
- **Image Optimization**: использовать WebP и lazy loading
- **Database Indexes**: добавлять индексы для часто используемых колонок
- **Caching**: рассмотреть кеширование для статичных данных

### Logging:
```php
// Для важных событий
error_log("User {$user['id']} created property {$propertyId}");

// Для ошибок
error_log("Database error: " . $e->getMessage());

// Не логировать чувствительные данные (пароли, токены)
```

### Error Handling:
```php
// Production
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Development
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

---

## Common Tasks & Patterns

### Добавление нового объекта недвижимости:
1. Заполнить русские поля: `title_ru`, `description_ru` (обязательно)
2. Поля `title` и `description` автозаполнятся автоматически из русских
3. Загрузка изображений → `property_images` (первое = главное)
4. Привязка удобств → `property_amenities`
5. Площадь указывать в **квадратных метрах (м²)**, не в sqft

### Система избранного:
- **Add**: `INSERT IGNORE INTO favorites`
- **Remove**: `DELETE FROM favorites WHERE user_id = ? AND property_id = ?`
- **Check**: `SELECT COUNT(*) FROM favorites WHERE...`
- **JS Handler**: `favorites.js` (toggle через AJAX)

### Отправка сообщений:
1. POST на `/php/chat/send_message.php`
2. Вставка в `messages` таблицу
3. Real-time обновление через polling (setInterval в `chat.js`)
4. Уведомления для получателя

### Добавление новой роли пользователя:
1. Обновить ENUM в таблице `users.role`
2. Создать папку `/new-role/` с dashboard
3. Добавить проверки в `check_auth.php`
4. Создать sidebar и header для новой роли

---

## Database Schema Quick Reference

### Main Tables:
- `users` — пользователи (user/agent/admin)
- `properties` — готовое жильё (продажа/аренда) с полями для Екатеринбурга
- `property_images` — фото объектов
- `new_buildings` — новостройки (ЖК)
- `new_building_images` — фото ЖК
- `new_building_layouts` — планировки ЖК (студии, 1-комн, 2-комн и т.д.)
- `ekb_districts` — районы Екатеринбурга
- `developers` — застройщики
- `infrastructure_items` — справочник инфраструктуры
- `amenities` — удобства квартир
- `property_amenities` — связь объектов и удобств (M:N)
- `favorites` — избранное пользователей
- `inquiries` — заявки/запросы
- `messages` — чат между пользователями
- `reviews` — отзывы на объекты/агентов
- `viewings` — календарь просмотров
- `newsletter_subscribers` — подписчики рассылки

### Key Relationships:
```
users (1) ─── (N) properties [agent_id]
users (1) ─── (N) favorites [user_id]
properties (1) ─── (N) property_images [property_id]
properties (N) ─── (M) amenities [property_amenities]
users (1) ─── (N) messages [sender_id, receiver_id]
properties (1) ─── (N) reviews [property_id]
```

---

## Troubleshooting

### Проблема: "Connection refused" к базе данных
```bash
# Решение:
1. Проверить, запущен ли MySQL в OSPanel
2. Проверить credentials в includes/config/database.php
3. Убедиться, что база realestate_db существует
```

### Проблема: Сессия не сохраняется
```php
// Проверить:
1. session_start() вызван в начале файла
2. Нет вывода до session_start()
3. Права на папку session.save_path
```

### Проблема: Изображения не загружаются
```bash
# Проверить:
1. Права на папку images/properties/ и uploads/properties/
2. upload_max_filesize и post_max_size в php.ini
3. GD/Imagick расширение включено
4. Для JFIF файлов — проверить, что расширение .jfif разрешено (конвертируется в .jpg)
```

### Проблема: JavaScript не работает
```javascript
// Открыть Dev Tools (F12) → Console
// Проверить ошибки CORS, 404, синтаксис
```

### Проблема: Поиск не находит добавленные объекты
```bash
# Решение:
1. Проверить, применена ли миграция fix_search_index.sql
2. Убедиться, что заполнены поля title_ru, location, community
3. Проверить query в phpMyAdmin:
   SELECT * FROM properties WHERE title_ru LIKE '%название%';
4. Применить миграцию:
   mysql -u root realestate_db < database/fix_search_index.sql
```

### Проблема: "Data truncated for column 'rooms_type'" при сохранении объекта
```php
// Причина: Пустая строка из формы пытается вставиться в ENUM колонку
// Решение: Конвертировать пустую строку в NULL

// ❌ НЕПРАВИЛЬНО
$roomsType = $_POST['rooms_type'] ?? null;

// ✅ ПРАВИЛЬНО
$roomsType = ($_POST['rooms_type'] ?? null) ?: null;

// Применить для всех ENUM полей:
// rooms_type, bathroom_type, renovation, balcony, window_view, 
// house_type, metro_station, rent_commission_type
```

---

## Useful Links & Resources

- **Project Phases**: `/prompts/` — документация по фазам разработки (phase1-4)
- **References**: `/references/` — UI/UX референсы и скриншоты
- **phpMyAdmin**: `http://localhost/openserver/?page=phpmyadmin` (OSPanel)
- **PHP Docs**: https://www.php.net/manual/en/
- **MDN CSS**: https://developer.mozilla.org/en-US/docs/Web/CSS
- **Font Awesome Icons**: https://fontawesome.com/icons

---

## Notes

- **Currency**: Проект изначально создавался для Dubai (AED), затем адаптирован для Екатеринбурга (RUB). Некоторые данные могут содержать остатки AED/Dubai контекста.
- **Area Units**: Поле `area_sqft` в БД теперь хранит площадь в **квадратных метрах (м²)**, а не в sqft. Название колонки сохранено для обратной совместимости. См. `database/area_sqft_info.sql` для деталей.
- **Language**: Сайт полностью на русском. Поля `title` и `description` (EN) автоматически заполняются из `title_ru` и `description_ru` для обратной совместимости БД.
- **No Framework**: Чистый PHP без фреймворков (Laravel/Symfony). Минимальные зависимости.
- **No Package Manager**: Нет Composer. Все зависимости (Font Awesome, Google Fonts) подключены через CDN.
- **Production Deployment**: Для прода потребуется настроить:
  - HTTPS и SSL сертификаты
  - Email SMTP для уведомлений
  - Оптимизацию изображений и minification CSS/JS
  - Резервное копирование БД

---

**Last Updated**: 2025-12-15  
**Version**: 2.0  
**Maintainer**: Development Team

---

## CRM Refactoring v2.0 (Екатеринбург)

### Новая структура CRM:
1. **Готовое жильё (Продажа)** — `/admin/properties.php?category=sale`
2. **Готовое жильё (Аренда)** — `/admin/properties.php?category=rent`
3. **Новостройки (ЖК)** — `/admin/new-buildings.php`

### Поля для готового жилья:
- Адрес: улица, дом, район Екатеринбурга
- Площади: общая, жилая, кухня (м²)
- Комнаты: количество, планировка (изолированные/смежные)
- Этаж / этажность дома
- Санузел: тип, количество
- Балкон/лоджия: тип, количество
- Ремонт: дизайнерский, евро, косметический и т.д.
- Характеристики дома: тип, год постройки, лифт, мусоропровод
- Транспорт: метро Екатеринбурга, минуты пешком/на транспорте
- Для аренды: залог, предоплата, КУ, условия проживания

### Поля для новостроек (ЖК):
- Застройщик, район
- Сроки сдачи: квартал, год, стадия строительства
- Цены: от, за м²
- Характеристики: этажность, секции, паркинг, отделка
- Планировки: студии, 1-4 комн. с ценами и площадями
- Описания: о доме, о районе, преимущества
- Медиа: фото, видео, 3D-тур

### Миграция БД:
```bash
mysql -u root realestate_db < database/ekb_migration.sql
```

