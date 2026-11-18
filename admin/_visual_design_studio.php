<?php
declare(strict_types=1);

function hb_design_admin_path_name(string $path): string
{
    return 'design[' . str_replace('.', '][', $path) . ']';
}

function hb_design_admin_input(string $path, string $label, mixed $value, string $type = 'text', array $attributes = []): void
{
    $id = 'design-' . str_replace('.', '-', $path);
    $name = hb_design_admin_path_name($path);
    $attributeText = '';
    foreach ($attributes as $key => $attributeValue) {
        $attributeText .= ' ' . e($key) . '="' . e((string) $attributeValue) . '"';
    }

    if ($type === 'checkbox') {
        echo '<div class="field"><label class="design-toggle"><input type="hidden" name="' . e($name) . '" value="0">';
        echo '<input id="' . e($id) . '" type="checkbox" name="' . e($name)
            . '" value="1" data-design-value data-design-path="' . e($path) . '"'
            . ((bool) $value ? ' checked' : '') . '><span>' . e($label) . '</span></label></div>';
        return;
    }

    echo '<div class="field"><label for="' . e($id) . '">' . e($label) . '</label>';
    if ($type === 'color') {
        echo '<div class="design-color-control"><input id="' . e($id) . '" type="color" value="' . e((string) $value)
            . '" data-design-color data-design-path="' . e($path) . '" aria-label="' . e($label) . '">';
        echo '<input type="text" value="' . e((string) $value) . '" maxlength="7" pattern="#[0-9a-fA-F]{6}"'
            . ' data-design-color-text data-design-path="' . e($path) . '" aria-label="' . e($label) . ' HEX">';
        echo '<input type="hidden" name="' . e($name) . '" value="' . e((string) $value) . '"'
            . ' data-design-value data-design-path="' . e($path) . '"></div>';
    } else {
        echo '<input id="' . e($id) . '" type="' . e($type) . '" name="' . e($name) . '" value="' . e((string) $value)
            . '" data-design-value data-design-path="' . e($path) . '"' . $attributeText . '>';
    }
    echo '</div>';
}

function hb_design_admin_select(string $path, string $label, string $value, array $options): void
{
    $id = 'design-' . str_replace('.', '-', $path);
    echo '<div class="field"><label for="' . e($id) . '">' . e($label) . '</label>';
    echo '<select id="' . e($id) . '" name="' . e(hb_design_admin_path_name($path))
        . '" data-design-value data-design-path="' . e($path) . '">';
    foreach ($options as $optionValue => $optionLabel) {
        echo '<option value="' . e((string) $optionValue) . '"'
            . ((string) $value === (string) $optionValue ? ' selected' : '') . '>'
            . e((string) $optionLabel) . '</option>';
    }
    echo '</select></div>';
}

$designSchemaReady = hb_design_is_ready($pdo);
$designDefaults = hb_design_defaults();
$designDraft = $designSchemaReady ? (hb_design_read($pdo, 'draft_json') ?? hb_design_defaults()) : hb_design_defaults();
$designPublished = $designSchemaReady ? (hb_design_read($pdo, 'published_json') ?? hb_design_defaults()) : hb_design_defaults();
$designMeta = $designSchemaReady
    ? $pdo->query('SELECT updated_at,published_at,updated_by,published_by FROM me_web_design WHERE id=1')->fetch()
    : false;
$designProducts = $pdo->query(
    "SELECT slug,emri FROM me_produktet
     WHERE aktiv=1 AND i_dukshem_ne_web=1 ORDER BY emri"
)->fetchAll();
$designLocalImages = [];
$designLocalVideos = [];
foreach (glob(dirname(__DIR__) . '/web-images/*') ?: [] as $assetPath) {
    if (!is_file($assetPath)) {
        continue;
    }
    $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
    $url = app_url('/web-images/' . rawurlencode(basename($assetPath)));
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'avif'], true)) {
        $designLocalImages[$url] = basename($assetPath);
    } elseif (in_array($extension, ['mp4', 'webm'], true)) {
        $designLocalVideos[$url] = basename($assetPath);
    }
}
$designPreviewSlug = (string) ($designProducts[0]['slug'] ?? '');
$designInitialJson = json_encode(
    $designDraft,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
);
$designDefaultsJson = json_encode(
    $designDefaults,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
);
?>
<section class="panel visual-design-studio" id="visual-design-studio">
    <div class="design-heading">
        <div>
            <p class="eyebrow">HerbaBloom Visual Design Studio</p>
            <h2>Personalizimi i dizajnit</h2>
            <p class="help">Ndryshimet shfaqen në preview. Vizitorët i shohin vetëm pasi të publikohen.</p>
        </div>
        <span class="design-status"><?= $designMeta && $designMeta['published_at'] ? 'Publikuar: ' . e($designMeta['published_at']) : 'Dizajni bazë është aktiv' ?></span>
    </div>

    <?php if (!$designSchemaReady): ?>
        <div class="alert error" role="alert">
            Visual Design Studio nuk mund të ruajë konfigurime derisa të krijohet tabela e tij.
            Ekzekutoni një herë migrimin <code>database/migrations/003_visual_design_studio.sql</code>.
            Të dhënat ekzistuese nuk preken.
        </div>
    <?php else: ?>
        <div class="design-workspace">
            <form method="post" class="design-controls" data-design-form
                data-initial-config="<?= e($designInitialJson) ?>"
                data-default-config="<?= e($designDefaultsJson) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_design_draft" data-design-action>
                <input type="hidden" name="<?= e(hb_design_admin_path_name('palette.preset')) ?>" value="<?= e($designDraft['palette']['preset']) ?>" data-design-value data-design-path="palette.preset">

                <fieldset class="design-fieldset">
                    <legend>Paleta e ngjyrave</legend>
                    <div class="design-preset-list" aria-label="Tema të gatshme">
                        <button type="button" class="btn btn-outline-forest" data-design-preset="yellow" aria-pressed="<?= $designDraft['palette']['preset'] === 'yellow' ? 'true' : 'false' ?>">Golden Meadow</button>
                        <button type="button" class="btn btn-outline-forest" data-design-preset="bright" aria-pressed="<?= $designDraft['palette']['preset'] === 'bright' ? 'true' : 'false' ?>">Vivid Bloom</button>
                        <button type="button" class="btn btn-outline-forest" data-design-preset="earth" aria-pressed="<?= $designDraft['palette']['preset'] === 'earth' ? 'true' : 'false' ?>">Earth &amp; Beige</button>
                        <button type="button" class="btn btn-outline-forest" data-design-preset="sky" aria-pressed="<?= $designDraft['palette']['preset'] === 'sky' ? 'true' : 'false' ?>">Sky Blue</button>
                        <button type="button" class="btn btn-outline-forest" data-design-preset="rose" aria-pressed="<?= $designDraft['palette']['preset'] === 'rose' ? 'true' : 'false' ?>">Rose Garden</button>
                        <button type="button" class="btn btn-outline-forest" data-design-preset="original" aria-pressed="<?= $designDraft['palette']['preset'] === 'original' ? 'true' : 'false' ?>">Original HerbaBloom</button>
                    </div>
                    <p class="help design-preset-help">Zgjidh një temë si pikënisje. Pastaj mund t’i ndryshosh ngjyrat veç e veç te fushat më poshtë.</p>
                    <?php
                    $paletteLabels = [
                        'primary' => 'Primary', 'secondary' => 'Secondary', 'accent' => 'Accent',
                        'page_background' => 'Sfondi i faqes', 'header_background' => 'Sfondi i header-it',
                        'footer_background' => 'Sfondi i footer-it', 'card_background' => 'Sfondi i kartës',
                        'product_title' => 'Titulli i produktit', 'body_text' => 'Teksti i faqes',
                        'price' => 'Çmimi', 'button_background' => 'Sfondi i butonit',
                        'button_text' => 'Teksti i butonit', 'hover' => 'Hover',
                        'sale_badge' => 'Etiketa e ofertës', 'out_of_stock_badge' => 'Etiketa pa stok',
                        'announcement_background' => 'Sfondi i shiritit lëvizës',
                        'announcement_text' => 'Teksti i shiritit lëvizës',
                        'benefit_background' => 'Sfondi i përfitimeve',
                        'benefit_accent' => 'Ngjyra dytësore e përfitimeve',
                        'benefit_text' => 'Teksti dhe ikonat e përfitimeve',
                        'benefit_muted' => 'Teksti i vogël i përfitimeve',
                    ];
                    foreach ($paletteLabels as $key => $label) {
                        hb_design_admin_input('palette.' . $key, $label, $designDraft['palette'][$key], 'color');
                    }
                    ?>
                </fieldset>

                <fieldset class="design-fieldset">
                    <legend>Sfondi dhe fotografitë</legend>
                    <?php hb_design_admin_input('background.enabled', 'Aktivizo sfondin', $designDraft['background']['enabled'], 'checkbox'); ?>
                    <?php hb_design_admin_input('background.image', 'URL e fotos së sfondit', $designDraft['background']['image'], 'text', ['maxlength' => 2048, 'placeholder' => 'https://... ose zgjidh skedarin lokal']); ?>
                    <div class="field"><label for="design-background-local">Fotografi lokale</label>
                        <select id="design-background-local" data-design-media-picker="background.image"><option value="">Zgjidh imazh lokal</option>
                            <?php foreach ($designLocalImages as $url => $name): ?><option value="<?= e($url) ?>" <?= $designDraft['background']['image'] === $url ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <?php hb_design_admin_select('background.position', 'Pozicioni', $designDraft['background']['position'], array_combine(['center center','center top','center bottom','left center','right center'], ['Qendër','Sipër','Poshtë','Majtas','Djathtas'])); ?>
                    <?php hb_design_admin_select('background.size', 'Madhësia', $designDraft['background']['size'], ['cover' => 'Cover', 'contain' => 'Contain', 'auto' => 'Auto']); ?>
                    <?php hb_design_admin_input('background.opacity', 'Opaciteti i imazhit (%)', $designDraft['background']['opacity'], 'number', ['min' => 0, 'max' => 100, 'step' => 1]); ?>
                    <?php hb_design_admin_input('background.overlay_color', 'Ngjyra overlay', $designDraft['background']['overlay_color'], 'color'); ?>
                    <?php hb_design_admin_input('background.overlay_opacity', 'Transparenca overlay (%)', $designDraft['background']['overlay_opacity'], 'number', ['min' => 0, 'max' => 100, 'step' => 1]); ?>
                    <?php hb_design_admin_input('background.blur', 'Blur (px)', $designDraft['background']['blur'], 'number', ['min' => 0, 'max' => 20, 'step' => 1]); ?>
                    <div class="design-checks">
                        <?php hb_design_admin_input('background.sections.body', 'Sfondi i faqes', $designDraft['background']['sections']['body'], 'checkbox'); ?>
                        <?php hb_design_admin_input('background.sections.hero', 'Sfondi i Hero Section', $designDraft['background']['sections']['hero'], 'checkbox'); ?>
                        <?php hb_design_admin_input('background.sections.content', 'Sfondi i seksioneve/kartave', $designDraft['background']['sections']['content'], 'checkbox'); ?>
                    </div>
                </fieldset>

                <fieldset class="design-fieldset">
                    <legend>Tipografia</legend>
                    <?php
                    $fontOptions = array_combine(
                        array_keys(hb_design_font_options()),
                        array_keys(hb_design_font_options())
                    );
                    foreach ([
                        'heading_font' => 'Fonti i titujve',
                        'description_font' => 'Fonti i përshkrimeve',
                        'body_font' => 'Fonti i tekstit',
                        'decorative_font' => 'Fonti dekorativ',
                        'price_font' => 'Fonti i çmimeve',
                    ] as $key => $label) {
                        hb_design_admin_select('typography.' . $key, $label, $designDraft['typography'][$key], $fontOptions);
                    }
                    hb_design_admin_input('typography.heading_size', 'Madhësia e titullit (px)', $designDraft['typography']['heading_size'], 'number', ['min' => 22, 'max' => 72]);
                    hb_design_admin_input('typography.body_size', 'Madhësia e tekstit (px)', $designDraft['typography']['body_size'], 'number', ['min' => 12, 'max' => 22]);
                    hb_design_admin_input('typography.weight', 'Trashësia (300–700)', $designDraft['typography']['weight'], 'number', ['min' => 300, 'max' => 700, 'step' => 100]);
                    hb_design_admin_input('typography.line_height', 'Lartësia e rreshtit', $designDraft['typography']['line_height'], 'number', ['min' => 1.2, 'max' => 2.2, 'step' => 0.05]);
                    ?>
                </fieldset>

                <fieldset class="design-fieldset">
                    <legend>Kartat e produkteve</legend>
                    <?php hb_design_admin_select('cards.size', 'Madhësia e kartës', $designDraft['cards']['size'], ['small' => 'E vogël', 'medium' => 'Mesatare', 'large' => 'E madhe']); ?>
                    <?php hb_design_admin_input('cards.radius', 'Rrumbullakësia (px)', $designDraft['cards']['radius'], 'number', ['min' => 0, 'max' => 40]); ?>
                    <?php hb_design_admin_input('cards.shadow', 'Hije', $designDraft['cards']['shadow'], 'checkbox'); ?>
                    <?php hb_design_admin_input('cards.border', 'Kufi', $designDraft['cards']['border'], 'checkbox'); ?>
                    <?php hb_design_admin_input('cards.background_opacity', 'Transparenca e kartës (%)', $designDraft['cards']['background_opacity'], 'number', ['min' => 0, 'max' => 100]); ?>
                    <?php hb_design_admin_select('cards.hover', 'Efekti gjatë hover', $designDraft['cards']['hover'], ['none' => 'Asnjë', 'lift' => 'Ngritje', 'scale' => 'Zmadhim i lehtë']); ?>
                    <?php hb_design_admin_select('cards.image_fit', 'Paraqitja e fotos', $designDraft['cards']['image_fit'], ['cover' => 'Cover', 'contain' => 'Contain']); ?>
                    <?php hb_design_admin_input('cards.image_height', 'Lartësia e fotos (px)', $designDraft['cards']['image_height'], 'number', ['min' => 120, 'max' => 420]); ?>
                    <?php hb_design_admin_input('cards.columns', 'Kolonat në desktop', $designDraft['cards']['columns'], 'number', ['min' => 2, 'max' => 6]); ?>
                </fieldset>

                <fieldset class="design-fieldset">
                    <legend>Hero Section</legend>
                    <?php hb_design_admin_input('hero.enabled', 'Shfaq Hero Section', $designDraft['hero']['enabled'], 'checkbox'); ?>
                    <?php hb_design_admin_input('hero.height', 'Lartësia (px)', $designDraft['hero']['height'], 'number', ['min' => 300, 'max' => 900]); ?>
                    <?php hb_design_admin_input('hero.min_height', 'Lartësia minimale (px)', $designDraft['hero']['min_height'], 'number', ['min' => 240, 'max' => 900]); ?>
                    <?php hb_design_admin_input('hero.max_height', 'Lartësia maksimale (px)', $designDraft['hero']['max_height'], 'number', ['min' => 300, 'max' => 1100]); ?>
                    <?php hb_design_admin_input('hero.width', 'Gjerësia (%)', $designDraft['hero']['width'], 'number', ['min' => 60, 'max' => 100]); ?>
                    <?php hb_design_admin_input('hero.padding', 'Hapësira e brendshme (px)', $designDraft['hero']['padding'], 'number', ['min' => 8, 'max' => 100]); ?>
                    <?php hb_design_admin_input('hero.background_image', 'URL e imazhit të Hero', $designDraft['hero']['background_image'], 'text', ['maxlength' => 2048]); ?>
                    <div class="field"><label for="design-hero-local">Imazh lokal për Hero</label>
                        <select id="design-hero-local" data-design-media-picker="hero.background_image"><option value="">Zgjidh imazh lokal</option>
                            <?php foreach ($designLocalImages as $url => $name): ?><option value="<?= e($url) ?>" <?= $designDraft['hero']['background_image'] === $url ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <?php hb_design_admin_input('hero.video_url', 'URL e videos (MP4/WebM)', $designDraft['hero']['video_url'], 'text', ['maxlength' => 2048]); ?>
                    <div class="field"><label for="design-hero-video-local">Video lokale</label>
                        <select id="design-hero-video-local" data-design-media-picker="hero.video_url"><option value="">Zgjidh video lokale</option>
                            <?php foreach ($designLocalVideos as $url => $name): ?><option value="<?= e($url) ?>" <?= $designDraft['hero']['video_url'] === $url ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <?php hb_design_admin_input('hero.poster', 'Poster / foto rezervë', $designDraft['hero']['poster'], 'text', ['maxlength' => 2048]); ?>
                    <div class="field"><label for="design-hero-poster-local">Poster lokal</label>
                        <select id="design-hero-poster-local" data-design-media-picker="hero.poster"><option value="">Zgjidh poster lokal</option>
                            <?php foreach ($designLocalImages as $url => $name): ?><option value="<?= e($url) ?>" <?= $designDraft['hero']['poster'] === $url ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <?php hb_design_admin_input('hero.overlay_color', 'Ngjyra overlay e videos', $designDraft['hero']['overlay_color'], 'color'); ?>
                    <?php hb_design_admin_input('hero.overlay_opacity', 'Transparenca overlay (%)', $designDraft['hero']['overlay_opacity'], 'number', ['min' => 0, 'max' => 100]); ?>
                    <?php hb_design_admin_select('hero.position', 'Pozicioni i medias', $designDraft['hero']['position'], array_combine(['center center','center top','center bottom','left center','right center'], ['Qendër','Sipër','Poshtë','Majtas','Djathtas'])); ?>
                    <?php hb_design_admin_input('hero.eyebrow', 'Nëntitulli i vogël', $designDraft['hero']['eyebrow'], 'text', ['maxlength' => 100]); ?>
                    <?php hb_design_admin_input('hero.title', 'Titulli kryesor', $designDraft['hero']['title'], 'text', ['maxlength' => 180]); ?>
                    <?php hb_design_admin_input('hero.description', 'Përshkrimi', $designDraft['hero']['description'], 'text', ['maxlength' => 500]); ?>
                    <?php hb_design_admin_select('hero.title_font', 'Fonti i titullit', $designDraft['hero']['title_font'], $fontOptions); ?>
                    <?php hb_design_admin_select('hero.decorative_font', 'Fonti dekorativ', $designDraft['hero']['decorative_font'], $fontOptions); ?>
                    <?php hb_design_admin_input('hero.text_color', 'Ngjyra e tekstit', $designDraft['hero']['text_color'], 'color'); ?>
                    <?php hb_design_admin_input('hero.button_text', 'Teksti i butonit', $designDraft['hero']['button_text'], 'text', ['maxlength' => 60]); ?>
                    <?php hb_design_admin_input('hero.button_url', 'Lidhja e butonit', $designDraft['hero']['button_url'], 'text', ['maxlength' => 2048]); ?>
                </fieldset>

                <fieldset class="design-fieldset">
                    <legend>Header dhe footer</legend>
                    <?php hb_design_admin_input('header.opacity', 'Transparenca e header-it (%)', $designDraft['header']['opacity'], 'number', ['min' => 50, 'max' => 100]); ?>
                    <?php hb_design_admin_input('header.height', 'Lartësia e header-it (px)', $designDraft['header']['height'], 'number', ['min' => 54, 'max' => 140]); ?>
                    <?php hb_design_admin_input('header.logo_width', 'Gjerësia e ikonës së logos (px)', $designDraft['header']['logo_width'], 'number', ['min' => 20, 'max' => 60]); ?>
                    <?php hb_design_admin_input('header.menu_color', 'Ngjyra e menusë', $designDraft['header']['menu_color'], 'color'); ?>
                    <?php hb_design_admin_input('header.menu_hover', 'Ngjyra hover e menusë', $designDraft['header']['menu_hover'], 'color'); ?>
                    <?php hb_design_admin_input('header.sticky', 'Header ngjitës (sticky)', $designDraft['header']['sticky'], 'checkbox'); ?>
                    <?php hb_design_admin_input('footer.text_color', 'Teksti i footer-it', $designDraft['footer']['text_color'], 'color'); ?>
                    <?php hb_design_admin_input('footer.heading_color', 'Titujt e footer-it', $designDraft['footer']['heading_color'], 'color'); ?>
                    <?php hb_design_admin_input('footer.spacing', 'Hapësira e footer-it (px)', $designDraft['footer']['spacing'], 'number', ['min' => 8, 'max' => 80]); ?>
                </fieldset>

                <div class="design-actions">
                    <button class="btn btn-outline-forest" type="button" data-design-preview>Shiko parapamjen</button>
                    <button class="btn btn-forest" type="submit" data-design-submit="draft">Ruaj draftin</button>
                    <button class="btn btn-forest" type="submit" data-design-submit="publish">Publiko dizajnin</button>
                    <button class="btn btn-outline-forest" type="button" data-design-reset>Rivendos ndryshimet e paruajtura</button>
                    <button class="btn btn-outline-forest" type="button" data-design-default>Ngarko dizajnin fillestar</button>
                </div>
                <p class="help" data-design-message aria-live="polite"></p>
            </form>

            <dialog class="design-publish-dialog" data-design-publish-dialog aria-labelledby="design-publish-title">
                <div class="design-publish-icon" aria-hidden="true"><i class="bi bi-globe2"></i></div>
                <p class="eyebrow">HerbaBloom Visual Design Studio</p>
                <h3 id="design-publish-title">Publiko dizajnin?</h3>
                <p>Vizitorët e faqes do ta shohin menjëherë versionin e ri të dizajnit.</p>
                <div class="design-publish-actions">
                    <button class="btn btn-outline-forest" type="button" data-design-publish-cancel>Anulo</button>
                    <button class="btn btn-forest" type="button" data-design-publish-confirm>Po, publikoje</button>
                </div>
            </dialog>

            <aside class="design-preview-panel" aria-label="Parapamja e faqes">
                <div class="design-preview-toolbar">
                    <label for="design-preview-page">Faqja</label>
                    <select id="design-preview-page" data-preview-page>
                        <option value="index.php">Ballina</option>
                        <option value="produktet.php">Produktet</option>
                        <option value="product-detail">Detajet e produktit</option>
                        <option value="bestseller.php">Bestseller</option>
                        <option value="oferta.php">Ofertat</option>
                        <option value="rreth-nesh.php">Rreth nesh</option>
                        <option value="kontakti.php">Kontakt</option>
                        <option value="llogaria.php">Llogaria</option>
                        <option value="shporta.php">Shporta</option>
                        <option value="gjurmo.php">Gjurmimi i porosisë</option>
                        <option value="checkout.php">Përfundo porosinë</option>
                    </select>
                    <select aria-label="Zgjidh produktin për preview" data-preview-product>
                        <?php foreach ($designProducts as $product): ?>
                            <option value="<?= e($product['slug']) ?>"><?= e($product['emri']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="design-device-picker" role="group" aria-label="Përmasat e pajisjes">
                        <button type="button" data-preview-width="100%" aria-pressed="true">Desktop</button>
                        <button type="button" data-preview-width="768px" aria-pressed="false">Tablet</button>
                        <button type="button" data-preview-width="390px" aria-pressed="false">Mobile</button>
                    </div>
                </div>
                <div class="design-preview-frame-wrap">
                    <iframe
                        title="Preview real i dyqanit HerbaBloom"
                        data-design-preview-frame
                        src="<?= e(app_url('/web/index.php?visual_preview=1')) ?>"
                        loading="lazy"
                        referrerpolicy="same-origin"
                    ></iframe>
                </div>
                <p class="help">Preview përdor faqet dhe të dhënat reale të dyqanit. Ndryshimet këtu nuk publikohen.</p>
            </aside>
        </div>
    <?php endif; ?>
</section>
