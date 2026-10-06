document.addEventListener('DOMContentLoaded', () => {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');

    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    }

    // Category Carousel Logic
    const carousel = document.getElementById('category-carousel');
    const scrollLeftBtn = document.getElementById('scroll-left');
    const scrollRightBtn = document.getElementById('scroll-right');

    if (carousel && scrollLeftBtn && scrollRightBtn) {
        const scrollAmount = 250;

        const updateScrollButtons = () => {
            const isScrollable = carousel.scrollWidth > carousel.clientWidth;
            const isAtStart = carousel.scrollLeft <= 10;
            const isAtEnd = Math.ceil(carousel.scrollLeft + carousel.clientWidth) >= carousel.scrollWidth - 10;

            if (!isScrollable) {
                scrollLeftBtn.style.opacity = '0';
                scrollLeftBtn.style.pointerEvents = 'none';
                scrollRightBtn.style.opacity = '0';
                scrollRightBtn.style.pointerEvents = 'none';
            } else {
                scrollLeftBtn.style.opacity = isAtStart ? '0' : '1';
                scrollLeftBtn.style.pointerEvents = isAtStart ? 'none' : 'auto';
                scrollRightBtn.style.opacity = isAtEnd ? '0' : '1';
                scrollRightBtn.style.pointerEvents = isAtEnd ? 'none' : 'auto';
            }
        };

        scrollLeftBtn.addEventListener('click', (e) => {
            e.preventDefault();
            carousel.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        });

        scrollRightBtn.addEventListener('click', (e) => {
            e.preventDefault();
            carousel.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        });


        carousel.addEventListener('scroll', updateScrollButtons);
        window.addEventListener('resize', updateScrollButtons);
        
        // Initial check
        setTimeout(() => {
            // Scroll active item into view
            const activeLink = carousel.querySelector('.border-sacred-gold');
            if (activeLink) {
                // Calculate center position
                const scrollLeftPos = activeLink.offsetLeft - (carousel.clientWidth / 2) + (activeLink.clientWidth / 2);
                // Ensure it's not negative
                carousel.scrollLeft = Math.max(0, scrollLeftPos);
            }
            updateScrollButtons();
        }, 50);
    }

    // Navigation Scroll Logic
    const mainNav = document.getElementById('main-nav');
    const mobileLinkMenu = document.getElementById('mobile-menu'); // Already defined as mobileMenu on line 3, but let's re-select or use the existing one.
    let lastScrollY = window.scrollY;
    const hideThreshold = 150; 

    if (mainNav) {
        window.addEventListener('scroll', () => {
            const currentScrollY = window.scrollY;
            const mobileMenuOpen = mobileMenu && !mobileMenu.classList.contains('hidden');
            
            // Prevent hiding if mobile menu is open
            if (mobileMenuOpen) {
                if (mainNav.classList.contains('nav-hidden')) {
                    mainNav.classList.remove('nav-hidden');
                }
                return;
            }

            // Always show at the very top
            if (currentScrollY < 50) {
                if (mainNav.classList.contains('nav-hidden')) {
                    mainNav.classList.remove('nav-hidden');
                }
                lastScrollY = currentScrollY;
                return;
            }

            // Determine scroll direction and intent
            const diff = currentScrollY - lastScrollY;

            if (diff > 5 && currentScrollY > hideThreshold) {
                // Scrolling Down
                if (!mainNav.classList.contains('nav-hidden')) {
                    mainNav.classList.add('nav-hidden');
                    mainNav.style.top = '-200px'; // Nuclear override
                }
            } else if (diff < -15) {
                // Scrolling Up
                if (mainNav.classList.contains('nav-hidden')) {
                    mainNav.classList.remove('nav-hidden');
                    mainNav.style.top = '0'; // Nuclear override
                }
            }
            
            lastScrollY = currentScrollY;
        }, { passive: true });
    }

    // Dynamic Search Logic
    const searchInput = document.getElementById('search-articles');
    const articleContainer = document.getElementById('article-listing-container');
    
    if (searchInput && articleContainer) {
        let debounceTimer;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const query = e.target.value;
                const url = new URL(window.location.href);
                if (query.trim() !== '') {
                    url.searchParams.set('search', query);
                } else {
                    url.searchParams.delete('search');
                }
                url.searchParams.delete('page'); // reset to page 1 on new search
                
                // Add a loading state optionally
                articleContainer.style.opacity = '0.5';
                articleContainer.style.pointerEvents = 'none';
                
                // Fetch new results
                fetch(url.toString())
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newContainer = doc.getElementById('article-listing-container');
                        if (newContainer) {
                            articleContainer.innerHTML = newContainer.innerHTML;
                        }
                        articleContainer.style.opacity = '1';
                        articleContainer.style.pointerEvents = 'auto';
                        
                        // Update URL without reloading
                        window.history.pushState({}, '', url.toString());
                    })
                    .catch(err => {
                        console.error('Error fetching search results:', err);
                        articleContainer.style.opacity = '1';
                        articleContainer.style.pointerEvents = 'auto';
                    });
            }, 300); // 300ms debounce
        });
    }
});


