# WOS Widget / Q-Quartile Finder

**Aktuální verze:** `1.1.0`

Webová aplikace a doprovodné prohlížečové rozšíření pro rychlé zjištění kvartilu časopisu a souvisejících bibliometrických metrik. Projekt vyhledává DOI nebo textový dotaz, dohledá záznam přes OpenAlex a následně jej doplní o metriky z Clarivate Web of Science Journals API.

## Co projekt umí

- vyhledat článek podle DOI nebo textového dotazu
- dohledat časopis, ISSN, rok publikace a rok metrik
- zobrazit kvartil `Q1-Q4` a další metriky jako `JIF`, `JCI`, `Article Influence` nebo `EigenFactor`
- uložit uživatelské preference, které metriky se mají zobrazovat
- přihlašovat uživatele přes LDAP a vydávat tokeny s expirací a serverovým odvoláním
- ukládat odpovědi externích služeb do sdílené MySQL/MariaDB cache
- obsloužit Chrome/Chromium rozšíření, které umí číst DOI ze stránky a spouštět hledání z kontextového menu i ručně v popupu

## Použité technologie

- PHP
- Composer
- Dibi
- Doctrine ORM
- Latte
- Tracy
- PHPMailer
- PhpSpreadsheet
- JavaScript pro browser extension

## Požadavky

- PHP 8.2.x (aktuální `composer.lock`: Symfony vyžaduje alespoň 8.2, Latte méně než 8.3)
- Composer
- MySQL nebo MariaDB
- PHP rozšíření `curl`
- PHP rozšíření `json`
- PHP rozšíření `ldap`
- PHP rozšíření `pdo_mysql`
- PHP rozšíření `mysqli` (Dibi/Doctrine)
- právo zápisu do `cache` a `tmp`
- přístup k OpenAlex API; pro nasazení doporučený `OPENALEX_API_KEY`
- platný `WOS_API_KEY`
- platný `WOS_JOURNALS_API_KEY`
- dostupný institucionální LDAP server pro přihlášení rozšíření
- aktuální Chrome/Chromium nebo Edge; manifest deklaruje minimum Chrome 103
- další PHP rozšíření podle `composer.lock`; ověřte pomocí `composer check-platform-reqs`

## Instalace

0. Připravte potřebné externí přístupy a přihlašovací údaje. Ještě před instalací by měla instituce komunikovat s Clarivate ohledně přístupu k Web of Science API a získat 2 API klíče, které tento projekt používá. Viz [Přístup ke Clarivate API](#přístup-ke-clarivate-api).
1. Naklonujte repozitář do webového rootu, například `D:\xampp\htdocs\school\widget`.
2. Nainstalujte PHP závislosti:

```bash
composer install
composer check-platform-reqs
```

3. Volitelně nainstalujte Node závislosti pro vývoj rozšíření:

```bash
npm install
```

4. Vytvořte lokální konfigurační soubor `config/config.php`.
5. Ověřte, že webserver směruje požadavky na `index.php`.
6. Ověřte, že aplikace může zapisovat do `cache/` a `tmp/`.
7. Naimportujte databázové schéma ze souboru `database/schema.sql`.
8. Připravte nebo ověřte hodnoty v `config/config.php`, aby mířily na vytvořenou databázi.

## Konfigurace

Soubor `config/config.php` je lokální a je ignorovaný Gitem. Podle aktuálního kódu projekt očekává alespoň tyto konstanty:

- `CONNECT` pro databázové připojení Dibi a Doctrine
- `LOCALE` pro zapnutí lokálního debug baru
- `DB_HOST`
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_DATABASE`
- `DB_PORT` (volitelně; výchozí 3306)
- `OPENALEX_API_KEY` (volitelně v kódu, doporučený pro provoz)
- `WOS_API_KEY`
- `WOS_JOURNALS_API_KEY`

Minimální kostra může vypadat takto:

```php
<?php

define('LOCALE', false);

define('CONNECT', [
    'driver' => 'mysqli',
    'host' => '127.0.0.1',
    'username' => 'widget_user', // Dibi
    'user' => 'widget_user',     // Doctrine
    'password' => '',
    'database' => 'widget',     // Dibi
    'dbname' => 'widget',       // Doctrine
    'port' => 3306,
    'charset' => 'utf8mb4',
]);

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_USERNAME', 'widget_user');
define('DB_PASSWORD', '');
define('DB_DATABASE', 'widget');
// PDO in the token and cache classes uses utf8mb4 directly.

define('OPENALEX_API_KEY', ''); // https://openalex.org/settings/api
define('WOS_API_KEY', 'YOUR_WOS_API_KEY');
define('WOS_JOURNALS_API_KEY', 'YOUR_WOS_JOURNALS_API_KEY');
```

Tyto hodnoty jsou pouze ilustrační. Skutečné přihlašovací údaje a API klíče musí odpovídat vašemu prostředí.

`CONNECT` předávají Dibi i Doctrine, proto příklad obsahuje obě sady názvů parametrů. `DB_*` používají samostatná PDO připojení pro tokeny a cache; musí mířit na tutéž databázi. `DB_CHARSET` nynější kód nečte. Nastavení cache a důvěryhodných certifikátů je uvedeno níže přímo v README; samostatné vzorové konfigurační soubory nejsou potřeba.

## Přístup ke Clarivate API

Tento projekt závisí na 2 Clarivate API klíčích:

- `WOS_API_KEY`
- `WOS_JOURNALS_API_KEY`

Podle oficiálního Clarivate Developer Portalu vyžaduje přístup k Web of Science API placenou licenci, používá přístup přes API klíče a dostupnost závisí na předplatném a smluvním nastavení dané instituce. V praxi to znamená, že projekt nelze plnohodnotně nasadit, dokud vaše instituce nevykomunikuje s Clarivate potřebný přístup v rámci institucionální licence. Zdroj: [Clarivate Developer Portal - Web of Science API Expanded](https://developer.clarivate.com/apis/wos).

Doporučený postup:

- ověřit, že má instituce aktivní předplatné Web of Science
- kontaktovat Clarivate nebo institucionální knihovnu / správce licence
- požádat o přístup k API potřebným pro tuto aplikaci
- získat a bezpečně uložit oba API klíče
- nastavit `WOS_API_KEY` a `WOS_JOURNALS_API_KEY` v `config/config.php`

## Nastavení databáze

Repozitář teď obsahuje SQL schéma v `database/schema.sql`.

Příklad importu:

```bash
mysql -u root -p < database/schema.sql
```

Skript vytvoří:

- databázi `widget`
- tabulku `tUser`
- tabulku `tSettings`
- tabulku `tApiToken` pro životní cyklus relací
- tabulku `tLookupCache` pro sdílené externí odpovědi
- cizí klíč `tSettings.FK_userID -> tUser.id`

`tSettings.FK_userID` je zároveň unikátní, protože aktuální logika aplikace počítá s jedním záznamem nastavení na jednoho uživatele.

## Datový tok

1. Přihlášený uživatel vybere text přes kontextové menu nebo odešle název/DOI z popupu.
2. `background.js` předá hledání do `client.js`, který připojí bearer token a zavolá backend.
3. `presenters/api.php` ověří token i aktivní účet a získá záznam ze sdílené cache nebo OpenAlex.
4. Z výsledku přečte ISSN a rok publikace; dotazy podle názvu používají první výsledek OpenAlex, bez dalšího ověření shody.
5. Odpovědi Clarivate získá z cache nebo přes `UpstreamHttp.php`; podle dostupnosti postupně zkouší kandidátní roky.
6. Vrátí metriky a aplikuje aktuální nastavení uživatele. Pole `isWoS` označuje dostupnost metrik časopisu, nikoli potvrzení indexace konkrétního článku.

## Hlavní URL a endpointy

- `/` výchozí vstup aplikace; aktuálně přesměrovává do administrace
- `/user/signIn` přihlašovací stránka
- `/user/login` zpracování přihlášení
- `/admin` administrace uživatelských nastavení zobrazovaných metrik
- `/admin/saveSettings` uloží nebo resetuje nastavení
- `POST /ajax/logIn` JSON přihlášení pro browser extension
- `/ajax/userSettings` vrátí uložená nastavení s hlavičkou `Authorization: Bearer <token>`
- `POST /ajax/revokeToken` odvolá předaný token
- `GET /presenters/api.php?q=...` ověřený vstup vyhledávání s hlavičkou `Authorization: Bearer <token>`; podporuje také `OPTIONS`

Adresy jsou relativní k URL nasazení. Rozšíření používá `https://imitweby.uhk.cz/widget/`. Alias `/api` není výslovně definován v dodaném `.htaccess`; používejte přímý endpoint. U routovaných webových/Ajax adres ověřte nastavení podadresáře na konkrétním serveru.

## Nastavení zobrazovaných metrik

Administrace ukládá nastavení pro uživatele do `tSettings`. Hlavní přepínač skupinu zapne/vypne a jednotlivé volby vybírají její hodnoty. Prázdné nastavení obnoví výchozí zobrazení. Backend aplikuje preference po načtení společných zdrojových dat z cache; změna nastavení proto nevyžaduje novou cache pro každého uživatele.

| Skupina | Dostupné volby |
| --- | --- |
| JIF | Category, Edition, Quartile, Rank |
| Impact Metrics | JIF, JIF 5 Years, JIF Without Self Citations, JCI, Immediacy Index, Total Cites |
| Article Influence | Category, Edition, Quartile, Rank |
| Influence Metrics | Article Influence, EigenFactor Score, EigenFactor Normalized |
| Source Metrics | JIF Percentile, Citable Items Total, Articles Percentage, Half Life Cited, Half Life Citing |

Dostupnost hodnot závisí na odpovědi Clarivate pro daný časopis a rok. Nejde o záruku, že každý vyhledaný článek vrátí všechny uvedené metriky.

## Browser extension

Zdrojové soubory rozšíření jsou ve složce `www/extension`.

Rozšíření umí:

- ručně vyhledávat podle názvu nebo DOI přes Search/Enter v popupu
- spustit hledání z kontextového menu nad vybraným textem
- uložit token s časem expirace do úložiště rozšíření a odvolat jej při odhlášení
- zobrazit poslední výsledek v popup okně
- zobrazit kvartil jako badge na ikoně rozšíření

Pro lokální instalaci v Chrome nebo Edge:

1. Otevřete stránku pro správu rozšíření.
2. Zapněte režim vývojáře.
3. Zvolte `Load unpacked`.
4. Vyberte složku `www/extension`.

Repozitář obsahuje i soubory `www/extension.crx` a `www/extension.pem`, ale pro vývoj je bezpečnější a přehlednější použít nebalenou verzi ze složky.

Po úpravách znovu načtěte rozšíření na `chrome://extensions` nebo `edge://extensions`. Kopírujte celý aktuální adresář včetně `client.js`; historický `.crx` nemusí odpovídat aktuálním zdrojům. `extension.pem` je podpisový klíč, ne součást instalace rozšíření.

`background.js` zůstává service workerem a přes `importScripts('client.js')` načítá obsluhu HTTP a relací. `client.js` obsahuje adresu `https://imitweby.uhk.cz/widget` a volá `/ajax/logIn`, `/ajax/revokeToken` a `/presenters/api.php`. Pro jiné nasazení změňte tuto adresu i odkaz administrace v `www/extension/index.html`; aktualizace místních souborů backendu sama neaktualizuje univerzitní server.

`content.js` sice rozpoznává DOI a posílá zprávu `FOUND_DOI`, ale service worker ji aktuálně neobsluhuje. Automatické hledání při otevření stránky není implementováno. Vyhledávání spouští uživatel přes popup nebo kontextové menu. Manifest momentálně žádá oprávnění k HTTP/HTTPS stránkám.

Popup má tmavé vyhledávací pole, společné barvy a zaoblení, tlačítko Search přes celou šířku a zvýraznění při ovládání klávesnicí. Dlouhý název článku se zalamuje a jeho flex kontejner má `min-width: 0`, aby neroztahoval okno. Zdroj stylů je `assets/css/popup.less`, načítá se `assets/css/popup.css`; při změně LESS aktualizujte i CSS. Backend není potřeba měnit kvůli vzhledu.

## Struktura projektu

- `index.php` vstupní bod aplikace
- `common_functions.php` jednoduché routování a pomocné funkce
- `presenters/` webové a API controllery
- `classes/` doménové třídy, databázové managery a helpery
- `www/` Latte šablony, assety a browser extension
- `config/` lokální konfigurace
- `classes/MySqlCache.php` MySQL cache
- `classes/security/apiToken.php` tokeny rozšíření
- `classes/UpstreamHttp.php` společná HTTPS volání a bezpečné chybové zprávy
- `database/migrations/` změny existující databáze
- `tests/` regresní a integrační kontroly
- `vendor/` Composer balíčky

## Databáze

Z kódu je zřejmé, že aplikace pracuje minimálně s těmito tabulkami:

- `tUser`
- `tSettings`
- `tApiToken`
- `tLookupCache`

`tUser` ukládá e-mail, původní token používaný starší webovou administrací, nikoli API, stav, oprávnění a čas poslední akce. `tApiToken` uchovává hashe a životní cyklus tokenů rozšíření. `tSettings` ukládá JSON s preferencemi zobrazení metrik navázaný na konkrétního uživatele přes `FK_userID`.

## Provozní poznámky

- Lokální a serverové autoloadování se přepíná podle `REMOTE_ADDR` mezi `autoload.php` a `autoload_linux.php`.
- Session se ukládají do lokální složky `./tmp`.
- LDAP server je v kódu natvrdo směrován na interní adresu `172.25.4.10`, takže mimo cílovou síť nebude přihlášení fungovat bez úpravy.
- Bez dostupného LDAP se nelze přihlásit do rozšíření; nastavte institucionální síť/VPN a adresu v `classes/super/lDAP.php`.

## Bezpečnostní upozornění

- Produkční konfigurace není verzovaná v repozitáři, což je správně. Klíče a hesla necommitujte do Gitu.
- Starší webové přihlášení v `presenters/user.php` obsahuje pevně zapsanou výjimku a větev, která nového uživatele přihlásí bez LDAP. V `presenters/ajax.php` tyto výjimky nejsou: přihlášení rozšíření ověřuje LDAP i aktivní účet. Zabezpečení API tokenů proto není potvrzením bezpečnosti celé webové administrace; starší přihlášení vyžaduje samostatnou opravu před veřejným nasazením.
- Všechna externí HTTPS volání ověřují certifikát i jméno serveru. Ve Windows se používá systémové úložiště certifikátů, pokud ho cURL podporuje; jinde výchozí nastavení PHP/cURL. Vlastní důvěryhodný PEM soubor lze určit přes `UPSTREAM_CA_BUNDLE`.

## Vývoj a údržba

- PHP závislosti jsou definované v `composer.json`.
- Node část je minimální a `package.json` aktuálně obsahuje jen typovou podporu pro Chrome API.
- Spusťte automatické testy uvedené níže a následně ověřte přihlášení, API a rozšíření v institucionálním prostředí.

## Licence

Platné podmínky jsou uvedeny v `Licence_CZ.txt` a `Licence.txt`; tyto soubory označují projekt jako proprietární software.

## Aktualizace na 1.1.0

Cache nyní používá stávající MySQL/MariaDB a ovladač PDO MySQL. Není potřeba instalovat další službu ani zvláštní PHP rozšíření pro cache. Pro aktuálně očekávaný nízký počet uživatelů a vyhledávání na univerzitě jde o jednodušší nasazení a správu. Není to tvrzení o naměřené vyšší rychlosti. Při růstu provozu může další verze nabídnout Redis, pokud to odůvodní měření zátěže; nyní není implementován ani vyžadován.

1. Do databáze aplikace importujte `database/migrations/001_api_tokens.sql` a `database/migrations/002_lookup_cache.sql`. Nová instalace použije celý `database/schema.sql`. Migrace vytvářejí `tApiToken` a `tLookupCache`, uživatele ani nastavení nemažou.
2. Zajistěte PHP `pdo_mysql`. Používají se existující `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE` a volitelně `DB_PORT` (výchozí 3306). Všechny backendové procesy musí používat stejný databázový server. Účet aplikace potřebuje SELECT/INSERT/UPDATE/DELETE pro nové tabulky; vytvoření tabulek je krok nasazení.
3. Do soukromého `config/config.php` lze přidat následující definice. Uvedené hodnoty odpovídají výchozím hodnotám kódu:

```php
define('LOOKUP_CACHE_NAMESPACE', 'qfinder:v1:');
define('OPENALEX_CACHE_TTL_SECONDS', 3600);
define('WOS_CACHE_TTL_SECONDS', 86400);
define('API_TOKEN_TTL_SECONDS', 28800);
```

4. Backend a rozšíření nasaďte společně přes HTTPS. Přeneste i nové soubory `classes/MySqlCache.php`, `classes/security/apiToken.php`, `classes/UpstreamHttp.php`, migrace a celý adresář `www/extension`. Soubory označené `??` v `git status` nejsou součástí diffu sledovaných souborů. Staré tokeny přestanou fungovat; uživatelé se znovu přihlásí. Původní sloupec `tUser.token` zůstává kvůli kompatibilitě uživatelských záznamů, API ho nepřijímá.
5. Pro jiné nasazení upravte institucionální adresu v `www/extension/client.js` a odkaz na nastavení v `www/extension/index.html`. Proxy/FastCGI musí předávat hlavičku Authorization; dodaný `.htaccess` obsahuje odpovídající pravidlo. Skutečné LDAP, placená API a rozšíření ověřte v institucionálním prostředí.

### Přístup k OpenAlex a diagnostika HTTPS

Do `OPENALEX_API_KEY` v soukromém `config/config.php` vložte vlastní klíč z [nastavení OpenAlex](https://openalex.org/settings/api). Backend jej posílá v hlavičce Authorization; do rozšíření ani klíče cache se nedostane. Podle [aktuální dokumentace](https://help.openalex.org/api/authentication/) je základní anonymní přístup možný s menším limitem; při přetížení může být anonymní hledání také pozastavené. Pro nasazení doporučujeme serverový klíč.

XAMPP může obsahovat zastaralý soubor certifikačních autorit. `classes/UpstreamHttp.php` ve Windows zapíná podporované systémové úložiště důvěryhodných certifikátů a zachovává kontrolu certifikátu i jména serveru pro OpenAlex i Clarivate. Pro vlastní úložiště lze nastavit `UPSTREAM_CA_BUNDLE` na existující důvěryhodný PEM soubor; jinak aktualizujte `curl.cainfo` v PHP nebo systémové certifikáty. Ověřování TLS nevypínejte.

Popup rozlišuje chybu certifikátu, spojení, timeout, odmítnutý API klíč, vyčerpaný limit a nedostupnost služby. Backend vrací HTTP 502/503/504, takže odmítnutí externího API klíče neodhlásí uživatele widgetu. Serverový log obsahuje pouze poskytovatele, kategorii chyby, HTTP stav a číslo chyby cURL; neobsahuje klíče, URL ani těla odpovědí. Chyby se do cache neukládají.

`php tests/upstream.php` ověří zpracování chyb bez sítě. `php tests/upstream.php --live` navíc zavolá skutečné OpenAlex jednou přes DOI a jednou přes název, s privátním serverovým klíčem, pokud je vyplněn. Nepoužívá LDAP, databázi ani Clarivate. S upraveným presenterem nasaďte i `classes/UpstreamHttp.php`.

Volitelné nastavení vlastní certifikační autority v `config/config.php`:

```php
// Set only if using an existing trusted PEM bundle.
// define('UPSTREAM_CA_BUNDLE', 'D:/certificates/cacert.pem');
```

### Rozlišení chyb a časové limity

`client.js` nyní rozlišuje selhání spojení s backendem, timeout 60 sekund a nečitelnou/neplatnou JSON odpověď s konkrétním HTTP stavem. Zpráva `The extension could not complete the operation` označuje jinou chybu běhu rozšíření. Původní obecná hláška `Connection to the server failed` mohla skrývat i chybu parsování JSON; pokud ji stále vidíte, ověřte aktualizaci `client.js` i `background.js` a znovu načtěte správnou složku rozšíření.

| Projev | Co ověřit |
| --- | --- |
| HTTP 401 z chráněného API | Token vypršel nebo byl odvolán; znovu se přihlásit. |
| OpenAlex/Clarivate odmítl přístup nebo vyčerpal limit | Klíč a přístupové oprávnění příslušné služby v konfiguraci serveru; chyba externího klíče neznamená neplatnou relaci widgetu. |
| Chyba HTTPS certifikátu | Důvěryhodné certifikáty na backendu, případně `UPSTREAM_CA_BUNDLE`. |
| Nečitelná odpověď (HTTP 200/500/502/504…) | PHP a webserver/proxy log pro stejný čas; backend mohl vrátit HTML, prázdné tělo nebo neplatný JSON. |
| Timeout 60 sekund | Celkovou dobu hledání a limity serveru; obnovování spojení není automatické. |
| Nelze dosáhnout `imitweby.uhk.cz` | Síť/VPN, DNS, certifikát backendu a oprávnění rozšíření. |
| `Lookup temporarily unavailable` | Databázi, import obou migrací, zámek cache a serverový log. |

Jeden externí požadavek má timeout připojení 5 sekund a celkový timeout 20 sekund. Hledání může zkoušet více roků Clarivate za sebou, takže 20 sekund není limit celého hledání. Rozšíření čeká nejvýše 60 sekund; PHP, webserver nebo proxy mohou požadavek ukončit dříve. Kód zatím nemá společný časový rozpočet všech externích volání ani automatické opakování. Selhání kolem 30 sekund je vodítko ke kontrole limitů, nikoli samo o sobě důkaz konkrétní příčiny. Diagnostika bez přihlášení neověřuje celý průchod hledáním.

Do konzole service workeru se zapisuje jen pevná kategorie chyby a HTTP stav, u ostatních chyb typ výjimky. API klíče, bearer tokeny a surové odpovědi nesdílejte při hlášení chyby. Platná relace při síťové/serverové chybě zůstává zachována a požadavek lze zopakovat; odhlášení při nečitelné odpovědi nemaže token bez potvrzení serveru.

### Chování cache

`tLookupCache` obsahuje SHA-256 klíč, JSON odpověď a čas expirace, s indexy na klíči a expiraci. Identita zahrnuje endpoint, dotaz a rok metrik; u Clarivate i hash použitého API klíče. Uživatelské tokeny, přihlašovací údaje ani osobní nastavení se do odpovědí cache neukládají. Autentizace a nastavení se zpracují zvlášť pro každý požadavek.

Výchozí TTL je hodina pro OpenAlex a 24 hodin pro Clarivate. Prázdné úspěšné výsledky a HTTP 404 z OpenAlex i Clarivate mají nejvýše pět minut. Expirace se kontroluje při každém čtení podle databázového času. Při cache miss se odstraní nejvýše 100 expirovaných záznamů; žádný další proces není nutný. Správce může doplnit periodické čištění pomocí `DELETE FROM tLookupCache WHERE expiresAt <= UNIX_TIMESTAMP() LIMIT 1000`. Staré záznamy čekající na smazání se nikdy nevracejí jako platné. Změna `LOOKUP_CACHE_NAMESPACE` zneplatní aktivní cache bez zásahu do uživatelů.

Při chybějícím výsledku koordinuje shodné dotazy databázový zámek `GET_LOCK` s čekáním nejvýše jednu sekundu. Po získání zámku se cache znovu kontroluje. Zámek se uvolní i při chybě nebo ukončení spojení; během volání externího API nezůstává otevřená transakce. Kolize či nedostupná databáze vrací HTTP 503 bez neomezeného přechodu na externí API. Chybové odpovědi se neukládají jako úspěšná data. Jednotlivá externí volání mají timeout 20 sekund. Koordinace předpokládá jeden společný databázový server.

Tokenové záznamy v `tApiToken` se při expiraci automaticky nemažou; expirace a odvolání se vynucují při autorizaci. Výše uvedený automatický úklid se týká pouze `tLookupCache`.

### Tokeny a ruční hledání

Každé úspěšné přihlášení rozšíření přes LDAP vydá nový náhodný token s výchozí absolutní platností osm hodin. V databázi je pouze hash a časy vydání, expirace a odvolání. Backend ověřuje token i aktivní účet před každým chráněným dotazem. Přenos používá `Authorization: Bearer`, nikoli URL. Rozšíření omezuje přístup k lokálnímu úložišti a čistí neplatnou relaci; rozhodující kontrolu provádí backend.

Odhlášení volá `POST /ajax/revokeToken` a po potvrzení vymaže lokální data. Při neúspěchu odvolání zobrazí chybu a dovolí opakování. Webová administrace má oddělené PHP sessions. Uživatel zadává název článku nebo DOI a hledá přes Search/Enter či kontextové menu; obě cesty sdílejí obsluhu. Prázdné, příliš dlouhé a souběžné dotazy se odmítnou. Limit je 2000 UTF-8 bajtů. OCR ani samostatné hledání názvu časopisu nejsou součástí funkce a metriky časopisu nedokazují indexaci konkrétního článku.

### Testy

```sh
php tests/backend.php
php tests/upstream.php
node tests/extension.cjs
```

Tokenové testy používají izolovanou SQLite databázi (`pdo_sqlite` je potřeba jen pro ně). Testy rozšíření simulují HTTP, úložiště a DOM popupu. Integrační test MySQL/MariaDB spusťte vůči samostatnému testovacímu serveru:

```powershell
$env:QF_TEST_MYSQL_DSN = 'mysql:host=127.0.0.1;port=33379;charset=utf8mb4'
$env:QF_TEST_MYSQL_USER = 'root'
# Případné heslo patří do QF_TEST_MYSQL_PASSWORD.
php tests/mysql.php
```

Test nečte privátní konfiguraci aplikace. Vytvoří vlastní náhodné schéma `qfinder_test_*`, ověří migrace, cache a tokeny přes více spojení a smaže pouze toto testovací schéma. Účet testu potřebuje CREATE/DROP DATABASE. Automatické testy nenahrazují měření produkční zátěže ani ověření LDAP a licencovaných API.

Integrační sada také spouští skutečné zdroje lookup/settings/revoke endpointů v izolovaném PHP procesu s přednaplněnou cache a vypnutým `curl_init`. Ověřuje stavové kódy, JSON, aktuální nastavení a kontrolu tokenu i pro cache hit bez externích dotazů; netestuje Apache routování ani živé LDAP.
