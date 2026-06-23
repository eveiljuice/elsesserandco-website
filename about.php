<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="О компании Elsesser & Co. — ведущем агентстве недвижимости. Наша миссия, ценности и команда.">
    
    <title>О компании | Elsesser & Co.</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="images/favicon.png">

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
    <header class="header header--solid" id="header">
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
                        <li><a href="about.php" class="nav__link nav__link--active">О нас</a></li>
                    </ul>
                    <a href="contact.php" class="btn btn--primary">Оценка недвижимости</a>
                </nav>
                
                <button class="hamburger" id="hamburger" aria-label="Открыть меню">
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                </button>
            </div>
        </div>
    </header>

    <!-- Mobile Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <span class="sidebar__logo">Elsesser & Co.</span>
            <button class="sidebar__close" id="sidebarClose" aria-label="Закрыть меню">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <nav>
            <ul>
                <li class="sidebar__nav-item">
                    <a href="properties.php?category=sale" class="sidebar__nav-link">Купить недвижимость</a>
                </li>
                <li class="sidebar__nav-item">
                    <a href="properties.php?category=rent" class="sidebar__nav-link">Аренда недвижимости</a>
                </li>
                <li class="sidebar__nav-item">
                    <a href="contact.php" class="sidebar__nav-link">Продать недвижимость</a>
                </li>
                <li class="sidebar__nav-item">
                    <a href="new-buildings.php" class="sidebar__nav-link">Новостройки</a>
                </li>
                <li class="sidebar__nav-item">
                    <a href="about.php" class="sidebar__nav-link">О компании</a>
                </li>
                <li class="sidebar__nav-item">
                    <a href="contact.php" class="sidebar__nav-link">Контакты</a>
                </li>
            </ul>
        </nav>
    </aside>
    
    <!-- Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Page Header -->
    <div class="page-header" style="background-image: url('https://images.unsplash.com/photo-1497366216548-37526070297c?w=1920&q=80');">
        <div class="page-header__content">
            <h1 class="page-header__title">О компании Elsesser & Co.</h1>
            <p class="page-header__subtitle">
                Узнайте больше о самом быстрорастущем агентстве недвижимости —
                основанном на доверии, прозрачности и исключительном сервисе.
            </p>
            <a href="#team" class="btn btn--white">Познакомиться с командой</a>
        </div>
    </div>

    <!-- Who We Are Section -->
    <section class="section">
        <div class="container">
            <div class="about-section">
                <div class="about-section__content">
                    <h2>Кто мы такие</h2>
                    <p>
                        Elsesser & Co. — это больше, чем просто агентство недвижимости. Мы — команда 
                        профессионалов, стремящихся поднять стандарты услуг на рынке недвижимости.
                        С момента основания мы быстро стали одним из самых надёжных агентств города, 
                        известных прямолинейными советами, исключительным сервисом и культурой, 
                        в которой люди на первом месте.
                    </p>
                    <p>
                        Мы специализируемся на покупке, продаже, аренде и управлении недвижимостью 
                        по всей стране — от квартир и домов до коммерческих помещений и новостроек.
                        Но что действительно отличает нас — это наша приверженность делать всё правильно, 
                        с честностью, энергией и искренним желанием помочь.
                    </p>
                    <a href="contact.php" class="btn btn--secondary">Связаться с нами</a>
                </div>
                <div class="about-section__image">
                    <img src="images/team/team.webp" alt="Офис Elsesser & Co.">
                </div>
            </div>
        </div>
    </section>

    <!-- How We Work Section -->
    <section class="section section--gray" id="team">
        <div class="container">
            <div class="about-section about-section--reverse">
                <div class="about-section__content">
                    <h2>Как мы работаем</h2>
                    <p>
                        Мы собрали команду специалистов по районам, которые знают свои сообщества 
                        как свои пять пальцев. Это значит, что работая с Elsesser & Co., вы получаете 
                        не просто доступ к объявлениям — вы получаете реальные инсайты, экспертные 
                        переговоры и партнёра, который знает, как добиться результатов.
                    </p>
                    <p>
                        Наши внутренние услуги — включая ипотечное консультирование, управление 
                        недвижимостью, краткосрочную аренду и коммерческий консалтинг — означают, 
                        что вы можете получить всё необходимое под одной крышей. Никаких посредников, 
                        никакой путаницы. Просто слаженный, полный спектр услуг, который работает 
                        вокруг вас.
                    </p>
                    <a href="#" class="btn btn--secondary">Познакомиться с командой</a>
                </div>
                <div class="about-section__image">
                    <img src="images/team/team.webp" alt="Команда Elsesser & Co.">
                </div>
            </div>
        </div>
    </section>

    <!-- Our Mission Section -->
    <section class="section">
        <div class="container">
            <div class="section__header">
                <h2 class="section__title">Наша миссия</h2>
                <p class="section__subtitle" style="max-width: 800px;">
                    Сделать путь к недвижимости проще, понятнее и более выгодным — для каждого. 
                    Будь вы первый покупатель, опытный инвестор или арендодатель, ищущий поддержку, 
                    мы здесь, чтобы направлять вас со знанием дела, заботой и уверенностью.
                </p>
            </div>
            
            <div class="values-grid">
                <div class="value-card">
                    <h3 class="value-card__title">Люди на первом месте</h3>
                    <p class="value-card__text">
                        Мы строим отношения, прежде чем заключать сделки.
                    </p>
                </div>
                <div class="value-card">
                    <h3 class="value-card__title">Всегда честность</h3>
                    <p class="value-card__text">
                        Никакого блефа, никакого давления — только реальные советы, которым можно доверять.
                    </p>
                </div>
                <div class="value-card">
                    <h3 class="value-card__title">Результат важен</h3>
                    <p class="value-card__text">
                        Мы нацелены на результат и делаем то, что обещаем.
                    </p>
                </div>
                <div class="value-card">
                    <h3 class="value-card__title">Постоянное развитие</h3>
                    <p class="value-card__text">
                        Мы никогда не стоим на месте. Учимся, растём и развиваемся, чтобы служить вам лучше.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- CEO Message Section -->
    <section class="section section--gray">
        <div class="container">
            <div class="ceo-section">
                <div class="ceo-section__image">
                    <img src="images/ceo.png" alt="Генеральный директор">
                </div>
                <div class="ceo-section__content">
                    <h2>Обращение основателя</h2>
                    <p>
                        Размышляя о пути, который мы прошли с момента основания Elsesser & Co., 
                        я не могу не испытывать гордость за то, как далеко мы продвинулись. 
                        С первого дня наша цель была проста, но амбициозна: создать бизнес, 
                        построенный на доверии, прозрачности и искренней приверженности 
                        достижению лучших результатов для наших клиентов — будь то покупка, 
                        продажа или аренда.
                    </p>
                    <p>
                        То, что начиналось как небольшая команда с большим видением, теперь 
                        превратилось в нечто особенное, растущее быстрее, чем мы могли себе 
                        представить. Но рост для нас никогда не был связан с цифрами — 
                        он связан с сохранением целостности нашей миссии.
                    </p>
                    <p>
                        Я больше, чем когда-либо, воодушевлён будущим как Elsesser & Co., 
                        так и рынка недвижимости в целом. По мере того как отрасль развивается 
                        и спрос на более сложный, сервис-ориентированный подход растёт, 
                        я уверен, что наш фокус на качестве, честности и инновациях будет 
                        продолжать выделять нас.
                    </p>
                    <div class="ceo-section__signature">Тимофей Эльсессер</div>
                    <p class="ceo-section__title">Основатель и генеральный директор</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2 class="cta-section__title">Готовы работать с нами?</h2>
            <p class="cta-section__text">
                Свяжитесь с нами сегодня, чтобы обсудить ваши цели в сфере недвижимости.
            </p>
            <a href="contact.php" class="btn btn--cta btn--lg">Связаться с нами</a>
        </div>
    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="js/navigation.js"></script>
    <script src="js/main.js"></script>
    <?php include __DIR__ . '/includes/cookie-banner.php'; ?>
</body>
</html>

