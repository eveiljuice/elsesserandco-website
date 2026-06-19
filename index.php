<?php
/**
 * Home Page - Elsesser & Co.
 * Главная страница с динамическими данными из БД
 */

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$user = getUserData();
$pdo = getDBConnection();

// Получаем featured объекты
$stmt = $pdo->prepare("
    SELECT p.*, pi.image_url as primary_image
    FROM properties p
    LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
    WHERE p.status = 'available' AND p.featured = 1
    ORDER BY p.created_at DESC
    LIMIT 6
");
$stmt->execute();
$featuredProperties = $stmt->fetchAll();

// Если featured меньше 6, добираем обычными
if (count($featuredProperties) < 6) {
    $ids = array_column($featuredProperties, 'id');
    $placeholders = $ids ? implode(',', array_fill(0, count($ids), '?')) : '0';
    
    $stmt = $pdo->prepare("
        SELECT p.*, pi.image_url as primary_image
        FROM properties p
        LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
        WHERE p.status = 'available' AND p.id NOT IN ($placeholders)
        ORDER BY p.created_at DESC
        LIMIT ?
    ");
    $params = array_merge($ids, [6 - count($featuredProperties)]);
    $stmt->execute($params);
    $additionalProperties = $stmt->fetchAll();
    $featuredProperties = array_merge($featuredProperties, $additionalProperties);
}

// Получаем избранное пользователя
$userFavorites = [];
$favoritesCount = 0;
if ($user['logged_in']) {
    $stmt = $pdo->prepare("SELECT property_id FROM favorites WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $userFavorites = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $favoritesCount = count($userFavorites);
}

// Статистика по районам
$stmt = $pdo->query("
    SELECT community, COUNT(*) as count 
    FROM properties 
    WHERE status = 'available' AND community IS NOT NULL
    GROUP BY community 
    ORDER BY count DESC 
    LIMIT 4
");
$topCommunities = $stmt->fetchAll();

$logoutMessage = isset($_GET['logout']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Elsesser & Co. — ведущее агентство недвижимости в Екатеринбурге. Покупка, продажа и аренда элитной недвижимости.">
    <meta name="keywords" content="недвижимость Екатеринбург, купить квартиру Екатеринбург, аренда дом Екатеринбург, элитная недвижимость">
    
    <!-- Open Graph -->
    <meta property="og:title" content="Elsesser & Co. — Элитная недвижимость в Екатеринбурге">
    <meta property="og:description" content="Профессиональное агентство недвижимости в Екатеринбурге. Помогаем найти идеальный дом.">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ru_RU">
    
    <title>Elsesser & Co. — Элитная недвижимость в Екатеринбурге</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="images/favicon.png">

    <!-- PWA -->
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#00736c">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="/images/favicon.png">
    <meta name="vapid-public-key" content="<?= htmlspecialchars((string)Config::get('VAPID_PUBLIC_KEY', '')) ?>">
    <meta name="csrf-token" content="<?= htmlspecialchars(generateCSRFToken()) ?>">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <!-- Header -->
    <header class="header header--transparent" id="header">
        <div class="container">
            <div class="header__inner">
                <a href="index.php" class="header__logo">
                    <span class="header__logo-text">Elsesser & Co.</span>
                </a>
                
                <nav class="nav">
                    <ul class="nav__list">
                        <li><a href="properties.php?category=sale" class="nav__link">Купить</a></li>
                        <li><a href="properties.php?category=rent" class="nav__link">Аренда</a></li>
                        <li><a href="contact.php" class="nav__link">Продать</a></li>
                        <li><a href="new-buildings.php" class="nav__link">Новостройки</a></li>
                        <li><a href="about.php" class="nav__link">О нас</a></li>
                    </ul>
                    <?php include __DIR__ . '/includes/nav-compare-link.php'; ?>
                    <?php if ($user['logged_in']): ?>
                    <a href="favorites.php" class="nav__link nav__link--icon">
                        <i class="fas fa-heart"></i>
                        <?php if (!empty($favoritesCount)): ?>
                        <span class="badge"><?= $favoritesCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="dashboard.php" class="btn btn--primary">
                        <i class="fas fa-user"></i> <?= escape($user['name']) ?>
                    </a>
                    <?php else: ?>
                    <a href="login.php" class="btn btn--primary">Войти</a>
                    <?php endif; ?>
                </nav>
                
                <button class="hamburger" id="hamburger" aria-label="Открыть меню">
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                </button>
            </div>
        </div>
    </header>

    <?php include __DIR__ . '/includes/mobile-menu.php'; ?>

    <!-- Hero Section -->
    <section class="hero" style="background-image: url('images/properties/hero.jfif');">
        <div class="hero__content">
            <h1 class="hero__title">
                <span class="hero__title-accent">Прогрессивный подход</span>
                к недвижимости
            </h1>
            <p class="hero__subtitle">
                Elsesser & Co. — надёжное агентство недвижимости в Екатеринбурге. Мы делаем процесс покупки, 
                продажи и аренды простым и понятным. Качественный сервис, честные советы и помощь 
                в поиске идеального дома.
            </p>
            
            <!-- Search Box -->
            <form action="properties.php" method="GET" class="search-box" id="heroSearchForm">
                <div class="search-box__toggle">
                    <button type="button" class="search-box__toggle-btn search-box__toggle-btn--active" data-type="sale">Купить</button>
                    <button type="button" class="search-box__toggle-btn" data-type="rent">Аренда</button>
                </div>
                <input type="hidden" name="category" value="sale" id="searchType">
                <div class="search-box__input-wrapper">
                    <input type="text" name="search" class="search-box__input" placeholder="Район, ЖК или адрес" id="heroSearchInput"
                           data-autocomplete autocomplete="off">
                    <button type="submit" class="search-box__btn" aria-label="Поиск">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>           
           
            <a href="properties.php" class="search-advanced">
                <i class="fas fa-sliders-h"></i>
                Расширенный поиск
            </a>
        </div>
    </section>

    <!-- Featured Properties -->
    <section class="section">
        <div class="container">
            <div class="section__header">
                <h2 class="section__title">Рекомендуемые объекты</h2>
                <p class="section__subtitle">Эксклюзивные предложения от наших экспертов по недвижимости</p>
            </div>
            
            <div class="properties-grid">
                <?php foreach (array_slice($featuredProperties, 0, 3) as $property): 
                    $isFavorite = in_array($property['id'], $userFavorites);
                ?>
                <article class="property-card" data-id="<?= $property['id'] ?>">
                    <div class="property-card__image">
                        <a href="property.php?id=<?= $property['id'] ?>">
                            <img src="<?= escape($property['primary_image'] ?? 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=600&q=80') ?>" 
                                 alt="<?= escape($property['title']) ?>" 
                                 class="property-card__img">
                        </a>
                        <button class="property-card__favorite favorite-btn <?= $isFavorite ? 'favorite-btn--active' : '' ?>"
                                data-property-id="<?= $property['id'] ?>"
                                title="<?= $isFavorite ? 'Удалить из избранного' : 'Добавить в избранное' ?>">
                            <i class="<?= $isFavorite ? 'fas' : 'far' ?> fa-heart"></i>
                        </button>
                        <label class="compare-checkbox" aria-label="Добавить в сравнение">
                            <input type="checkbox" class="compare-checkbox__input" data-property-id="<?= (int)$property['id'] ?>" onchange="toggleCompare(<?= (int)$property['id'] ?>)">
                            <i class="fas fa-balance-scale compare-checkbox__icon"></i>
                        </label>
                    </div>
                    <div class="property-card__body">
                        <div class="property-card__price">
                            <?= formatPrice($property['price']) ?>
                            <?php if ($property['category'] === 'rent'): ?>
                            <span class="property-card__period">/мес</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="property-card__title">
                            <?= escape($property['title_ru'] ?? $property['title']) ?>
                        </h3>
                        <div class="property-card__location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?= escape($property['location']) ?>
                        </div>
                        <div class="property-card__footer">
                            <div class="property-card__specs">
                                <span class="property-card__spec"><i class="fas fa-bed"></i> <?= $property['bedrooms'] ?></span>
                                <span class="property-card__spec"><i class="fas fa-bath"></i> <?= $property['bathrooms'] ?></span>
                                <span class="property-card__spec"><i class="fas fa-vector-square"></i> <?= number_format($property['area_sqft']) ?> м²</span>
                            </div>
                            <a href="chat.php?user=<?= $property['agent_id'] ?>&property=<?= $property['id'] ?>" 
                               class="property-card__whatsapp">
                                <i class="fas fa-comment"></i>
                                Чат с агентом
                            </a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-8">
                <a href="properties.php" class="btn btn--secondary btn--lg">Смотреть все объекты</a>
            </div>
        </div>
    </section>

    <!-- About Preview Section -->
    <section class="section section--gray">
        <div class="container">
            <div class="about-preview">
                <div class="about-preview__image">
                    <img src="images/team/team.webp" alt="Команда Elsesser & Co.">
                </div>
                <div class="about-preview__content">
                    <h2 class="about-preview__title">Кто мы такие</h2>
                    <p class="about-preview__text">
                        Elsesser & Co. — это больше, чем просто агентство недвижимости. Мы — команда 
                        профессионалов, стремящихся поднять стандарты услуг на рынке недвижимости Екатеринбурга.
                    </p>
                    <p class="about-preview__text">
                        С момента основания мы быстро стали одним из самых надёжных агентств города, 
                        известных прямолинейными советами, исключительным сервисом и культурой, 
                        в которой люди на первом месте.
                    </p>
                    <p class="about-preview__text mb-0">
                        Мы специализируемся на покупке, продаже, аренде и управлении недвижимостью 
                        по всему Екатеринбургу — от квартир и домов до коммерческих помещений и новостроек.
                    </p>
                    <div class="mt-8">
                        <a href="about.php" class="btn btn--secondary">Узнать больше</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Communities Section -->
    <section class="section">
        <div class="container">
            <div class="section__header">
                <h2 class="section__title">Популярные районы</h2>
                <p class="section__subtitle">Откройте для себя лучшие локации для жизни и инвестиций в Екатеринбурге</p>
            </div>
            
            <div class="communities-grid">
                <a href="properties.php?district=1" class="community-card">
                    <img src="images/properties/center.jfif" alt="Центр" class="community-card__image">
                    <div class="community-card__overlay">
                        <h3 class="community-card__name">Центр (ВИЗ)</h3>
                        <span class="community-card__count"><?= $topCommunities[0]['count'] ?? 0 ?> объектов</span>
                    </div>
                </a>
                
                <a href="properties.php?district=5" class="community-card">
                    <img src="images/properties/academic.jfif" alt="Академический" class="community-card__image">
                    <div class="community-card__overlay">
                        <h3 class="community-card__name">Академический</h3>
                        <span class="community-card__count"><?= $topCommunities[1]['count'] ?? 0 ?> объектов</span>
                    </div>
                </a>
                
                <a href="properties.php?district=13" class="community-card">
                    <img src="images/properties/wide-river.jfif" alt="Широкая Речка" class="community-card__image">
                    <div class="community-card__overlay">
                        <h3 class="community-card__name">Широкая речка</h3>
                        <span class="community-card__count"><?= $topCommunities[2]['count'] ?? 0 ?> объектов</span>
                    </div>
                </a>
                
                <a href="properties.php?district=6" class="community-card">
                    <img src="images/properties/uctus.jfif" alt="Уралмаш" class="community-card__image">
                    <div class="community-card__overlay">
                        <h3 class="community-card__name">Уралмаш</h3>
                        <span class="community-card__count"><?= $topCommunities[3]['count'] ?? 0 ?> объектов</span>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2 class="cta-section__title">Готовы найти свой идеальный дом?</h2>
            <p class="cta-section__text">
                Наши эксперты помогут вам на каждом этапе — от поиска до сделки. 
                Закажите бесплатную консультацию уже сегодня.
            </p>
            <a href="contact.php" class="btn btn--cta btn--lg">Связаться с нами</a>
        </div>
    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="js/navigation.js"></script>
    <script src="js/favorites.js"></script>
    <script src="js/autocomplete.js"></script>
    <script src="js/pwa.js" defer></script>
    <?php include __DIR__ . '/includes/compare-bar.php'; ?>
    <script>
        // Search type toggle
        document.querySelectorAll('.search-box__toggle-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.search-box__toggle-btn').forEach(b =>
                    b.classList.remove('search-box__toggle-btn--active')
                );
                this.classList.add('search-box__toggle-btn--active');
                document.getElementById('searchType').value = this.dataset.type;
            });
        });

        // Newsletter subscription
        const newsletterForm = document.getElementById('newsletterForm');
        if (newsletterForm) {
            newsletterForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const email = this.querySelector('input[name="email"]').value;
                const messageEl = document.getElementById('newsletterMessage');
                const submitBtn = this.querySelector('button[type="submit"]');

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                try {
                    const response = await fetch('/php/newsletter/subscribe.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'email=' + encodeURIComponent(email)
                    });

                    const data = await response.json();

                    messageEl.style.display = 'block';
                    messageEl.className = 'footer__newsletter-message ' + (data.success ? 'success' : 'error');
                    messageEl.textContent = data.message || data.error;

                    if (data.success) {
                        this.reset();
                    }
                } catch (error) {
                    messageEl.style.display = 'block';
                    messageEl.className = 'footer__newsletter-message error';
                    messageEl.textContent = 'Произошла ошибка. Попробуйте позже.';
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';

                    setTimeout(() => {
                        messageEl.style.display = 'none';
                    }, 5000);
                }
            });
        }
    </script>
    <?php include __DIR__ . '/includes/cookie-banner.php'; ?>

</body>
</html>
