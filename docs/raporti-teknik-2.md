# Raporti Teknik 2 – Databaza, backend-i dhe API

**Projekti:** HerbaBloom – Sistemi për menaxhimin e barnatores bimore
**Studentja:** Vesa Kastrati

## 1. Databaza dhe lidhja me PHP

PHP inicializon lidhjen te `db.php` me PDO:

1. Host-i, emri i databazës dhe përdoruesi lexohen nga variablat `DB_HOST`, `DB_NAME`, `DB_USER` dhe `DB_PASSWORD`.
2. PDO hap lidhjen `mysql:...;charset=utf8mb4`, kthen rezultatet si asociative dhe çaktivizon prepared statements emuluese.
3. Variablat e kërkesës lidhen me `prepare()`/`execute()`; faqet e stafit, storefront-i dhe API-ja përdorin të njëjtin objekt `$pdo`.
4. Gabimi i lidhjes regjistrohet në logun e serverit dhe klientit i kthehet përgjigje e përgjithshme `503`, pa detaje të brendshme.

Sekuenca e porosisë API:

```text
POST /api/orders
  -> rutimi i api/index.php
  -> verifikimi i CSRF, sesionit dhe rolit
  -> validimi i klientit dhe artikujve
  -> transaksion MySQL
  -> INSERT porosi + porosi_items
  -> zbritje nga batch-et e vlefshme + INSERT lëvizjet e stokut
  -> COMMIT dhe përgjigjja JSON 201
```

Në mungesë stoku ose gabim gjatë ruajtjes, transaksioni anulohet. Anulimi kërkon lëvizjet fillestare të shitjes, rikthen sasinë në batch-et origjinale dhe regjistron lëvizje `anullim`.

### Entitetet dhe kufizimet kryesore

Databaza lokale përmban tabelat `me_audit_log`, `me_cilesimet`, `me_kategorite`, `me_login_tentativa`, `me_mesazhet`, `me_njoftimet`, `me_njoftimet_lexuara`, `me_otp_kodet`, `me_perdoruesit`, `me_porosi_items`, `me_porosi_statuset`, `me_porosite_online`, `me_produktet`, `me_stoku_batch`, `me_stoku_levizjet` dhe pamjen `me_produktet_stoku`.

Lidhjet kryesore të deklaruara janë:

- Kategoria → produktet; produkti → batch-et.
- Porosia → artikujt dhe historiku i statuseve.
- Përdoruesi → porositë si kurier (opsional).
- Artikulli i porosisë → produkti (opsional për ruajtjen e historisë nëse produkti hiqet).
- Batch-i dhe produkti → lëvizjet e stokut.
- Njoftimi/përdoruesi → rreshtat e leximit; përdoruesi → kodet OTP.

Skema vendos unikësi për `sku`, `slug`, `username`, `kodi_gjurmimit` dhe çiftin produkt/batch. Ka gjithashtu kufizime për çmim jo-negativ dhe sasi pozitive në artikujt e porosisë. `me_produktet_stoku` llogarit `stoku_aktual`, `skadenca_me_e_afert` dhe `statusi_stokut` nga batch-et që nuk kanë skaduar.

> `database/schema.sql` është versioni i pastër DDL i strukturës lokale: përmban tabelat dhe pamjen, por asnjë rresht me të dhëna aplikacioni. Vlerat personale të parazgjedhura janë zëvendësuar me vlera neutrale. Ky skedar nuk krijon llogari fillestare apo konfigurimin e dyqanit. Versioni fillestar i databazës nuk gjendet në repository, prandaj ndryshimet historike nuk mund të krahasohen.

## 2. Backend-i dhe API REST

API-ja gjendet te `api/index.php`; `api/.htaccess` përcjell URL-të te ruteri. Përgjigjet janë JSON dhe përdorin format të njëtrajtshëm gabimi. API-ja përdor sesionin PHP dhe nuk ka nevojë për tabela të veçanta API.

| Metoda/endpoint | Hyrja kryesore | Suksesi | Gabime të rëndësishme |
|---|---|---|---|
| `GET /api/auth/csrf` | Pa body | `200`, token CSRF dhe cookie sesioni | `500` |
| `POST /api/auth/login` | `{"username":"...","password":"..."}` | `202`, kërkohet OTP | `401`, `422`, `429`, `503` |
| `POST /api/auth/verify-otp` | `{"otp_code":"123456"}` | `200`, sesioni i stafit | `401`, `422` |
| `POST /api/auth/change-password` | Fjalëkalimi aktual, i ri dhe konfirmimi | `200` | `401`, `422` |
| `POST /api/auth/logout` | Cookie + `X-CSRF-Token` | `200` | `401`, `419` |
| `GET /api/products?q=tea&category=herbal&page=1&page_size=20` | Filtra opsionalë | `200`, produkte dhe faqezim | `422` |
| `GET /api/orders?status=N%C3%AB%20pritje` | Sesion stafi; status opsional | `200`; kurieri sheh vetëm porositë e caktuara | `401`, `403`, `422` |
| `POST /api/orders` | Të dhënat e klientit dhe `items` | `201`; admin/farmacist | `401`, `403`, `404`, `409`, `422` |
| `PATCH /api/orders/{id}` | `{"status":"Në përgatitje"}` ose `{"courier_id":3}` | `200` | `401`, `403`, `404`, `409`, `422` |
| `DELETE /api/orders/{id}` | `{"cancellation_reason":"..."}` | `200`, anulim dhe kthim stoku | `401`, `403`, `404`, `409`, `422` |
| `GET /api/orders/track/{tracking_code}` | Kod publik i gjurmimit | `200` | `404` |

Shkrimet e autentikuara kërkojnë cookie-n e sesionit dhe header-in `X-CSRF-Token`. Body-t përdorin `Content-Type: application/json`. Për të krijuar porosi, roli duhet të jetë `admin` ose `farmacist`; dërgesat e kurierit kufizohen te porositë e caktuara. Shumat financiare në endpoint-et e stafit përfshihen vetëm për `admin`.

### Shembull kërkese për porosi

```http
POST /api/orders
Content-Type: application/json
X-CSRF-Token: <token>
Cookie: herbabloom_session=<sesion>
```

```json
{
  "customer": "Klient Testues",
  "email": "klienti@example.com",
  "phone": "044000000",
  "city": "Prizren",
  "address": "Rruga Testuese",
  "items": [
    {"product_id": 1, "quantity": 2},
    {"product_id": 2, "quantity": 1}
  ]
}
```

Përgjigjja e pritshme pas krijimit është `201 Created` me `success: true`, identifikuesin e porosisë, kodin e gjurmimit dhe statusin fillestar. Përgjigjja përmban shuma vetëm kur përdoruesi është administrator.

### Validimi dhe trajtimi i gabimeve

- Pa sesion në endpoint privat: `401 AUTHENTICATION_REQUIRED`.
- Roli nuk lejohet: `403 FORBIDDEN`.
- Produkti/porosia nuk ekziston: `404 PRODUCT_NOT_FOUND` ose `404 ORDER_NOT_FOUND`.
- Të dhëna të pavlefshme, si `page=0` ose artikull pa sasi: `422 VALIDATION_ERROR`.
- Stoku nuk mjafton ose statusi i porosisë nuk lejon kalimin: `409`.
- Mungon ose është i pavlefshëm token-i CSRF: `419`.

Shembull real i formatit JSON për porosi që nuk ekziston:

```http
HTTP/1.1 404 Not Found
Content-Type: application/json
```

```json
{
  "success": false,
  "error": "ORDER_NOT_FOUND",
  "message": "Order does not exist."
}
```

Shembull për validim të faqëzimit (`GET /api/products?page=0`):

```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "page must be positive and page_size must be between 1 and 100."
}
```

## 3. Demonstrimi dhe verifikimi i kryer

Në ambientin lokal Laragon janë verifikuar përmes HTTP:

- `GET /api/products?page=1&page_size=2` → `200` dhe JSON me produktet/faqëzimin.
- `GET /api/orders` pa sesion → `401`.
- Gjurmimi me kod që nuk ekziston → `404 ORDER_NOT_FOUND`.
- `GET /api/products?page=0` → `422 VALIDATION_ERROR`.
- Lint sintaksik i 23 skedarëve PHP → kaloi.
- Kontrolli i qasjes së farmacistit: vlerat financiare mungojnë në porositë API dhe kolonat e totalit nuk shfaqen në porositë aktive/historike.

Këto kërkesa nuk krijuan porosi dhe nuk ndryshuan të dhënat e databazës. Rrjedha reale OTP dhe krijimi/anulimi i porosisë duhet të demonstrohen me një llogari testuese dhe mjedis SMTP/databazë të konfiguruar; nuk duhen përdorur të dhënat e klientëve realë në prezantim.

## 4. Çështje teknike dhe mënyra e zgjidhjes

- **Stoku në lote:** sasitë nuk ruhen si një numër i vetëm në produkt; llogariten nga batch-et e vlefshme. Rezervimi ndjek skadencën më të afërt dhe përdor transaksione për të shmangur shitjen mbi stokun.
- **Anulimi:** rikthimi bëhet te batch-et ku ishte zbritur stoku, jo si shtim i përgjithshëm i pasaktë; në rast gabimi transaksioni anulohet.
- **API dhe faqet përdorin të njëjtën logjikë:** ruteri REST përdor funksionet e përbashkëta të autentikimit/auditimit dhe të inventarit, duke shmangur një gjendje tjetër të stokut.
- **Krahasimi i skemës:** DDL-ja aktuale është shtuar te `database/schema.sql`; ende duhet verifikuar importimi në databazë të re. Llogaria fillestare e administratorit dhe cilësimet e dyqanit duhen konfiguruar veçmas, pa fjalëkalime të shpërndara në GitHub.

## 5. Përgatitja për prezantim

1. Hape databazën dhe trego tabelat, çelësat dhe lidhjet e diagramit.
2. Hape `db.php` dhe shpjego PDO, parametrizimin dhe variablat e mjedisit.
3. Në klient API, kërko produktet dhe trego përgjigjen `200`.
4. Kërko porosi pa sesion dhe trego `401`; kërko gjurmim të panjohur dhe trego `404`; dërgo `page=0` dhe trego `422`.
5. Me llogari testuese, demonstro OTP dhe rrjedhën e porosisë pa ndryshuar të dhëna reale.

Udhëzuesi kërkon demonstrim real, skemë të versionuar dhe histori Git që tregon progres. Skema tani është e versionuar, por duhet verifikuar importimi i saj në databazë të re; repository aktual ka një commit fillestar dhe nuk tregon progresin në histori.
