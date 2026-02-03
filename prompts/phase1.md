# 📋 Промпт для Cursor IDE: Фаза 1 - Backend, Авторизация, БД, Личный кабинет

## Цель
Создай backend и систему авторизации для MVP real estate сайта на **ванильном PHP с сессиями** (без REST API).

***

## 🗄️ База данных MySQL

### Создай SQL-скрипт для phpMyAdmin

Файл: `database/create_database.sql`

```sql
-- Создание базы данных
CREATE DATABASE IF NOT EXISTS `realestate_db` 
DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `realestate_db`;

-- Таблица пользователей
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `role` ENUM('user', 'agent', 'admin') DEFAULT 'user',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица объектов недвижимости
CREATE TABLE IF NOT EXISTS `properties` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `property_type` ENUM('apartment', 'villa', 'townhouse', 'penthouse', 'studio') NOT NULL,
  `listing_type` ENUM('sale', 'rent') NOT NULL,
  `price` DECIMAL(12,2) NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `community` VARCHAR(100) DEFAULT NULL,
  `bedrooms` TINYINT UNSIGNED DEFAULT 0,
  `bathrooms` TINYINT UNSIGNED DEFAULT 0,
  `area_sqft` INT UNSIGNED NOT NULL,
  `agent_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('available', 'sold', 'rented', 'pending') DEFAULT 'available',
  `featured` TINYINT(1) DEFAULT 0,
  `views_count` INT UNSIGNED DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`agent_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_type` (`property_type`, `listing_type`),
  INDEX `idx_price` (`price`),
  INDEX `idx_status` (`status`),
  INDEX `idx_featured` (`featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица изображений недвижимости
CREATE TABLE IF NOT EXISTS `property_images` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `property_id` INT UNSIGNED NOT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `is_primary` TINYINT(1) DEFAULT 0,
  `sort_order` TINYINT UNSIGNED DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE,
  INDEX `idx_property` (`property_id`),
  INDEX `idx_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица избранного
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `property_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`property_id`) REFERENCES `properties`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_favorite` (`user_id`, `property_id`),
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вставка тестовых данных
INSERT INTO `users` (`email`, `password_hash`, `first_name`, `last_name`, `role`) VALUES
('admin@example.com', '$2y$10$example_hash_here', 'Admin', 'User', 'admin'),
('agent@example.com', '$2y$10$example_hash_here', 'John', 'Smith', 'agent'),
('user@example.com', '$2y$10$example_hash_here', 'Jane', 'Doe', 'user');
```

***

## 🔐 Система авторизации на PHP сессиях

### Реализуй безопасную аутентификацию с использованием сессий:[2][4][1]

**Файл:** `php/config/database.php`
```php
<?php
// Конфигурация подключения к БД
define('DB_HOST', 'localhost');
define('DB_NAME', 'realestate_db');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
?>
```

**Файл:** `php/auth/register.php`
```php
<?php
// Регистрация нового пользователя
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Валидация входных данных
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];
    $first_name = htmlspecialchars($_POST['first_name']);
    $last_name = htmlspecialchars($_POST['last_name']);
    
    // Проверка пароля (минимум 8 символов)
    if (strlen($password) < 8) {
        die("Password must be at least 8 characters");
    }
    
    // Хеширование пароля с bcrypt
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    $pdo = getDBConnection();
    
    // Проверка существующего email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        die("Email already exists");
    }
    
    // Вставка нового пользователя
    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?)");
    $stmt->execute([$email, $password_hash, $first_name, $last_name]);
    
    header("Location: /login.php");
    exit;
}
?>
```

**Файл:** `php/auth/login.php`
```php
<?php
// Авторизация пользователя через сессии
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, password_hash, first_name, role FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        // Регенерация session ID для безопасности (против session fixation)
        session_regenerate_id(true);
        
        // Сохранение данных в сессию
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        
        header("Location: /dashboard.php");
        exit;
    } else {
        die("Invalid credentials");
    }
}
?>
```

**Файл:** `php/auth/logout.php`
```php
<?php
// Выход из системы
session_start();
session_unset();
session_destroy();
header("Location: /index.php");
exit;
?>
```

**Файл:** `php/auth/check_auth.php`
```php
<?php
// Middleware для проверки авторизации
session_start();

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /login.php");
        exit;
    }
}
?>
```

***

## 📄 Основной функционал

### Каталог недвижимости

**Файл:** `properties.php`
```php
<?php
require_once 'php/config/database.php';

// Получение списка объектов с фильтрацией
$pdo = getDBConnection();

$listing_type = $_GET['type'] ?? 'sale';
$min_price = $_GET['min_price'] ?? 0;
$max_price = $_GET['max_price'] ?? 999999999;

$stmt = $pdo->prepare("
    SELECT p.*, pi.image_url 
    FROM properties p
    LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
    WHERE p.listing_type = ? AND p.price BETWEEN ? AND ? AND p.status = 'available'
    ORDER BY p.featured DESC, p.created_at DESC
");
$stmt->execute([$listing_type, $min_price, $max_price]);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
```

### Избранное

**Файл:** `php/favorites/add.php`
```php
<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check_auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $property_id = (int)$_POST['property_id'];
    $user_id = $_SESSION['user_id'];
    
    $pdo = getDBConnection();
    
    try {
        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, property_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $property_id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Already in favorites']);
    }
}
?>
```

***

## 🎨 Фронтенд (HTML/CSS/JS)

### Страницы для реализации:

1. **login.html** - Форма входа
2. **register.html** - Форма регистрации
3. **dashboard.php** - Личный кабинет (требует авторизации)
4. **properties.php** - Каталог недвижимости
5. **property-detail.php?id=X** - Детальная страница объекта
6. **favorites.php** - Избранное (требует авторизации)

### JavaScript для AJAX запросов

**Файл:** `js/favorites.js`
```javascript
function toggleFavorite(propertyId) {
    fetch('/php/favorites/add.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `property_id=${propertyId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Added to favorites!');
        } else {
            alert(data.error);
        }
    });
}
```

***

## 🔒 Безопасность[4][5][2]

Обязательно реализуй:

1. **Bcrypt хеширование паролей** - `password_hash()` с `PASSWORD_BCRYPT`
2. **Регенерация Session ID** - `session_regenerate_id(true)` после логина[5][2]
3. **Prepared Statements** - для всех SQL запросов (защита от SQL injection)
4. **Input validation** - `filter_var()`, `htmlspecialchars()` для всех пользовательских данных[2]
5. **HTTPS only cookies** - в `php.ini`: `session.cookie_secure = 1`, `session.cookie_httponly = 1`[2]
6. **CSRF защита** - токены для форм (опционально для MVP)

***

## 📂 Структура файлов

```
project/
├── index.php
├── login.php
├── register.php
├── dashboard.php
├── properties.php
├── property-detail.php
├── favorites.php
├── logout.php
├── database/
│   └── create_database.sql  ← SQL скрипт для phpMyAdmin
├── php/
│   ├── config/
│   │   └── database.php
│   ├── auth/
│   │   ├── login.php
│   │   ├── register.php
│   │   ├── logout.php
│   │   └── check_auth.php
│   └── favorites/
│       └── add.php
├── css/
│   └── style.css
├── js/
│   ├── main.js
│   └── favorites.js
└── images/
```

***

## ✅ Чеклист реализации

- [ ] Создать БД через phpMyAdmin (импортировать `create_database.sql`)
- [ ] Настроить подключение к БД в `database.php`
- [ ] Реализовать регистрацию с bcrypt хешированием
- [ ] Реализовать авторизацию через сессии
- [ ] Создать страницу личного кабинета с проверкой авторизации
- [ ] Вывести каталог недвижимости с базовыми фильтрами
- [ ] Реализовать добавление в избранное (AJAX)
- [ ] Сделать страницу избранного для авторизованных пользователей
- [ ] Применить semantic HTML5 и CSS Grid/Flexbox
- [ ] Добавить базовую валидацию форм на JS

***

Этот подход проще, быстрее и безопаснее для MVP, чем REST API с токенами ! 🚀[6][1][2]

[1](https://www.php.net/manual/en/features.session.security.management.php)
[2](https://webcraftingcode.com/web-development-best-practices/secure-user-authentication-best-practices-in-php/)
[3](https://www.webmasterworld.com/databases_sql_mysql/3780819.htm)
[4](https://www.vaadata.com/blog/php-security-best-practices-vulnerabilities-and-attacks/)
[5](https://hostadvice.com/blog/web-hosting/php/php-session-security/)
[6](https://dev.to/dgihost/difference-between-php-session-vs-tokens-189e)
[7](https://www.whiteandcogroup.com/about-us/dubai-communities/arabian-ranches/)
[8](https://whiteandcogroup.com/off-plan-properties/for-sale/in-dubai/)
[9](https://www.whiteandcogroup.com/property-for-sale/5-bedroom-apartment-for-sale-in-eywa-business-bay-dubai-674c178691004051f5a0d77d/)
[10](https://www.whiteandcogroup.com/property-for-rent/5-bedroom-villa-to-rent-in-garden-homes-frond-n-garden-homes-palm-jumeirah-dubai-67fccf0771934b0cc4d3a69d/)
[11](https://www.whiteandcogroup.com/property-for-rent/5-bedroom-townhouse-to-rent-in-palma-residences-palm-jumeirah-dubai-6499e54be19c572f7ea286d7/)
[12](https://whiteandcogroup.com)
[13](https://www.whiteandcogroup.com/property-for-sale/2-bedroom-apartment-for-sale-in-five-palm-jumeirah-palm-jumeirah-dubai-677be42391004051f5a2f5ec/)
[14](https://whiteandcogroup.com/about-us/latest-property-news/why-should-you-list-your-home-with-a-real-estate-agent-in-dubai/)
[15](https://www.whiteandcogroup.com/property-for-sale/7-bedroom-villa-for-sale-in-serenity-mansions-tilal-al-ghaf-dubai-6786f9ec91004051f5a3c17c/)
[16](https://www.whiteandcogroup.com/about-us/latest-property-news/the-role-of-real-estate-agents-in-the-digital-age/)
[17](https://stackoverflow.com/questions/8119496/php-secure-session-login-best-practice)
[18](https://www.darazhost.com/best-practices-for-php-session-management/)
[19](https://www.reddit.com/r/PHP/comments/12onohq/best_practices_on_keeping_user_sessions_logged_in/)
[20](https://github.com/DragScorpio/MySQL-Real-Estate-MLS-System)
[21](https://stackoverflow.com/questions/17579874/mysql-database-structure-for-realestate-directory)