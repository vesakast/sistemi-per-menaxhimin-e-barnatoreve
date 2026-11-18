<?php
declare(strict_types=1);

function hb_design_defaults(): array
{
    $defaultBackground = is_file(dirname(__DIR__) . '/web-images/background-image.jpg')
        ? app_url('/web-images/background-image.jpg')
        : '';
    $defaultHeroVideo = is_file(dirname(__DIR__) . '/web-images/hero-slide-1.mp4')
        ? app_url('/web-images/hero-slide-1.mp4')
        : '';
    return [
        'palette' => [
            'preset' => 'original',
            'primary' => '#344a38',
            'secondary' => '#65704e',
            'accent' => '#e8ebe1',
            'page_background' => '#f8f6f0',
            'header_background' => '#344a38',
            'footer_background' => '#fffefa',
            'card_background' => '#f4f8f0',
            'product_title' => '#344a38',
            'body_text' => '#30362e',
            'price' => '#344a38',
            'button_background' => '#344a38',
            'button_text' => '#ffffff',
            'hover' => '#65704e',
            'sale_badge' => '#9c4a3c',
            'out_of_stock_badge' => '#73756d',
            'announcement_background' => '#344a38',
            'announcement_text' => '#ffffff',
            'benefit_background' => '#273e2b',
            'benefit_accent' => '#4b6543',
            'benefit_text' => '#ffffff',
            'benefit_muted' => '#e1e8dc',
        ],
        'background' => [
            'enabled' => true,
            'image' => $defaultBackground,
            'position' => 'center center',
            'size' => 'cover',
            'opacity' => 100,
            'overlay_color' => '#f8f6f0',
            'overlay_opacity' => 0,
            'blur' => 0,
            'sections' => ['body' => true, 'hero' => false, 'content' => false],
        ],
        'typography' => [
            'heading_font' => 'DM Serif Display',
            'description_font' => 'DM Sans',
            'body_font' => 'DM Sans',
            'decorative_font' => 'Lora',
            'price_font' => 'DM Sans',
            'heading_size' => 44,
            'body_size' => 15,
            'weight' => 400,
            'line_height' => 1.65,
        ],
        'cards' => [
            'size' => 'medium',
            'radius' => 18,
            'shadow' => true,
            'border' => true,
            'background_opacity' => 82,
            'hover' => 'lift',
            'image_fit' => 'cover',
            'image_height' => 200,
            'columns' => 4,
        ],
        'hero' => [
            'enabled' => true,
            'height' => 490,
            'min_height' => 390,
            'max_height' => 760,
            'width' => 100,
            'padding' => 48,
            'background_image' => '',
            'video_url' => $defaultHeroVideo,
            'poster' => '',
            'overlay_color' => '#344a38',
            'overlay_opacity' => 0,
            'position' => 'center center',
            'eyebrow' => 'Kujdes i frymëzuar nga natyra',
            'title' => 'Zgjedhje të kujdesshme për rutinën tënde.',
            'description' => 'Eksploro çajra, suplemente, vajra dhe produkte për kujdesin e lëkurës e flokëve.',
            'title_font' => 'DM Serif Display',
            'decorative_font' => 'Lora',
            'text_color' => '#30362e',
            'button_text' => 'Shfleto katalogun',
            'button_url' => app_url('/web/produktet.php'),
        ],
        'header' => [
            'opacity' => 96,
            'height' => 76,
            'logo_width' => 31,
            'menu_color' => '#fffefa',
            'menu_hover' => '#dce4d4',
            'sticky' => true,
        ],
        'footer' => [
            'text_color' => '#73756d',
            'heading_color' => '#344a38',
            'spacing' => 25,
        ],
    ];
}

function hb_design_font_options(): array
{
    return [
        'DM Sans' => '"DM Sans", Arial, sans-serif',
        'DM Serif Display' => '"DM Serif Display", Georgia, serif',
        'Cormorant Garamond' => '"Cormorant Garamond", Georgia, serif',
        'Playfair Display' => '"Playfair Display", Georgia, serif',
        'Lora' => '"Lora", Georgia, serif',
        'Great Vibes' => '"Great Vibes", cursive',
        'Allura' => '"Allura", cursive',
        'Georgia' => 'Georgia, serif',
        'Arial' => 'Arial, sans-serif',
    ];
}

function hb_design_is_ready(PDO $pdo): bool
{
    $query = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='me_web_design'"
    );
    $query->execute();
    return (int) $query->fetchColumn() === 1;
}

function hb_design_read(PDO $pdo, string $column): ?array
{
    if (!in_array($column, ['draft_json', 'published_json'], true) || !hb_design_is_ready($pdo)) {
        return null;
    }

    $query = $pdo->query("SELECT {$column} FROM me_web_design WHERE id=1");
    $json = $query->fetchColumn();
    if (!is_string($json) || $json === '') {
        return null;
    }

    try {
        $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        error_log('HerbaBloom design configuration is invalid JSON: ' . $exception->getMessage());
        return null;
    }

    if (!is_array($decoded)) {
        error_log('HerbaBloom design configuration must decode to an object.');
        return null;
    }

    try {
        return hb_design_validate($decoded);
    } catch (DomainException $exception) {
        error_log('HerbaBloom design configuration failed validation: ' . $exception->getMessage());
        return null;
    }
}

function hb_design_public(PDO $pdo): array
{
    return hb_design_read($pdo, 'published_json') ?? hb_design_defaults();
}

function hb_design_hex_rgb(string $hex): array
{
    $value = ltrim($hex, '#');
    return [
        hexdec(substr($value, 0, 2)),
        hexdec(substr($value, 2, 2)),
        hexdec(substr($value, 4, 2)),
    ];
}

function hb_design_rgba(string $hex, int $opacity): string
{
    [$red, $green, $blue] = hb_design_hex_rgb($hex);
    return "rgba({$red},{$green},{$blue}," . number_format($opacity / 100, 2, '.', '') . ')';
}

function hb_design_css(array $design): string
{
    $fonts = hb_design_font_options();
    $palette = $design['palette'];
    $background = $design['background'];
    $cards = $design['cards'];
    $hero = $design['hero'];
    $header = $design['header'];
    $footer = $design['footer'];
    $type = $design['typography'];
    $image = $background['enabled'] && $background['sections']['body'] && $background['image'] !== ''
        ? 'url("' . $background['image'] . '")'
        : 'none';
    $sectionHeroImage = $background['enabled'] && $background['sections']['hero'] && $background['image'] !== ''
        ? 'url("' . $background['image'] . '")'
        : 'none';
    $sectionContentImage = $background['enabled'] && $background['sections']['content'] && $background['image'] !== ''
        ? 'url("' . $background['image'] . '")'
        : 'none';
    $backgroundPosition = $background['position'];
    $backgroundSize = $background['size'];
    $cardRadius = $cards['radius'];
    $cardImageHeight = $cards['image_height'];
    $heroHeight = $hero['height'];
    $heroMinHeight = min($hero['min_height'], $hero['height']);
    $heroMaxHeight = max($hero['max_height'], $hero['height']);
    $heroWidth = $hero['width'];
    $heroPadding = $hero['padding'];

    $css = ':root{'
        . '--forest:' . $palette['primary'] . ';'
        . '--forest-deep:' . $palette['primary'] . ';'
        . '--olive:' . $palette['secondary'] . ';'
        . '--sage:' . $palette['accent'] . ';'
        . '--cream:' . $palette['page_background'] . ';'
        . '--paper:' . $palette['header_background'] . ';'
        . '--sand:' . $palette['accent'] . ';'
        . '--ink:' . $palette['body_text'] . ';'
        . '--muted:' . $footer['text_color'] . ';'
        . '--line:' . $palette['accent'] . ';'
        . '--serif:' . $fonts[$type['heading_font']] . ';'
        . '--sans:' . $fonts[$type['body_font']] . ';'
        . '--design-description-font:' . $fonts[$type['description_font']] . ';'
        . '--design-decorative-font:' . $fonts[$type['decorative_font']] . ';'
        . '--design-price-font:' . $fonts[$type['price_font']] . ';'
        . '--design-card-bg:' . hb_design_rgba($palette['card_background'], $cards['background_opacity']) . ';'
        . '--design-card-radius:' . $cardRadius . 'px;'
        . '--design-card-image-height:' . $cardImageHeight . 'px;'
        . '--design-card-image-fit:' . $cards['image_fit'] . ';'
        . '--design-grid-columns:' . $cards['columns'] . ';'
        . '--design-grid-gap:' . ($cards['size'] === 'small' ? '12' : ($cards['size'] === 'large' ? '24' : '18')) . 'px;'
        . '--design-card-padding:' . ($cards['size'] === 'small' ? '10' : ($cards['size'] === 'large' ? '20' : '14')) . 'px;'
        . '--design-card-border:' . ($cards['border'] ? '1px solid ' . $palette['accent'] : '0') . ';'
        . '--design-card-shadow:' . ($cards['shadow'] ? '0 8px 25px rgba(0,0,0,.10)' : 'none') . ';'
        . '--design-card-hover-shadow:' . ($cards['shadow'] ? '0 13px 30px rgba(0,0,0,.16)' : 'none') . ';'
        . '--design-card-hover-transform:' . ($cards['hover'] === 'lift' ? 'translateY(-4px)' : ($cards['hover'] === 'scale' ? 'scale(1.015)' : 'none')) . ';'
        . '--design-header-bg:' . hb_design_rgba($palette['header_background'], $header['opacity']) . ';'
        . '--design-header-height:' . $header['height'] . 'px;'
        . '--design-logo-width:' . $header['logo_width'] . 'px;'
        . '--design-menu-color:' . $header['menu_color'] . ';'
        . '--design-menu-hover:' . $header['menu_hover'] . ';'
        . '--design-footer-bg:' . $palette['footer_background'] . ';'
        . '--design-footer-text:' . $footer['text_color'] . ';'
        . '--design-footer-heading:' . $footer['heading_color'] . ';'
        . '--design-footer-spacing:' . $footer['spacing'] . 'px;'
        . '--design-button-bg:' . $palette['button_background'] . ';'
        . '--design-button-text:' . $palette['button_text'] . ';'
        . '--design-hover:' . $palette['hover'] . ';'
        . '--design-product-title:' . $palette['product_title'] . ';'
        . '--design-price:' . $palette['price'] . ';'
        . '--design-sale-badge:' . $palette['sale_badge'] . ';'
        . '--design-stock-badge:' . $palette['out_of_stock_badge'] . ';'
        . '--design-announcement-bg:' . $palette['announcement_background'] . ';'
        . '--design-announcement-text:' . $palette['announcement_text'] . ';'
        . '--design-benefit-bg:' . hb_design_rgba($palette['benefit_background'], 91) . ';'
        . '--design-benefit-accent:' . hb_design_rgba($palette['benefit_accent'], 84) . ';'
        . '--design-benefit-text:' . $palette['benefit_text'] . ';'
        . '--design-benefit-muted:' . hb_design_rgba($palette['benefit_muted'], 78) . ';'
        . '--design-heading-size:' . $type['heading_size'] . 'px;'
        . '--design-body-size:' . $type['body_size'] . 'px;'
        . '--design-font-weight:' . $type['weight'] . ';'
        . '--design-line-height:' . number_format($type['line_height'], 2, '.', '') . ';'
        . '--design-header-position:' . ($header['sticky'] ? 'sticky' : 'relative') . ';'
        . '--design-hero-height:' . $heroHeight . 'px;'
        . '--design-hero-min-height:' . $heroMinHeight . 'px;'
        . '--design-hero-max-height:' . $heroMaxHeight . 'px;'
        . '--design-hero-width:' . $heroWidth . '%;'
        . '--design-hero-padding:' . $heroPadding . 'px;'
        . '--design-hero-text:' . $hero['text_color'] . ';'
        . '--design-hero-title-font:' . $fonts[$hero['title_font']] . ';'
        . '--design-hero-decorative-font:' . $fonts[$hero['decorative_font']] . ';'
        . '--design-hero-position:' . $hero['position'] . ';'
        . '--design-hero-overlay:' . hb_design_rgba($hero['overlay_color'], $hero['overlay_opacity']) . ';'
        . '--design-background-image:' . $image . ';'
        . '--design-section-hero-image:' . $sectionHeroImage . ';'
        . '--design-section-content-image:' . $sectionContentImage . ';'
        . '--design-hero-image:' . ($hero['background_image'] !== '' ? 'url("' . $hero['background_image'] . '")' : 'none') . ';'
        . '--design-background-position:' . $backgroundPosition . ';'
        . '--design-background-size:' . $backgroundSize . ';'
        . '--design-background-opacity:' . number_format($background['opacity'] / 100, 2, '.', '') . ';'
        . '--design-background-overlay:' . ($background['enabled'] && $background['sections']['body']
            ? hb_design_rgba($background['overlay_color'], $background['overlay_opacity'])
            : 'transparent') . ';'
        . '--design-content-overlay:' . ($background['enabled'] && $background['sections']['content']
            ? hb_design_rgba($background['overlay_color'], $background['overlay_opacity'])
            : 'transparent') . ';'
        . '--design-background-blur:' . $background['blur'] . 'px;'
        . '}';

    $css .= 'html{background:var(--cream)}body{background:transparent!important;color:var(--ink);font-family:var(--sans);font-size:var(--design-body-size);font-weight:var(--design-font-weight);line-height:var(--design-line-height);isolation:isolate}'
        . 'body::before{content:"";position:fixed;inset:0;z-index:-2;pointer-events:none;background-image:var(--design-background-image);background-position:var(--design-background-position);background-size:var(--design-background-size);background-repeat:no-repeat;background-attachment:fixed;opacity:var(--design-background-opacity);filter:blur(var(--design-background-blur));transform:scale(1.02)}'
        . 'body::after{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;background:var(--design-background-overlay)}'
        . 'h1,h2,h3,h4,h5,h6,.store-brand{font-family:var(--serif)}'
        . '.page-title,.section-heading h2,.content-card h1,.content-card h2{color:var(--design-product-title);font-size:var(--design-heading-size)}'
        . 'p,.product-use-note{font-family:var(--design-description-font)}'
        . '.product-card h2,.product-card h3,.product-title,.product-title a{color:var(--design-product-title)}'
        . '.product-card .price,.detail-price,.product-price{color:var(--design-price);font-family:var(--design-price-font)}'
        . '.btn-forest{background:var(--design-button-bg);border-color:var(--design-button-bg);color:var(--design-button-text)}'
        . '.btn-forest:hover,.btn-forest:focus{background:var(--design-hover);border-color:var(--design-hover);color:var(--design-button-text)}'
        . '.store-header{position:var(--design-header-position);min-height:var(--design-header-height);background:var(--design-header-bg)}'
        . '.announcement-bar{background:var(--design-announcement-bg);color:var(--design-announcement-text)}'
        . '.benefit-strip{background:linear-gradient(105deg,var(--design-benefit-bg),var(--design-benefit-accent));color:var(--design-benefit-text)}'
        . '.benefit-strip>div>i{color:var(--design-benefit-text)}.benefit-strip small{color:var(--design-benefit-muted)}'
        . '.store-navbar{min-height:var(--design-header-height)}.store-navbar .nav-link{color:var(--design-menu-color)}'
        . '.store-navbar .nav-link:hover,.store-navbar .nav-link:focus{color:var(--design-menu-hover)}'
        . '.store-brand .brand-mark{width:var(--design-logo-width);height:var(--design-logo-width)}'
        . '.store-footer{background:var(--design-footer-bg);color:var(--design-footer-text)}'
        . '.store-footer p,.store-footer small{color:var(--design-footer-text)}.store-footer h2,.store-footer h3{color:var(--design-footer-heading)}'
        . '.store-footer-inner{padding-block:var(--design-footer-spacing)}'
        . '.product-grid{grid-template-columns:repeat(var(--design-grid-columns),minmax(0,1fr));gap:var(--design-grid-gap)}'
        . '.product-card,.content-card .product-card{border-radius:var(--design-card-radius);background:var(--design-card-bg);'
        . 'border:var(--design-card-border);box-shadow:var(--design-card-shadow);'
        . 'transition:transform .22s ease,box-shadow .22s ease}'
        . '.product-card-body{background:var(--design-card-bg);padding:var(--design-card-padding)}'
        . '.product-card-image-wrap{height:var(--design-card-image-height);overflow:hidden}'
        . '.product-card-image-wrap>.product-image{height:100%;aspect-ratio:auto}'
        . '.product-card-image-wrap img,.product-card .product-image img{width:100%;height:100%;object-fit:var(--design-card-image-fit)}'
        . '.product-card:hover{transform:var(--design-card-hover-transform);box-shadow:var(--design-card-hover-shadow)}'
        . '.product-badge,.sale-badge,.badge-sale{background:var(--design-sale-badge)!important;color:#fff}'
        . '.stock-out,.out-of-stock-badge{display:inline-flex;padding:4px 7px;border-radius:4px;background:var(--design-stock-badge)!important;color:#fff}'
        . '.store-section p,.page-intro p{font-family:var(--design-description-font)}'
        . '.hero-slider{min-height:var(--design-hero-height);max-height:var(--design-hero-max-height);width:var(--design-hero-width);margin-inline:auto}'
        . '.hero-slide,.hero-slide.is-active{min-height:var(--design-hero-min-height)}'
        . '.hero-copy{padding:var(--design-hero-padding);color:var(--design-hero-text);font-family:var(--sans)}'
        . '.hero-copy h1{font-family:var(--design-hero-title-font);color:var(--design-hero-text);font-size:var(--design-heading-size)}'
        . '.hero-copy p{color:var(--design-hero-text)}'
        . '.hero-visual::after{content:"";position:absolute;inset:0;pointer-events:none;background:var(--design-hero-overlay)}'
        . '.hero-visual img,.hero-visual video{object-position:var(--design-hero-position)}'
        . '.hero-visual .hero-video-poster{z-index:0;object-fit:cover;padding:0}'
        . '.hero-visual video{z-index:1;opacity:0;transition:opacity .25s ease}.hero-visual video.is-playing{opacity:1}'
        . '.hero-visual{background-image:var(--design-hero-image);background-position:var(--design-hero-position);background-size:cover}'
        . '.eyebrow.is-decorative,.hero-copy .eyebrow{font-family:var(--design-hero-decorative-font)}'
        . '.product-price,.detail-price{color:var(--design-price)}';
    $css .= '@media(max-width:1100px){.product-grid{grid-template-columns:repeat(min(var(--design-grid-columns),3),minmax(0,1fr))}}'
        . '@media(max-width:760px){.product-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.hero-slider{width:100%;min-height:auto}.hero-copy{padding:clamp(20px,6vw,var(--design-hero-padding))}}'
        . '@media(max-width:420px){.product-grid{gap:10px}.product-card-body{padding:10px}.product-card-image-wrap{height:min(var(--design-card-image-height),170px)}}';

    $css .= '.hero-slider{background-image:var(--design-section-hero-image),linear-gradient(var(--design-hero-overlay),var(--design-hero-overlay));background-position:center;background-size:cover}'
        . '.page-intro,.content-card,.glass-card{background-image:linear-gradient(var(--design-content-overlay),var(--design-content-overlay)),var(--design-section-content-image);background-position:center;background-size:cover}';

    return $css;
}

function hb_design_validate(array $input): array
{
    $defaults = hb_design_defaults();
    $result = $defaults;
    $colorKeys = [
        'primary', 'secondary', 'accent', 'page_background', 'header_background',
        'footer_background', 'card_background', 'product_title', 'body_text', 'price',
        'button_background', 'button_text', 'hover', 'sale_badge', 'out_of_stock_badge',
        'announcement_background', 'announcement_text',
        'benefit_background', 'benefit_accent', 'benefit_text', 'benefit_muted',
    ];
    $fontOptions = array_keys(hb_design_font_options());
    $enumOptions = [
        'palette.preset' => ['original', 'forest', 'botanical', 'earth', 'calm', 'bright', 'sky', 'rose', 'yellow'],
        'background.size' => ['cover', 'contain', 'auto'],
        'background.position' => ['center center', 'center top', 'center bottom', 'left center', 'right center'],
        'typography.heading_font' => $fontOptions,
        'typography.description_font' => $fontOptions,
        'typography.body_font' => $fontOptions,
        'typography.decorative_font' => $fontOptions,
        'typography.price_font' => $fontOptions,
        'cards.size' => ['small', 'medium', 'large'],
        'cards.hover' => ['none', 'lift', 'scale'],
        'cards.image_fit' => ['cover', 'contain'],
        'hero.position' => ['center center', 'center top', 'center bottom', 'left center', 'right center'],
        'hero.title_font' => $fontOptions,
        'hero.decorative_font' => $fontOptions,
    ];

    foreach ($colorKeys as $key) {
        $value = hb_design_input($input, 'palette.' . $key, $defaults['palette'][$key]);
        if (!is_string($value) || !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            throw new DomainException('Ngjyra e zgjedhur nuk është HEX e vlefshme.');
        }
        $result['palette'][$key] = strtolower($value);
    }

    foreach ($enumOptions as $path => $allowed) {
        $value = hb_design_input($input, $path, hb_design_get($defaults, $path));
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new DomainException('Një nga zgjedhjet e dizajnit nuk është e vlefshme.');
        }
        hb_design_set($result, $path, $value);
    }

    $booleanPaths = [
        'background.enabled', 'background.sections.body', 'background.sections.hero',
        'background.sections.content', 'cards.shadow', 'cards.border', 'hero.enabled', 'header.sticky',
    ];
    foreach ($booleanPaths as $path) {
        $value = hb_design_input($input, $path, hb_design_get($defaults, $path));
        if (!is_bool($value) && !in_array($value, ['0', '1', 0, 1, 'false', 'true', 'on', 'off'], true)) {
            throw new DomainException('Një nga opsionet po/jo nuk është e vlefshme.');
        }
        hb_design_set($result, $path, in_array($value, [true, 1, '1', 'true', 'on'], true));
    }

    $numeric = [
        'background.opacity' => [0, 100, true],
        'background.overlay_opacity' => [0, 100, true],
        'background.blur' => [0, 20, true],
        'typography.heading_size' => [22, 72, true],
        'typography.body_size' => [12, 22, true],
        'typography.weight' => [300, 700, true],
        'typography.line_height' => [1.2, 2.2, false],
        'cards.radius' => [0, 40, true],
        'cards.background_opacity' => [0, 100, true],
        'cards.image_height' => [120, 420, true],
        'cards.columns' => [2, 6, true],
        'hero.height' => [300, 900, true],
        'hero.min_height' => [240, 900, true],
        'hero.max_height' => [300, 1100, true],
        'hero.width' => [60, 100, true],
        'hero.padding' => [8, 100, true],
        'hero.overlay_opacity' => [0, 100, true],
        'header.opacity' => [50, 100, true],
        'header.height' => [54, 140, true],
        'header.logo_width' => [20, 60, true],
        'footer.spacing' => [8, 80, true],
    ];
    foreach ($numeric as $path => [$minimum, $maximum, $integerOnly]) {
        $value = hb_design_input($input, $path, hb_design_get($defaults, $path));
        if (!is_numeric($value)) {
            throw new DomainException('Një nga vlerat numerike të dizajnit nuk është e vlefshme.');
        }
        $number = $integerOnly ? filter_var($value, FILTER_VALIDATE_INT) : (float) $value;
        if ($number === false || $number < $minimum || $number > $maximum) {
            throw new DomainException('Një nga vlerat numerike është jashtë kufijve të lejuar.');
        }
        hb_design_set($result, $path, $integerOnly ? (int) $number : (float) $number);
    }

    $strings = [
        'background.image' => 2048,
        'hero.background_image' => 2048,
        'hero.video_url' => 2048,
        'hero.poster' => 2048,
    ];
    foreach ($strings as $path => $maximumLength) {
        $value = hb_design_input($input, $path, hb_design_get($defaults, $path));
        if (!is_string($value) || strlen($value) > $maximumLength) {
            throw new DomainException('URL-ja e imazhit ose videos nuk është e vlefshme.');
        }
        $isVideo = $path === 'hero.video_url';
        $isValid = $isVideo ? hb_design_valid_video_url($value) : hb_design_valid_image_url($value);
        if (!$isValid) {
            throw new DomainException('URL-ja e imazhit ose videos nuk është e vlefshme.');
        }
        hb_design_set($result, $path, $value);
    }

    $textLimits = [
        'hero.eyebrow' => 100,
        'hero.title' => 180,
        'hero.description' => 500,
        'hero.button_text' => 60,
        'hero.button_url' => 2048,
    ];
    foreach ($textLimits as $path => $maximumLength) {
        $value = hb_design_input($input, $path, hb_design_get($defaults, $path));
        if (!is_string($value) || strlen($value) > $maximumLength
            || ($path === 'hero.button_text' && trim($value) === '')
            || ($path === 'hero.button_url' && !hb_design_valid_link($value))) {
            throw new DomainException('Teksti ose lidhja e Hero Section nuk është e vlefshme.');
        }
        hb_design_set($result, $path, trim($value));
    }

    foreach ([
        'hero.overlay_color' => $defaults['hero']['overlay_color'],
        'hero.text_color' => $defaults['hero']['text_color'],
        'background.overlay_color' => $defaults['background']['overlay_color'],
        'header.menu_color' => $defaults['header']['menu_color'],
        'header.menu_hover' => $defaults['header']['menu_hover'],
        'footer.text_color' => $defaults['footer']['text_color'],
        'footer.heading_color' => $defaults['footer']['heading_color'],
    ] as $path => $default) {
        $color = hb_design_input($input, $path, $default);
        if (!is_string($color) || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            throw new DomainException('Një nga ngjyrat nuk është HEX e vlefshme.');
        }
        hb_design_set($result, $path, strtolower($color));
    }

    if ($result['hero']['min_height'] > $result['hero']['height']
        || $result['hero']['height'] > $result['hero']['max_height']) {
        throw new DomainException('Lartësia e Hero duhet të jetë ndërmjet minimumit dhe maksimumit.');
    }

    return $result;
}

function hb_design_input(array $input, string $path, mixed $default): mixed
{
    if (isset($input['design']) && is_array($input['design'])) {
        $input = $input['design'];
    }
    return hb_design_get($input, $path) ?? $default;
}

function hb_design_get(array $input, string $path): mixed
{
    $value = $input;
    foreach (explode('.', $path) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return null;
        }
        $value = $value[$segment];
    }
    return $value;
}

function hb_design_set(array &$input, string $path, mixed $value): void
{
    $segments = explode('.', $path);
    $last = array_pop($segments);
    $target = &$input;
    foreach ($segments as $segment) {
        $target = &$target[$segment];
    }
    $target[$last] = $value;
}

function hb_design_valid_media_url(string $value): bool
{
    if ($value === '') {
        return true;
    }
    if (preg_match('/[\x00-\x20"\'\\\\()<>`]/', $value)) {
        return false;
    }
    if (str_starts_with($value, '/') && !str_starts_with($value, '//')
        && preg_match('#^/(?:[A-Za-z0-9._~-]+/)*web-images/[^/]+$#', $value)) {
        $path = parse_url($value, PHP_URL_PATH);
        $file = is_string($path) ? basename(rawurldecode($path)) : '';
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'mp4', 'webm'], true)) {
            return false;
        }
        $asset = realpath(dirname(__DIR__) . '/web-images/' . $file);
        $assetRoot = realpath(dirname(__DIR__) . '/web-images');
        return is_string($asset) && is_string($assetRoot)
            && str_starts_with($asset, $assetRoot . DIRECTORY_SEPARATOR)
            && is_file($asset);
    }
    $parts = parse_url($value);
    return is_array($parts)
        && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
        && isset($parts['host'])
        && !isset($parts['user']) && !isset($parts['pass']);
}

function hb_design_valid_image_url(mixed $value): bool
{
    if (!is_string($value) || !hb_design_valid_media_url($value)) {
        return false;
    }
    if ($value === '') {
        return true;
    }
    $path = (string) parse_url($value, PHP_URL_PATH);
    return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)),
        ['jpg', 'jpeg', 'png', 'webp', 'avif'], true);
}

function hb_design_valid_video_url(mixed $value): bool
{
    if (!is_string($value) || !hb_design_valid_media_url($value)) {
        return false;
    }
    if ($value === '') {
        return true;
    }
    $path = (string) parse_url($value, PHP_URL_PATH);
    return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['mp4', 'webm'], true);
}

function hb_design_valid_link(string $value): bool
{
    if (preg_match('/[\x00-\x20"\'\\\\<>`]/', $value)) {
        return false;
    }
    if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
        $path = parse_url($value, PHP_URL_PATH);
        $webPath = rtrim(app_url('/web/'), '/');
        return is_string($path)
            && (str_starts_with($path, '/web/') || str_starts_with($path, $webPath . '/'));
    }
    $parts = parse_url($value);
    return is_array($parts)
        && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
        && isset($parts['host'])
        && !isset($parts['user']) && !isset($parts['pass']);
}
