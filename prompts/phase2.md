

# 📋 Промпт: Реализация Фазы 2 (Расширенный функционал Real Estate сайта)

## Цель  
Расширить функционал MVP сайта недвижимости, построенного на PHP сессиях, MySQL, HTML, CSS, JS. Улучшить каталог, добавить заявки, минимальную админку, и систему сравнения объектов.

***

## Функции для внедрения

1. **Поиск и расширенная фильтрация.**
   - Реализуй фильтры по цене, типу, количеству комнат, площади, статусу (продажа/аренда).
   - Интерфейс: dropdown/select, чекбоксы, ползунки.

2. **Детальная страница объекта.**
   - Вывод галереи изображений (слайдер, кнопки "вперед"/"назад").
   - Информация об объекте: заголовок, описание, характеристики, агент, карта (Google Maps embed или статика).
   - Кнопка "Запросить просмотр/Связаться".

3. **Система заявок на просмотр.**
   - Реализуй форму: Имя, Телефон, Email, комментарий.
   - Запись заявки в отдельную таблицу MySQL.
   - Связь заявки с id объекта и id пользователя (если авторизован).

4. **Минимальная административная панель** (отдельный интерфейс).
   - Авторизация по роли admin.
   - Управление списком объектов: добавление, изменение, удаление.
   - Просмотр и управление заявками: статус (в обработке/закрыто).

5. **Сравнение объектов недвижимости.**
   - Чекбоксы на карточках каталога ("Добавить к сравнению").
   - Страница сравнения с таблицей характеристик 2-4 объектов (название, цена, параметры).
   - JS для динамического управления списком сравнения, хранение id выбранных объектов в LocalStorage.

6. **Безопасность и сессии.**
   - Все действия админки и заявок — только для авторизованных пользователей (роль в сессии).
   - Проверка сессии на каждой защищённой странице.
   - Prepared statements для работы с БД.

***

## Пример структуры БД MySQL (расширение):

```sql
-- Таблица заявок
CREATE TABLE IF NOT EXISTS `requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `property_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20),
  `message` TEXT,
  `status` ENUM('pending', 'in_progress', 'closed') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`property_id`) REFERENCES properties(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES users(`id`) ON DELETE SET NULL
);

-- Таблица для логов/меток сравнения (по необходимости, можно хранить в LocalStorage)
```

***

## Пример файловой структуры

```
project/
├── catalog.php            // расширенный каталог с фильтрами
├── property-detail.php    // детальная страница недвижимости
├── compare.php            // сравнение объектов
├── request.php            // отправка заявки на просмотр
├── admin/
│   ├── index.php          // вход для админа
│   ├── objects.php        // CRUD объектов
│   └── requests.php       // управление заявками
├── js/
│   └── compare.js         // логика сравнения
├── css/
│   └── admin.css
└── ... остальные файлы MVP
```

***

## Вёрстка и интерфейс

- Используй HTML5, CSS Grid/Flexbox.
- Для галереи изображений — простую js-реализацию или готовый легкий скрипт.
- Для фильтрации и сравнения — чистый JS без фреймворков.
- Для админки: отдельный дизайн, отличающийся от основного сайта.
- Везде валидируй входные данные.

***

## Кратко по логике страниц

- **catalog.php**: фильтры, таблица объектов, кнопки "Добавить к сравнению".
- **property-detail.php**: галерея, характеристики, карта, форма заявки.
- **compare.php**: сравнение параметров объектов.
- **request.php**: обработка формы, запись в БД.
- **admin/***: все CRUD процессы и управление, доступно только роли admin (проверять через $_SESSION['role']).

***

## Чеклист для Cursor:

- Реализуй поиск и фильтрацию каталога по нескольким параметрам.
- Добавь галерею изображений (слайдер) на детальной странице.
- Сделай форму заявок + сохранение в БД + управление статусом.
- Реализуй страницу сравнения объектов.
- Создай базовую админку (управление объектами и заявками) только для роли admin.
- Проверь нормальную работу сессий и авторизации.
- Протестируй все функции для авторизованных и неавторизованных пользователей.

***

**Всё реализуй на чистом PHP, JS, MySQL — без сторонних фреймворков!**

[1](https://www.whiteandcogroup.com/about-us/latest-property-news/off-plan-vs-resale-properties-in-dubai-what-is-better-to-buy/)
[2](https://www.whiteandcogroup.com/about-us/latest-property-news/best-neighbourhoods-to-invest-in-dubai-s-real-estate-market/)
[3](https://www.whiteandcogroup.com/about-us/dubai-communities/arabian-ranches/)
[4](https://www.whiteandcogroup.com/about-us/dubai-communities/jumeirah-golf-estate/)
[5](https://www.whiteandcogroup.com/about-us/dubai-communities/town-square/)
[6](https://www.whiteandcogroup.com/about-us/dubai-communities/jumeirah-village-triangle/)
[7](https://www.whiteandcogroup.com/about-us/dubai-communities/the-meadows/)
[8](https://www.whiteandcogroup.com/meet-the-team/jordan-smith/)
[9](https://www.whiteandcogroup.com/property-for-sale/4-bedroom-villa-for-sale-in-district-one-west-phase-i-district-one-mohammed-bin-rashid-city-dubai-67a9a52b35c244ba49188ded/)
[10](https://www.whiteandcogroup.com/about-us/latest-property-news/the-role-of-real-estate-agents-in-the-digital-age/)
[11](http://blog.ox2.ru/php/avtorizaciya-i-rabota-sessii/)
[12](https://code.mu/ru/php/book/prime/auth/account/)
[13](https://lectoria.pro/read/sessii-v-php-i-modx-revolution.html)
[14](https://ru.stackoverflow.com/questions/1084324/%D0%A1%D0%B5%D1%81%D1%81%D0%B8%D1%8F-%D0%BB%D0%B8%D1%87%D0%BD%D0%BE%D0%B3%D0%BE-%D0%BA%D0%B0%D0%B1%D0%B8%D0%BD%D0%B5%D1%82%D0%B0-%D0%BD%D0%B0-%D1%81%D0%B0%D0%B9%D1%82%D0%B5)
[15](https://ragemp.pro/threads/prostaja-registracija-avtorizacija-i-lichnyj-kabinet-na-php-dlja-sajta.8862/)
[16](https://www.youtube.com/watch?v=eCItZh6uMVc)
[17](https://radiohlam.ru/session_php/)
[18](https://habr.com/ru/articles/665602/)
[19](https://itproger.com/course/php-website/4)
[20](https://webdevkin.ru/posts/backend/authorization)