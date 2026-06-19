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
                <a href="properties.php?category=sale" class="sidebar__nav-link">
                    Купить недвижимость
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
            <li class="sidebar__nav-item">
                <a href="properties.php?category=rent" class="sidebar__nav-link">
                    Аренда недвижимости
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
            <li class="sidebar__nav-item">
                <a href="contact.html" class="sidebar__nav-link">
                    Продать недвижимость
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
            <li class="sidebar__nav-item">
                <a href="new-buildings.php" class="sidebar__nav-link">
                    Новостройки
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
            <li class="sidebar__nav-item">
                <a href="about.html" class="sidebar__nav-link">О компании</a>
            </li>
            <li class="sidebar__nav-item">
                <a href="contact.html" class="sidebar__nav-link">Контакты</a>
            </li>
            <?php if ($user['logged_in']): ?>
            <li class="sidebar__nav-item">
                <a href="dashboard.php" class="sidebar__nav-link">
                    <i class="fas fa-user"></i> Личный кабинет
                </a>
            </li>
            <li class="sidebar__nav-item">
                <a href="favorites.php" class="sidebar__nav-link">
                    <i class="fas fa-heart"></i> Избранное
                </a>
            </li>
            <li class="sidebar__nav-item">
                <a href="compare.php" class="sidebar__nav-link">
                    <i class="fas fa-balance-scale"></i> Сравнение
                </a>
            </li>
            <li class="sidebar__nav-item">
                <a href="includes/auth/logout.php" class="sidebar__nav-link sidebar__nav-link--logout">
                    <i class="fas fa-sign-out-alt"></i> Выйти
                </a>
            </li>
            <?php else: ?>
            <li class="sidebar__nav-item">
                <a href="login.php" class="sidebar__nav-link">
                    <i class="fas fa-sign-in-alt"></i> Войти
                </a>
            </li>
            <li class="sidebar__nav-item">
                <a href="register.php" class="sidebar__nav-link">
                    <i class="fas fa-user-plus"></i> Регистрация
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
</aside>

<!-- Overlay -->
<div class="overlay" id="overlay"></div>

