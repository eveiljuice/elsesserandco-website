/**
 * Filters Module - Elsesser & Co.
 * Handles property filtering on the properties page
 */

(function() {
    'use strict';

    const filtersForm = document.getElementById('filtersForm');
    const propertiesGrid = document.getElementById('propertiesGrid');
    const resultsCount = document.getElementById('resultsCount');
    const sortSelect = document.getElementById('sortBy');

    if (!filtersForm || !propertiesGrid) return;

    // Get all property cards
    let propertyCards = Array.from(propertiesGrid.querySelectorAll('.property-card'));

    /**
     * Get filter values from form
     */
    function getFilterValues() {
        return {
            search: document.getElementById('searchInput')?.value.toLowerCase() || '',
            type: document.getElementById('filterType')?.value || '',
            propertyType: document.getElementById('filterProperty')?.value || '',
            minPrice: parseInt(document.getElementById('filterMinPrice')?.value) || 0,
            maxPrice: parseInt(document.getElementById('filterMaxPrice')?.value) || Infinity,
            beds: document.getElementById('filterBeds')?.value || '',
            baths: document.getElementById('filterBaths')?.value || ''
        };
    }

    /**
     * Filter properties based on criteria
     */
    function filterProperties() {
        const filters = getFilterValues();
        let visibleCount = 0;

        propertyCards.forEach(card => {
            const price = parseInt(card.dataset.price) || 0;
            const beds = parseInt(card.dataset.beds) || 0;
            const baths = parseInt(card.dataset.baths) || 0;
            const type = card.dataset.type || '';
            const title = card.querySelector('.property-card__title')?.textContent.toLowerCase() || '';
            const location = card.querySelector('.property-card__location')?.textContent.toLowerCase() || '';

            let isVisible = true;

            // Search filter (title and location)
            if (filters.search && !title.includes(filters.search) && !location.includes(filters.search)) {
                isVisible = false;
            }

            // Property type filter
            if (filters.propertyType && type !== filters.propertyType) {
                isVisible = false;
            }

            // Price range filter
            if (price < filters.minPrice || price > filters.maxPrice) {
                isVisible = false;
            }

            // Bedrooms filter
            if (filters.beds) {
                const filterBeds = parseInt(filters.beds);
                if (filters.beds === '5' && beds < 5) {
                    isVisible = false;
                } else if (filters.beds !== '5' && beds !== filterBeds) {
                    isVisible = false;
                }
            }

            // Bathrooms filter
            if (filters.baths) {
                const filterBaths = parseInt(filters.baths);
                if (filters.baths === '4' && baths < 4) {
                    isVisible = false;
                } else if (filters.baths !== '4' && baths !== filterBaths) {
                    isVisible = false;
                }
            }

            // Show/hide card with animation
            if (isVisible) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Update results count
        if (resultsCount) {
            resultsCount.textContent = visibleCount;
        }
    }

    /**
     * Sort properties
     */
    function sortProperties() {
        const sortValue = sortSelect?.value || 'newest';
        
        propertyCards.sort((a, b) => {
            const priceA = parseInt(a.dataset.price) || 0;
            const priceB = parseInt(b.dataset.price) || 0;
            const idA = parseInt(a.dataset.id) || 0;
            const idB = parseInt(b.dataset.id) || 0;
            
            switch (sortValue) {
                case 'price-asc':
                    return priceA - priceB;
                case 'price-desc':
                    return priceB - priceA;
                case 'area':
                    // Sort by area (extract from specs)
                    const areaA = parseInt(a.querySelector('.property-card__spec:last-child')?.textContent.replace(/\D/g, '')) || 0;
                    const areaB = parseInt(b.querySelector('.property-card__spec:last-child')?.textContent.replace(/\D/g, '')) || 0;
                    return areaB - areaA;
                case 'newest':
                default:
                    return idB - idA;
            }
        });

        // Re-append cards in new order
        propertyCards.forEach(card => {
            propertiesGrid.appendChild(card);
        });

        // Re-apply filters after sorting
        filterProperties();
    }

    /**
     * Handle URL parameters
     */
    function handleUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Set filter values from URL
        const type = urlParams.get('type');
        const community = urlParams.get('community');
        const category = urlParams.get('category');

        if (type) {
            const typeSelect = document.getElementById('filterType');
            if (typeSelect) {
                typeSelect.value = type;
            }
        }

        if (community) {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                // Convert community slug to readable name
                const communityNames = {
                    'center': 'Центр',
                    'akademicheskiy': 'Академический',
                    'shirokaya-rechka': 'Широкая Речка',
                    'uktus': 'Уктус'
                };
                searchInput.value = communityNames[community] || community;
            }
        }

        // Apply initial filters
        filterProperties();
    }

    /**
     * Initialize filter events
     */
    function initFilterEvents() {
        // Form submit
        filtersForm.addEventListener('submit', function(e) {
            e.preventDefault();
            filterProperties();
        });

        // Real-time filtering on select changes
        const selects = filtersForm.querySelectorAll('select');
        selects.forEach(select => {
            select.addEventListener('change', filterProperties);
        });

        // Search on input (debounced)
        const searchInput = document.getElementById('searchInput');
        let searchTimeout;
        searchInput?.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterProperties, 300);
        });

        // Sort change
        sortSelect?.addEventListener('change', sortProperties);
    }

    /**
     * Initialize filters
     */
    function init() {
        initFilterEvents();
        handleUrlParams();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

