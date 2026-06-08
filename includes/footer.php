<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer__grid">
            <div class="footer__brand">
                <div class="footer__logo">Elsesser & Co.</div>
                <a href="tel:+73432505050" class="footer__phone">+7 (343) 250-50-50</a>
                <p class="footer__address">
                    ООО "Эльсессер и Ко"<br>
                    БЦ "Высоцкий", 20-й этаж,<br>
                    ул. Малышева, 51, Екатеринбург
                </p>
                <a href="https://yandex.ru/maps/-/CHEuRXxx" target="_blank" class="footer__directions">
                    <i class="fas fa-map-marker-alt"></i>
                    Проложить маршрут
                </a>
            </div>
            
            <div class="footer__column">
                <h4 class="footer__title">Недвижимость</h4>
                <ul class="footer__links">
                    <li><a href="/properties.php?category=sale" class="footer__link">Купить</a></li>
                    <li><a href="/properties.php?category=rent" class="footer__link">Аренда</a></li>
                    <li><a href="/contact.html" class="footer__link">Продать</a></li>
                    <li><a href="/new-buildings.php" class="footer__link">Новостройки</a></li>
                </ul>
            </div>
            
            <div class="footer__column">
                <h4 class="footer__title">О нас</h4>
                <ul class="footer__links">
                    <li><a href="/about.html" class="footer__link">О компании</a></li>
                    <li><a href="/about.html#team" class="footer__link">Наша команда</a></li>
                    <li><a href="/contact.html" class="footer__link">Контакты</a></li>
                </ul>
            </div>
            
            <div class="footer__column footer__column--newsletter">
                <h4 class="footer__title">Подписка на рассылку</h4>
                <p class="footer__newsletter-text">Получайте новые объекты и актуальные новости рынка</p>
                <form class="footer__newsletter-form" id="footerNewsletterForm">
                    <input type="email" name="email" placeholder="Ваш email" required class="footer__newsletter-input">
                    <button type="submit" class="footer__newsletter-btn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
                <div id="footerNewsletterMessage" class="footer__newsletter-message" style="display: none;"></div>
            </div>
        </div>

        <!-- Yandex Map -->
        <div class="footer__map" id="footerMap">
            <iframe 
                src="https://yandex.ru/map-widget/v1/?um=constructor%3Afc2c9b8b9e8f4c5f7a3e8b2c1d0e9f8a7b6c5d4e&amp;source=constructor&amp;ll=60.597465%2C56.838011&amp;z=16&amp;pt=60.597465,56.838011,pm2rdm"
                width="100%" 
                height="300" 
                frameborder="0"
                allowfullscreen="true"
                loading="lazy"
                title="Офис Elsesser & Co. на карте"></iframe>
        </div>

        <!-- Awards Banner -->
        <div class="footer__awards">
            <img src="/images/Footer-Banners-1.webp" alt="Награды и сертификаты Elsesser & Co." class="footer__awards-banner">
        </div>

        <div class="footer__bottom">
            <p class="footer__copyright">
                © <?= date('Y') ?> Elsesser & Co. Real Estate LLC. Все права защищены.
            </p>
            <div class="footer__social">
                <a href="https://t.me/elsesserco" class="footer__social-link" aria-label="Telegram" target="_blank"><i class="fab fa-telegram"></i></a>
                <a href="https://vk.com/elsesserco" class="footer__social-link" aria-label="VK" target="_blank"><i class="fab fa-vk"></i></a>
                <a href="https://wa.me/73432505050" class="footer__social-link" aria-label="WhatsApp" target="_blank"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>
    </div>
</footer>

<script>
// Newsletter subscription (footer)
const footerNewsletterForm = document.getElementById('footerNewsletterForm');
if (footerNewsletterForm) {
    footerNewsletterForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const email = this.querySelector('input[name="email"]').value;
        const messageEl = document.getElementById('footerNewsletterMessage');
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

