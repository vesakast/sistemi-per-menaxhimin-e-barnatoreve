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

    const announcementBar = document.querySelector('.announcement-bar');
    const announcementTrack = announcementBar?.querySelector('.announcement-track');
    const announcementItem = announcementTrack?.querySelector(':scope > span');

    if (
        announcementBar instanceof HTMLElement
        && announcementTrack instanceof HTMLElement
        && announcementItem instanceof HTMLElement
    ) {
        const fillAnnouncementLoop = () => {
            announcementTrack.querySelectorAll('[data-announcement-copy]').forEach((copy) => copy.remove());

            const itemWidth = announcementItem.getBoundingClientRect().width;
            if (itemWidth <= 0) {
                return;
            }

            announcementTrack.style.setProperty('--announcement-offset', `-${itemWidth}px`);
            while (announcementTrack.scrollWidth < announcementBar.clientWidth + itemWidth) {
                const copy = announcementItem.cloneNode(true);
                copy.setAttribute('aria-hidden', 'true');
                copy.dataset.announcementCopy = 'true';
                announcementTrack.append(copy);
            }
        };

        fillAnnouncementLoop();
        if ('ResizeObserver' in window) {
            const announcementObserver = new ResizeObserver(fillAnnouncementLoop);
            announcementObserver.observe(announcementBar);
            announcementObserver.observe(announcementItem);
        } else {
            window.addEventListener('resize', fillAnnouncementLoop);
        }
    }

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

        const scheduleFallbackAdvance = (slideIndex) => {
            stopFallbackTimer();
            if (reducedMotion.matches || slideIndex !== active) {
                return;
            }

            fallbackTimer = window.setTimeout(() => {
                if (slideIndex === active) {
                    showSlide(slideIndex + 1);
                }
            }, 20000);
        };

        const playActiveVideo = (video, restart) => {
            if (!(video instanceof HTMLVideoElement)) {
                return;
            }
            showVideoPoster(video);
            if (restart) {
                video.currentTime = 0;
            }

            const playback = video.play();

            if (playback) {
                playback.catch((error) => {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    showVideoPoster(video);
                    console.error('HerbaBloom hero video playback failed.', error);
                    const slideIndex = slides.findIndex((slide) => slide.contains(video));
                    scheduleFallbackAdvance(slideIndex);
                });
            }
        };

        const showVideoPoster = (video) => {
            video.classList.remove('is-playing');
            const poster = video.closest('.hero-visual')?.querySelector('.hero-video-poster');
            if (poster instanceof HTMLImageElement) {
                poster.hidden = false;
            }
        };

        const showVideoFrame = (video) => {
            const poster = video.closest('.hero-visual')?.querySelector('.hero-video-poster');
            if (poster instanceof HTMLImageElement) {
                poster.hidden = true;
            }
            video.classList.add('is-playing');
        };

        const showSlide = (next, restartVideo = true) => {
            const nextIndex = (next + slides.length) % slides.length;

            if (nextIndex === active) {
                return;
            }

            stopFallbackTimer();
            const previousVideo = slides[active].querySelector('video');
            previousVideo?.pause();
            if (previousVideo) {
                showVideoPoster(previousVideo);
            }
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
                scheduleFallbackAdvance(active);
            }
        };

        slides.forEach((slide, index) => {
            const video = slide.querySelector('video');

            if (!video) {
                return;
            }

            showVideoPoster(video);
            video.addEventListener('playing', () => {
                if (index === active) {
                    stopFallbackTimer();
                }
                showVideoFrame(video);
            });
            video.addEventListener('error', () => {
                showVideoPoster(video);
                scheduleFallbackAdvance(index);
            });
            video.addEventListener('ended', () => {
                if (index === active) {
                    if (reducedMotion.matches) {
                        playActiveVideo(video, true);
                    } else {
                        showSlide(active + 1);
                    }
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

    if (document.body.dataset.visualDesignPreview === 'authorized' && window.parent !== window) {
        const fontFamilies = {
            'DM Sans': '"DM Sans", Arial, sans-serif',
            'DM Serif Display': '"DM Serif Display", Georgia, serif',
            'Cormorant Garamond': '"Cormorant Garamond", Georgia, serif',
            'Playfair Display': '"Playfair Display", Georgia, serif',
            Lora: 'Lora, Georgia, serif',
            'Great Vibes': '"Great Vibes", cursive',
            Allura: 'Allura, cursive',
            Georgia: 'Georgia, serif',
            Arial: 'Arial, sans-serif',
        };
        const safeColor = (value, fallback) =>
            typeof value === 'string' && /^#[\da-f]{6}$/i.test(value) ? value : fallback;
        const safeNumber = (value, minimum, maximum, fallback) => {
            const number = Number(value);
            return Number.isFinite(number) ? Math.min(maximum, Math.max(minimum, number)) : fallback;
        };
        const safeMedia = (value, video = false) => {
            if (typeof value !== 'string' || value === '' || /[\\\s"'()<>\x00-\x1f]/.test(value)) {
                return '';
            }
            try {
                const url = new URL(value, window.location.origin);
                if (!['http:', 'https:'].includes(url.protocol)
                    || (value.startsWith('/') && !url.pathname.includes('/web-images/'))
                    || (video ? !/\.(mp4|webm)$/i.test(url.pathname)
                        : !/\.(jpe?g|png|webp|avif)$/i.test(url.pathname))) {
                    return '';
                }
                return url.href;
            } catch {
                return '';
            }
        };
        const rgba = (hex, opacity) => {
            const color = safeColor(hex, '#ffffff').slice(1);
            const channels = color.match(/.{2}/g).map((part) => Number.parseInt(part, 16));
            return `rgba(${channels.join(',')},${safeNumber(opacity, 0, 100, 0) / 100})`;
        };
        const enumValue = (value, values, fallback) => values.includes(value) ? value : fallback;
        const set = (name, value) => document.documentElement.style.setProperty(name, String(value));

        const applyDesignPreview = (config) => {
            if (!config || typeof config !== 'object') {
                return;
            }
            const palette = config.palette || {};
            const background = config.background || {};
            const typography = config.typography || {};
            const cards = config.cards || {};
            const hero = config.hero || {};
            const header = config.header || {};
            const footer = config.footer || {};
            const primary = safeColor(palette.primary, '#344a38');
            const secondary = safeColor(palette.secondary, '#65704e');
            const accent = safeColor(palette.accent, '#e8ebe1');
            const bodyColor = safeColor(palette.body_text, '#30362e');
            const bgEnabled = Boolean(background.enabled);
            const opacity = safeNumber(cards.background_opacity, 0, 100, 82);
            const font = (name, fallback) => fontFamilies[name] || fallback;
            const imageUrl = bgEnabled ? safeMedia(background.image) : '';
            const heroImage = safeMedia(hero.background_image);
            const bgOverlay = bgEnabled && background.sections?.body
                ? rgba(background.overlay_color, background.overlay_opacity)
                : 'transparent';
            const contentOverlay = bgEnabled && background.sections?.content
                ? rgba(background.overlay_color, background.overlay_opacity)
                : 'transparent';
            const cardSize = enumValue(cards.size, ['small', 'medium', 'large'], 'medium');
            const cardHover = enumValue(cards.hover, ['none', 'lift', 'scale'], 'lift');
            const headerPosition = header.sticky ? 'sticky' : 'relative';
            const root = document.documentElement;

            set('--forest', primary);
            set('--forest-deep', primary);
            set('--olive', secondary);
            set('--sage', accent);
            set('--cream', safeColor(palette.page_background, '#f8f6f0'));
            set('--paper', safeColor(palette.header_background, '#fffefa'));
            set('--sand', accent);
            set('--ink', bodyColor);
            set('--muted', safeColor(footer.text_color, '#73756d'));
            set('--line', accent);
            set('--serif', font(typography.heading_font, '"DM Serif Display", Georgia, serif'));
            set('--sans', font(typography.body_font, '"DM Sans", Arial, sans-serif'));
            set('--design-description-font', font(typography.description_font, '"DM Sans", Arial, sans-serif'));
            set('--design-decorative-font', font(typography.decorative_font, 'Lora, Georgia, serif'));
            set('--design-price-font', font(typography.price_font, '"DM Sans", Arial, sans-serif'));
            set('--design-card-bg', rgba(palette.card_background, opacity));
            set('--design-card-radius', `${safeNumber(cards.radius, 0, 40, 18)}px`);
            set('--design-card-image-height', `${safeNumber(cards.image_height, 120, 420, 200)}px`);
            set('--design-card-image-fit', enumValue(cards.image_fit, ['cover', 'contain'], 'cover'));
            set('--design-grid-columns', safeNumber(cards.columns, 2, 6, 4));
            set('--design-grid-gap', `${cardSize === 'small' ? 12 : cardSize === 'large' ? 24 : 18}px`);
            set('--design-card-padding', `${cardSize === 'small' ? 10 : cardSize === 'large' ? 20 : 14}px`);
            set('--design-card-border', cards.border ? `1px solid ${accent}` : '0');
            set('--design-card-shadow', cards.shadow ? '0 8px 25px rgba(0,0,0,.10)' : 'none');
            set('--design-card-hover-shadow', cards.shadow ? '0 13px 30px rgba(0,0,0,.16)' : 'none');
            set('--design-card-hover-transform', cardHover === 'lift' ? 'translateY(-4px)' : cardHover === 'scale' ? 'scale(1.015)' : 'none');
            set('--design-header-bg', rgba(palette.header_background, safeNumber(header.opacity, 50, 100, 96)));
            set('--design-header-height', `${safeNumber(header.height, 54, 140, 76)}px`);
            set('--design-logo-width', `${safeNumber(header.logo_width, 20, 60, 31)}px`);
            set('--design-menu-color', safeColor(header.menu_color, '#41483d'));
            set('--design-menu-hover', safeColor(header.menu_hover, secondary));
            set('--design-footer-bg', safeColor(palette.footer_background, '#fffefa'));
            set('--design-footer-text', safeColor(footer.text_color, '#73756d'));
            set('--design-footer-heading', safeColor(footer.heading_color, primary));
            set('--design-footer-spacing', `${safeNumber(footer.spacing, 8, 80, 25)}px`);
            set('--design-button-bg', safeColor(palette.button_background, primary));
            set('--design-button-text', safeColor(palette.button_text, '#ffffff'));
            set('--design-hover', safeColor(palette.hover, secondary));
            set('--design-product-title', safeColor(palette.product_title, primary));
            set('--design-price', safeColor(palette.price, primary));
            set('--design-sale-badge', safeColor(palette.sale_badge, '#9c4a3c'));
            set('--design-stock-badge', safeColor(palette.out_of_stock_badge, '#73756d'));
            set('--design-announcement-bg', safeColor(palette.announcement_background, primary));
            set('--design-announcement-text', safeColor(palette.announcement_text, '#ffffff'));
            set('--design-benefit-bg', rgba(safeColor(palette.benefit_background, primary), 91));
            set('--design-benefit-accent', rgba(safeColor(palette.benefit_accent, secondary), 84));
            set('--design-benefit-text', safeColor(palette.benefit_text, '#ffffff'));
            set('--design-benefit-muted', rgba(safeColor(palette.benefit_muted, '#e1e8dc'), 78));
            set('--design-heading-size', `${safeNumber(typography.heading_size, 22, 72, 44)}px`);
            set('--design-body-size', `${safeNumber(typography.body_size, 12, 22, 15)}px`);
            set('--design-font-weight', safeNumber(typography.weight, 300, 700, 400));
            set('--design-line-height', safeNumber(typography.line_height, 1.2, 2.2, 1.65));
            set('--design-header-position', headerPosition);
            set('--design-hero-height', `${safeNumber(hero.height, 300, 900, 490)}px`);
            set('--design-hero-min-height', `${safeNumber(hero.min_height, 240, 900, 390)}px`);
            set('--design-hero-max-height', `${safeNumber(hero.max_height, 300, 1100, 760)}px`);
            set('--design-hero-width', `${safeNumber(hero.width, 60, 100, 100)}%`);
            set('--design-hero-padding', `${safeNumber(hero.padding, 8, 100, 48)}px`);
            set('--design-hero-text', safeColor(hero.text_color, bodyColor));
            set('--design-hero-title-font', font(hero.title_font, '"DM Serif Display", Georgia, serif'));
            set('--design-hero-decorative-font', font(hero.decorative_font, 'Lora, Georgia, serif'));
            set('--design-hero-position', enumValue(hero.position,
                ['center center', 'center top', 'center bottom', 'left center', 'right center'], 'center center'));
            set('--design-hero-overlay', rgba(hero.overlay_color, hero.overlay_opacity));
            set('--design-background-image', imageUrl ? `url("${imageUrl}")` : 'none');
            set('--design-section-hero-image',
                bgEnabled && background.sections?.hero && imageUrl ? `url("${imageUrl}")` : 'none');
            set('--design-section-content-image',
                bgEnabled && background.sections?.content && imageUrl ? `url("${imageUrl}")` : 'none');
            set('--design-hero-image', heroImage ? `url("${heroImage}")` : 'none');
            set('--design-background-position', enumValue(background.position,
                ['center center', 'center top', 'center bottom', 'left center', 'right center'], 'center center'));
            set('--design-background-size', enumValue(background.size, ['cover', 'contain', 'auto'], 'cover'));
            set('--design-background-opacity', safeNumber(background.opacity, 0, 100, 100) / 100);
            set('--design-background-overlay', bgOverlay);
            set('--design-content-overlay', contentOverlay);
            set('--design-background-blur', `${safeNumber(background.blur, 0, 20, 0)}px`);

            const heroSection = document.querySelector('[data-design-hero]');
            if (heroSection instanceof HTMLElement) {
                heroSection.style.display = hero.enabled ? '' : 'none';
                const textValues = [
                    ['[data-design-hero-eyebrow]', hero.eyebrow],
                    ['[data-design-hero-title]', hero.title],
                    ['[data-design-hero-description]', hero.description],
                ];
                for (const [selector, value] of textValues) {
                    const element = heroSection.querySelector(selector);
                    if (element && typeof value === 'string') {
                        element.textContent = value;
                    }
                }
                const button = heroSection.querySelector('[data-design-hero-button]');
                if (button instanceof HTMLAnchorElement && typeof hero.button_text === 'string') {
                    const href = typeof hero.button_url === 'string' ? hero.button_url : '';
                    if ((href.startsWith('/web/') && !href.startsWith('//'))
                        || (() => { try { return new URL(href).protocol === 'https:'; } catch { return false; } })()) {
                        button.href = href;
                    }
                    const icon = button.querySelector('i');
                    button.textContent = `${hero.button_text} `;
                    if (icon) {
                        button.append(icon);
                    }
                }

                const visual = heroSection.querySelector('.hero-slide.is-active .hero-visual');
                if (visual instanceof HTMLElement) {
                    const videoUrl = safeMedia(hero.video_url, true);
                    const imageUrl = safeMedia(hero.poster || hero.background_image);
                    let video = visual.querySelector('video');
                    let image = visual.querySelector('img');

                    if (videoUrl && !(video instanceof HTMLVideoElement)) {
                        video = document.createElement('video');
                        video.muted = true;
                        video.playsInline = true;
                        video.preload = 'metadata';
                        visual.append(video);
                    }
                    if (imageUrl && !(image instanceof HTMLImageElement)) {
                        image = document.createElement('img');
                        image.alt = 'Përzgjedhje produktesh bimore HerbaBloom';
                        visual.append(image);
                    }

                    if (image instanceof HTMLImageElement) {
                        image.hidden = !imageUrl && !videoUrl;
                        if (imageUrl && image.src !== imageUrl) {
                            image.src = imageUrl;
                        }
                    }
                    if (video instanceof HTMLVideoElement) {
                        const source = video.querySelector('source');
                        if (videoUrl) {
                            let videoSource = source;
                            if (!(videoSource instanceof HTMLSourceElement)) {
                                videoSource = document.createElement('source');
                                video.append(videoSource);
                            }
                            if (videoSource.src !== videoUrl) {
                                videoSource.src = videoUrl;
                                videoSource.type = /\.webm$/i.test(new URL(videoUrl).pathname)
                                    ? 'video/webm'
                                    : 'video/mp4';
                                video.load();
                            }
                            if (imageUrl) {
                                video.poster = imageUrl;
                            }
                            video.hidden = false;
                            video.classList.remove('is-playing');
                            if (!video.dataset.previewFallbackHandlers) {
                                video.dataset.previewFallbackHandlers = 'true';
                                video.addEventListener('playing', () => {
                                    const posterImage = video.closest('.hero-visual')?.querySelector('.hero-video-poster');
                                    if (posterImage instanceof HTMLImageElement) {
                                        posterImage.hidden = true;
                                    }
                                    video.classList.add('is-playing');
                                });
                                video.addEventListener('error', () => {
                                    video.classList.remove('is-playing');
                                    video.hidden = true;
                                    const posterImage = video.closest('.hero-visual')?.querySelector('.hero-video-poster');
                                    if (posterImage instanceof HTMLImageElement) {
                                        posterImage.hidden = false;
                                    }
                                });
                            }
                            video.play().catch((error) => {
                                if (error.name !== 'AbortError' && error.name !== 'NotAllowedError') {
                                    console.error('HerbaBloom preview video playback failed.', error);
                                }
                                video.classList.remove('is-playing');
                            });
                        } else {
                            video.pause();
                            video.hidden = true;
                        }
                    }
                }
            }

            root.dataset.designPreviewApplied = 'true';
        };

        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin || event.source !== window.parent
                || event.data?.type !== 'herbabloom:design-preview') {
                return;
            }
            applyDesignPreview(event.data.config);
        });
        window.parent.postMessage({ type: 'herbabloom:design-preview-ready' }, window.location.origin);
    }
})();