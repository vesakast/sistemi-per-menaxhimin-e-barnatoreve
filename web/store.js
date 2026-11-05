(() => {
    document.documentElement.classList.add('has-js');

    // Ruaj pozicionin në katalog pas filtrimit, faqëzimit dhe shtimit në shportë.
    const returnScrollKey = 'herbabloom:return-scroll';

    const saveReturnScroll = (target = '') => {
        try {
            sessionStorage.setItem(returnScrollKey, JSON.stringify({
                path: window.location.pathname,
                target,
                y: window.scrollY,
                savedAt: Date.now(),
            }));
        } catch (_) {
            // Faqja vazhdon të punojë edhe nëse sessionStorage nuk është i disponueshëm.
        }
    };

    const restoreReturnScroll = () => {
        let saved;

        try {
            saved = JSON.parse(sessionStorage.getItem(returnScrollKey) || 'null');
            sessionStorage.removeItem(returnScrollKey);
        } catch (_) {
            return;
        }

        if (
            !saved
            || saved.path !== window.location.pathname
            || Date.now() - Number(saved.savedAt) > 15 * 60 * 1000
            || !Number.isFinite(Number(saved.y))
        ) {
            return;
        }

        requestAnimationFrame(() => requestAnimationFrame(() => {
            const targetElement = saved.target
                ? document.getElementById(saved.target)
                : null;
            let scrollTop = Number(saved.y);

            if (targetElement) {
                const stickyHeader = document.querySelector('.store-header');
                const headerHeight = stickyHeader?.getBoundingClientRect().height || 0;
                scrollTop = window.scrollY
                    + targetElement.getBoundingClientRect().top
                    - headerHeight
                    - 14;
            }

            window.scrollTo(0, Math.max(0, scrollTop));
        }));
    };

    window.addEventListener('pageshow', restoreReturnScroll, { once: true });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.id === 'catalog-search') {
            saveReturnScroll('product-results');
            return;
        }

        const action = form.querySelector('input[name="action"]')?.value;

        if (action !== 'add' && action !== 'add_bundle') {
            return;
        }

        try {
            const destination = new URL(
                form.action || window.location.href,
                window.location.href
            );

            if (destination.pathname.endsWith('/web/shporta.php')) {
                const isProductCatalog = window.location.pathname.endsWith('/web/produktet.php');
                saveReturnScroll(isProductCatalog ? 'product-results' : '');
            }
        } catch (_) {
            // Injoro adresat e pavlefshme të formularit.
        }
    }, true);

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const paginationLink = event.target.closest(
            'nav[aria-label="Faqet"] a[href]'
        );

        if (paginationLink) {
            saveReturnScroll('product-results');
        }
    });

    const slides = [...document.querySelectorAll('[data-hero-slide]')];
    const dots = [...document.querySelectorAll('[data-hero-dot]')];

    if (slides.length > 1) {
        let active = 0;
        let fallbackTimer;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

        const stopFallbackTimer = () => {
            window.clearTimeout(fallbackTimer);
        };

        const playActiveVideo = (video, restart) => {
            if (reducedMotion.matches) {
                video.pause();
                return;
            }

            if (restart) {
                video.currentTime = 0;
            }

            const playback = video.play();

            if (playback) {
                playback.catch((error) => {
                    if (error.name !== 'AbortError') {
                        console.error('HerbaBloom hero video playback failed.', error);
                    }
                });
            }
        };

        const showSlide = (next, restartVideo = true) => {
            const nextIndex = (next + slides.length) % slides.length;

            if (nextIndex === active) {
                return;
            }

            stopFallbackTimer();
            slides[active].querySelector('video')?.pause();
            active = nextIndex;

            slides.forEach((slide, index) => {
                const isActive = index === active;
                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', String(!isActive));
            });

            dots.forEach((dot, index) => {
                const isActive = index === active;
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-current', String(isActive));
            });

            const activeVideo = slides[active].querySelector('video');

            if (activeVideo) {
                playActiveVideo(activeVideo, restartVideo);
            } else if (!reducedMotion.matches) {
                fallbackTimer = window.setTimeout(() => showSlide(active + 1), 20000);
            }
        };

        slides.forEach((slide, index) => {
            const video = slide.querySelector('video');

            if (!video) {
                return;
            }

            video.addEventListener('ended', () => {
                if (index === active && !reducedMotion.matches) {
                    showSlide(active + 1);
                }
            });
        });

        playActiveVideo(slides[active].querySelector('video'), false);

        document.querySelector('[data-hero-prev]')?.addEventListener('click', () => {
            showSlide(active - 1);
        });

        document.querySelector('[data-hero-next]')?.addEventListener('click', () => {
            showSlide(active + 1);
        });

        dots.forEach((dot) => dot.addEventListener('click', () => {
            showSlide(Number(dot.dataset.heroDot));
        }));

        reducedMotion.addEventListener('change', () => {
            const activeVideo = slides[active].querySelector('video');

            if (!activeVideo) {
                return;
            }

            if (reducedMotion.matches) {
                activeVideo.pause();
                stopFallbackTimer();
            } else {
                playActiveVideo(activeVideo, false);
            }
        });
    }

    const revealTargets = document.querySelectorAll('[data-reveal]');

    if (
        'IntersectionObserver' in window
        && !window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        const observer = new IntersectionObserver((entries, currentObserver) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    currentObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });

        revealTargets.forEach((target) => observer.observe(target));
    } else {
        revealTargets.forEach((target) => target.classList.add('is-visible'));
    }

    const toast = document.querySelector('.store-toast');
    const params = new URLSearchParams(window.location.search);

    if (toast && params.has('njoftim')) {
        const messages = {
            shporta: 'Produkti u shtua në shportë.',
            favorite: 'Produkti u ruajt te të preferuarat.',
            porosia: 'Porosia u regjistrua me sukses.',
        };

        const message = messages[params.get('njoftim')];

        if (message) {
            toast.textContent = message;
            toast.classList.add('is-visible');
            window.setTimeout(() => toast.classList.remove('is-visible'), 3200);
            params.delete('njoftim');

            const query = params.toString();

            window.history.replaceState(
                {},
                '',
                `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`
            );
        }
    }

    document.querySelectorAll('form[data-loading-label]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const button = event.submitter || form.querySelector('button[type="submit"]');

            if (!(button instanceof HTMLButtonElement) || button.disabled) {
                return;
            }

            button.dataset.originalLabel = button.textContent || '';
            button.textContent = form.dataset.loadingLabel || 'Duke u përpunuar…';
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
    });

    const searchDialog = document.querySelector('#storeSearch');
    const searchOpen = document.querySelector('[data-search-open]');
    const searchClose = searchDialog?.querySelector('[data-search-close]');
    const globalSearchInput = searchDialog?.querySelector('[data-store-search-input]');
    const globalSearchResults = searchDialog?.querySelector('[data-store-search-results]');
    const globalSearchStatus = searchDialog?.querySelector('[data-store-search-status]');

    if (
        searchDialog instanceof HTMLDialogElement
        && searchOpen instanceof HTMLButtonElement
        && globalSearchInput instanceof HTMLInputElement
        && globalSearchResults instanceof HTMLUListElement
        && globalSearchStatus
    ) {
        let globalSearchTimer;
        let globalSearchRequest;

        const pageMatches = [
            { label: 'Rreth nesh', href: '../web/rreth-nesh.php', terms: ['rreth', 'histori', 'mision'] },
            { label: 'Kontakt', href: '../web/kontakti.php', terms: ['kontakt', 'email', 'telefon'] },
            { label: 'Oferta dhe sete', href: '../web/oferta.php', terms: ['oferta', 'sete', 'set'] },
        ];

        const closeSearch = () => {
            searchDialog.close();
            searchOpen.focus();
        };

        const addGlobalSearchResult = (label, href, detail = '') => {
            const item = document.createElement('li');
            const link = document.createElement('a');

            link.href = href;
            link.textContent = label;

            if (detail) {
                const description = document.createElement('small');
                description.textContent = detail;
                link.append(description);
            }

            item.append(link);
            globalSearchResults.append(item);
        };

        const findGlobalResults = async () => {
            const term = globalSearchInput.value.trim();
            globalSearchResults.replaceChildren();
            globalSearchRequest?.abort();

            if (term.length < 2) {
                globalSearchStatus.textContent = 'Shkruaj të paktën dy shkronja për të kërkuar.';
                return;
            }

            const normalizedTerm = term.toLocaleLowerCase('sq');
            let resultCount = 0;

            document.querySelectorAll('.store-search-links a').forEach((link) => {
                if (link.textContent.toLocaleLowerCase('sq').includes(normalizedTerm)) {
                    addGlobalSearchResult(link.textContent.trim(), link.href, 'Kategori ose faqe');
                    resultCount += 1;
                }
            });

            pageMatches.forEach((entry) => {
                if (
                    entry.terms.some((keyword) =>
                        keyword.includes(normalizedTerm) || normalizedTerm.includes(keyword)
                    )
                    && ![...globalSearchResults.querySelectorAll('a')]
                        .some((link) => link.textContent === entry.label)
                ) {
                    addGlobalSearchResult(
                        entry.label,
                        new URL(entry.href, window.location.href).href,
                        'Faqe'
                    );
                    resultCount += 1;
                }
            });

            globalSearchRequest = new AbortController();
            globalSearchStatus.textContent = 'Duke kërkuar produkte…';

            try {
                const endpoint = new URL('../api/products', window.location.href);
                endpoint.search = new URLSearchParams({ q: term, page_size: '8' });

                const response = await fetch(endpoint, {
                    headers: { Accept: 'application/json' },
                    signal: globalSearchRequest.signal,
                });

                if (!response.ok) {
                    throw new Error('Search request failed.');
                }

                const payload = await response.json();

                if (!payload.success || !Array.isArray(payload.data)) {
                    throw new Error('Search response is invalid.');
                }

                payload.data.forEach((product) => {
                    const price = Number(product.price);

                    addGlobalSearchResult(
                        product.name,
                        new URL(
                            `../web/produktet.php?slug=${encodeURIComponent(product.slug)}`,
                            window.location.href
                        ).href,
                        Number.isFinite(price) ? `${price.toFixed(2)} € · Produkt` : 'Produkt'
                    );

                    resultCount += 1;
                });

                globalSearchStatus.textContent = resultCount === 0
                    ? 'Nuk u gjet rezultat. Provo një term tjetër ose shfleto kategoritë.'
                    : `${resultCount} rezultat(e) të gjetura.`;
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                globalSearchStatus.textContent = resultCount > 0
                    ? `${resultCount} rezultat(e) të gjetura; kërkimi i produkteve dështoi.`
                    : 'Kërkimi dështoi. Provo përsëri ose shfleto kategoritë.';
            }
        };

        searchOpen.addEventListener('click', () => {
            searchDialog.showModal();
            searchOpen.setAttribute('aria-expanded', 'true');
            globalSearchStatus.textContent =
                'Shkruaj për të kërkuar produkte, kategori ose faqe.';
            globalSearchInput.focus();
        });

        searchDialog.addEventListener('close', () => {
            searchOpen.setAttribute('aria-expanded', 'false');
        });

        searchClose?.addEventListener('click', closeSearch);

        searchDialog.addEventListener('click', (event) => {
            if (event.target === searchDialog) {
                closeSearch();
            }
        });

        globalSearchInput.addEventListener('input', () => {
            window.clearTimeout(globalSearchTimer);
            globalSearchTimer = window.setTimeout(findGlobalResults, 250);
        });

        globalSearchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeSearch();
            }
        });
    }

    const searchInput = document.querySelector('#catalog-search .search-input');
    const suggestions = document.querySelector('#search-suggestions');
    const searchStatus = document.querySelector('#search-status');

    if (
        searchInput instanceof HTMLInputElement
        && suggestions instanceof HTMLUListElement
        && searchStatus
    ) {
        let timer;
        let request;

        const closeSuggestions = () => {
            suggestions.replaceChildren();
            suggestions.hidden = true;
            searchInput.setAttribute('aria-expanded', 'false');
            searchInput.removeAttribute('aria-activedescendant');
        };

        const search = async () => {
            const term = searchInput.value.trim();

            if (term.length < 2) {
                request?.abort();
                closeSuggestions();
                searchStatus.textContent = '';
                return;
            }

            request?.abort();
            request = new AbortController();
            searchStatus.textContent = 'Duke kërkuar…';

            try {
                const endpoint = new URL('../api/products', window.location.href);
                const searchParams = new URLSearchParams({ q: term, page_size: '6' });
                const category = document.querySelector('#category')?.value;

                if (category) {
                    searchParams.set('category', category);
                }

                endpoint.search = searchParams;

                const response = await fetch(endpoint, {
                    headers: { Accept: 'application/json' },
                    signal: request.signal,
                });

                if (!response.ok) {
                    throw new Error('Search request failed.');
                }

                const payload = await response.json();

                if (!payload.success || !Array.isArray(payload.data)) {
                    throw new Error('Search response is invalid.');
                }

                closeSuggestions();

                if (payload.data.length === 0) {
                    searchStatus.textContent = 'Nuk u gjet produkt me këtë emër.';
                    return;
                }

                payload.data.forEach((product, index) => {
                    const item = document.createElement('li');
                    item.id = `search-result-${index}`;
                    item.setAttribute('role', 'option');

                    const link = document.createElement('a');
                    link.href = `produktet.php?slug=${encodeURIComponent(product.slug)}`;
                    link.textContent =
                        `${product.name} · ${Number(product.price).toFixed(2)} €`;

                    item.append(link);
                    suggestions.append(item);
                });

                suggestions.hidden = false;
                searchInput.setAttribute('aria-expanded', 'true');
                searchStatus.textContent = `${payload.data.length} sugjerime.`;
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                closeSuggestions();
                searchStatus.textContent =
                    'Kërkimi dështoi. Provo përsëri ose përdor butonin Apliko.';
            }
        };

        searchInput.addEventListener('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(search, 300);
        });

        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeSuggestions();
            }

            if (event.key === 'ArrowDown' && !suggestions.hidden) {
                event.preventDefault();
                suggestions.querySelector('a')?.focus();
            }
        });

        document.addEventListener('click', (event) => {
            if (
                !(event.target instanceof Node)
                || !searchInput.parentElement?.contains(event.target)
            ) {
                closeSuggestions();
            }
        });
    }
})();