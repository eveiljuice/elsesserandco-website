/**
 * Similar Properties — простой слайдер с линиями-точками.
 *  - 3 карточки в ряд, под ними 3 индикатора-точки.
 *  - Клик по точке выделяет соответствующую карточку.
 *  - Клик по неактивной карточке: первый — активирует, второй — переходит по ссылке.
 *  - Стрелки клавиатуры (←/→) переключают точки.
 */
(function () {
    'use strict';

    function initSlider(root) {
        var track = root.querySelector('.similar-slider__track');
        var dotsContainer = root.querySelector('.similar-slider__dots');
        if (!track || !dotsContainer) return;

        var slides = Array.prototype.slice.call(track.querySelectorAll('.similar-slider__slide'));
        if (slides.length === 0) return;

        // Создаём точки по числу слайдов
        dotsContainer.innerHTML = '';
        var dots = slides.map(function (_, i) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'similar-slider__dot';
            btn.setAttribute('role', 'tab');
            btn.setAttribute('aria-label', 'Показать объект ' + (i + 1));
            btn.dataset.index = String(i);
            btn.addEventListener('click', function () { setActive(i); });
            dotsContainer.appendChild(btn);
            return btn;
        });

        var activeIndex = Math.floor(slides.length / 2); // по умолчанию — центральный

        function setActive(idx) {
            if (idx < 0 || idx >= slides.length) return;
            activeIndex = idx;
            slides.forEach(function (slide, i) {
                slide.classList.toggle('is-faded', i !== idx);
                slide.classList.toggle('is-pending', false); // сбрасываем «ожидание клика»
            });
            dots.forEach(function (dot, i) {
                dot.classList.toggle('is-active', i === idx);
                dot.setAttribute('aria-selected', i === idx ? 'true' : 'false');
            });
        }

        // Первый клик по неактивной карточке → активирует её, не открывает ссылку.
        // Второй клик (когда она уже активна) → обычный переход по ссылке.
        slides.forEach(function (slide, i) {
            slide.addEventListener('click', function (e) {
                if (i === activeIndex) return; // активная — обычное поведение ссылки
                e.preventDefault();
                // если кликнули по другой неактивной — снимаем «pending» с неё и активируем
                setActive(i);
            });
        });

        // Инициализация
        setActive(activeIndex);

        // Переключение стрелками клавиатуры, когда фокус внутри слайдера
        root.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                setActive((activeIndex - 1 + slides.length) % slides.length);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                setActive((activeIndex + 1) % slides.length);
            }
        });

        // Делаем корень фокусируемым, чтобы стрелки работали
        if (!root.hasAttribute('tabindex')) root.setAttribute('tabindex', '0');
    }

    function initAll() {
        document.querySelectorAll('[data-similar-slider]').forEach(initSlider);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
