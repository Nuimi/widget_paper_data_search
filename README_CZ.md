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

- PHP 8.x
- Composer
- MySQL nebo MariaDB
- PHP rozšíření `curl`
- PHP rozšíření `json`
- PHP rozšíření `ldap`
- PHP rozšíření `pdo_mysql`
- právo zápisu do `cache` a `tmp`
- přístup k OpenAlex API
- platný `WOS_API_KEY`
- platný `WOS_JOURNALS_API_KEY`
- dostupný LDAP server, pokud má fungovat přihlášení

## Instalace

0. Připravte potřebné externí přístupy a přihlašovací údaje. Ještě před instalací by měla instituce komunikovat s Clarivate ohledně přístupu k Web of Science API a získat 2 API klíče, které tento projekt používá. Viz [Přístup ke Clarivate API](#přístup-ke-clarivate-api).
1. Naklonujte repozitář do webového rootu, například `D:\xampp\htdocs\school\widget`.
2. Nainstalujte PHP závislosti:

```bash
composer install
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
- `DB_CHARSET`
- `WOS_API_KEY`
- `WOS_JOURNALS_API_KEY`

Minimální kostra může vypadat takto:

```php
<?php

define('LOCALE', 'true');

define('CONNECT', [
    'driver' => 'mysqli',
    'host' => '127.0.0.1',
    'username' => 'root',
    'password' => '',
    'database' => 'widget',
    'charset' => 'utf8mb4',
]);

define('DB_HOST', '127.0.0.1');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_DATABASE', 'widget');
define('DB_CHARSET', 'utf8mb4');

define('WOS_API_KEY', 'YOUR_WOS_API_KEY');
define('WOS_JOURNALS_API_KEY', 'YOUR_WOS_JOURNALS_API_KEY');
```

Tyto hodnoty jsou pouze ilustrační. Skutečné přihlašovací údaje a API klíče musí odpovídat vašemu prostředí.

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
- cizí klíč `tSettings.FK_userID -> tUser.id`

`tSettings.FK_userID` je zároveň unikátní, protože aktuální logika aplikace počítá s jedním záznamem nastavení na jednoho uživatele.

## Datový tok

1. Uživatel zadá DOI nebo textový dotaz.
2. `presenters/api.php` zavolá OpenAlex a pokusí se dohledat odpovídající záznam.
3. Z výsledku se získá ISSN a rok publikace.
4. Nad ISSN se zavolá Clarivate Web of Science API.
5. Aplikace vrátí kvartil, bibliometrické metriky a případně uživatelská nastavení zobrazení.

## Hlavní URL a endpointy

- `/` výchozí vstup aplikace; aktuálně přesměrovává do administrace
- `/user/signIn` přihlašovací stránka
- `/user/login` zpracování přihlášení
- `/admin` administrace uživatelských nastavení zobrazovaných metrik
- `/admin/saveSettings` uloží nebo resetuje nastavení
- `/ajax/logIn` JSON přihlášení pro browser extension
- `/ajax/userSettings` vrátí uložená nastavení s hlavičkou `Authorization: Bearer <token>`
- `POST /ajax/revokeToken` odvolá předaný token
- `/api?q=...` hlavní API dotaz pro vyhledání kvartilu a metrik
- `/presenters/api.php?q=...` přímý vstup, který aktuálně používá browser extension

## Browser extension

Zdrojové soubory rozšíření jsou ve složce `www/extension`.

Rozšíření umí:

- najít DOI přímo na otevřené stránce
- spustit hledání z kontextového menu nad vybraným textem
- uložit přihlašovací token do lokálního uložiště prohlížeče
- zobrazit poslední výsledek v popup okně
- zobrazit kvartil jako badge na ikoně rozšíření

Pro lokální instalaci v Chrome nebo Edge:

1. Otevřete stránku pro správu rozšíření.
2. Zapněte režim vývojáře.
3. Zvolte `Load unpacked`.
4. Vyberte složku `www/extension`.

Repozitář obsahuje i soubory `www/extension.crx` a `www/extension.pem`, ale pro vývoj je bezpečnější a přehlednější použít nebalenou verzi ze složky.

## Struktura projektu

- `index.php` vstupní bod aplikace
- `common_functions.php` jednoduché routování a pomocné funkce
- `presenters/` webové a API controllery
- `classes/` doménové třídy, databázové managery a helpery
- `www/` Latte šablony, assety a browser extension
- `config/` lokální konfigurace
- `vendor/` Composer balíčky

## Databáze

Z kódu je zřejmé, že aplikace pracuje minimálně s těmito tabulkami:

- `tUser`
- `tSettings`

`tUser` ukládá e-mail, původní již nepoužívaný token, stav, oprávnění a čas poslední akce. `tApiToken` uchovává hashe a životní cyklus tokenů rozšíření. `tSettings` ukládá JSON s preferencemi zobrazení metrik navázaný na konkrétního uživatele přes `FK_userID`.

## Provozní poznámky

- Lokální a serverové autoloadování se přepíná podle `REMOTE_ADDR` mezi `autoload.php` a `autoload_linux.php`.
- Session se ukládají do lokální složky `./tmp`.
- LDAP server je v kódu natvrdo směrován na interní adresu `172.25.4.10`, takže mimo cílovou síť nebude přihlášení fungovat bez úpravy.
- Pokud LDAP není dostupný, je potřeba přihlašování upravit nebo dočasně vypnout.

## Bezpečnostní upozornění

- Produkční konfigurace není verzovaná v repozitáři, což je správně. Klíče a hesla necommitujte do Gitu.
- Kód obsahuje natvrdo zapsanou speciální přihlašovací výjimku v `presenters/user.php` a `presenters/ajax.php`. Před produkčním nasazením doporučuji tuto výjimku odstranit.
- Všechna externí HTTPS volání ověřují certifikát i jméno serveru. Ve Windows se používá systémové úložiště certifikátů, pokud ho cURL podporuje; jinde výchozí nastavení PHP/cURL. Vlastní důvěryhodný PEM soubor lze určit přes `UPSTREAM_CA_BUNDLE`.

## Vývoj a údržba

- PHP závislosti jsou definované v `composer.json`.
- Node část je minimální a `package.json` aktuálně obsahuje jen typovou podporu pro Chrome API.
- Spusťte automatické testy uvedené níže a následně ověřte přihlášení, API a rozšíření v institucionálním prostředí.

## Licence

Podmínky použití jsou popsány v souboru `Licence_CZ.txt`. Anglická verze je dostupná v `Licence.txt`. Repozitář je teď zdokumentovaný konzervativně jako proprietární software, protože v kódové bázi dříve nebyla jednoznačně deklarovaná open-source licence.

## Aktualizace na 1.1.0

Cache nyní používá stávající MySQL/MariaDB a ovladač PDO MySQL. Není potřeba instalovat další službu ani zvláštní PHP rozšíření pro cache. Pro aktuálně očekávaný nízký počet uživatelů a vyhledávání na univerzitě jde o jednodušší nasazení a správu. Není to tvrzení o naměřené vyšší rychlosti. Při růstu provozu může další verze nabídnout Redis, pokud to odůvodní měření zátěže; nyní není implementován ani vyžadován.

1. Do databáze aplikace importujte `database/migrations/001_api_tokens.sql` a `database/migrations/002_lookup_cache.sql`. Nová instalace použije celý `database/schema.sql`. Migrace vytvářejí `tApiToken` a `tLookupCache`, uživatele ani nastavení nemažou.
2. Zajistěte PHP `pdo_mysql`. Používají se existující `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE` a volitelně `DB_PORT` (výchozí 3306). Všechny backendové procesy musí používat stejný databázový server. Účet aplikace potřebuje SELECT/INSERT/UPDATE/DELETE pro nové tabulky; vytvoření tabulek je krok nasazení.
3. Volitelné definice z `config/cache.example.php` lze zkopírovat do soukromého `config/config.php`:

```php
define('LOOKUP_CACHE_NAMESPACE', 'qfinder:v1:');
define('OPENALEX_CACHE_TTL_SECONDS', 3600);
define('WOS_CACHE_TTL_SECONDS', 86400);
define('API_TOKEN_TTL_SECONDS', 28800);
```

4. Backend a rozšíření nasaďte společně přes HTTPS. Přeneste i nové soubory `classes/MySqlCache.php`, `classes/security/apiToken.php`, migrace, vzor konfigurace a `www/extension/client.js`. Soubory označené `??` v `git status` nejsou součástí diffu sledovaných souborů. Staré tokeny přestanou fungovat; uživatelé se znovu přihlásí. Původní sloupec `tUser.token` zůstává kvůli kompatibilitě uživatelských záznamů, API ho nepřijímá.
5. Pro jiné nasazení upravte institucionální adresu v `www/extension/client.js` a odkaz na nastavení v `index.html`. Proxy/FastCGI musí předávat hlavičku Authorization; dodaný `.htaccess` obsahuje odpovídající pravidlo. Skutečné LDAP, placená API a rozšíření ověřte v institucionálním prostředí.

### Přístup k OpenAlex a diagnostika HTTPS

Volitelné nastavení z `config/upstream.example.php` zkopírujte do soukromého `config/config.php`. Do `OPENALEX_API_KEY` vložte vlastní klíč z [nastavení OpenAlex](https://openalex.org/settings/api). Backend jej posílá v hlavičce Authorization; do rozšíření ani klíče cache se nedostane. Podle [aktuální dokumentace](https://help.openalex.org/api/authentication/) je základní anonymní přístup možný s menším limitem; při přetížení může být anonymní hledání také pozastavené. Pro nasazení doporučujeme serverový klíč.

XAMPP může obsahovat zastaralý soubor certifikačních autorit. `classes/UpstreamHttp.php` ve Windows zapíná podporované systémové úložiště důvěryhodných certifikátů a zachovává kontrolu certifikátu i jména serveru pro OpenAlex i Clarivate. Pro vlastní úložiště lze nastavit `UPSTREAM_CA_BUNDLE` na existující důvěryhodný PEM soubor; jinak aktualizujte `curl.cainfo` v PHP nebo systémové certifikáty. Ověřování TLS nevypínejte.

Popup rozlišuje chybu certifikátu, spojení, timeout, odmítnutý API klíč, vyčerpaný limit a nedostupnost služby. Backend vrací HTTP 502/503/504, takže odmítnutí externího API klíče neodhlásí uživatele widgetu. Serverový log obsahuje pouze poskytovatele, kategorii chyby, HTTP stav a číslo chyby cURL; neobsahuje klíče, URL ani těla odpovědí. Chyby se do cache neukládají.

`php tests/upstream.php` ověří zpracování chyb bez sítě. `php tests/upstream.php --live` navíc zavolá skutečné OpenAlex jednou přes DOI a jednou přes název, s privátním serverovým klíčem, pokud je vyplněn. Nepoužívá LDAP, databázi ani Clarivate. S upraveným presenterem nasaďte i `classes/UpstreamHttp.php`.

### Chování cache

`tLookupCache` obsahuje SHA-256 klíč, JSON odpověď a čas expirace, s indexy na klíči a expiraci. Identita zahrnuje endpoint, dotaz a rok metrik; u Clarivate i hash použitého API klíče. Uživatelské tokeny, přihlašovací údaje ani osobní nastavení se do odpovědí cache neukládají. Autentizace a nastavení se zpracují zvlášť pro každý požadavek.

Výchozí TTL je hodina pro OpenAlex a 24 hodin pro Clarivate. Prázdné úspěšné výsledky a nenalezené zdroje Clarivate mají nejvýše pět minut. Expirace se kontroluje při každém čtení podle databázového času. Při cache miss se odstraní nejvýše 100 expirovaných záznamů; žádný další proces není nutný. Správce může doplnit periodické čištění pomocí `DELETE FROM tLookupCache WHERE expiresAt <= UNIX_TIMESTAMP() LIMIT 1000`. Staré záznamy čekající na smazání se nikdy nevracejí jako platné. Změna `LOOKUP_CACHE_NAMESPACE` zneplatní aktivní cache bez zásahu do uživatelů.

Při chybějícím výsledku koordinuje shodné dotazy databázový zámek `GET_LOCK` s čekáním nejvýše jednu sekundu. Po získání zámku se cache znovu kontroluje. Zámek se uvolní i při chybě nebo ukončení spojení; během volání externího API nezůstává otevřená transakce. Kolize či nedostupná databáze vrací HTTP 503 bez neomezeného přechodu na externí API. Chybové odpovědi se neukládají jako úspěšná data. Jednotlivá externí volání mají timeout 20 sekund. Koordinace předpokládá jeden společný databázový server.

### Tokeny a ruční hledání

Každé LDAP přihlášení vydá nový náhodný token s výchozí absolutní platností osm hodin. V databázi je pouze hash a časy vydání, expirace a odvolání. Backend ověřuje token i aktivní účet před každým chráněným dotazem. Přenos používá `Authorization: Bearer`, nikoli URL. Rozšíření omezuje přístup k lokálnímu úložišti a čistí neplatnou relaci; rozhodující kontrolu provádí backend.

Odhlášení volá `POST /ajax/revokeToken` a po potvrzení vymaže lokální data. Při neúspěchu odvolání zobrazí chybu a dovolí opakování. Webová administrace má oddělené PHP sessions. Uživatel zadává název článku nebo DOI a hledá přes Search/Enter či kontextové menu; obě cesty sdílejí obsluhu. Prázdné, příliš dlouhé a souběžné dotazy se odmítnou. Limit je 2000 UTF-8 bajtů. OCR ani samostatné hledání názvu časopisu nejsou součástí funkce a metriky časopisu nedokazují indexaci konkrétního článku.

### Testy

```sh
php tests/backend.php
php tests/upstream.php
node --test tests/extension.cjs
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
