(() => {
    const form = document.querySelector('[data-design-form]');
    const frame = document.querySelector('[data-design-preview-frame]');
    if (!(form instanceof HTMLFormElement) || !(frame instanceof HTMLIFrameElement)) {
        return;
    }

    const initialConfig = JSON.parse(form.dataset.initialConfig || '{}');
    const defaultConfig = JSON.parse(form.dataset.defaultConfig || '{}');
    const actionInput = form.querySelector('[data-design-action]');
    const message = form.querySelector('[data-design-message]');
    const publishDialog = document.querySelector('[data-design-publish-dialog]');
    const publishCancel = publishDialog?.querySelector('[data-design-publish-cancel]');
    const publishConfirm = publishDialog?.querySelector('[data-design-publish-confirm]');
    let publishSubmitter = null;
    const pagePicker = document.querySelector('[data-preview-page]');
    const productPicker = document.querySelector('[data-preview-product]');
    const deviceButtons = [...document.querySelectorAll('[data-preview-width]')];
    const presets = {
        original: {
            primary: '#344a38', secondary: '#65704e', accent: '#e8ebe1',
            page_background: '#f8f6f0', header_background: '#344a38', footer_background: '#fffefa',
            card_background: '#f4f8f0', product_title: '#344a38', body_text: '#30362e',
            price: '#344a38', button_background: '#344a38', button_text: '#ffffff',
            hover: '#65704e', sale_badge: '#9c4a3c', out_of_stock_badge: '#73756d',
            announcement_background: '#344a38', announcement_text: '#ffffff',
            benefit_background: '#273e2b', benefit_accent: '#4b6543',
            benefit_text: '#ffffff', benefit_muted: '#e1e8dc',
        },
        yellow: {
            primary: '#725522', secondary: '#B68A36', accent: '#E9D79A',
            page_background: '#FAF6E9', header_background: '#6A4D1D', footer_background: '#F0E7C9',
            card_background: '#FFFCF1', product_title: '#60481F', body_text: '#403A2D',
            price: '#81601E', button_background: '#80601F', button_text: '#FFFFFF',
            hover: '#A47A28', sale_badge: '#B84C42', out_of_stock_badge: '#77716A',
            announcement_background: '#E9D79A', announcement_text: '#4E3B18',
            benefit_background: '#725522', benefit_accent: '#A98235',
            benefit_text: '#FFFFFF', benefit_muted: '#F7EED1',
        },
        bright: {
            primary: '#702756', secondary: '#F05D5E', accent: '#FFD166',
            page_background: '#FFF9F1', header_background: '#702756', footer_background: '#FCE8EF',
            card_background: '#FFFFFF', product_title: '#702756', body_text: '#3D3440',
            price: '#C23A61', button_background: '#E85D75', button_text: '#FFFFFF',
            hover: '#C7445A', sale_badge: '#D64550', out_of_stock_badge: '#77716A',
            announcement_background: '#FFD166', announcement_text: '#702756',
            benefit_background: '#702756', benefit_accent: '#C7445A',
            benefit_text: '#FFFFFF', benefit_muted: '#FCE8EF',
        },
        earth: {
            primary: '#5B4636', secondary: '#A47752', accent: '#E7D5B8',
            page_background: '#FAF5EC', header_background: '#5B4636', footer_background: '#F1E7D7',
            card_background: '#FFFDF8', product_title: '#5B4636', body_text: '#453C34',
            price: '#7A4E2D', button_background: '#6A4D38', button_text: '#FFFFFF',
            hover: '#A47752', sale_badge: '#B65D48', out_of_stock_badge: '#77716A',
            announcement_background: '#E7D5B8', announcement_text: '#5B4636',
            benefit_background: '#5B4636', benefit_accent: '#A47752',
            benefit_text: '#FFFFFF', benefit_muted: '#F4E9D8',
        },
        sky: {
            primary: '#1E5A7A', secondary: '#4C9BB8', accent: '#C8EAF2',
            page_background: '#F2FAFC', header_background: '#174A68', footer_background: '#E2F3F7',
            card_background: '#FCFEFF', product_title: '#1E5A7A', body_text: '#293D47',
            price: '#166A8A', button_background: '#1E6F91', button_text: '#FFFFFF',
            hover: '#328BAC', sale_badge: '#C85850', out_of_stock_badge: '#6D7B80',
            announcement_background: '#C8EAF2', announcement_text: '#174A68',
            benefit_background: '#174A68', benefit_accent: '#328BAC',
            benefit_text: '#FFFFFF', benefit_muted: '#DDF3F8',
        },
        rose: {
            primary: '#783B55', secondary: '#BC7185', accent: '#F3CED8',
            page_background: '#FBF3F5', header_background: '#783B55', footer_background: '#F3E2E7',
            card_background: '#FFFCFD', product_title: '#783B55', body_text: '#45353C',
            price: '#9E4566', button_background: '#9E4566', button_text: '#FFFFFF',
            hover: '#BC7185', sale_badge: '#C24C4C', out_of_stock_badge: '#77716A',
            announcement_background: '#F3CED8', announcement_text: '#783B55',
            benefit_background: '#783B55', benefit_accent: '#BC7185',
            benefit_text: '#FFFFFF', benefit_muted: '#FBE8ED',
        },
    };

    const pathParts = (path) => path.split('.');
    const getValue = (object, path) => pathParts(path).reduce((value, part) => value?.[part], object);
    const setValue = (object, path, value) => {
        const parts = pathParts(path);
        const last = parts.pop();
        const target = parts.reduce((value, part) => value[part] ||= {}, object);
        target[last] = value;
    };

    const getConfig = () => {
        const config = structuredClone(defaultConfig);
        for (const control of form.querySelectorAll('[data-design-value]')) {
            const path = control.dataset.designPath;
            if (!path) {
                continue;
            }
            let value;
            if (control instanceof HTMLInputElement && control.type === 'checkbox') {
                value = control.checked;
            } else if (control instanceof HTMLInputElement && control.type === 'number') {
                value = Number(control.value);
            } else {
                value = control.value;
            }
            setValue(config, path, value);
        }
        return config;
    };

    const syncColorInputs = (path, value, source) => {
        const normalized = /^#[\da-f]{6}$/i.test(value) ? value.toLowerCase() : null;
        if (!normalized) {
            return;
        }
        for (const control of form.querySelectorAll(`[data-design-path="${path}"]`)) {
            if (control !== source && control instanceof HTMLInputElement && control.type !== 'hidden') {
                control.value = normalized;
            }
        }
        const hidden = form.querySelector(`input[type="hidden"][data-design-path="${path}"]`);
        if (hidden instanceof HTMLInputElement) {
            hidden.value = normalized;
        }
    };

    const safeMediaUrl = (value, video = false) => {
        if (!value) {
            return '';
        }
        if (/[\\\s"'()<>\x00-\x1f]/.test(value)) {
            return '';
        }
        try {
            const url = new URL(value, window.location.origin);
            if (!['http:', 'https:'].includes(url.protocol)) {
                return '';
            }
            if (value.startsWith('/') && !url.pathname.includes('/web-images/')) {
                return '';
            }
            if (video ? !/\.(mp4|webm)$/i.test(url.pathname)
                : !/\.(jpe?g|png|webp|avif)$/i.test(url.pathname)) {
                return '';
            }
            return url.href;
        } catch {
            return '';
        }
    };

    const rgba = (hex, opacity) => {
        if (!/^#[\da-f]{6}$/i.test(hex)) {
            return 'rgba(255,255,255,1)';
        }
        const channels = hex.slice(1).match(/.{2}/g).map((part) => Number.parseInt(part, 16));
        return `rgba(${channels.join(',')},${Math.min(100, Math.max(0, Number(opacity))) / 100})`;
    };

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

    const applyPreview = () => {
        const target = frame.contentWindow;
        if (!target || target === window) {
            return;
        }
        target.postMessage({ type: 'herbabloom:design-preview', config: getConfig() }, window.location.origin);
        if (message) {
            message.textContent = 'Parapamja u përditësua. Këto ndryshime nuk janë publikuar.';
        }
    };

    for (const control of form.querySelectorAll('[data-design-color], [data-design-color-text]')) {
        control.addEventListener('input', () => {
            syncColorInputs(control.dataset.designPath, control.value, control);
            applyPreview();
        });
    }

    form.addEventListener('input', (event) => {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.dataset.designPath
            && target.matches('[data-design-color], [data-design-color-text]')) {
            return;
        }
        applyPreview();
    });
    form.addEventListener('change', applyPreview);
    frame.addEventListener('load', applyPreview);

    form.querySelector('[data-design-preview]')?.addEventListener('click', applyPreview);
    form.querySelector('[data-design-reset]')?.addEventListener('click', () => fillForm(initialConfig));
    form.querySelector('[data-design-default]')?.addEventListener('click', () => {
        if (window.confirm('Ngarko dizajnin fillestar në formular? Dizajni publik nuk do të ndryshojë pa publikim.')) {
            fillForm(defaultConfig);
        }
    });

    form.querySelectorAll('[data-design-preset]').forEach((button) => {
        button.addEventListener('click', () => {
            const presetName = button.dataset.designPreset;
            const preset = presets[presetName];
            if (!preset) {
                return;
            }
            for (const [key, value] of Object.entries(preset)) {
                syncColorInputs(`palette.${key}`, value, null);
            }
            const headerColor = preset.header_background;
            const headerRgb = headerColor.match(/[\da-f]{2}/gi)?.map((part) => Number.parseInt(part, 16)) || [];
            const headerLuminance = headerRgb.length === 3
                ? (headerRgb[0] * 0.299 + headerRgb[1] * 0.587 + headerRgb[2] * 0.114) / 255
                : 1;
            const menuColor = headerLuminance > 0.62 ? '#41483d' : '#fffefa';
            syncColorInputs('header.menu_color', menuColor, null);
            syncColorInputs('header.menu_hover', headerLuminance > 0.62 ? preset.secondary : '#dce4d4', null);
            const presetInput = form.querySelector('[data-design-path="palette.preset"]');
            if (presetInput instanceof HTMLInputElement) {
                presetInput.value = presetName;
            }
            form.querySelectorAll('[data-design-preset]').forEach((item) => {
                item.setAttribute('aria-pressed', String(item === button));
            });
            applyPreview();
        });
    });

    form.querySelectorAll('[data-design-media-picker]').forEach((picker) => {
        picker.addEventListener('change', () => {
            const path = picker.dataset.designMediaPicker;
            const input = form.querySelector(`[data-design-path="${path}"][data-design-value]`);
            if (input instanceof HTMLInputElement) {
                input.value = picker.value;
                applyPreview();
            }
        });
    });

    form.querySelectorAll('[data-design-submit]').forEach((button) => {
        button.addEventListener('click', (event) => {
            const mode = button.dataset.designSubmit;
            if (!(actionInput instanceof HTMLInputElement)) {
                event.preventDefault();
                return;
            }
            actionInput.value = mode === 'publish' ? 'publish_design' : 'save_design_draft';
            if (mode === 'publish' && publishDialog instanceof HTMLDialogElement) {
                event.preventDefault();
                publishSubmitter = button;
                publishDialog.showModal();
            }
        });
    });
    publishCancel?.addEventListener('click', () => {
        if (publishDialog instanceof HTMLDialogElement) {
            publishDialog.close();
        }
    });
    publishConfirm?.addEventListener('click', () => {
        if (!(actionInput instanceof HTMLInputElement) || !(publishDialog instanceof HTMLDialogElement)
            || !(publishSubmitter instanceof HTMLButtonElement)) {
            return;
        }
        actionInput.value = 'publish_design';
        publishDialog.close();
        form.requestSubmit(publishSubmitter);
    });
    publishDialog?.addEventListener('click', (event) => {
        if (event.target === publishDialog && publishDialog instanceof HTMLDialogElement) {
            publishDialog.close();
        }
    });

    const updatePreviewPage = () => {
        if (!(pagePicker instanceof HTMLSelectElement)) {
            return;
        }
        const baseUrl = new URL(frame.src, window.location.href);
        const basePath = baseUrl.pathname.replace(/\/web\/[^/]+$/, '');
        const page = pagePicker.value;
        const route = page === 'product-detail' ? 'produktet.php' : page;
        baseUrl.pathname = `${basePath}/web/${route}`;
        baseUrl.search = '';
        baseUrl.searchParams.set('visual_preview', '1');
        if (page === 'product-detail' && productPicker instanceof HTMLSelectElement && productPicker.value) {
            baseUrl.searchParams.set('slug', productPicker.value);
        }
        frame.src = baseUrl.href;
        if (productPicker instanceof HTMLSelectElement) {
            productPicker.hidden = page !== 'product-detail';
        }
    };
    pagePicker?.addEventListener('change', updatePreviewPage);
    productPicker?.addEventListener('change', updatePreviewPage);
    if (productPicker instanceof HTMLSelectElement && pagePicker instanceof HTMLSelectElement) {
        productPicker.hidden = pagePicker.value !== 'product-detail';
    }
    deviceButtons.forEach((button) => {
        button.addEventListener('click', () => {
            deviceButtons.forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
            frame.style.flexBasis = button.dataset.previewWidth || '100%';
            frame.style.width = button.dataset.previewWidth || '100%';
        });
    });

    function fillForm(config) {
        for (const control of form.querySelectorAll('[data-design-value]')) {
            const value = getValue(config, control.dataset.designPath);
            if (value === undefined) {
                continue;
            }
            if (control instanceof HTMLInputElement && control.type === 'checkbox') {
                control.checked = Boolean(value);
            } else {
                control.value = String(value);
            }
        }
        for (const color of form.querySelectorAll('[data-design-color]')) {
            syncColorInputs(color.dataset.designPath, color.value, color);
        }
        for (const picker of form.querySelectorAll('[data-design-media-picker]')) {
            const current = getValue(config, picker.dataset.designMediaPicker);
            picker.value = [...picker.options].some((option) => option.value === current) ? current : '';
        }
        applyPreview();
    }

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin || event.source !== frame.contentWindow
            || event.data?.type !== 'herbabloom:design-preview-ready') {
            return;
        }
        applyPreview();
    });
})();
