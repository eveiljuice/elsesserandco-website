📋 Требования проекта
Создай MVP сайта агентства недвижимости, похожий на https://whiteandcogroup.com/

Стек: HTML5 + CSS3 + JavaScript + PHP (backend для контактов)

Тип: Адаптивный многостраничный сайт

Целевая аудитория: Покупатели, арендаторы и инвесторы в недвижимость (Dubai market)

🎨 Дизайн и стили
Цветовая палитра
text
Primary: #FFFFFF (белый фон)
Secondary: #1a1a1a (тёмный текст)
Accent: #00a3e0 или #0066cc (голубой - премиум, доверие)
Text: #333333 (основной текст)
Light Gray: #f5f5f5 (фоны секций)
Border: #e0e0e0 (разделители)
Типография
Заголовки: Sans-serif (Google Fonts: Inter, Poppins или похожий)

Основной текст: Sans-serif, легко читаемый

Размеры:

H1: 48px

H2: 36px

H3: 24px

Body: 16px

Small: 14px

Стиль
Минималистичный, современный

Большие отступы и белое пространство

Карточки с тонкими тенями (box-shadow: 0 2px 8px rgba(0,0,0,0.08))

Плавные переходы (transition: 0.3s ease)

Rounded corners: 8-12px

📱 Структура сайта
Страницы
index.html - Главная

properties.html - Каталог недвижимости

about.html - О компании

contact.html - Контакты + форма

Компоненты
Навигационное меню (sticky header)

Hero секция

Featured Properties (карточки)

About Team (профили)

Communities Section (города/районы)

CTA блоки

Footer

🎯 Функциональность
Frontend (HTML/CSS/JS)
Навигация

Sticky header с логотипом и меню

Mobile hamburger menu

Активные ссылки

Hero секция

Фоновое изображение или градиент

Заголовок + подзаголовок

CTA кнопка "Начать поиск"

Каталог недвижимости

Фильтры: Тип (Продажа/Аренда), Тип объекта (Квартира/Вилла), Цена

Карточки свойств:

Изображение

Название, локация

Цена

Характеристики (спальни, площадь, ванны)

"Подробнее" кнопка

Форма контакта

Поля: Имя, Email, Телефон, Сообщение

Валидация на клиенте

Submit -> PHP backend

Интерактивность

Hover эффекты на карточках

Modal окно для деталей недвижимости

Smooth scroll

Responsive design (Mobile first)

Backend (PHP)
contact.php - обработка формы контакта

Валидация данных

Email отправка админу

JSON ответ для JS

API endpoints (опционально):

GET /api/properties - список недвижимости

GET /api/properties/{id} - детали

POST /api/inquiries - сохранение запроса

📐 Макет/Grid система
text
Header (100%)
├── Logo (20%)
├── Nav Menu (60%)
└── CTA Button (20%)

Hero Section (fullwidth)
├── Background Image
└── Content Overlay

Main Content
├── Featured Properties (3 колонки на desktop, 1 на mobile)
├── About Section (2 колонки: текст + изображение)
└── CTA Section

Communities Section
└── Community Cards (4 колонки на desktop)

Footer
├── Links
├── Contact Info
└── Copyright
🔧 Технические требования
Файловая структура
text
project/
├── index.html
├── properties.html
├── about.html
├── contact.html
├── css/
│   ├── reset.css
│   ├── style.css
│   └── responsive.css
├── js/
│   ├── main.js
│   ├── navigation.js
│   ├── form.js
│   └── filters.js
├── php/
│   ├── contact.php
│   ├── api.php
│   └── db.php (конфиг БД если нужна)
├── images/
│   ├── hero/
│   ├── properties/
│   └── team/
└── README.md
HTML Требования
Semantic HTML5 (header, main, section, article, footer)

Accessibility: alt текст на изображениях, aria-labels

Meta tags: viewport, description, og-tags для социальных сетей

Structured data: Schema.org для недвижимости

CSS Требования
CSS Grid для layout

Flexbox для компонентов

CSS Variables для цветов и размеров

Mobile-first подход

BEM методология для классов

Нет !important

JavaScript Требования
Vanilla JS (без зависимостей)

Event listeners для интерактивности

Form validation

AJAX для контакт формы

LocalStorage для фильтров (опционально)

PHP Требования
Валидация всех входных данных

Защита от SQL injection (если БД)

CORS headers для AJAX

JSON responses

Error handling

📊 Примеры данных
Property Object
json
{
  "id": 1,
  "title": "Luxury Villa in Emirates Hills",
  "type": "Villa",
  "status": "For Sale",
  "price": "5,500,000 RUB",
  "location": "Emirates Hills, Dubai",
  "bedrooms": 5,
  "bathrooms": 6,
  "area": "8,500 sqft",
  "image": "villa-1.jpg",
  "description": "Stunning villa with...",
  "featured": true
}
Community Object
json
{
  "id": 1,
  "name": "Downtown Dubai",
  "image": "downtown.jpg",
  "description": "Modern living...",
  "properties_count": 245
}
🎬 Приоритет функционала (MVP)
Phase 1 (Обязательно)
 Макет и навигация

 Hero секция

 Featured Properties с hard-coded данными

 Footer

 Responsive design

 Contact form (базовая валидация)

Phase 2 (Желательно)
 PHP backend для контактов

 Фильтры по свойствам

 Страница "About"

 Детальная страница свойства

Phase 3 (Будущее)
 БД для свойств

 Admin панель

 User аккаунты

 Платежная система

💡 UI/UX Особенности (как на referenсе)
Доверие и профессионализм

Чистый, немного воздушный дизайн

Качественные изображения

Четкая типография

Ориентировка на пользователя

"People Who Know Property" - выделить экспертизу

"Homes You'll Love" - показать качество

"Support from Start to Sold" - подчеркнуть сервис

CTA элементы

Видимые, контрастные кнопки

Минимум на 2-3 местах на главной

"Свяжитесь с нами", "Начать поиск"

Социальное доказательство

Awards/Testimonials секция

Team member profiles

Property statistics

🚀 Команда для Cursor
Используй эти команды в Cursor IDE:

text
> Generate HTML structure for real estate MVP with sections:
  - Header with navigation
  - Hero section
  - Featured properties grid
  - About section
  - Communities section
  - Contact form
  - Footer

> Create responsive CSS using Grid/Flexbox, mobile-first approach
  Colors: #fff bg, #1a1a1a text, #00a3e0 accent

> Add JavaScript for:
  - Mobile hamburger menu toggle
  - Smooth scroll navigation
  - Contact form validation and AJAX submit
  - Property filter functionality

> Create PHP backend:
  - contact.php for form submission
  - Input validation
  - Email notification
  - JSON response
📝 Дополнительные заметки
Используй placeholder изображения (unsplash, pexels) для недвижимости

Добавь Google Fonts для типографии

Рассмотри FontAwesome для иконок

Веб-шрифты оптимизируй (woff2 формат)

Минимизируй CSS/JS для продакшена

Тестируй на Chrome, Safari, Firefox, мобильные браузеры

