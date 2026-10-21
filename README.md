# sistemi-per-menaxhimin-e-barnatoreve
Sistem për menaxhimin e barnatoreve bimore (herbale), duke përfshirë administrimin e produkteve natyrore, çajrave bimorë, barnave herbale, inventarit, shitjeve dhe furnizimeve.
# 🌸 HerbaBloom – Sistem për Menaxhimin e Barnatores Bimore

Sistem për menaxhimin e barnatoreve bimore (herbale), duke përfshirë administrimin e produkteve natyrore, çajrave bimorë, barnave herbale, inventarit, shitjeve dhe furnizimeve.

---

## RAPORTI TEKNIK 1: ANALIZA, KËRKESAT DHE DIZAJNI TEKNIK

**Titulli i Projektit:** HerbaBloom – Sistem për Menaxhimin e Barnatores Bimore  
**Studenti:** Vesa Kastrati  

---

## 1. Qëllimi dhe Definimi i Problemit

### 1.1 Konteksti i Projektit
Në tregun e sotëm të shëndetësisë dhe produkteve natyrale, farmacitë bimore po forcojnë praninë e tyre dixhitale për shkak të kërkesës në rritje për shitje online dhe dërgesa direkte te klienti. Menaxhimi manual i stokut të produkteve bimore, përpunimi i porosive përmes rrjeteve sociale pa një sistem qendror dhe mungesa e lidhjes direkte me shërbimet e transportit (kurierët) krijojnë vonesa, gabime në inventar dhe pamundësi për të pasur një pasqyrë të saktë financiare.

### 1.2 Zgjidhja e Propozuar (HerbaBloom)
Sistemi **HerbaBloom** është një platformë profesionale web e ndërtuar posaçërisht për menaxhimin e inventarit të një farmacie bimore dhe automatizimin e porosive online me dërgesë. Platforma zgjidh problemin e menaxhimit duke ofruar:
* **Menaxhim të saktë të inventarit bimor** sipas kategorive specifike (Çajra Bimorë, Tinktura & Ekstrakte, Vajra Esenciale, Kozmetikë Natyrale, Vitamina & Shtesa).
* **Përpunim të porosive online dhe menaxhim të dërgesave** duke iu caktuar çdo porosie një kurier/kompani transporti dhe status livrimi.
* **Pranim dhe trajtim të kërkesave/pyetjeve të klientëve** që vijnë nga forma e kontaktit në uebfaqen kryesore.
* **Llogaritje automatike të raporteve financiare** (shitjet sot, këtë javë, këtë muaj) dhe paralajmërim për stokun kritik.

---

## 2. Kërkesat e Softuerit (Requirements Specification)

### 2.1 Kërkesat Funksionale (Functional Requirements)
* **FR-01 (Autentikimi dhe Siguria e Hyrjes):** Sistemi duhet të mundësojë kyçjen e përdoruesve të autorizuar (Stafit/Adminit) përmes emrit të përdoruesit dhe fjalëkalimit të enkriptuar.
* **FR-02 (Menaxhimi i Inventarit të Barnave Bimore):** Përdoruesi mund të regjistrojë barna të reja duke specifikuar emrin, kategorinë bimore, çmimin, sasinë në stok dhe datën e skadencës.
* **FR-03 (Menaxhimi i Porosive Online dhe Dërgesave):** Përdoruesi mund të regjistrojë një porosi të re online me të dhënat e klientit (emri, qyteti, adresa, telefoni), të zgjedhë produktin, sasinë, t'i caktojë një kurier/kompani transporti dhe të përditësojë statusin e dërgesës (*Në Përgatitje, I Dorëzuar te Kurieri, Në Rrugë për Dorëzim, I Kryer / Dorëzuar*).
* **FR-04 (Menaxhimi i Stafit dhe Roleve):** Administratori mund të regjistrojë anëtarë të rinj të stafit (Administrator, Farmacist, Kurier) duke u ruajtur të dhënat e plota dhe fjalëkalimi i sigurt.
* **FR-05 (Pranimi i Mesazheve të Kontaktit Online):** Sistemi ruan pyetjet e dërguara nga klientët në uebfaqe dhe lejon shënimin e tyre si "Të Trajtuara".
* **FR-06 (Raportimi Financiar dhe Statistikat):** Sistemi llogarit automatikisht të ardhurat totale ditore, javore dhe mujore nga porositë e kryera, si dhe liston automatikisht produktet me stok kritik (≤5 copë).

### 2.2 Kërkesat Jofunksionale (Non-Functional Requirements)
* **NFR-01 (Siguria e të Dhënave):** Fjalëkalimet e përdoruesve nuk ruhen kurrë në tekst të thjeshtë (plain text), por enkriptohen me algoritmin e sigurt `password_hash()` (Bcrypt).
* **NFR-02 (Qëndrueshmëria e Strukturës - Dynamic Self-Healing Schema):** Baza e të dhënave kontrollon dhe përditëson automatikisht skemën e tabelave dhe kolonat e munguara gjatë ekzekutimit në PHP për të parandaluar gabimet e tipit "Unknown Column" ose "Table Not Found".
* **NFR-03 (Përdorshmëria dhe Dizajni - UI/UX):** Ndërfaqja është realizuar me një estetikë moderne Girly/Pastel Bloom me përshtatshmëri në ekranet responsive dhe navigim të qartë.
* **NFR-04 (Performanca):** Koha e ekzekutimit të kërkesave SQL dhe ngarkimit të faqeve duhet të jetë nën 200 milisekonda në ambientin lokal.

---

## 3. Dizajni Teknik dhe Arkitektura e Sistemit

### 3.1 Përzgjedhja e Teknologjive (Tech Stack) dhe Arsyetimi
* **Backend: PHP 8.x (Native)**  
  *Arsyetimi:* PHP është përzgjedhur për shkak të fleksibilitetit të lartë në integrimin me serverët ueb (Laragon-HeidiSQL), ekzekutimit të shpejtë të logjikës së biznesit dhe mundësisë për të ndërtuar një mekanizëm vetë-korrigjues për bazën e të dhënave (Self-Healing Migration Engine).
* **Database: MySQL 8.x**  
  *Arsyetimi:* Baza e të dhënave relacionale është e domosdoshme për këtë projekt sepse ekzistojnë lidhje të drejtpërdrejta strukturore midis Porosive, Produkteve Bimore, Kurierëve dhe Stafit.
* **Frontend: HTML5, CSS3, JavaScript**  
  *Arsyetimi:* Përdorimi i CSS3 të personalizuar me konceptin Glassmorphism dhe ngjyra Pastel lejon krijimin e një brendi unik pa ngarkuar aplikacionin me framework-e të rënda të jashtme.

### 3.2 Shpjegimi i Arkitekturës së Sistemit
Arkitektura e sistemit **HerbaBloom** është e ndërtuar mbi modelin tradicional me tre shtresa (Three-Tier Architecture), i cili siguron një ndarje të qartë midis ndërfaqes së përdoruesit, logjikës së biznesit dhe shtresës së ruajtjes së të dhënave. Komunikimi rrjedh në mënyrë sekuenciale përmes shtresave të mëposhtme:
* **Shtresa e Përdoruesit (Client / Presentation Layer):** Pika e nisjes së sistemit është përdoruesi ose stafi i farmacisë, i cili ndërvepron me aplikacionin përmes shfletuesit web duke dërguar kërkesa përmes formularëve ose navigimit HTTP (p.sh., përmes faqeve `dashboard.php`, `produkte.php`, `shitjet.php`, etj.).
* **Shtresa e Logjikës së Biznesit (Backend Layer):** Kërkesat e përdoruesit pranohen dhe përpunohen nga shtresa e backend-it e ndërtuar në PHP Native. Në këtë shtresë, sistemi kryen validimin e sesioneve, mbrojtjen e faqeve, zbaton rregullat e logjikës së biznesit duke përdorur *Prepared Statements* për siguri, dhe aktivizon motorin dinamik vetë-korrigjues (`db.php`) që kontrollon dhe përditëson strukturën e skemës në kohë reale.
* **Shtresa e Ruajtjes së Të Dhënave (Database Layer):** Për të ruajtur dhe lexuar informacionin, backend-i komunikon direkt me serverin e bazës së të dhënave MySQL përmes portit standard 3306, duke ekzekutuar kërkesa SQL mbi tabelat kryesore relacionale si `me_perdoruesit`, `produkte`, `porosite`, `kurieret`, etj., për të kthyer më pas rezultatin e përpunuar te përdoruesi.

---

## 4. Modeli i Bazës së Të Dhënave (ERD / Database Schema)

Baza e të dhënave **`herbabloom_db`** përbëhet nga 6 tabela kryesore relacionale:
* **`me_perdoruesit`**: Ruajnë të dhënat e stafit (`id`, `emri`, `email`, `username`, `fjalekalimi`, `rooli`, `data_krijimit`).
* **`produkte`**: Ruajnë stokun e barnave bimore (`id`, `emri`, `kategoria`, `cmimi`, `sasia`, `skadenca`, `data_krijimit`).
* **`kurieret`**: Ruajnë kompanitë e transportit/kurierët (`id`, `emri_kurierit`, `kompania`, `telefoni`).
* **`porosite`**: Ruajnë dërgesat online duke lidhur Foreign Keys te `produkt_id` dhe `kurier_id` (`id`, `klienti`, `qyteti`, `adresa`, `telefoni`, `produkt_id`, `sasia`, `total_cmimi`, `kurier_id`, `statusi_dergeses`, `data_porosise`).
* **`kontaktet`**: Ruajnë pyetjet/mesazhet nga uebfaqja (`id`, `emri_klientit`, `email`, `subjekti`, `mesazhi`, `lexuar`, `data_dergimit`).
* **`cilesimet`**: Ruajnë parametrat e sistemit (`id`, `celesi`, `vlera`).

---

## 5. Plani i Punës dhe Progresi i Pritshëm për Raportin 2

* **Faza e Raportit 1 (Statusi Aktual):** U realizua analiza e kërkesave, dizajni teknik, arkitektura e sistemit, modeli i bazës së të dhënave dhe skedari bazë `db.php` me vetë-krijim të skemës.
* **Objektivat për Raportin 2 (Database, Backend & API):** Përfundimi i plotë i logjikës së backend-it, implementimi i rreptë i SQL Prepared Statements për parandalimin e SQL Injection, validimi i inputeve, testimi i thirrjeve të bazës së të dhënave me të dhëna reale dhe dokumentimi i dërgesave me kurierë.
