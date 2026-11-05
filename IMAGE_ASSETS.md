# HerbaBloom — manifesti i imazheve lokale

Imazhet dhe videot e përdorura nga dyqani ruhen në `web-images/`; faqet përdorin hartëzim nga slug-u ekzistues te skedari dhe nuk ndryshojnë emrat/slugs në databazë. Kur mungon një aset i hartëzuar, faqja shfaq placeholder. Për produkte dhe oferta mund të ruhen gjithashtu URL imazhesh përmes panelit; në instalimin lokal URL-të me `localhost` funksionojnë vetëm në atë kompjuter dhe duhen zëvendësuar me URL publike gjatë deployment-it. Disa URL të ruajtura në panel drejtojnë te skedarë lokalë të shkarkuar në `web-images/`.

## Shënim për përdorimin e aseteve

Ky manifest regjistron skedarët që përdoren në instalimin lokal; dosja `web-images/` përjashtohet nga GitHub dhe manifesti nuk dokumenton lejen e shpërndarjes për secilin aset. Në një instalim tjetër, shto vetëm skedarë që ke të drejtë t'i shpërndash.

## Sfondi, identiteti dhe seksionet

| Skedari | Përdorimi | Gjendja aktuale |
|---|---|---|
| `background-image.jpg` | Sfondi i përbashkët i të gjitha faqeve publike | Ekziston |
| `herbabloom-favicon.svg` | Ikona lokale e shfletuesit | Krijuar në projekt |
| `barna-dhe-medikamente.jpg` | Kategoria me slug `barna-dhe-medikamente` | Ekziston |
| `kremra-dhe-salca-bimore.jpg` | Kategoria me slug `kremra-salca-bimore` | Ekziston |
| `vajra-esenciale.jpg` | Kategoria me slug `vajra-esenciale` | Ekziston |
| `qajra-bimore.jpg` | Kategoria me slug `cajra-bimore` | Ekziston |
| `banner-natural-care.jpg` | Baneri i kujdesit bimor në ballinë | Ekziston |
| `about-herbabloom.jpg` | Fotografia e seksionit “Rreth nesh” | Ekziston |
| `hero-slide-1.mp4` | Videoja e slide-it të parë | Ekziston |
| `hero-slide-2.mp4` | Videoja e slide-it të dytë | Ekziston |
| `hero-slide-3.mp4` | Videoja e slide-it të tretë | Ekziston |

Slugu i kategorisë `cajra-bimore` ruhet siç është; vetëm emri i skedarit përdor formën e kërkuar `qajra-bimore.jpg`.

## Imazhet e seteve dhe ofertave

| Slugu ekzistues i ofertës | Oferta në katalog | Skedari i kërkuar | Gjendja aktuale |
|---|---|---|---|
| `set-kujdesi-i-lekures` | Set kujdesi për lëkurën | `offer-set-kujdesi-i-lekures.jpg` | Ekziston |
| `set-bime-dhe-vitamina` | Përzgjedhje bimore dhe vitamina | `offer-set-bime-dhe-vitamina.jpg` | Ekziston |
| `set-caj-dhe-vitamina` | Set çaj dhe vitamina | `offer-set-caj-dhe-vitamina.jpg` | Ekziston |
| `set-perzgjedhje-herbabloom` | Përzgjedhje HerbaBloom | `offer-set-perzgjedhje-herbabloom.jpg` | Ekziston |
| `set-kujdes-i-perditeshem` | Set kujdesi i përditshëm | `offer-set-kujdes-i-perditeshem.jpg` | Ekziston |
| `set-caj-dhe-arome` | Set çaj dhe aromë | `offer-set-caj-dhe-arome.jpg` | Ekziston |
| `set-i-kompletuar-i-vitaminave` | Set i kompletuar i vitaminave | `set-vitaminat-esenciale.jpg` | URL lokale e ruajtur në panel; skedari ekziston |

Çmimet dhe përbërja lexohen nga katalogu ekzistues; fotografitë nuk përcaktojnë çmime apo zbritje.

## Manifesti i fotografive të produkteve

Çdo rresht lidh slugu real me imazhin e përdorur. Për disa produkte, skedari vendoset si URL nga paneli dhe jo përmes hartëzimit automatik; emri i produktit në katalog ruhet pa ndryshim.

| Slugu i produktit | Emri në katalog | Skedari i kërkuar |
|---|---|---|
| `aspirin` | Aspirin | `Aspirin.jpg` — URL e ruajtur në panel |
| `echinacea` | Echinacea | `Echinacea.jpg` |
| `ginkgo-biloba` | Ginkgo Biloba | `Ginkgo Biloba.jpg` |
| `ginseng-panax` | Ginseng (Panax) | `Ginseng (Panax).jpg` |
| `shafrani-i-indise-turmeric` | Shafrani i Indisë (Turmeric) | `Turmeric.jpg` |
| `silymarin-milk-thistle` | Silymarin (Milk Thistle) | `Silymarin (Milk Thistle).jpg` |
| `valeriana` | Valeriana | `Valerian.jpg` |
| `krifsa-e-shengjergjit` | Krifsa e Shëngjergjit | `St. John’s Wort.jpg` |
| `hudhra-ne-kapsula` | Hudhra në kapsula | `Garlic Capsules.jpg` |
| `saw-palmetto` | Saw Palmetto | `Saw Palmetto.jpg` |
| `ashwagandha` | Ashwagandha | `Ashwagandha.jpg` |
| `vaji-i-primales-evening-primrose` | Vaji i Primalës (Evening Primrose) | `Evening Primrose Oil.jpg` |
| `glukomanan` | Glukomanan | `Glucomannan.jpg` |
| `boronica-e-kuqe-cranberry` | Boronica e Kuqe (Cranberry) | `Cranberry.jpg` |
| `pelini-i-zi` | Pelini i Zi | `Black Wormwood.jpg` |
| `xhinxher-ne-kapsula` | Xhinxher në kapsula | `Ginger Capsules.jpg` |
| `macca-root` | Macca Root | `Maca Root.jpg` |
| `aloe-vera-xhel-oral` | Aloe Vera xhel (oral) | `Aloe Vera Gel (Oral).jpg` |
| `fenugreek` | Fenugreek | `Fenugreek.jpg` |
| `cascara-sagrada` | Cascara Sagrada | `Cascara Sagrada.jpg` |
| `bromelain` | Bromelain | `Bromelain.jpg` |
| `melatonina` | Melatonina | `Melatonin.jpg` |
| `magnezum-citrat` | Magnezum Citrat | `Magnesium Citrate.jpg` |
| `kompleksi-i-vitaminave-b` | Kompleksi i Vitaminave B | `Vitamin B Complex.jpg` |
| `vitamina-d3` | Vitamina D3 | `Vitamin D3.jpg` |
| `vitamina-c-me-zinc` | Vitamina C me Zinc | `Vitamin C with Zinc.jpg` |
| `omega-3-vaj-peshku` | Omega-3 (Vaj Peshku) | `Omega-3 (Fish Oil).jpg` |
| `koenzima-q10-coq10` | Koenzima Q10 (CoQ10) | `Coenzyme Q10 (CoQ10).jpg` |
| `probiotiket` | Probiotikët | `Probiotics.jpg` |
| `5-htp` | 5-HTP | `5-HTP.jpg` |
| `l-theanine` | L-Theanine | `L-Theanine.jpg` |
| `vitamina-e` | Vitamina E | `Vitamin E.jpg` |
| `vitamin-a` | Vitamina A | `Vitamin A.jpg` — URL e ruajtur në panel |
| `hekur-bisglicinat` | Hekur Bisglicinat | `Iron Bisglycinate.jpg` |
| `kalcium-me-magnez-dhe-zink` | Kalcium me Magnez dhe Zink | `Calcium with Magnesium and Zinc.jpg` |
| `glukozamine-dhe-kondroitine` | Glukozaminë dhe Kondroitinë | `Glucosamine and Chondroitin.jpg` |
| `kollagjen-hidrolizuar` | Kollagjen Hidrolizuar | `Hydrolyzed Collagen.jpg` |
| `acid-folik-vitamina-b9` | Acid Folik (Vitamina B9) | `Folic Acid (Vitamin B9).jpg` |
| `kollostrum-kulloshter` | Kollostrum (Kulloshtër) | `Colostrum.jpg` |
| `l-carnitine` | L-Carnitine | `L-Carnitine.jpg` |
| `spirulina` | Spirulina | `Spirulina.jpg` |
| `pasiflora` | Pasiflora | `Passionflower.jpg` |
| `caj-kamomili` | Çaj Kamomili | `Chamomile Tea.jpg` |
| `caj-mente` | Çaj Mente | `Peppermint Tea.jpg` |
| `caj-mali` | Çaj Mali | `Mountain Tea.jpg` |
| `caj-bliri` | Çaj Bliri | `Linden Tea.jpg` |
| `caj-sherbele` | Çaj Sherbele | `Sage Tea.jpg` |
| `caj-uthi-hithre` | Çaj Uthi (Hithre) | `Nettle Tea.jpg` |
| `caj-gjembgomari` | Çaj Gjembgomari | `Milk Thistle Tea.jpg` |
| `caj-trendafili-te-eger` | Çaj Trëndafili të Egër | `Rosehip Tea.jpg` |
| `caj-xhinxheri-me-limon` | Çaj Xhinxheri me Limon | `Ginger and Lemon Tea.jpg` |
| `caj-hibiskusi` | Çaj Hibiskusi | `Hibiscus Tea.jpg` |
| `caj-anasi` | Çaj Anasi | `Anise Tea.jpg` |
| `caj-finoku` | Çaj Finoku | `Fennel Tea.jpg` |
| `caj-flete-ulliri` | Çaj Fletë Ulliri | `Olive Leaf Tea.jpg` |
| `caj-flete-boronice` | Çaj Fletë Boronice | `Bilberry Leaf Tea.jpg` |
| `caj-rrenje-luleradhiqe` | Çaj Rrënjë Luleradhiqe | `Dandelion Root Tea.jpg` |
| `caj-flete-mjedre` | Çaj Fletë Mjedre | `Raspberry Leaf Tea.jpg` |
| `caj-matcha` | Çaj Matcha | `Matcha Tea.jpg` |
| `caj-rooibos` | Çaj Rooibos | `Rooibos Tea.jpg` |
| `caj-trumze` | Çaj Trumze | `Thyme Tea.jpg` |
| `caj-flete-senna` | Çaj Fletë Senna | `Senna Leaf Tea.jpg` |
| `vaj-esencial-lavande` | Vaj Esencial Lavande | `Lavender Essential Oil.jpg` |
| `vaj-esencial-te-pema-e-cajit` | Vaj Esencial te Pema e Çajit | `Tea Tree Essential Oil.jpg` |
| `vaj-esencial-mente` | Vaj Esencial Mente | `Peppermint Essential Oil.jpg` |
| `vaj-esencial-eukalipti` | Vaj Esencial Eukalipti | `Eucalyptus Essential Oil.jpg` |
| `vaj-esencial-limoni` | Vaj Esencial Limoni | `Lemon Essential Oil.jpg` |
| `vaj-esencial-rozmarine` | Vaj Esencial Rozmarine | `Rosemary Essential Oil.jpg` |
| `vaj-esencial-portokalli` | Vaj Esencial Portokalli | `Orange Essential Oil.jpg` |
| `vaj-esencial-frankincense` | Vaj Esencial Frankincense | `Frankincense Essential Oil.jpg` |
| `vaj-esencial-ylang-ylang` | Vaj Esencial Ylang-Ylang | `Ylang-Ylang Essential Oil.jpg` |
| `vaj-esencial-oregano` | Vaj Esencial Oregano | `Oregano Essential Oil.jpg` |
| `vaj-esencial-jasmine` | Vaj Esencial Jasmine | `Jasmine Essential Oil.jpg` |
| `vaj-esencial-grapefruit` | Vaj Esencial Grapefruit | `Grapefruit Essential Oil.jpg` |
| `vaj-esencial-vetiver` | Vaj Esencial Vetiver | `Vetiver Essential Oil.jpg` |
| `vaj-esencial-karafili` | Vaj Esencial Karafili | `Clove Essential Oil.jpg` |
| `vaj-esencial-cypress` | Vaj Esencial Cypress | `Cypress Essential Oil.jpg` |
| `vaj-esencial-marjoram` | Vaj Esencial Marjoram | `Marjoram Essential Oil.jpg` |
| `vaj-esencial-myrrh` | Vaj Esencial Myrrh | `Myrrh Essential Oil.jpg` |
| `vaj-esencial-spearmint` | Vaj Esencial Spearmint | `Spearmint Essential Oil.jpg` |
| `vaj-esencial-citronella` | Vaj Esencial Citronella | `Citronella Essential Oil.jpg` |
| `vaj-esencial-wintergreen` | Vaj Esencial Wintergreen | `Wintergreen Essential Oil.jpg` |
| `krem-me-aloe-vera` | Krem me Aloe Vera | `Aloe Vera Cream.jpg` |
| `krem-me-calendula` | Krem me Calendula | `Calendula Cream.jpg` |
| `krem-me-arnica` | Krem me Arnica | `Arnica Cream.jpg` |
| `krem-me-acid-hialuronik` | Krem me Acid Hialuronik | `Hyaluronic Acid Cream.jpg` |
| `krem-me-retinol` | Krem me Retinol | `Retinol Cream.jpg` |
| `krem-me-vitamine-c` | Krem me Vitaminë C | `Vitamin C Cream.jpg` |
| `krem-niacinamide` | Krem Niacinamide | `Niacinamide Cream.jpg` |
| `krem-me-propolis` | Krem me Propolis | `Propolis Cream.jpg` |
| `krem-me-centella-asiatica-cica` | Krem me Centella Asiatica (Cica) | `Centella Asiatica (Cica) Cream.jpg` |
| `krem-me-gjalpe-shea` | Krem me Gjalpë Shea | `Shea Butter Cream.jpg` |
| `krem-me-ceramide` | Krem me Ceramide | `Ceramide Cream.jpg` |
| `krem-me-acid-salicilik` | Krem me Acid Salicilik | `Salicylic Acid Cream.jpg` |
| `krem-me-bakuchiol` | Krem me Bakuchiol | `Bakuchiol Cream.jpg` |
| `krem-me-azelaic-acid` | Krem me Azelaic Acid | `Azelaic Acid Cream.jpg` |
| `krem-me-peptide` | Krem me Peptide | `Peptide Cream.jpg` |
| `krem-me-panthenol` | Krem me Panthenol | `Panthenol Cream.jpg` |
| `krem-me-vaj-neem` | Krem me Vaj Neem | `Neem Oil Cream.jpg` |
| `krem-me-zinc-oxide` | Krem me Zinc Oxide | `Zinc Oxide Cream.jpg` |
| `krem-me-ure` | Krem me Ure | `Urea Cream.jpg` |
| `krem-me-ekstrakt-kafeine` | Krem me Ekstrakt Kafeine | `Caffeine Extract Cream.jpg` |

## Krahasimi me katalogun aktual

Katalogu publik u kontrollua në 9 faqe më 5 nëntor 2026: 102 produkte, katër kategori aktive dhe shtatë oferta aktive. Manifesti mbulon 102 produktet; dy rreshtat për Aspirin dhe Vitaminë A tregojnë skedarët lokalë që përdoren në URL-të e ruajtura në panel. Nga ofertat aktive, gjashtë janë seed-et bazë dhe një ofertë shtesë ka imazhin e vet të ruajtur në panel. Nuk u krijua produkt apo kategori gjatë kontrollit.

Emërtimi `Macca Root` në katalog ka një `c` shtesë krahasuar me `Maca Root.jpg`; harta përdor skedarin e kërkuar për slugu ekzistues `macca-root`, por kjo ndryshore drejtshkrimore shënohet për verifikim. Emrat e tjerë janë përputhur sipas emrit të produktit në katalog dhe barasvlerësit të dhënë në listë.

Skedarët e listuar për kategoritë, produktet, ofertat, banerin dhe seksionin “Rreth nesh” gjenden aktualisht në `web-images/`. Kontrolli i ekzistencës nuk verifikon që fotografia paraqet saktësisht produktin përkatës apo që ka leje ripërdorimi.

Përshkrimet e ruajtura për produktet nuk shfaqen në kartela ose në faqen e detajeve, sepse katalogu aktual përmban pretendime shëndetësore të paverifikuara. Të dhënat nuk u ndryshuan; në vend të tyre faqja udhëzon klientin te etiketa dhe te profesionisti shëndetësor.

Për çdo fotografi verifiko produktin e paraqitur dhe të drejtën e përdorimit; mos paraqit një produkt tjetër si fotografi zyrtare. Informacioni për produktet duhet të mbështetet te etiketa dhe burimet e verifikuara, pa premtime mjekësore të paverifikuara.
