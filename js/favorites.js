/**
 * Favorites Module - Elsesser & Co.
 * Управление избранным через AJAX
 */

(function() {
    'use strict';

    /**
     * Toggle favorite status
     * @param {number} propertyId - ID объекта
     * @param {HTMLElement} button - Кнопка избранного
     */
    async function toggleFavorite(propertyId, button) {
        try {
            const response = await fetch('/includes/favorites/toggle.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ property_id: propertyId })
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.require_login) {
                    // Редирект на логин
                    const currentUrl = encodeURIComponent(window.location.pathname + window.location.search);
                    window.location.href = `/login.php?redirect=${currentUrl}`;
                    return;
                }
                throw new Error(data.error || 'Ошибка сервера');
            }

            if (data.success) {
                // Обновляем UI кнопки
                if (button) {
                    if (data.is_favorite) {
                        button.classList.add('favorite-btn--active');
                        button.querySelector('i')?.classList.replace('far', 'fas');
                    } else {
                        button.classList.remove('favorite-btn--active');
                        button.querySelector('i')?.classList.replace('fas', 'far');
                    }
                }

                // Обновляем счётчик в хедере
                updateFavoritesCount(data.favorites_count);

                // Показываем уведомление
                showNotification(data.message, 'success');
            } else {
                showNotification(data.error || 'Произошла ошибка', 'error');
            }

        } catch (error) {
            console.error('Toggle favorite error:', error);
            showNotification(error.message || 'Произошла ошибка', 'error');
        }
    }

    /**
     * Add to favorites
     * @param {number} propertyId - ID объекта
     */
    async function addFavorite(propertyId) {
        try {
            const response = await fetch('/includes/favorites/add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ property_id: propertyId })
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.require_login) {
                    const currentUrl = encodeURIComponent(window.location.pathname + window.location.search);
                    window.location.href = `/login.php?redirect=${currentUrl}`;
                    return;
                }
                throw new Error(data.error || 'Ошибка сервера');
            }

            if (data.success) {
                updateFavoritesCount(data.favorites_count);
                showNotification(data.message, 'success');
                return true;
            } else {
                showNotification(data.error || 'Произошла ошибка', 'error');
                return false;
            }

        } catch (error) {
            console.error('Add favorite error:', error);
            showNotification(error.message || 'Произошла ошибка', 'error');
            return false;
        }
    }

    /**
     * Remove from favorites
     * @param {number} propertyId - ID объекта
     */
    async function removeFavorite(propertyId) {
        try {
            const response = await fetch('/includes/favorites/remove.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ property_id: propertyId })
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.require_login) {
                    const currentUrl = encodeURIComponent(window.location.pathname + window.location.search);
                    window.location.href = `/login.php?redirect=${currentUrl}`;
                    return;
                }
                throw new Error(data.error || 'Ошибка сервера');
            }

            if (data.success) {
                // Удаляем карточку из DOM если на странице избранного
                const card = document.querySelector(`.favorite-card[data-property-id="${propertyId}"]`) ||
                             document.querySelector(`.property-card[data-id="${propertyId}"]`);
                
                if (card && window.location.pathname.includes('favorites')) {
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.9)';
                    setTimeout(() => {
                        card.remove();
                        // Проверяем, остались ли ещё карточки
                        const remainingCards = document.querySelectorAll('.favorite-card, .favorites-grid .property-card');
                        if (remainingCards.length === 0) {
                            showEmptyState();
                        }
                    }, 300);
                }

                updateFavoritesCount(data.favorites_count);
                showNotification(data.message, 'success');
                return true;
            } else {
                showNotification(data.error || 'Произошла ошибка', 'error');
                return false;
            }

        } catch (error) {
            console.error('Remove favorite error:', error);
            showNotification(error.message || 'Произошла ошибка', 'error');
            return false;
        }
    }

    /**
     * Update favorites count in header
     * @param {number} count - Количество избранного
     */
    function updateFavoritesCount(count) {
        const badges = document.querySelectorAll('.favorites-count, .badge');
        badges.forEach(badge => {
            if (badge.closest('[href*="favorites"]') || badge.closest('.nav__link')) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = '';
                } else {
                    badge.style.display = 'none';
                }
            }
        });
    }

    /**
     * Show empty state for favorites page
     */
    function showEmptyState() {
        const container = document.querySelector('.favorites-grid') || 
                         document.querySelector('.dashboard__section .favorites-grid')?.parentElement;
        
        if (container) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state__icon">
                        <i class="far fa-heart"></i>
                    </div>
                    <h3>Избранное пусто</h3>
                    <p>Добавляйте понравившиеся объекты в избранное, чтобы не потерять их</p>
                    <a href="/properties.php" class="btn btn--secondary">Найти недвижимость</a>
                </div>
            `;
        }
    }

    /**
     * Show notification
     * @param {string} message - Сообщение
     * @param {string} type - Тип (success, error, info)
     */
    function showNotification(message, type = 'info') {
        // Удаляем предыдущее уведомление
        const existing = document.querySelector('.notification');
        if (existing) {
            existing.remove();
        }

        const notification = document.createElement('div');
        notification.className = `notification notification--${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;

        // Стили для уведомления
        notification.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 16px 24px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            ${type === 'success' ? 'background: #10b981; color: white;' : ''}
            ${type === 'error' ? 'background: #ef4444; color: white;' : ''}
            ${type === 'info' ? 'background: #3b82f6; color: white;' : ''}
        `;

        document.body.appendChild(notification);

        // Анимация появления
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        // Автоматическое скрытие
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    /**
     * Initialize favorite buttons
     */
    function initFavoriteButtons() {
        document.addEventListener('click', function(e) {
            const favoriteBtn = e.target.closest('.favorite-btn, .property-card__favorite');
            if (favoriteBtn) {
                e.preventDefault();
                e.stopPropagation();
                
                const propertyId = favoriteBtn.dataset.propertyId || 
                                   favoriteBtn.closest('[data-id]')?.dataset.id ||
                                   favoriteBtn.closest('[data-property-id]')?.dataset.propertyId;
                
                if (propertyId) {
                    toggleFavorite(parseInt(propertyId), favoriteBtn);
                }
            }
        });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFavoriteButtons);
    } else {
        initFavoriteButtons();
    }

    // Export functions to global scope
    window.toggleFavorite = toggleFavorite;
    window.addFavorite = addFavorite;
    window.removeFavorite = removeFavorite;

})();
