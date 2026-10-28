# HerbaBloom

HerbaBloom është aplikacion PHP për administrimin e një barnatoreje bimore dhe shitjen online. Projekti përfshin panelin e stafit, dyqanin publik dhe një API REST në JSON. Aplikacioni përdor skemën MySQL me prefiksin `me_`; PHP komunikon me MySQL përmes PDO.

> `database/schema.sql` përmban vetëm strukturën e tabelave dhe pamjes së stokut; nuk përmban porosi, produkte, llogari ose kredenciale. README-ja nuk e zëvendëson prezantimin teknik dhe demonstrimin praktik të kërkuar në udhëzues.

## Raportet teknike

- [Raporti Teknik 1 – Analiza, kërkesat dhe dizajni teknik](docs/raporti-teknik-1.md)
- [Raporti Teknik 2 – Databaza, backend-i dhe API](docs/raporti-teknik-2.md)

Këto dokumente përmbledhin projektin dhe mund të shërbejnë si bazë për prezantimet. Në Raportin 2 duhet të hapen edhe aplikacioni, databaza dhe një klient API për demonstrim të drejtpërdrejtë.

## Funksionalitetet kryesore

- Menaxhimi i produkteve dhe stokut sipas loteve dhe datës së skadencës.
- Porosi online dhe manuale, me artikuj të shumtë, caktim të kurierit dhe gjurmim të porosisë.
- Role të stafit: administrator, farmacist dhe kurier.
- Kyçje e stafit me fjalëkalim dhe verifikim OTP me email.
- Menaxhimi i mesazheve të klientëve, cilësimeve të dyqanit dhe raporteve financiare sipas lejeve.
- API REST për autentikim, produkte, porosi dhe gjurmim.

## Arkitektura dhe lidhja PHP–databazë

```text
Shfletuesi (web/ ose admin/) ── formularë/HTTP ──┐
Klienti i API-së ─────────────── JSON/HTTP ─────┤
                                                v
                         PHP (validim, role, logjikë biznesi)
                                                |
                                    PDO + SQL të përgatitura
                                                |
                                                v
                              MySQL: databaza herbabloom_db
```

`db.php` krijon lidhjen PDO dhe e ruan objektin e lidhjes te `$pdo`. Faqet e `admin/`, faqet e `web/` dhe `api/index.php` përdorin të njëjtën lidhje. Për shembull, API-ja merr listën e produkteve nga `me_produktet` dhe pamja `me_produktet_stoku`; gjatë krijimit të porosisë, PHP lexon produktet, shkruan porosinë dhe artikujt e saj, rezervon sasinë nga `me_stoku_batch` dhe regjistron lëvizjet në `me_stoku_levizjet`. Këto ndryshime bëhen brenda transaksioneve.

Pra, **PHP nuk e krijon vetë databazën dhe API-ja nuk është tabelë databaze**. MySQL ruan të dhënat; PHP i lexon ose i ndryshon ato dhe API-ja kthen përgjigje JSON. Projekti nuk krijon apo migron tabela gjatë kërkesave.

## Teknologjitë

- PHP 8.1 ose më i ri (i verifikuar me PHP 8.3), me PDO MySQL dhe OpenSSL
- MySQL 8
- HTML, CSS dhe JavaScript
- Laragon për zhvillim lokal
- PHPMailer për dërgimin e kodeve OTP

PHP/PDO përshtatet me ambientin Laragon dhe lejon përdorimin e query-ve të përgatitura; MySQL ruan të dhënat relacionale; HTML/CSS/JavaScript implementojnë ndërfaqen pa framework të rëndë.

## Struktura e projektit

```text
herbabloom/
├── admin/       Paneli i stafit, RBAC, inventari dhe porositë
├── api/         Rutimi i API-së REST dhe konfigurimi Apache
├── database/    Skema SQL pa të dhëna aplikacioni
├── docs/        Dokumentet për Raportet Teknike 1 dhe 2
├── web/         Dyqani publik dhe rrjedhat e klientit
├── db.php       Lidhja PDO, sesioni dhe ndihmësit e sigurisë
├── composer.json
└── composer.lock
```

## Ekzekutimi lokal me Laragon

1. Klono repository-n në dosjen e web-it të Laragon-it dhe ndiz Apache/MySQL.
2. Krijo databazën `herbabloom_db` dhe importo `database/schema.sql` përmes HeidiSQL ose klientit MySQL. Skema krijon tabelat dhe pamjen, por nuk fut të dhëna dhe nuk fshin objekte ekzistuese. **Përdor databazë të re/bosh; mos importo mbi databazë me të dhëna që dëshiron t’i ruash.**
3. Ekzekuto `composer install` për varësitë PHP.
4. Konfiguro variablat e mjedisit për procesin PHP:

   - `DB_HOST` (parazgjedhja lokale: `127.0.0.1`)
   - `DB_NAME` (parazgjedhja: `herbabloom_db`)
   - `DB_USER` dhe `DB_PASSWORD`
   - `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASSWORD`, `SMTP_FROM`

5. Hape dyqanin te `/web/index.php`, panelin te `/admin/login.php` ose API-në te `/api/`.

Në Laragon, adresat tipike janë `http://herbabloom.test/web/`, `http://herbabloom.test/admin/login.php` dhe `http://herbabloom.test/api/products`. Mund të përdoret edhe `http://localhost/herbabloom/...`, në varësi të konfigurimit lokal.

Pas importimit, konfigurimi i dyqanit në `me_cilesimet` dhe të paktën një llogari aktive administratori duhet të krijohen veçmas në mënyrë të sigurt. Skema që dorëzohet nuk krijon llogari fillestare apo fjalëkalim. OTP-ja kërkon `otp_email` të konfiguruar dhe SMTP funksional.

`db.php` ka vlera parazgjedhëse për zhvillim lokal (`root` pa fjalëkalim). Në server ose në GitHub mos vendos kredenciale në kod: cakto variablat e mjedisit dhe përdor përdorues MySQL me privilegje minimale. Mos publiko `SMTP_PASSWORD`, fjalëkalime, kode OTP, të dhëna klientësh ose kopje të databazës.

## API REST

Kërkesat me body përdorin `Content-Type: application/json`. Shkrimet e autentikuara përdorin cookie-n e sesionit dhe header-in `X-CSRF-Token`. Hyrja e stafit ka dy hapa: `POST /auth/login` nis dërgimin e OTP-së dhe `POST /auth/verify-otp` e verifikon atë.

| Metoda | Endpoint | Përmbledhja | Përgjigjet kryesore |
|---|---|---|---|
| GET | `/auth/csrf` | Merr token CSRF dhe cookie sesioni | `200` |
| POST | `/auth/login` | Username/fjalëkalim; nis hyrjen me OTP | `202`, `401`, `422`, `429`, `503` |
| POST | `/auth/verify-otp` | Verifikon kodin OTP | `200`, `401`, `422` |
| POST | `/auth/change-password` | Ndryshon fjalëkalimin e llogarisë | `200`, `401`, `422` |
| POST | `/auth/logout` | Mbyll sesionin | `200`, `401`, `419` |
| GET | `/products?q=tea&page=1&page_size=20` | Kërkon produktet publike | `200`, `422` |
| GET | `/orders?status=N%C3%AB%20pritje` | Liston porositë e stafit; kurieri sheh të caktuarat | `200`, `401`, `403`, `422` |
| POST | `/orders` | Krijon porosi me artikuj; administrator/farmacist | `201`, `401`, `403`, `404`, `409`, `422` |
| PATCH | `/orders/{id}` | Ndryshon statusin ose cakton kurier | `200`, `401`, `403`, `404`, `409`, `422` |
| DELETE | `/orders/{id}` | Anulon porosinë dhe rikthen stokun | `200`, `401`, `403`, `404`, `409`, `422` |
| GET | `/orders/track/{tracking_code}` | Gjurmim publik me kod | `200`, `404` |

Për shembujt e hyrjes, trupave JSON, përgjigjeve dhe gabimeve shih [Raportin Teknik 2](docs/raporti-teknik-2.md).

## Siguria dhe shënime për dorëzim

- Query-t e databazës përdorin PDO dhe parametrizim; daljet HTML kodohen për mbrojtje nga XSS.
- Paneli kontrollon rolin në backend; veprimet e shkrimit mbrohen me CSRF. API-ja përdor të njëjtat rregulla për sesionin/rolet dhe kërkon CSRF për shkrimet e autentikuara.
- Fjalëkalimet hash-ohen; hyrja e stafit kërkon OTP. Të ardhurat dhe shumat në pamjet e stafit/API-në e stafit shfaqen vetëm për administratorin; gjurmimi publik kthen përmbledhjen e porosisë për klientin.
- `composer.lock` versionohet. Dosja `vendor/` aktualisht është e pranishme edhe në Git; zakonisht mjafton të dorëzohen manifestet Composer dhe varësitë të instalohen me `composer install`.
- `database/schema.sql` përmban vetëm strukturën lokale të tabelave `me_` dhe pamjen e stokut; vlerat personale të parazgjedhura janë zëvendësuar me vlera neutrale.
- Para dorëzimit, kontrollo skedarët dhe commit-et në GitHub, dhe demonstro endpoint-et me një klient API. Në këtë moment repository ka vetëm një commit fillestar; historia e commit-eve nuk tregon faza të mëparshme të zhvillimit.
