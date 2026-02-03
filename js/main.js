/**
 * Main Module - Elsesser & Co.
 * Core functionality and property modal
 */

(function() {
    'use strict';

    // Property data for modal
    const propertyData = {
        1: {
            id: 1,
            price: '₽ 35,000,000',
            title: 'Роскошный коттедж в Широкой Речке',
            beds: 5,
            baths: 6,
            area: '790 м²',
            location: 'Широкая Речка, Екатеринбург',
            image: 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=900&q=80',
            description: 'Потрясающий коттедж с просторными интерьерами, элегантной отделкой и открытой планировкой, идеальной для современной жизни. Расположен в зелёном районе с развитой инфраструктурой, идеально сочетает приватность, стиль жизни и удобство.'
        },
        2: {
            id: 2,
            price: '₽ 18,500,000',
            title: 'Апартаменты в Центре',
            beds: 3,
            baths: 4,
            area: '265 м²',
            location: 'Центр, Екатеринбург',
            image: 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=900&q=80',
            description: 'Роскошные апартаменты с захватывающим видом на центр города. Современный дизайн, премиальная отделка и первоклассные удобства делают это жильё идеальным для тех, кто ценит комфорт и престиж.'
        },
        3: {
            id: 3,
            price: '₽ 28,000,000',
            title: 'Пентхаус в Академическом',
            beds: 4,
            baths: 5,
            area: '390 м²',
            location: 'Академический, Екатеринбург',
            image: 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=900&q=80',
            description: 'Эксклюзивный пентхаус с панорамными видами на город. Просторные террасы, высокие потолки и премиальная отделка создают атмосферу роскоши и комфорта.'
        },
        4: {
            id: 4,
            price: '₽ 15,500,000',
            title: 'Апартаменты в ЖК "Кольцово"',
            beds: 2,
            baths: 3,
            area: '154 м²',
            location: 'Кольцово, Екатеринбург',
            image: 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=900&q=80',
            description: 'Современные апартаменты в престижном комплексе. Свободно для немедленного заселения, отличная инвестиционная возможность с высокой доходностью от аренды.'
        },
        5: {
            id: 5,
            price: '₽ 25,500,000',
            title: 'Апартаменты в ЖК "Макаровский Квартал"',
            beds: 2,
            baths: 3,
            area: '136 м²',
            location: 'Центр, Екатеринбург',
            image: 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?w=900&q=80',
            description: 'Элитные апартаменты в одном из самых престижных адресов центра. Вид на реку, современный дизайн и доступ к премиальным удобствам комплекса.'
        },
        6: {
            id: 6,
            price: '₽ 22,000,000',
            title: 'Дом в Уктусе',
            beds: 4,
            baths: 5,
            area: '209 м²',
            location: 'Уктус, Екатеринбург',
            image: 'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?w=900&q=80',
            description: 'Новый современный дом с просторными интерьерами и видом на лес. Расположен в тихом районе с прямым выходом к зелёной зоне. Идеально подходит для семьи.'
        },
        7: {
            id: 7,
            price: '₽ 11,000,000',
            title: 'Студия в ЖК "Серебряный ручей"',
            beds: 1,
            baths: 2,
            area: '83 м²',
            location: 'Академический, Екатеринбург',
            image: 'https://images.unsplash.com/photo-1600573472556-e636c2acda88?w=900&q=80',
            description: 'Стильная студия на высоком этаже с видом на город. Отличная инвестиционная возможность в быстрорастущем районе Екатеринбурга.'
        },
        8: {
            id: 8,
            price: '₽ 42,000,000',
            title: 'Особняк в Широкой Речке',
            beds: 6,
            baths: 7,
            area: '948 м²',
            location: 'Широкая Речка, Екатеринбург',
            image: 'https://images.unsplash.com/photo-1600585154526-990dced4db0d?w=900&q=80',
            description: 'Роскошный особняк с участком, бассейном и садом на территории. Потрясающие виды, максимальная приватность. Уникальная возможность жить в одном из самых престижных районов города.'
        }
    };

    // Modal elements
    const modal = document.getElementById('propertyModal');
    const modalClose = document.getElementById('modalClose');
    const modalImage = document.getElementById('modalImage');
    const modalPrice = document.getElementById('modalPrice');
    const modalBeds = document.getElementById('modalBeds');
    const modalBaths = document.getElementById('modalBaths');
    const modalArea = document.getElementById('modalArea');
    const modalLocation = document.getElementById('modalLocation');
    const modalDescription = document.getElementById('modalDescription');

    /**
     * Open property modal
     */
    function openModal(propertyId) {
        const property = propertyData[propertyId];
        if (!property || !modal) return;

        // Populate modal
        if (modalImage) modalImage.src = property.image;
        if (modalPrice) modalPrice.textContent = property.price;
        if (modalBeds) modalBeds.textContent = property.beds;
        if (modalBaths) modalBaths.textContent = property.baths;
        if (modalArea) modalArea.textContent = property.area;
        if (modalLocation) modalLocation.querySelector('span').textContent = property.location;
        if (modalDescription) modalDescription.textContent = property.description;

        // Show modal
        modal.classList.add('modal--open');
        document.body.style.overflow = 'hidden';
    }

    /**
     * Close property modal
     */
    function closeModal() {
        if (!modal) return;
        
        modal.classList.remove('modal--open');
        document.body.style.overflow = '';
    }

    /**
     * Initialize property card interactions
     */
    function initPropertyCards() {
        const propertyCards = document.querySelectorAll('.property-card');
        
        propertyCards.forEach(card => {
            // Click on card (except WhatsApp button) opens modal
            card.addEventListener('click', function(e) {
                // Don't open modal if clicking on chat button or nav buttons
                if (e.target.closest('.property-card__whatsapp') || 
                    e.target.closest('.property-card__nav')) {
                    return;
                }
                
                const propertyId = this.dataset.id;
                if (propertyId) {
                    openModal(propertyId);
                }
            });

            // Make card appear clickable
            card.style.cursor = 'pointer';
        });
    }

    /**
     * Initialize modal interactions
     */
    function initModal() {
        if (!modal) return;

        // Close button
        modalClose?.addEventListener('click', closeModal);

        // Click outside modal
        modal.querySelector('.modal__overlay')?.addEventListener('click', closeModal);

        // Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('modal--open')) {
                closeModal();
            }
        });
    }

    /**
     * Initialize search box toggle
     */
    function initSearchBox() {
        const toggleBtns = document.querySelectorAll('.search-box__toggle-btn');
        
        toggleBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active from all
                toggleBtns.forEach(b => b.classList.remove('search-box__toggle-btn--active'));
                // Add active to clicked
                this.classList.add('search-box__toggle-btn--active');
            });
        });

        // Search button functionality
        const searchBox = document.querySelector('.search-box');
        const searchInput = searchBox?.querySelector('.search-box__input');
        const searchBtn = searchBox?.querySelector('.search-box__btn');

        searchBtn?.addEventListener('click', function() {
            const query = searchInput?.value.trim();
            const activeType = searchBox?.querySelector('.search-box__toggle-btn--active')?.dataset.type || 'buy';
            
            if (query) {
                window.location.href = `properties.html?type=${activeType}&search=${encodeURIComponent(query)}`;
            } else {
                window.location.href = `properties.html?type=${activeType}`;
            }
        });

        // Enter key on search
        searchInput?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchBtn?.click();
            }
        });
    }

    /**
     * Initialize lazy loading for images
     */
    function initLazyLoading() {
        const images = document.querySelectorAll('img[data-src]');
        
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                });
            });

            images.forEach(img => imageObserver.observe(img));
        } else {
            // Fallback for older browsers
            images.forEach(img => {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            });
        }
    }

    /**
     * Initialize scroll animations
     */
    function initScrollAnimations() {
        const animatedElements = document.querySelectorAll('.property-card, .community-card, .value-card, .benefit-card, .feature-card');
        
        if ('IntersectionObserver' in window) {
            const animationObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });

            animatedElements.forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                animationObserver.observe(el);
            });
        }
    }

    /**
     * Initialize all main functionality
     */
    function init() {
        initPropertyCards();
        initModal();
        initSearchBox();
        initLazyLoading();
        initScrollAnimations();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

