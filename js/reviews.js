/**
 * Reviews JavaScript - Elsesser & Co.
 * Система отзывов и рейтингов
 */

(function() {
    'use strict';
    
    // Star Rating Component
    class StarRating {
        constructor(container, options = {}) {
            this.container = container;
            this.rating = options.rating || 0;
            this.readonly = options.readonly || false;
            this.size = options.size || 'medium';
            this.onChange = options.onChange || (() => {});
            
            this.init();
        }
        
        init() {
            this.container.classList.add('star-rating', `star-rating--${this.size}`);
            if (this.readonly) {
                this.container.classList.add('star-rating--readonly');
            }
            
            this.render();
            
            if (!this.readonly) {
                this.bindEvents();
            }
        }
        
        render() {
            let html = '';
            for (let i = 1; i <= 5; i++) {
                const starClass = i <= this.rating ? 'fas fa-star' : 'far fa-star';
                html += `<i class="${starClass}" data-rating="${i}"></i>`;
            }
            this.container.innerHTML = html;
        }
        
        bindEvents() {
            this.container.addEventListener('mouseover', (e) => {
                if (e.target.hasAttribute('data-rating')) {
                    this.highlight(parseInt(e.target.dataset.rating));
                }
            });
            
            this.container.addEventListener('mouseout', () => {
                this.highlight(this.rating);
            });
            
            this.container.addEventListener('click', (e) => {
                if (e.target.hasAttribute('data-rating')) {
                    this.setRating(parseInt(e.target.dataset.rating));
                }
            });
        }
        
        highlight(rating) {
            const stars = this.container.querySelectorAll('i');
            stars.forEach((star, index) => {
                star.className = index < rating ? 'fas fa-star' : 'far fa-star';
            });
        }
        
        setRating(rating) {
            this.rating = rating;
            this.highlight(rating);
            this.onChange(rating);
        }
        
        getRating() {
            return this.rating;
        }
    }
    
    // Reviews Manager
    class ReviewsManager {
        constructor(propertyId, agentId = null) {
            this.propertyId = propertyId;
            this.agentId = agentId;
            this.page = 1;
            this.selectedRating = 0;
            
            this.init();
        }
        
        init() {
            this.container = document.getElementById('reviewsSection');
            if (!this.container) return;
            
            this.reviewsList = this.container.querySelector('.reviews-list');
            this.loadMoreBtn = this.container.querySelector('.load-more-reviews');
            
            // Initialize form star rating
            const formStars = this.container.querySelector('.review-form-stars');
            if (formStars) {
                this.formRating = new StarRating(formStars, {
                    onChange: (rating) => { this.selectedRating = rating; }
                });
            }
            
            // Load reviews
            this.loadReviews();
            
            // Bind form submit
            const form = this.container.querySelector('.review-form');
            if (form) {
                form.addEventListener('submit', (e) => this.handleSubmit(e));
            }
            
            // Load more button
            if (this.loadMoreBtn) {
                this.loadMoreBtn.addEventListener('click', () => {
                    this.page++;
                    this.loadReviews(true);
                });
            }
        }
        
        async loadReviews(append = false) {
            try {
                const params = new URLSearchParams();
                if (this.propertyId) params.append('property_id', this.propertyId);
                if (this.agentId) params.append('agent_id', this.agentId);
                params.append('page', this.page);
                
                const response = await fetch(`/php/reviews/get_reviews.php?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    this.renderReviews(data, append);
                    this.updateSummary(data);
                }
            } catch (error) {
                console.error('Error loading reviews:', error);
            }
        }
        
        renderReviews(data, append) {
            if (!this.reviewsList) return;
            
            if (!append) {
                this.reviewsList.innerHTML = '';
            }
            
            if (data.reviews.length === 0 && !append) {
                this.reviewsList.innerHTML = `
                    <div class="reviews-empty">
                        <i class="far fa-comment-dots"></i>
                        <p>Пока нет отзывов. Будьте первым!</p>
                    </div>
                `;
                if (this.loadMoreBtn) this.loadMoreBtn.style.display = 'none';
                return;
            }
            
            data.reviews.forEach(review => {
                this.reviewsList.insertAdjacentHTML('beforeend', this.createReviewHTML(review));
            });
            
            // Show/hide load more button
            if (this.loadMoreBtn) {
                this.loadMoreBtn.style.display = this.page < data.pages ? 'block' : 'none';
            }
        }
        
        createReviewHTML(review) {
            const stars = Array(5).fill(0).map((_, i) => 
                `<i class="${i < review.rating ? 'fas' : 'far'} fa-star"></i>`
            ).join('');
            
            return `
                <div class="review-card">
                    <div class="review-card__header">
                        <div class="review-card__author">
                            <div class="review-card__avatar">${review.author_name.charAt(0).toUpperCase()}</div>
                            <div class="review-card__info">
                                <span class="review-card__name">${escapeHtml(review.author_name)}</span>
                                <span class="review-card__date">${review.created_at_formatted}</span>
                            </div>
                        </div>
                        <div class="review-card__rating">${stars}</div>
                    </div>
                    ${review.comment ? `<div class="review-card__comment">${escapeHtml(review.comment)}</div>` : ''}
                </div>
            `;
        }
        
        updateSummary(data) {
            const avgElement = this.container.querySelector('.reviews-avg-rating');
            const countElement = this.container.querySelector('.reviews-count');
            const starsElement = this.container.querySelector('.reviews-avg-stars');
            
            if (avgElement) avgElement.textContent = data.avg_rating.toFixed(1);
            if (countElement) countElement.textContent = data.total;
            
            if (starsElement) {
                const fullStars = Math.floor(data.avg_rating);
                const hasHalf = data.avg_rating % 1 >= 0.5;
                let starsHtml = '';
                
                for (let i = 0; i < 5; i++) {
                    if (i < fullStars) {
                        starsHtml += '<i class="fas fa-star"></i>';
                    } else if (i === fullStars && hasHalf) {
                        starsHtml += '<i class="fas fa-star-half-alt"></i>';
                    } else {
                        starsHtml += '<i class="far fa-star"></i>';
                    }
                }
                starsElement.innerHTML = starsHtml;
            }
        }
        
        async handleSubmit(e) {
            e.preventDefault();
            
            if (this.selectedRating === 0) {
                alert('Пожалуйста, выберите рейтинг');
                return;
            }
            
            const form = e.target;
            const comment = form.querySelector('textarea[name="comment"]')?.value || '';
            const name = form.querySelector('input[name="name"]')?.value || '';
            const submitBtn = form.querySelector('button[type="submit"]');

            submitBtn.disabled = true;
            submitBtn.textContent = 'Отправка...';

            try {
                const response = await fetch('/php/reviews/add_review.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        property_id: this.propertyId,
                        agent_id: this.agentId,
                        rating: this.selectedRating,
                        comment: comment,
                        name: name
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Show success message
                    form.innerHTML = `
                        <div class="review-success">
                            <i class="fas fa-check-circle"></i>
                            <p>Спасибо за отзыв! Он будет опубликован после модерации.</p>
                        </div>
                    `;
                } else {
                    alert(data.error || data.errors?.join('\n') || 'Ошибка при отправке');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Отправить отзыв';
                }
            } catch (error) {
                console.error('Error submitting review:', error);
                alert('Ошибка при отправке отзыва');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Отправить отзыв';
            }
        }
    }
    
    // Utility function
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize readonly star ratings
        document.querySelectorAll('[data-star-rating]').forEach(el => {
            new StarRating(el, {
                rating: parseFloat(el.dataset.starRating) || 0,
                readonly: true,
                size: el.dataset.starSize || 'medium'
            });
        });
        
        // Initialize reviews manager if on property page
        const reviewsSection = document.getElementById('reviewsSection');
        if (reviewsSection) {
            const propertyId = reviewsSection.dataset.propertyId;
            const agentId = reviewsSection.dataset.agentId;
            new ReviewsManager(propertyId, agentId);
        }
    });
    
    // Export for global access
    window.StarRating = StarRating;
    window.ReviewsManager = ReviewsManager;
})();
