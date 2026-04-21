document.addEventListener('DOMContentLoaded', function() {
    const drawer = document.getElementById('cpdi-details-drawer');
    const backToTop = document.getElementById('cpdi-back-to-top');
    const grid = document.getElementById('cpdi-directory-list');

    /**
     * 1. Drawer Logic
     */
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('cpdi-details-trigger')) {
            e.preventDefault();
            const slug = e.target.dataset.slug;
            openDrawer(slug);
        }
        
        if (e.target.classList.contains('cpdi-drawer-close') || e.target === drawer) {
            drawer.close();
        }
    });

    async function openDrawer(slug) {
        drawer.showModal();
        const container = document.getElementById('cpdi-drawer-inner');
        container.innerHTML = '<span class="spinner is-active"></span>';

        try {
            // We use the AJAX action we'll define in the Helpers trait
            const response = await fetch(`${cpdiData.ajaxUrl}?action=cpdi_get_details&slug=${slug}&type=${cpdiData.type}&_wpnonce=${cpdiData.nonce}`);
            const data = await response.json();
            if (data.success) {
                container.innerHTML = data.data.html;
            }
        } catch (error) {
            container.innerHTML = 'Error loading details.';
        }
    }

    /**
     * 2. Back to Top Logic
     */
    window.addEventListener('scroll', () => {
        backToTop.style.display = window.scrollY > 500 ? 'block' : 'none';
    });

    backToTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    /**
     * 3. Infinite Scroll (Intersection Observer)
     */
    const loader = document.getElementById('cpdi-loader');
    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !grid.classList.contains('loading')) {
            loadMoreItems();
        }
    }, { threshold: 1.0 });

    if (loader) observer.observe(loader);

    async function loadMoreItems() {
        const page = parseInt(grid.dataset.page) + 1;
        grid.classList.add('loading');
        loader.style.display = 'block';

        // Fetch logic similar to search, but appending results
        // ... (We will implement the specific AJAX handler next)
    }

    /**
     * 4. Search Feature
     */
    const searchInput = document.getElementById('cpdi-search-input');
    // Implement debounce for search to avoid hitting the API every keystroke
});
