/**
 * ClassicPress Directory Integration - Vanilla JS Version
 */
(function() {
    'use strict';

    const CPDI = {
        grid: document.getElementById('cpdi-directory-list'),
        drawer: document.getElementById('cpdi-details-drawer'),
        loader: document.getElementById('cpdi-loader'),
        backToTop: document.getElementById('cpdi-back-to-top'),
        page: 1,
        canLoad: true,
        type: cpdiData.type,

        init() {
            this.bindEvents();
            this.setupInfiniteScroll();
        },

        bindEvents() {
            // Search submission
            const searchForm = document.getElementById('cpdi-search-form');
            if (searchForm) {
                searchForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.performSearch();
                });
            }

            // Back to Top scroll visibility
            window.addEventListener('scroll', () => {
                if (window.scrollY > 500) {
                    this.backToTop.style.display = 'block';
                } else {
                    this.backToTop.style.display = 'none';
                }
            });

            this.backToTop.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });

            // Delegation for dynamic elements (Cards/Buttons)
            document.addEventListener('click', (e) => {
                const target = e.target;

                // Drawer Details
                if (target.classList.contains('cpdi-details-trigger')) {
                    e.preventDefault();
                    this.openDrawer(target.dataset.slug);
                }

                // Close Drawer
                if (target.classList.contains('cpdi-drawer-close')) {
                    this.drawer.close();
                }

                // Install Button
                if (target.classList.contains('cpdi-button-install')) {
                    this.handleInstall(target);
                }

                // Activate Button
                if (target.classList.contains('cpdi-button-activate')) {
                    this.handleActivation(target);
                }
            });
        },

        setupInfiniteScroll() {
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting && this.canLoad) {
                    this.loadMore();
                }
            }, { threshold: 0.1 });

            if (this.loader) {
                observer.observe(this.loader);
            }
        },

        async fetchItems(page) {
            const s = document.getElementById('cpdi-search-input').value;
            const stype = document.getElementById('cpdi-search-type').value;
            
            const url = new URL(cpdiData.ajaxUrl);
            url.searchParams.append('action', 'cpdi_fetch_items');
            url.searchParams.append('_wpnonce', cpdiData.nonce);
            url.searchParams.append('type', this.type);
            url.searchParams.append('page', page);
            url.searchParams.append('s', s);
            url.searchParams.append('stype', stype);

            const response = await fetch(url);
            return await response.json();
        },

        async performSearch() {
            this.page = 1;
            this.grid.innerHTML = '<span class="spinner is-active"></span>';
            
            const result = await this.fetchItems(1);
            if (result.success) {
                this.grid.innerHTML = result.data.html;
                this.page = result.data.next_page;
                this.canLoad = !!this.page;
            }
        },

        async loadMore() {
            if (!this.canLoad) return;
            this.canLoad = false;
            this.loader.style.display = 'block';

            const result = await this.fetchItems(this.page);
            
            if (result.success && result.data.html) {
                this.grid.insertAdjacentHTML('beforeend', result.data.html);
                this.page = result.data.next_page;
                this.canLoad = !!this.page;
            } else {
                this.canLoad = false;
            }
            this.loader.style.display = 'none';
        },

        async openDrawer(slug) {
            this.drawer.showModal();
            const inner = document.getElementById('cpdi-drawer-inner');
            inner.innerHTML = '<span class="spinner is-active"></span>';

            const url = new URL(cpdiData.ajaxUrl);
            url.searchParams.append('action', 'cpdi_get_details');
            url.searchParams.append('_wpnonce', cpdiData.nonce);
            url.searchParams.append('slug', slug);
            url.searchParams.append('type', this.type);

            const response = await fetch(url);
            const result = await response.json();

            if (result.success) {
                inner.innerHTML = result.data.html;
            }
        },

        async handleInstall(btn) {
            const slug = btn.dataset.slug;
            const card = btn.closest('.cpdi-card');
            const parent = card.dataset.parent;

            btn.classList.add('updating-message');
            btn.disabled = true;
            btn.textContent = cpdiData.strings.installing;

            // Step 1: Parent Dependency
            if (this.type === 'theme' && parent) {
                btn.textContent = cpdiData.strings.installing_parent;
                const parentResult = await this.runInstall(parent);
                if (!parentResult.success) {
                    alert('Error installing parent: ' + parent);
                    return;
                }
            }

            // Step 2: Main Install
            btn.textContent = cpdiData.strings.installing;
            const result = await this.runInstall(slug);

            if (result.success) {
                btn.classList.remove('updating-message');
                btn.textContent = cpdiData.strings.installed;
                window.location.reload(); 
            }
        },

        async runInstall(slug) {
            const formData = new FormData();
            formData.append('action', 'cpdi_install_item');
            formData.append('_wpnonce', cpdiData.nonce);
            formData.append('slug', slug);
            formData.append('type', this.type);

            const response = await fetch(cpdiData.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            return await response.json();
        }
    };

    document.addEventListener('DOMContentLoaded', () => CPDI.init());
})();
