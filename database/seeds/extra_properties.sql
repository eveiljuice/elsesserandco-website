-- =============================================================================
-- Расширенный сидер объектов недвижимости — Elsesser & Co.
-- 20 объектов в г. Миасс: 8 новостроек, 6 арендных, 6 на продажу.
-- Идемпотентный: можно запускать многократно, дубли не появятся (проверка по slug).
--
-- Запуск:
--   mysql -u root realestate_db < database/seeds/extra_properties.sql
-- или через OSPanel:
--   "C:\OSPanel\modules\MySQL-8.0\bin\mysql.exe" -uroot realestate_db < database/seeds/extra_properties.sql
--
-- Что добавляется:
--   8 новостроек (ЖК Северный, ЖК Озерный, ЖК Автозаводский и др.)
--   6 объектов аренды (студии, 1-к, 2-к, 3-к)
--   6 объектов продажи (дополнительные квартиры и дома)
-- =============================================================================

SET NAMES utf8mb4;

-- Вспомогательная временная таблица — пары (slug → image_url)
DROP TEMPORARY TABLE IF EXISTS _seed_images;
CREATE TEMPORARY TABLE _seed_images (
    `slug`     VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `image_url` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `sort_order` TINYINT UNSIGNED DEFAULT 0
);

INSERT INTO _seed_images (`slug`, `image_url`, `is_primary`, `sort_order`) VALUES
-- Новостройки
('zhk-severnyy-1k',     '/images/properties/miass-01.jpg', 1, 0),
('zhk-severnyy-1k',     '/images/properties/miass-07.jpg', 0, 1),
('zhk-severnyy-1k',     '/images/properties/miass-13.jpg', 0, 2),
('zhk-ozernyy-2k',      '/images/properties/miass-02.jpg', 1, 0),
('zhk-ozernyy-2k',      '/images/properties/miass-08.jpg', 0, 1),
('zhk-avtozavod-3k',    '/images/properties/miass-03.jpg', 1, 0),
('zhk-avtozavod-3k',    '/images/properties/miass-09.jpg', 0, 1),
('zhk-mashgorodok-st',  '/images/properties/miass-04.jpg', 1, 0),
('zhk-mashgorodok-st',  '/images/properties/miass-14.jpg', 0, 1),
('zhk-dinamo-2k',       '/images/properties/miass-05.jpg', 1, 0),
('zhk-dinamo-2k',       '/images/properties/miass-15.jpg', 0, 1),
('zhk-tsentralnyy-3k',  '/images/properties/miass-06.jpg', 1, 0),
('zhk-tsentralnyy-3k',  '/images/properties/miass-16.jpg', 0, 1),
('zhk-yuzhnyy-1k',      '/images/properties/miass-10.jpg', 1, 0),
('zhk-yuzhnyy-1k',      '/images/properties/miass-17.jpg', 0, 1),
('zhk-yuzhnyy-1k',      '/images/properties/miass-21.jpg', 0, 2),
('zhk-elitnyy-ph',      '/images/properties/miass-11.jpg', 1, 0),
('zhk-elitnyy-ph',      '/images/properties/miass-18.jpg', 0, 1),
('zhk-beregovoy-2k',    '/images/properties/miass-12.jpg', 1, 0),
('zhk-beregovoy-2k',    '/images/properties/miass-19.jpg', 0, 1),

-- Аренда
('rent-studiya-tsentr', '/images/properties/miass-20.jpg', 1, 0),
('rent-studiya-tsentr', '/images/properties/miass-22.jpg', 0, 1),
('rent-1k-severnyy',    '/images/properties/miass-23.jpg', 1, 0),
('rent-1k-severnyy',    '/images/properties/miass-24.jpg', 0, 1),
('rent-2k-mashgorodok', '/images/properties/miass-04.jpg', 1, 0),
('rent-2k-mashgorodok', '/images/properties/miass-09.jpg', 0, 1),
('rent-2k-ozernyy',     '/images/properties/miass-08.jpg', 1, 0),
('rent-2k-ozernyy',     '/images/properties/miass-15.jpg', 0, 1),
('rent-3k-semiynaya',   '/images/properties/miass-13.jpg', 1, 0),
('rent-3k-semiynaya',   '/images/properties/miass-17.jpg', 0, 1),
('rent-dom-uchastok',   '/images/properties/miass-06.jpg', 1, 0),
('rent-dom-uchastok',   '/images/properties/miass-16.jpg', 0, 1),

-- Продажа
('sale-1k-tsentr',      '/images/properties/miass-01.jpg', 1, 0),
('sale-1k-tsentr',      '/images/properties/miass-14.jpg', 0, 1),
('sale-2k-severnyy',    '/images/properties/miass-02.jpg', 1, 0),
('sale-2k-severnyy',    '/images/properties/miass-18.jpg', 0, 1),
('sale-2k-avtozavod',   '/images/properties/miass-03.jpg', 1, 0),
('sale-2k-avtozavod',   '/images/properties/miass-19.jpg', 0, 1),
('sale-3k-mashgorodok', '/images/properties/miass-05.jpg', 1, 0),
('sale-3k-mashgorodok', '/images/properties/miass-21.jpg', 0, 1),
('sale-dom-dinamo',     '/images/properties/miass-11.jpg', 1, 0),
('sale-dom-dinamo',     '/images/properties/miass-22.jpg', 0, 1),
('sale-dom-dinamo',     '/images/properties/miass-23.jpg', 0, 2),
('sale-studiya-bereg',  '/images/properties/miass-12.jpg', 1, 0),
('sale-studiya-bereg',  '/images/properties/miass-24.jpg', 0, 1);

-- =============================================================================
-- Добавление объектов. INSERT IGNORE — если slug уже есть, строка пропустится.
-- =============================================================================

INSERT IGNORE INTO `properties` (
    `category`, `title`, `title_ru`, `slug`, `description`, `description_ru`,
    `property_type`, `listing_type`, `price`, `currency`,
    `location`, `street`, `house_number`, `community`,
    `building_name`, `house_type`, `build_year`, `ceiling_height`,
    `has_elevator`, `has_garbage_chute`, `is_new_building`,
    `bedrooms`, `bathrooms`, `bathroom_type`, `area_sqft`, `area_total`, `area_living`, `area_kitchen`,
    `floor_number`, `total_floors`, `parking_spaces`,
    `furnished`, `renovation`, `balcony`, `balcony_count`,
    `window_view`, `agent_id`, `status`, `featured`,
    `created_at`, `updated_at`
) VALUES

-- ============ НОВОСТРОЙКИ (8) ============
('new-building', '1-к квартира в ЖК «Северный»',
    '1-к квартира в ЖК «Северный»',
    'zhk-severnyy-1k',
    'Bright new apartment in Severny residential complex with panoramic windows.',
    'Светлая квартира в новом жилом комплексе «Северный» с панорамными окнами и видом на город. Сдача — IV кв. 2026 г. Ипотека от застройщика, рассрочка 0%.',
    'apartment', 'sale', 3850000.00, 'RUB',
    'г. Миасс, Северный микрорайон', 'ул. Северная', '12', 'Северный',
    'ЖК «Северный»', 'monolith-brick', 2026, 2.75,
    1, 1, 1,
    1, 1, 'combined', 39.0, 39.20, 18.50, 11.30,
    8, 16, 1,
    'unfurnished', 'pre-finish', 'loggia', 1,
    'city', 2, 'available', 1,
    NOW(), NOW()),

('new-building', '2-к квартира в ЖК «Озёрный»',
    '2-к квартира в ЖК «Озёрный»',
    'zhk-ozernyy-2k',
    'Two-bedroom apartment in Ozyorny complex, near the lake.',
    'Двухкомнатная квартира в ЖК «Озёрный» с видом на озеро Тургояк. Закрытая территория, подземный паркинг, детский сад на территории.',
    'apartment', 'sale', 4950000.00, 'RUB',
    'г. Миасс, мкр. Озёрный', 'ул. Озёрная', '7', 'Озёрный',
    'ЖК «Озёрный»', 'monolith', 2027, 2.80,
    1, 1, 1,
    2, 1, 'separate', 58.0, 58.40, 32.10, 12.50,
    12, 18, 1,
    'unfurnished', 'pre-finish', 'both', 2,
    'park', 3, 'available', 1,
    NOW(), NOW()),

('new-building', '3-к квартира в ЖК «Автозаводский»',
    '3-к квартира в ЖК «Автозаводский»',
    'zhk-avtozavod-3k',
    'Three-bedroom apartment in Avtozavodsky complex for family.',
    'Просторная трёхкомнатная квартира для семьи в ЖК «Автозаводский». Школы и поликлиники в шаговой доступности. Сдача — II кв. 2027 г.',
    'apartment', 'sale', 6200000.00, 'RUB',
    'г. Миасс, Автозаводский район', 'проспект Автозаводцев', '55', 'Автозаводский',
    'ЖК «Автозаводский»', 'brick', 2027, 2.75,
    1, 1, 1,
    3, 2, 'separate', 82.0, 82.50, 48.20, 14.30,
    5, 9, 1,
    'unfurnished', 'pre-finish', 'both', 2,
    'both', 2, 'available', 1,
    NOW(), NOW()),

('new-building', 'Студия в ЖК «Машгородок»',
    'Студия в ЖК «Машгородок»',
    'zhk-mashgorodok-st',
    'Studio in Mashgorodok complex, perfect first home.',
    'Компактная студия в новом ЖК «Машгородок» — отличный вариант для первого жилья или сдачи в аренду. Ипотека с господдержкой от 4.5%.',
    'studio', 'sale', 2250000.00, 'RUB',
    'г. Миасс, Машгородок', 'ул. Машгородокская', '23', 'Машгородок',
    'ЖК «Машгородок»', 'panel', 2026, 2.70,
    1, 1, 1,
    0, 1, 'combined', 24.0, 24.50, 16.80, 6.20,
    4, 12, 1,
    'unfurnished', 'rough-finish', 'balcony', 1,
    'yard', 2, 'available', 0,
    NOW(), NOW()),

('new-building', '2-к квартира в ЖК «Динамо»',
    '2-к квартира в ЖК «Динамо»',
    'zhk-dinamo-2k',
    'Two-bedroom apartment in Dinamo with stadium view.',
    'Двухкомнатная квартира в ЖК «Динамо» с видом на стадион. Удобная транспортная развязка, рядом центр города и парк.',
    'apartment', 'sale', 4500000.00, 'RUB',
    'г. Миасс, Центральный район', 'ул. Динамовская', '8', 'Динамо',
    'ЖК «Динамо»', 'monolith-brick', 2026, 2.75,
    1, 1, 1,
    2, 1, 'combined', 56.0, 56.30, 30.40, 11.80,
    7, 14, 1,
    'unfurnished', 'pre-finish', 'loggia', 1,
    'city', 3, 'available', 1,
    NOW(), NOW()),

('new-building', '3-к квартира в ЖК «Центральный»',
    '3-к квартира в ЖК «Центральный»',
    'zhk-tsentralnyy-3k',
    'Premium three-bedroom apartment in the city center.',
    'Премиальная трёхкомнатная квартира в самом центре Миасса. Подземный паркинг, консьерж, видеонаблюдение. Идеально для ценителей городской жизни.',
    'apartment', 'sale', 7950000.00, 'RUB',
    'г. Миасс, Центральный район', 'проспект Автозаводцев', '43', 'Центральный',
    'ЖК «Центральный»', 'monolith', 2027, 3.00,
    1, 0, 1,
    3, 2, 'multiple', 95.0, 95.40, 56.20, 16.80,
    9, 12, 2,
    'unfurnished', 'pre-finish', 'both', 2,
    'city', 9, 'available', 1,
    NOW(), NOW()),

('new-building', '1-к квартира в ЖК «Южный»',
    '1-к квартира в ЖК «Южный»',
    'zhk-yuzhnyy-1k',
    'Affordable one-bedroom in new Yuzhny complex.',
    'Доступная однушка в новом ЖК «Южный». Хороший вариант для молодой семьи. Сдача — III кв. 2026 г. Материнский капитал в качестве первоначального взноса.',
    'apartment', 'sale', 2950000.00, 'RUB',
    'г. Миасс, Южная часть города', 'ул. Южная', '15', 'Южный',
    'ЖК «Южный»', 'panel', 2026, 2.65,
    1, 1, 1,
    1, 1, 'combined', 35.0, 35.40, 17.20, 9.80,
    3, 10, 0,
    'unfurnished', 'rough-finish', 'balcony', 1,
    'yard', 9, 'available', 0,
    NOW(), NOW()),

('new-building', 'Пентхаус в ЖК «Элитный»',
    'Пентхаус в ЖК «Элитный»',
    'zhk-elitnyy-ph',
    'Luxury penthouse with terrace.',
    'Эксклюзивный пентхаус на последнем этаже ЖК «Элитный» с собственной террасой 50 м² и панорамным видом на озеро.',
    'penthouse', 'sale', 12500000.00, 'RUB',
    'г. Миасс, Центральный район', 'проспект Автозаводцев', '20', 'Центральный',
    'ЖК «Элитный»', 'monolith', 2027, 3.20,
    1, 0, 1,
    4, 3, 'multiple', 165.0, 165.80, 105.30, 28.50,
    14, 14, 2,
    'unfurnished', 'pre-finish', 'both', 3,
    'park', 10, 'available', 1,
    NOW(), NOW()),

('new-building', '2-к квартира в ЖК «Береговой»',
    '2-к квартира в ЖК «Береговой»',
    'zhk-beregovoy-2k',
    'Two-bedroom apartment near the reservoir.',
    'Двухкомнатная квартира в новом ЖК «Береговой» рядом с городским пляжем и водохранилищем. Идеально для тех, кто любит природу.',
    'apartment', 'sale', 4400000.00, 'RUB',
    'г. Миасс, район Ильменского заповедника', 'ул. Береговая', '11', 'Береговой',
    'ЖК «Береговой»', 'monolith-brick', 2026, 2.75,
    1, 1, 1,
    2, 1, 'combined', 54.0, 54.20, 30.10, 11.50,
    6, 10, 1,
    'unfurnished', 'pre-finish', 'loggia', 1,
    'river', 10, 'available', 1,
    NOW(), NOW()),

-- ============ АРЕНДА (6) ============
('rent', 'Уютная студия в центре',
    'Уютная студия в центре',
    'rent-studiya-tsentr',
    'Cozy studio in the center, fully furnished.',
    'Светлая студия в самом центре Миасса с современным ремонтом и мебелью. Всё включено: интернет, коммунальные платежи, стиральная машина, посуда.',
    'studio', 'rent', 22000.00, 'RUB',
    'г. Миасс, Центральный район', 'ул. 8 Марта', '5', 'Центральный',
    NULL, 'brick', 2018, 2.70,
    1, 1, 0,
    0, 1, 'combined', 28.0, 28.30, 20.10, 7.50,
    3, 5, 0,
    'furnished', 'designer', 'loggia', 1,
    'city', 2, 'available', 0,
    NOW(), NOW()),

('rent', '1-к квартира, Северный',
    '1-к квартира, Северный',
    'rent-1k-severnyy',
    'One-bedroom apartment for long-term rent.',
    'Однокомнатная квартира в Северном микрорайоне для длительной аренды. Свежий ремонт, вся необходимая мебель и техника. Без животных.',
    'apartment', 'rent', 28000.00, 'RUB',
    'г. Миасс, Северный микрорайон', 'ул. Северная', '8', 'Северный',
    NULL, 'panel', 2015, 2.65,
    1, 1, 0,
    1, 1, 'combined', 36.0, 36.40, 18.50, 9.20,
    5, 9, 1,
    'furnished', 'euro', 'loggia', 1,
    'yard', 3, 'available', 0,
    NOW(), NOW()),

('rent', '2-к квартира, Машгородок',
    '2-к квартира, Машгородок',
    'rent-2k-mashgorodok',
    'Spacious two-bedroom apartment for family.',
    'Просторная двухкомнатная квартира в Машгородке. Подходит для семьи с детьми. Рядом школы, детские сады, парк.',
    'apartment', 'rent', 38000.00, 'RUB',
    'г. Миасс, Машгородок', 'ул. Машгородокская', '17', 'Машгородок',
    NULL, 'brick', 2010, 2.70,
    1, 1, 0,
    2, 1, 'separate', 54.0, 54.20, 32.40, 9.80,
    4, 5, 1,
    'semi-furnished', 'cosmetic', 'both', 2,
    'yard', 3, 'available', 0,
    NOW(), NOW()),

('rent', '2-к квартира у озера',
    '2-к квартира у озера',
    'rent-2k-ozernyy',
    'Two-bedroom apartment with lake view.',
    'Двухкомнатная квартира с видом на озеро. Свежий ремонт, встроенная кухня, кондиционер. Тихий спальный район.',
    'apartment', 'rent', 42000.00, 'RUB',
    'г. Миасс, мкр. Озёрный', 'ул. Озёрная', '3', 'Озёрный',
    NULL, 'monolith', 2019, 2.75,
    1, 1, 0,
    2, 1, 'combined', 52.0, 52.40, 30.20, 10.50,
    8, 12, 1,
    'furnished', 'euro', 'loggia', 1,
    'park', 9, 'available', 1,
    NOW(), NOW()),

('rent', '3-к квартира, семейная',
    '3-к квартира, семейная',
    'rent-3k-semiynaya',
    'Family three-bedroom apartment, long-term rent.',
    'Большая трёхкомнатная квартира для семьи на длительный срок. Мебель и техника — всё есть. Можно с детьми и животными.',
    'apartment', 'rent', 55000.00, 'RUB',
    'г. Миасс, Центральный район', 'ул. Лихачёва', '12', 'Центральный',
    NULL, 'brick', 2012, 2.70,
    1, 1, 0,
    3, 2, 'separate', 78.0, 78.40, 48.30, 12.50,
    5, 9, 1,
    'furnished', 'cosmetic', 'both', 2,
    'city', 9, 'available', 0,
    NOW(), NOW()),

('rent', 'Дом с участком',
    'Дом с участком',
    'rent-dom-uchastok',
    'Country house with large plot, long-term rent.',
    'Уютный дом с большим участком в аренду. Подходит для семьи, есть баня, гараж, теплица. Долгосрочная аренда от года.',
    'house', 'rent', 65000.00, 'RUB',
    'г. Миасс, район Тургоякского направления', 'ул. Дачная', '4', 'Загородный',
    NULL, 'wood', 2017, 2.80,
    0, 0, 0,
    3, 2, 'separate', 110.0, 110.50, 68.20, 16.40,
    1, 2, 2,
    'furnished', 'cosmetic', 'none', 0,
    'yard', 10, 'available', 1,
    NOW(), NOW()),

-- ============ ПРОДАЖА (6) ============
('sale', '1-к квартира в центре',
    '1-к квартира в центре',
    'sale-1k-tsentr',
    'One-bedroom apartment in historic center.',
    'Уютная однокомнатная квартира в историческом центре Миасса. Кирпичный дом, высокие потолки, толстые стены — прохладно летом, тепло зимой.',
    'apartment', 'sale', 2950000.00, 'RUB',
    'г. Миасс, Центральный район', 'ул. Романенко', '9', 'Центральный',
    NULL, 'brick', 1985, 2.80,
    0, 1, 0,
    1, 1, 'combined', 33.0, 33.20, 18.10, 7.80,
    4, 5, 0,
    'semi-furnished', 'cosmetic', 'balcony', 1,
    'yard', 2, 'available', 0,
    NOW(), NOW()),

('sale', '2-к квартира, Северный',
    '2-к квартира, Северный',
    'sale-2k-severnyy',
    'Two-bedroom apartment, renovated.',
    'Двухкомнатная квартира в Северном микрорайоне с дизайнерским ремонтом. Встроенная кухня, кондиционер, тёплые полы в ванной.',
    'apartment', 'sale', 4200000.00, 'RUB',
    'г. Миасс, Северный микрорайон', 'ул. Северная', '20', 'Северный',
    NULL, 'panel', 2012, 2.70,
    1, 1, 0,
    2, 1, 'separate', 58.0, 58.30, 34.20, 11.40,
    9, 12, 1,
    'unfurnished', 'designer', 'loggia', 1,
    'city', 3, 'available', 0,
    NOW(), NOW()),

('sale', '2-к квартира, Автозаводский',
    '2-к квартира, Автозаводский',
    'sale-2k-avtozavod',
    'Two-bedroom apartment, classic renovation.',
    'Двухкомнатная квартира в Автозаводском районе с качественным косметическим ремонтом. Готова к проживанию, документы в порядке.',
    'apartment', 'sale', 3550000.00, 'RUB',
    'г. Миасс, Автозаводский район', 'проспект Автозаводцев', '67', 'Автозаводский',
    NULL, 'brick', 1995, 2.70,
    1, 1, 0,
    2, 1, 'combined', 51.0, 51.40, 30.50, 9.40,
    3, 5, 0,
    'semi-furnished', 'cosmetic', 'balcony', 1,
    'street', 9, 'available', 0,
    NOW(), NOW()),

('sale', '3-к квартира, Машгородок',
    '3-к квартира, Машгородок',
    'sale-3k-mashgorodok',
    'Family three-bedroom apartment.',
    'Трёхкомнатная квартира для семьи в Машгородке. Изолированные комнаты, большая кухня, кладовка. Хорошие соседи.',
    'apartment', 'sale', 4750000.00, 'RUB',
    'г. Миасс, Машгородок', 'ул. Машгородокская', '9', 'Машгородок',
    NULL, 'brick', 2008, 2.75,
    1, 1, 0,
    3, 1, 'separate', 76.0, 76.20, 48.40, 12.80,
    6, 9, 1,
    'semi-furnished', 'euro', 'both', 2,
    'yard', 10, 'available', 1,
    NOW(), NOW()),

('sale', 'Дом с участком, Динамо',
    'Дом с участком, Динамо',
    'sale-dom-dinamo',
    'Detached house with land, Dinamo district.',
    'Отдельный дом с большим участком в районе Динамо. Кирпичный, тёплый, с гаражом и баней. Идеально для семьи.',
    'house', 'sale', 8500000.00, 'RUB',
    'г. Миасс, район Динамо', 'ул. Спортивная', '7', 'Динамо',
    NULL, 'brick', 2015, 2.90,
    0, 0, 0,
    4, 2, 'multiple', 145.0, 145.50, 88.30, 22.40,
    2, 2, 2,
    'semi-furnished', 'cosmetic', 'none', 0,
    'yard', 9, 'available', 1,
    NOW(), NOW()),

('sale', 'Студия у водохранилища',
    'Студия у водохранилища',
    'sale-studiya-bereg',
    'Studio with reservoir view, great investment.',
    'Студия с видом на водохранилище. Отличный инвестиционный вариант — можно сдавать в аренду. В пешей доступности пляж и лес.',
    'studio', 'sale', 2150000.00, 'RUB',
    'г. Миасс, район водохранилища', 'ул. Береговая', '5', 'Береговой',
    NULL, 'monolith', 2019, 2.75,
    1, 1, 0,
    0, 1, 'combined', 26.0, 26.30, 18.40, 6.80,
    2, 8, 0,
    'unfurnished', 'pre-finish', 'loggia', 1,
    'river', 10, 'available', 0,
    NOW(), NOW());

-- =============================================================================
-- Привязка фото к объектам.
-- Берём все только что добавленные property.id по slug и для каждого вставляем
-- строки из _seed_images с флагом primary.
-- =============================================================================

INSERT INTO `property_images` (`property_id`, `image_url`, `is_primary`, `sort_order`, `alt_text`)
SELECT
    p.id,
    si.image_url,
    si.is_primary,
    si.sort_order,
    p.title_ru
FROM `properties` p
JOIN _seed_images si ON si.slug = p.slug
WHERE NOT EXISTS (
    -- Идемпотентность: не дублируем фото, если уже есть для этого property+image
    SELECT 1 FROM `property_images` pi
    WHERE pi.property_id = p.id AND pi.image_url = si.image_url
);

DROP TEMPORARY TABLE IF EXISTS _seed_images;

-- =============================================================================
-- Готово. Проверить результат:
--   SELECT category, COUNT(*) FROM properties GROUP BY category;
--   SELECT p.id, p.slug, p.title_ru, COUNT(pi.id) photos
--   FROM properties p LEFT JOIN property_images pi ON pi.property_id = p.id
--   WHERE p.slug LIKE 'zhk-%' OR p.slug LIKE 'rent-%' OR p.slug LIKE 'sale-%'
--   GROUP BY p.id ORDER BY p.id;
-- =============================================================================