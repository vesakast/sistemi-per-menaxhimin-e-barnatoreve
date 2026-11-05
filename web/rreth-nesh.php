<?php
declare(strict_types=1);

require_once __DIR__ . '/_functions.php';

$storeName = (string) ($pdo->query('SELECT emri_dyqanit FROM me_cilesimet WHERE id=1')->fetchColumn() ?: 'HerbaBloom');
$imagePath = dirname(__DIR__) . '/web-images/about-herbabloom.jpg';
$imageUrl = is_file($imagePath) ? app_url('/web-images/about-herbabloom.jpg') : '';

web_header('Rreth nesh');
?>
<section class="page-intro" data-reveal>
    <p class="eyebrow">Njihu me HerbaBloom</p>
    <h1 class="page-title">Kujdes i përditshëm, i zgjedhur me vëmendje.</h1>
    <p>Një dyqan online për produkte me përbërës me bazë bimore, mirëqenie dhe kujdes personal.</p>
</section>
<section class="about-story" data-reveal>
    <div class="about-image">
        <?php if ($imageUrl !== ''): ?>
            <img src="<?= e($imageUrl) ?>" alt="Ambient i ngrohtë me produkte bimore" loading="lazy">
        <?php else: ?>
            <span class="image-placeholder" role="img" aria-label="Pamje ilustruese e HerbaBloom">
                <i class="bi bi-leaf" aria-hidden="true"></i><span>Kujdes i përzgjedhur me vëmendje</span>
            </span>
        <?php endif; ?>
    </div>
    <article class="about-copy">
        <p class="eyebrow">Historia jonë</p>
        <h2>Një ide e thjeshtë: zgjedhje më e lehtë për rutinën tënde.</h2>
        <p><?= e($storeName) ?> lindi nga dëshira për t’i bërë më të lehta zbulimin dhe porositjen e produkteve bimore dhe të kujdesit personal. Në katalog gjen kategori të ndryshme, nga çajrat e vajrat esencialë te vitaminat, suplementet dhe produktet për lëkurën e flokët.</p>
        <p>Ne besojmë se një përzgjedhje e mirë fillon me informacion të kuptueshëm. Prandaj synojmë t’i paraqesim produktet, çmimet dhe disponueshmërinë në mënyrë të qartë, që secili të mund të zgjedhë sipas preferencave dhe nevojave të veta.</p>
        <h3>Përkushtimi ynë</h3>
        <p>Të ofrojmë një përvojë të kujdesshme online, me komunikim të drejtpërdrejtë dhe mundësi për ta përcjellë porosinë pas regjistrimit.</p>
        <h3>Çfarë vlerësojmë</h3>
        <ul><li>Përzgjedhje të orientuar te përbërësit me bazë bimore dhe kujdesi personal.</li><li>Informacion të qartë për produktet, çmimet dhe porositë.</li><li>Mbështetje të sjellshme para dhe pas blerjes.</li></ul>
    </article>
</section>
<section class="store-section about-education" data-reveal>
    <p class="eyebrow">Informacion i përgjithshëm</p>
    <h2>Zgjedh me informacion dhe kujdes</h2>
    <div class="about-info-grid">
        <article class="content-card">
            <h3>Barna dhe medikamente</h3>
            <p>Çdo produkt i kësaj kategorie duhet përdorur vetëm sipas etiketës dhe udhëzimit profesional. Nëse ke pyetje për përshtatshmërinë ose përdorimin, këshillohu me farmacistin apo mjekun.</p>
        </article>
        <article class="content-card">
            <h3>Vitamina dhe suplemente</h3>
            <p>Vitaminat dhe suplementet nuk e zëvendësojnë një dietë të larmishme dhe nuk janë të përshtatshme për secilin person. Përbërësit mund të ndërveprojnë me barna; lexo etiketën dhe kërko këshillë profesionale kur ke paqartësi.</p>
        </article>
        <article class="content-card">
            <h3>Çajra dhe produkte bimore</h3>
            <p>Çajrat dhe produktet bimore ndryshojnë sipas përbërjes dhe mënyrës së përdorimit. Lexo udhëzimet përkatëse; natyra bimore nuk garanton vetvetiu siguri apo rezultat të caktuar.</p>
        </article>
        <article class="content-card">
            <h3>Vajra dhe kremra</h3>
            <p>Vajrat dhe kremrat mund të jenë pjesë e rutinës së kujdesit personal. Ndiq udhëzimet në etiketë, provo produktet sipas rekomandimit të tyre dhe ndërprite përdorimin nëse shfaqet irritim.</p>
        </article>
    </div>
</section>
<aside class="notice product-info-note" role="note" data-reveal>
    <strong>Informacion për produktet:</strong> Përbërja dhe udhëzimet ndryshojnë sipas produktit. Kontrollo etiketën për përbërjen, përdorimin dhe paralajmërimet përkatëse.
</aside>
<aside class="notice confirm-copy health-notice" role="note" data-reveal>
    <strong>Shënim i rëndësishëm:</strong> Përshkrimet në këtë faqe janë informuese dhe nuk premtojnë trajtim apo parandalim të sëmundjeve; ato nuk zëvendësojnë këshillën e profesionistit shëndetësor. Përbërjen, përdorimin dhe paralajmërimet specifike kontrolloji në etiketën e secilit produkt.
    <p>Burime për lexim të mëtejshëm:
        <a href="https://www.fda.gov/consumers/consumer-updates/fda-101-dietary-supplements" target="_blank" rel="noopener noreferrer">FDA – Dietary Supplements</a>
        dhe <a href="https://www.nccih.nih.gov/health/herbsataglance" target="_blank" rel="noopener noreferrer">NCCIH – Herbs at a Glance</a>.
    </p>
</aside>
<?php web_footer(); ?>
