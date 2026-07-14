# WOS Widget / Q-Quartile Finder

**Aktuální verze:** `1.0`

Webová aplikace a doprovodné prohlížečové rozšíření pro rychlé zjištění kvartilu časopisu a souvisejících bibliometrických metrik. Projekt vyhledává DOI nebo textový dotaz, dohledá záznam přes OpenAlex a následně jej doplní o metriky z Clarivate Web of Science Journals API.

## Co projekt umí

- vyhledat článek podle DOI nebo textového dotazu
- dohledat časopis, ISSN, rok publikace a rok metrik
- zobrazit kvartil `Q1-Q4` a další metriky jako `JIF`, `JCI`, `Article Influence` nebo `EigenFactor`
- uložit uživatelské preference, které metriky se mají zobrazovat
- přihlašovat uživatele přes LDAP a přidělovat jim vlastní API token
- obsloužit Chrome/Chromium rozšíření, které umí číst DOI ze stránky a spouštět hledání z kontextového menu

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
- `/ajax/userSettings?token=...` vrátí uložená nastavení pro daný token
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

`tUser` ukládá e-mail, token, stav, oprávnění a čas poslední akce. `tSettings` ukládá JSON s preferencemi zobrazení metrik navázaný na konkrétního uživatele přes `FK_userID`.

## Provozní poznámky

- Lokální a serverové autoloadování se přepíná podle `REMOTE_ADDR` mezi `autoload.php` a `autoload_linux.php`.
- Session se ukládají do lokální složky `./tmp`.
- LDAP server je v kódu natvrdo směrován na interní adresu `172.25.4.10`, takže mimo cílovou síť nebude přihlášení fungovat bez úpravy.
- Pokud LDAP není dostupný, je potřeba přihlašování upravit nebo dočasně vypnout.

## Bezpečnostní upozornění

- Produkční konfigurace není verzovaná v repozitáři, což je správně. Klíče a hesla necommitujte do Gitu.
- Kód obsahuje natvrdo zapsanou speciální přihlašovací výjimku v `presenters/user.php` a `presenters/ajax.php`. Před produkčním nasazením doporučuji tuto výjimku odstranit.
- V `presenters/api.php` je u některých cURL volání vypnuté SSL ověřování. Pokud to prostředí dovolí, je vhodné znovu zapnout standardní validaci certifikátů.

## Vývoj a údržba

- PHP závislosti jsou definované v `composer.json`.
- Node část je minimální a `package.json` aktuálně obsahuje jen typovou podporu pro Chrome API.
- V projektu zatím nejsou připravené automatické testy, proto je po větších změnách vhodné ručně ověřit login, `/api`, `/ajax/userSettings` a browser extension.

## Licence

Podmínky použití jsou popsány v souboru `Licence_CZ.txt`. Anglická verze je dostupná v `Licence.txt`. Repozitář je teď zdokumentovaný konzervativně jako proprietární software, protože v kódové bázi dříve nebyla jednoznačně deklarovaná open-source licence.
