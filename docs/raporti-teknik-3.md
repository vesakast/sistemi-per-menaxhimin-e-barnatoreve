# Raporti Teknik 3 – Frontend-i, integrimi dhe progresi funksional

**Projekti:** HerbaBloom – Sistemi për menaxhimin e barnatores bimore
**Studentja:** Vesa Kastrati

## 1. Organizimi i frontend-it

Projekti përdor PHP të renderuar në server dhe jo një aplikacion SPA. Kjo është arkitektura ekuivalente për organizimin e kërkuar: faqet trajtojnë pamjen dhe veprimet, funksionet e përbashkëta ripërdorin komponentët dhe `api/index.php` trajton kërkesat JSON.

```text
herbabloom/
├── admin/
│   ├── ofertat.php      Konfigurimi i seteve dhe URL-ve të imazheve të ofertave
│   ├── cilesimet.php    Cilësimet dhe Visual Design Studio për administratorin
│   ├── _visual_design_studio.php  Kontrollet dhe parapamja e studios së dizajnit
│   └── visual-design-studio.js    Përditësimi i parapamjes dhe formularit
├── web/
│   ├── _functions.php   Header/footer, karta produktesh, CSRF, sesion, shportë
│   ├── _design.php      Konfigurimi, validimi dhe CSS-ja e dizajnit të publikuar
│   ├── index.php        Ballina, kategori reale, produkte të reja/bestseller
│   ├── produktet.php    Katalogu, filtrat dhe sugjerimet e kërkimit
│   ├── shporta.php      Gjendja e shportës dhe sasitë
│   ├── checkout.php     Validimi dhe krijimi transaksional i porosisë
│   ├── gjurmo.php       Statusi dhe historiku me kod gjurmimi
│   ├── llogaria.php     Hyrja, regjistrimi, porositë, favorites, pëlqimet
│   ├── bestseller.php   Renditje nga shitjet reale të dorëzuara
│   ├── oferta.php       Setet që janë konfiguruar në databazë
│   ├── rreth-nesh.php   Histori orientuese dhe shënim për pretendimet
│   ├── style.css        Dizajni responsive dhe gjendjet e komponentëve
│   └── store.js         Carousel, animacione, njoftime dhe kërkim live
└── web-images/          Imazhet dhe videot lokale të storefront-it
```

`_functions.php` shërben si shtresa e përbashkët e paraqitjes dhe logjikës së sesionit. Gjendja e shportës së vizitorit ruhet në sesion, ndërsa katalogu, çmimet dhe stoku lexohen nga MySQL. API-ja përdor të njëjtat rregulla për identitetin e klientit dhe të njëjtën logjikë të stokut që përdorin porositë.

## 2. Rrjedha kryesore: kërkimi dhe porosia online

```text
Kërkimi në katalog
  -> JavaScript pret shkrimin dhe dërgon GET /api/products?q=...
  -> API kërkon në databazë me PDO dhe kthen sugjerimet JSON
  -> frontend-i i paraqet si tekst të koduar me lidhje te produkti

Kërkimi nga ikona e header-it
  -> hap dialogun e kërkimit pa larguar përdoruesin nga faqja
  -> kërkon produkte përmes API-së dhe përputh kategori/faqe lokale
  -> lidhja e zgjedhur hap produktin, kategorinë ose faqen përkatëse

Shtimi në shportë
  -> Formular POST me token CSRF
  -> web/shporta.php verifikon produktin aktiv, sasinë dhe stokun aktual
  -> ID-ja/sasia ruhen në sesion, jo çmimi i dërguar nga shfletuesi
  -> përdoruesi kthehet te faqja ku po shfletonte; numri i shportës përditësohet

Konfirmimi i porosisë
  -> web/checkout.php validon të dhënat e dërgesës në server
  -> transaksioni rilexon produktet, ofertat dhe tarifën nga databaza
  -> rezervohet stoku nga batch-et e vlefshme dhe regjistrohen lëvizjet
  -> ruhet porosia, artikujt dhe statusi fillestar
  -> COMMIT; shporta pastrohet dhe klienti dërgohet te gjurmimi
  -> gabimi para COMMIT bën ROLLBACK dhe formulari shfaq mesazh gabimi
```

Çmimi dhe stoku verifikohen në backend, edhe nëse ndryshojnë pasi faqja e shportës është hapur. Rezervimi bazohet në lotet me stok të vlefshëm; stoku i skaduar nuk shitet. Faqja e gjurmimit përdor token-in e porosisë dhe paraqet historikun e statuseve.

## 3. Llogaritë, të preferuarat dhe ofertat

Klientët ruhen në `me_klientet`, veçmas nga `me_perdoruesit` e stafit. Fjalëkalimet ruhen me `password_hash`; hyrja ka kufizim tentativash, sesion të veçantë dhe logout me token CSRF. Porositë e llogarisë filtrohen me `klient_id`. Të preferuarat dhe pëlqimet për njoftime lidhen me klientin, dhe asnjë pëlqim nuk regjistrohet nëse kutitë nuk zgjidhen.

Setet përdorin produkte reale të katalogut dhe çmimin e vendosur nga administratori. Faqja publike nuk sajon zbritje: çmimi i mëparshëm shfaqet vetëm kur totali aktual i përbërësve tejkalon çmimin e konfiguruar të setit. Bestseller-at llogariten nga lëvizjet `shitje` të porosive me status `Dorëzuar`, kështu që përfshihen edhe komponentët e seteve.

Skema për këto funksione gjendet te `database/schema.sql`; për instalimet ekzistuese, migrimi 001 krijon llogaritë/ofertat dhe lidhjen opsionale të porosive, migrimi 002 shton kolonën e imazhit të ofertës nëse mungon, ndërsa migrimi 003 krijon tabelën e Visual Design Studio. Gjashtë setet fillestare përgatiten veçmas te `database/seeds/001_herbabloom_bundles.sql` dhe `database/seeds/002_herbabloom_bundles.sql`, pas krijimit të tabelave dhe importimit të katalogut real. Seed-et aplikohen në databazën lokale vetëm pasi kontrollohen produktet dhe stoku ekzistues.

### Imazhet dhe videot

Skedarët lokalë mbahen te `web-images/`; slug-et e produkteve/ofertave përdoren për hartëzimin e parazgjedhur të imazheve lokale. Paneli lejon ruajtjen e një URL-je HTTP/HTTPS për imazhin e produktit dhe për imazhin e ofertës; `product_image_path()` dhe `offer_image_path()` përdorin URL-në e ruajtur, ndërsa mungesa e një imazhi të vlefshëm shfaq placeholder. Në databazën lokale, dy produkte dhe një ofertë përdorin URL që drejtojnë te skedarë lokalë në `web-images/`; kjo e bën menaxhimin të mundur nga paneli, pa ndryshuar kodin. URL-të me `localhost` janë për përdorim lokal. Manifesti i skedarëve është te `IMAGE_ASSETS.md`.

### Visual Design Studio

Administratori konfiguron ngjyrat, tipografinë dhe sfondet e dyqanit te cilësimet. Ndryshimet ruhen si draft veçmas nga dizajni i publikuar; parapamja live mund të shfaqë ballinën, katalogun ose detajet e një produkti në përmasa desktop, tablet dhe telefon. Publikimi kërkon konfirmim të veçantë. Konfigurimi publikohet në `me_web_design`; për instalimet ekzistuese kërkohet migrimi `database/migrations/003_visual_design_studio.sql`. Parapamja e draftit lejohet vetëm për administratorin e kyçur.

## 4. API-ja dhe trajtimi i gjendjeve

API-ja kthen JSON të njëtrajtshëm me `success`, `data` ose `error`/`message`. Kërkesat që ndryshojnë të dhëna përdorin cookie sesioni dhe `X-CSRF-Token`.

| Metoda | Endpoint | Input-i kryesor | Output-i/statuset kryesore |
|---|---|---|---|
| `GET` | `/api/auth/csrf` | Pa body | `200`: `{"success":true,"csrf_token":"..."}` dhe cookie sesioni |
| `POST` | `/api/customers/register` | `name`, `email`, `password`; opsionale `new_products`, `discounts`, `offers` (boolean) | `201`: ID/emër/email; `409` email ekzistues, `419` CSRF, `422` input, `503` migrimi |
| `POST` | `/api/customers/login` | `email`, `password` | `200`: ID/emër/email; `401` kredenciale, `419` CSRF, `422` input, `429` limit, `503` migrimi |
| `POST` | `/api/customers/logout` | Pa body; sesion + CSRF | `200`: mesazh daljeje; `401` sesion, `419` CSRF, `503` migrimi |
| `GET` | `/api/customers/me` | Sesioni i klientit | `200`: profil dhe pëlqimet; `401` pa sesion, `503` migrimi |
| `GET` | `/api/customers/orders` | Sesioni i klientit | `200`: listë e porosive të veta dhe kodeve të gjurmimit; `401`, `503` |
| `GET` | `/api/customers/favorites` | Sesioni i klientit | `200`: listë e produkteve të preferuara; `401`, `503` |
| `POST` | `/api/customers/favorites` | `product_id`; sesion + CSRF | `201`: produkti u ruajt; `401`, `404` produkti, `419`, `422`, `503` |
| `DELETE` | `/api/customers/favorites/{product_id}` | ID në URL; sesion + CSRF | `200`: produkti u largua; `401`, `404` favorite, `419`, `503` |
| `PUT` | `/api/customers/preferences` | `new_products`, `discounts`, `offers` (boolean); sesion + CSRF | `200`: preferencat u ruajtën; `401`, `419`, `422`, `503` |

Shembull regjistrimi (të gjitha fushat e pëlqimit janë opsionale dhe mungesa e tyre ruan `false`):

```http
POST /api/customers/register
Content-Type: application/json
X-CSRF-Token: <token>
Cookie: herbabloom_session=<sesion>
```

```json
{
  "name": "Klient Testues",
  "email": "klienti@example.com",
  "password": "fjalekalim-i-gjate",
  "new_products": false,
  "discounts": true,
  "offers": false
}
```

Përgjigjja `201 Created` përmban `{"success":true,"data":{"id":1,"name":"Klient Testues","email":"klienti@example.com"}}`. Token-i merret fillimisht me `GET /api/auth/csrf`; përdoret i njëjti cookie sesioni dhe header CSRF për veprimet që ndryshojnë të dhëna.

Gjendjet e ndërfaqes:

- **Duke u kërkuar / duke u përpunuar:** status i dukshëm për kërkimin live dhe dërgimin e formularëve; parandalon dërgimin e dyfishtë.
- **Bosh:** shportë bosh, pa porosi, pa të preferuara ose pa oferta shfaqin udhëzim dhe lidhje për veprimin vijues.
- **Gabim:** validimi i formularit, artikulli pa stok, kodi i gjurmimit që mungon dhe përgjigjet e dështuara të kërkimit japin mesazh të kuptueshëm.
- **API:** pa hyrje kthehet `401`; input i pavlefshëm `422`; porosi që mungon `404`; funksionet e klientit pa migrim `503`.

Shembuj të verifikuar lokalisht:

```json
HTTP 422
{"success":false,"error":"VALIDATION_ERROR","message":"page must be positive and page_size must be between 1 and 100."}
```

```json
HTTP 404
{"success":false,"error":"ORDER_NOT_FOUND","message":"Order does not exist."}
```

## 5. Verifikimi dhe kufijtë aktualë

Në mjedisin Laragon u kryen kontrolle pa krijuar porosi ose llogari klientësh:

- Më 4 nëntor 2026, `php -l` kaloi për 103 skedarë PHP në projekt (përfshirë `vendor/`), dhe `git diff --check` nuk raportoi gabime hapësirash. Ky kontroll nuk zëvendëson testet funksionale.
- Ballina, katalogu, shporta, ofertat, bestseller-at, rreth nesh dhe llogaria kthyen HTTP 200.
- API-ja ktheu 401 për listën e porosive pa sesion, 404 për kod gjurmimi të panjohur dhe 422 për parametrin e pavlefshëm `page=0`.
- Migrimet u aplikuan në databazën lokale `herbabloom_db`; dy seed-et përgatitën gjashtë sete bazë, me përbërje prej 2, 3, 5, 2, 3 dhe 4 produktesh. Asnjë produkt apo porosi ekzistuese nuk u ndryshua nga migrimet.
- Faqet publike të ofertave lexojnë setet nga MySQL. Skedarët përkatës ndodhen në `web-images/`; për disa produkte/oferta përdoret URL-ja e caktuar nga paneli.
- Kontrolli i ballinës në viewport 659 px nuk gjeti imazhe të prishura ose dalje horizontale. Kur mungon skedari, shfaqet placeholder lokal.
- Databaza e lexuar gjatë kontrollit më 5 nëntor 2026 përmbante 102 produkte, katër kategori aktive dhe shtatë oferta aktive; një ofertë kishte URL imazhi të ruajtur. Këto janë të dhëna të momentit, jo numër i fiksuar në kod; katalogu merret nga databaza.

Porosia reale nuk u dërgua gjatë kontrollit vetëm-lexim, për të mos krijuar të dhëna gjatë verifikimit teknik. Për provat ose demonstrimin e ardhshëm, nëse paraqiten rrjedha që krijojnë porosi/llogari, përdoren llogari dhe të dhëna testuese. Databaza ekzistuese është kontrolluar dhe nuk kërkohet krijimi i një databaze tjetër.

Për prezantimin mund të tregohet kodi i kërkimit në `web/store.js`, query i përgatitur te `api/index.php`, kontrolli në `web/checkout.php` dhe rezervimi i batch-eve te `admin/_inventory.php`.

## 6. Përfunduar dhe hapat deri në beta

**Përfunduar në këtë fazë:** ridizajni responsive, kategori nga databaza, kërkim AJAX në API, shportë me çmime/stok të rillogaritur në server, checkout transaksional, gjurmim, bestseller nga shitjet reale, API dhe UI për klientët, favorites/pëlqimet dhe administrim bazë i seteve. Funksionet në panel dhe në web i kam testuar manualisht. Demonstrimi live me profesorin është planifikuar për prezantimin dhe ende nuk është bërë.

**Punë e mbetur për prezantimin/dorëzimin:** përgatit provat me pamje ekrani dhe dorëzoji në dokument Word; zhvillo demonstrimin live me profesorin gjatë prezantimit; konfirmo tekstet dhe pretendimet për biznesin. Asetet vizuale janë për përdorim lokal; për publikimin e tyre në GitHub duhen përdorur vetëm skedarë që ke të drejtë t'i shpërndash. Nuk është planifikuar publikim i aplikacionit në domain; URL-të `localhost` janë për përdorim lokal.

Raportet 1–3 dhe ndryshimet e ridizajnit publikohen me këtë version; raporti 4 nuk përfshihet në këtë publikim. Udhëzuesi kërkon evidencë testimi, kontrolle sigurie/konfigurimi, si dhe demonstrimin praktik. Funksionet në panel dhe web i kam testuar manualisht; demonstrimi live me profesorin do të zhvillohet në prezantim, ndërsa provat me pamje ekrani do të dorëzohen në dokument Word. Nuk kërkohet URL publike apo hosting, sepse demonstrimi i aplikacionit do të bëhet lokalisht.
