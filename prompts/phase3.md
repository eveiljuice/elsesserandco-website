Вот полный промпт для Cursor IDE для реализации **Фазы 3** вашего real estate приложения на HTML, CSS, JS, PHP с сессиями:

***

# 📋 Промпт: Реализация Фазы 3 (CRM, чат, email-уведомления, отзывы)

## Цель
Добавить бизнес-функционал для агентов и пользователей: CRM для управления объектами и клиентами, внутренний чат, email-уведомления, систему отзывов и рейтингов на ванильном PHP с сессиями.

***

## 🎯 Функции для внедрения

### 1. **CRM для агентов недвижимости**

Создай отдельный раздел для пользователей с ролью `agent`:

**Функционал агента:**
- Личный кабинет агента (`agent/dashboard.php`)
- Список своих объектов с фильтром по статусу (available/sold/rented/pending)
- Добавление нового объекта (форма с загрузкой до 10 фото)
- Редактирование/удаление своих объектов
- Календарь просмотров (таблица назначенных встреч с клиентами)
- Статистика: количество просмотров объектов, полученных заявок
- Список заявок на свои объекты с возможностью изменения статуса

**Проверка прав доступа:**
```php
// В начале каждой страницы агента
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'agent') {
    header("Location: /login.php");
    exit;
}
```

***

### 2. **Внутренний чат между пользователем и агентом**

Реализуй простой чат на PHP + AJAX + MySQL:

**Структура БД:**
```sql
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sender_id` INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `property_id` INT UNSIGNED,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sender_id`) REFERENCES users(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`receiver_id`) REFERENCES users(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`property_id`) REFERENCES properties(`id`) ON DELETE SET NULL,
  INDEX `idx_conversation` (`sender_id`, `receiver_id`),
  INDEX `idx_unread` (`is_read`)
);
```

**Файлы для реализации:**
- `chat.php` - интерфейс чата (список диалогов + окно сообщений)
- `php/chat/send_message.php` - отправка сообщения
- `php/chat/get_messages.php` - получение сообщений (AJAX polling каждые 3 сек)
- `php/chat/mark_read.php` - пометить сообщения как прочитанные
- `js/chat.js` - AJAX логика обновления чата

**Интерфейс чата:**
- Левая колонка: список диалогов с аватарами и последним сообщением
- Правая колонка: окно переписки с формой отправки
- Индикатор непрочитанных сообщений (badge с числом)

***

### 3. **Email-уведомления**

Используй встроенную PHP функцию `mail()` или PHPMailer для отправки писем:

**События для уведомлений:**
1. **Регистрация нового пользователя** - письмо приветствия
2. **Новая заявка на просмотр** - уведомление агенту и пользователю
3. **Новое сообщение в чате** - уведомление получателю (если не онлайн)
4. **Новый объект в избранной категории** - подписчикам по критериям
5. **Изменение статуса заявки** - уведомление пользователю

**Пример файла:** `php/email/send_notification.php`
```php
<?php
function sendEmail($to, $subject, $message) {
    $headers = "From: noreply@yoursite.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

function sendRegistrationEmail($email, $name) {
    $subject = "Добро пожаловать на наш сайт!";
    $message = "<h2>Здравствуйте, $name!</h2><p>Спасибо за регистрацию...</p>";
    return sendEmail($email, $subject, $message);
}

function sendRequestNotification($agent_email, $property_title, $client_name) {
    $subject = "Новая заявка на просмотр: $property_title";
    $message = "<p>Клиент <b>$client_name</b> запросил просмотр объекта.</p>";
    return sendEmail($agent_email, $subject, $message);
}
?>
```

**Добавь вызовы отправки email:**
- После регистрации в `php/auth/register.php`
- После создания заявки в `request.php`
- После отправки сообщения в `php/chat/send_message.php`

***

### 4. **Система отзывов и рейтингов**

Реализуй отзывы на объекты недвижимости и профили агентов:

**Структура БД:**
```sql
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `property_id` INT UNSIGNED,
  `agent_id` INT UNSIGNED,
  `rating` TINYINT(1) CHECK (rating BETWEEN 1 AND 5),
  `comment` TEXT,
  `is_approved` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES users(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`property_id`) REFERENCES properties(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`agent_id`) REFERENCES users(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_review` (`user_id`, `property_id`, `agent_id`)
);
```

**Функционал:**
- Форма добавления отзыва (только для авторизованных пользователей)
- Рейтинг 1-5 звёзд (HTML/CSS звёздочки)
- Модерация отзывов админом (is_approved = 1)
- Вывод средней оценки объекта/агента
- Список отзывов на странице объекта и профиле агента

**Файлы:**
- `php/reviews/add_review.php` - добавление отзыва
- `php/reviews/get_reviews.php` - получение отзывов
- `admin/moderate_reviews.php` - модерация (только для admin)

***

### 5. **Расширение личного кабинета пользователя**

Добавь в `dashboard.php` новые разделы:

- **История заявок** - все мои запросы на просмотр со статусами
- **Мои сообщения** - ссылка на чат, счётчик непрочитанных
- **Мои отзывы** - список оставленных отзывов
- **Настройки уведомлений** - подписка на email рассылку

***

### 6. **Подписка на рассылку**

**Таблица подписчиков:**
```sql
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) UNIQUE NOT NULL,
  `preferences` JSON,
  `is_active` TINYINT(1) DEFAULT 1,
  `subscribed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Форма подписки:**
- Футер сайта: простая форма с email
- Страница настроек: выбор типов уведомлений (новые объекты, новости, акции)

**Файл:** `php/newsletter/subscribe.php`

***

## 📂 Структура файлов

```
project/
├── chat.php                    // интерфейс чата
├── agent/
│   ├── dashboard.php           // CRM дашборд агента
│   ├── add-property.php        // форма добавления объекта
│   ├── edit-property.php       // редактирование
│   ├── calendar.php            // календарь просмотров
│   └── requests.php            // заявки на мои объекты
├── php/
│   ├── chat/
│   │   ├── send_message.php
│   │   ├── get_messages.php
│   │   └── mark_read.php
│   ├── email/
│   │   └── send_notification.php
│   ├── reviews/
│   │   ├── add_review.php
│   │   ├── get_reviews.php
│   │   └── moderate.php
│   └── newsletter/
│       └── subscribe.php
├── js/
│   ├── chat.js                 // AJAX для чата
│   └── reviews.js              // звездный рейтинг
└── css/
    ├── chat.css
    └── agent-dashboard.css
```

***

## 🎨 UI/UX компоненты

### Чат интерфейс:
- Двухколоночный layout (список диалогов + окно чата)
- Auto-scroll вниз при новых сообщениях
- Индикатор "печатает..." (опционально)
- Badge с количеством непрочитанных

### Звёздный рейтинг:
- HTML/CSS звёздочки (Unicode ★ ☆)
- JS для выбора рейтинга (клик на звезду)
- Средняя оценка с десятичной (например: 4.7 ★)

### Email шаблоны:
- HTML письма с логотипом и стилями
- Кнопки CTA ("Посмотреть объект", "Ответить")
- Футер с отпиской от рассылки

***

## 🔒 Безопасность

1. **Валидация сообщений чата** - `htmlspecialchars()` для защиты от XSS
2. **Ограничение частоты отправки** - не более 10 сообщений в минуту
3. **Проверка прав доступа** - пользователь может читать только свои диалоги
4. **Модерация отзывов** - публикация после одобрения админом
5. **Защита от спама** - CAPTCHA на форме подписки (опционально)

***

## ✅ Чеклист реализации

- [ ] Создать таблицы `messages`, `reviews`, `newsletter_subscribers`
- [ ] Реализовать CRM дашборд для агентов с CRUD объектами
- [ ] Внедрить чат с AJAX обновлением каждые 3 секунды
- [ ] Настроить отправку email уведомлений на ключевые события
- [ ] Добавить систему отзывов и рейтингов (звёздочки)
- [ ] Расширить личный кабинет пользователя (история, сообщения, отзывы)
- [ ] Создать форму подписки на рассылку в футере
- [ ] Протестировать все роли (user, agent, admin)
- [ ] Проверить работу email (настроить SMTP если нужно)
- [ ] Добавить счётчики непрочитанных сообщений в header

***

**Используй только ванильный PHP, JS, MySQL без фреймворков!** Все функции должны работать через PHP сессии для авторизации. 🚀[1][2][3][4][5][6]