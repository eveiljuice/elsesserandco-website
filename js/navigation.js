/**
 * Navigation Module - Elsesser & Co.
 * Handles sticky header, mobile menu, and sidebar
 */

(function() {
    'use strict';

    // DOM Elements
    const header = document.getElementById('header');
    const hamburger = document.getElementById('hamburger');
    const sidebar = document.getElementById('sidebar');
    const sidebarClose = document.getElementById('sidebarClose');
    const overlay = document.getElementById('overlay');

    // State
    let lastScrollY = 0;
    let isScrollingDown = false;

    /**
     * Initialize sticky header behavior
     */
    function initStickyHeader() {
        if (!header) return;

        const isTransparentHeader = header.classList.contains('header--transparent');
        
        function handleScroll() {
            const currentScrollY = window.scrollY;
            isScrollingDown = currentScrollY > lastScrollY;
            
            // Add solid background when scrolled past threshold
            if (currentScrollY > 100) {
                header.classList.remove('header--transparent');
                header.classList.add('header--solid');
            } else if (isTransparentHeader) {
                header.classList.add('header--transparent');
                header.classList.remove('header--solid');
            }
            
            lastScrollY = currentScrollY;
        }

        // Throttle scroll events
        let ticking = false;
        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(function() {
                    handleScroll();
                    ticking = false;
                });
                ticking = true;
            }
        });
        
        // Initial check
        handleScroll();
    }

    /**
     * Toggle mobile sidebar
     */
    function toggleSidebar(open) {
        if (!sidebar || !overlay) return;

        if (open) {
            sidebar.classList.add('sidebar--open');
            overlay.classList.add('overlay--visible');
            document.body.style.overflow = 'hidden';
            hamburger?.classList.add('hamburger--active');
        } else {
            sidebar.classList.remove('sidebar--open');
            overlay.classList.remove('overlay--visible');
            document.body.style.overflow = '';
            hamburger?.classList.remove('hamburger--active');
        }
    }

    /**
     * Initialize mobile menu
     */
    function initMobileMenu() {
        // Hamburger click
        hamburger?.addEventListener('click', function() {
            const isOpen = sidebar?.classList.contains('sidebar--open');
            toggleSidebar(!isOpen);
        });

        // Close button click
        sidebarClose?.addEventListener('click', function() {
            toggleSidebar(false);
        });

        // Overlay click
        overlay?.addEventListener('click', function() {
            toggleSidebar(false);
        });

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar?.classList.contains('sidebar--open')) {
                toggleSidebar(false);
            }
        });

        // Close on window resize (if desktop)
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebar?.classList.contains('sidebar--open')) {
                toggleSidebar(false);
            }
        });
    }

    /**
     * Initialize smooth scroll for anchor links
     */
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                
                // Skip if it's just "#"
                if (href === '#') return;
                
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    
                    // Close mobile menu if open
                    toggleSidebar(false);
                    
                    // Calculate offset for sticky header
                    const headerHeight = header?.offsetHeight || 80;
                    const targetPosition = target.getBoundingClientRect().top + window.scrollY - headerHeight;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
    }

    /**
     * Initialize active navigation link highlighting
     */
    function initActiveNav() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav__link, .sidebar__nav-link');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href && currentPath.includes(href.split('?')[0]) && href !== 'index.html') {
                link.classList.add('nav__link--active');
            }
        });
    }

    /**
     * Initialize all navigation features
     */
    function init() {
        initStickyHeader();
        initMobileMenu();
        initSmoothScroll();
        initActiveNav();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

