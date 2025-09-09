/**
 * XPM Image SEO Lazy Loading
 */
(function() {
    'use strict';
    
    // Configuration
    const config = {
        threshold: (typeof xpmLazy !== 'undefined' && xpmLazy.threshold) ? parseInt(xpmLazy.threshold) : 200,
        effect: (typeof xpmLazy !== 'undefined' && xpmLazy.effect) ? xpmLazy.effect : 'fade'
    };
    
    // Check for Intersection Observer support
    const supportsIntersectionObserver = 'IntersectionObserver' in window;
    
    let lazyImages = [];
    let imageObserver;
    
    /**
     * Initialize lazy loading
     */
    function initLazyLoading() {
        lazyImages = document.querySelectorAll('.xpm-lazy');
        
        if (lazyImages.length === 0) {
            return;
        }
        
        if (supportsIntersectionObserver) {
            imageObserver = new IntersectionObserver(onIntersection, {
                rootMargin: config.threshold + 'px'
            });
            
            lazyImages.forEach(img => imageObserver.observe(img));
        } else {
            // Fallback for older browsers
            loadImagesImmediately();
        }
    }
    
    /**
     * Handle intersection observer callback
     */
    function onIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                loadImage(img);
                imageObserver.unobserve(img);
            }
        });
    }
    
    /**
     * Load a single image
     */
    function loadImage(img) {
        const src = img.getAttribute('data-src');
        if (!src) return;
        
        // Create new image to preload
        const imageLoader = new Image();
        
        imageLoader.onload = function() {
            // Apply effect class before changing src
            img.classList.add('xpm-loading');
            
            // Set the real source
            img.src = src;
            img.removeAttribute('data-src');
            
            // Add loaded class after a short delay for effect
            setTimeout(() => {
                img.classList.remove('xpm-loading');
                img.classList.add('xpm-loaded');
                img.classList.add('xpm-effect-' + config.effect);
            }, 50);
            
            // Remove lazy class
            img.classList.remove('xpm-lazy');
        };
        
        imageLoader.onerror = function() {
            // Handle error - remove lazy class and show original src
            img.classList.remove('xpm-lazy');
            img.src = src;
            img.removeAttribute('data-src');
        };
        
        // Start loading
        imageLoader.src = src;
    }
    
    /**
     * Fallback: load all images immediately
     */
    function loadImagesImmediately() {
        lazyImages.forEach(loadImage);
    }
    
    /**
     * Handle scroll for older browsers
     */
    function onScroll() {
        if (!supportsIntersectionObserver) {
            lazyImages.forEach((img, index) => {
                if (img.classList.contains('xpm-lazy') && isInViewport(img)) {
                    loadImage(img);
                }
            });
        }
    }
    
    /**
     * Check if element is in viewport (fallback)
     */
    function isInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= -config.threshold &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) + config.threshold &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }
    
    /**
     * Reinitialize for dynamic content
     */
    function reinitialize() {
        if (imageObserver) {
            // Disconnect existing observer
            imageObserver.disconnect();
        }
        
        // Reinitialize
        initLazyLoading();
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLazyLoading);
    } else {
        initLazyLoading();
    }
    
    // Add scroll listener for fallback
    if (!supportsIntersectionObserver) {
        let scrollTimeout;
        window.addEventListener('scroll', function() {
            if (scrollTimeout) {
                clearTimeout(scrollTimeout);
            }
            scrollTimeout = setTimeout(onScroll, 100);
        });
    }
    
    // Expose reinitialize function globally for dynamic content
    window.xpmLazyReinit = reinitialize;
    
    console.log('XPM Lazy Loading initialized with config:', config);
})();