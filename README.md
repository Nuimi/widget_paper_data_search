# WOS Widget / Q-Quartile Finder

**Current Version:** `1.1.0`

Web application and companion browser extension for quickly retrieving journal quartiles and related bibliometric metrics. The project looks up a DOI or text query, resolves the record through OpenAlex, and then enriches it with metrics from the Clarivate Web of Science Journals API.

## What the project does

- looks up an article by DOI or text query
- resolves the journal, ISSN, publication year, and metrics year
- displays the `Q1-Q4` quartile and additional metrics such as `JIF`, `JCI`, `Article Influence`, or `EigenFactor`
- stores user preferences for which metrics should be displayed
- caches source responses in the existing MySQL/MariaDB database
- authenticates users through LDAP and issues a separate expiring, revocable API token for each extension login
- serves a Chrome/Chromium extension that can read DOI values from a page and trigger searches from the context menu or a manual title/DOI field in the popup

## Technology stack

- PHP
- Composer
- Dibi
- Doctrine ORM
- Latte
- Tracy
- PHPMailer
- PhpSpreadsheet
- JavaScript for the browser extension

## Requirements

- PHP 8.x
- Composer
- MySQL or MariaDB
- PHP extension `curl`
- PHP extension `json`
- PHP extension `ldap`
- PHP extension `pdo_mysql`
- write access to `cache` and `tmp`
- access to the OpenAlex API
- valid `WOS_API_KEY`
- valid `WOS_JOURNALS_API_KEY`
- reachable LDAP server if login is expected to work

## Installation

0. Prepare the required external access and credentials. Before installation, your institution should contact Clarivate regarding access to the Web of Science APIs and obtain the 2 API keys used by this project. See [Clarivate API Access](#clarivate-api-access).
1. Clone the repository into your web root, for example `D:\xampp\htdocs\school\widget`.
2. Install PHP dependencies:

```bash
composer install
```

3. Optionally install Node dependencies for extension development:

```bash
npm install
```

4. Create the local configuration file `config/config.php`.
5. Make sure your web server routes requests to `index.php`.
6. Make sure the application can write to `cache/` and `tmp/`.
7. Import the database schema from `database/schema.sql`.
8. Prepare or verify the values in `config/config.php` so they point to the created database.

## Configuration

The `config/config.php` file is local and ignored by Git. Based on the current code, the project expects at least these constants:

- `CONNECT` for Dibi and Doctrine database connection
- `LOCALE` for enabling the local debug bar
- `DB_HOST`
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_DATABASE`
- `DB_CHARSET`
- `WOS_API_KEY`
- `WOS_JOURNALS_API_KEY`

A minimal skeleton can look like this:

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

These values are illustrative only. Real credentials and API keys must match your environment.

## Clarivate API Access

This project depends on 2 Clarivate API keys:

- `WOS_API_KEY`
- `WOS_JOURNALS_API_KEY`

According to the official Clarivate Developer Portal, access to the Web of Science API requires a paid license, uses API key access, and availability depends on the institution's subscription and contract configuration. In practice, this means the project cannot be fully deployed until your institution communicates with Clarivate and arranges access within its institutional licensing model. Source: [Clarivate Developer Portal - Web of Science API Expanded](https://developer.clarivate.com/apis/wos).

Recommended process:

- verify that your institution has an active Web of Science subscription
- contact Clarivate or your institutional library / license administrator
- request access to the APIs required for this application
- obtain and securely store both API keys
- configure `WOS_API_KEY` and `WOS_JOURNALS_API_KEY` in `config/config.php`

## Database setup

The repository now includes the SQL schema in `database/schema.sql`.

Import example:

```bash
mysql -u root -p < database/schema.sql
```

The script creates:

- the `widget` database
- the `tUser` table
- the `tSettings` table
- the foreign key relationship `tSettings.FK_userID -> tUser.id`

`tSettings.FK_userID` is also unique, because the current application logic expects one settings record per user.

## Data flow

1. The authenticated user selects text in the context menu or submits an article title/DOI in the popup.
2. `presenters/api.php` validates the bearer token, then resolves the record through the shared cache or OpenAlex.
3. The application extracts the ISSN and publication year from the result.
4. It retrieves cached Clarivate responses or calls the Web of Science APIs using the ISSN and report year.
5. The application returns the quartile, bibliometric metrics, and optional user display settings.

## Main URLs and endpoints

- `/` default application entry point; currently redirects into administration
- `/user/signIn` login page
- `/user/login` login processing
- `/admin` administration page for user metric display settings
- `/admin/saveSettings` saves or resets settings
- `/ajax/logIn` JSON login for the browser extension
- `/ajax/userSettings` returns saved settings with an `Authorization: Bearer <token>` header
- `POST /ajax/revokeToken` permanently revokes the supplied bearer token
- `/api?q=...` main API query for quartile and metrics lookup (bearer authentication required)
- `/presenters/api.php?q=...` direct entry point used by both extension search workflows (bearer authentication required)

## Metric display settings

Authenticated users can customize which journal metrics and related classification values are displayed in the application and browser extension. The settings are stored separately for each user and can be changed from the administration page.

Each metric group has a main switch that enables or disables the entire group. Individual values within an enabled group can then be selected separately.

### Available settings

#### JIF

- Category
- Edition
- Quartile
- Rank

#### Impact Metrics

- JIF
- JIF 5 Years
- JIF Without Self Citations
- JCI
- Immediacy Index
- Total Cites

#### Article Influence

- Category
- Edition
- Quartile
- Rank

#### Influence Metrics

- Article Influence
- EigenFactor Score
- EigenFactor Normalized

#### Source Metrics

- JIF Percentile
- Citable Items Total
- Articles Percentage
- Half Life Cited
- Half Life Citing

The availability of a particular value may depend on the journal, edition, category, and metrics year returned by the Clarivate API.

Definitions and additional information about the individual Journal Citation Reports metrics are available in the [Clarivate Journal Citation Reports Glossary](https://journalcitationreports.zendesk.com/hc/en-gb/articles/28351666061457-Glossary).

## Browser extension

The extension source files are located in `www/extension`.

The extension can:

- detect DOI values directly on an open page
- trigger a search from the context menu over selected text
- store the login token in browser local storage
- show the latest result in a popup window
- display the quartile as a badge on the extension icon

For local installation in Chrome or Edge:

1. Open the extensions management page.
2. Enable developer mode.
3. Choose `Load unpacked`.
4. Select the `www/extension` folder.

The repository also contains `www/extension.crx` and `www/extension.pem`, but for development the unpacked version from the folder is safer and easier to inspect.

## Project structure

- `index.php` application entry point
- `common_functions.php` simple routing and helper functions
- `presenters/` web and API controllers
- `classes/` domain classes, database managers, and helpers
- `www/` Latte templates, assets, and the browser extension
- `config/` local configuration
- `vendor/` Composer packages

## Database

From the codebase, the application clearly works with at least these tables:

- `tUser`
- `tSettings`

`tUser` stores the email, legacy token field, status, permissions, and the timestamp of the last action. `tApiToken` stores the hashes and lifecycle timestamps of active extension sessions. `tSettings` stores a JSON payload with metric display preferences bound to a specific user through `FK_userID`.

## Operational notes

- Local and server autoloading is switched by `REMOTE_ADDR` between `autoload.php` and `autoload_linux.php`.
- Sessions are stored in the local `./tmp` directory.
- The LDAP server is hardcoded to the internal address `172.25.4.10`, so login will not work outside the target network without modification.
- If LDAP is unavailable, the login flow must be adjusted or temporarily disabled.

## Security notes

- Production configuration is not versioned in the repository, which is correct. Do not commit keys or passwords.
- The code contains a hardcoded special login exception in `presenters/user.php` and `presenters/ajax.php`. I strongly recommend removing it before production deployment.
- All upstream HTTPS calls verify the certificate and hostname. Windows uses the native trust store when supported; other systems use PHP/cURL defaults. An explicit `UPSTREAM_CA_BUNDLE` can select a trusted PEM bundle.

## Development and maintenance

- PHP dependencies are defined in `composer.json`.
- The Node side is minimal, and `package.json` currently only contains Chrome API type support.
- Run the automated regression checks listed below; also verify institutional services and the browser extension in staging.

## License

Usage terms are described in `Licence.txt`. A Czech version is also available in `Licence_CZ.txt`. The repository is currently documented conservatively as proprietary software because the codebase did not previously declare a clear open-source license.

## Upgrading to 1.1.0

The revision adds shared MySQL/MariaDB caching, expiring/revocable extension sessions, and manual popup search. It reuses the existing database and PDO MySQL driver; no additional cache service or cache-specific PHP extension is required. This is a simpler deployment choice for the small number of users and low search volume currently anticipated at the university. Higher user/search counts could justify a future Redis-based version after measuring database load and cache effectiveness; no such backend is implemented or required now.

Deploy the backend and extension together. Old extension tokens in `tUser.token` are no longer accepted; users must sign in again. The old column remains for compatibility with existing user records and web administration. Include new files when transferring this revision: `classes/security/apiToken.php`, `classes/MySqlCache.php`, both SQL migrations, `config/cache.example.php`, and `www/extension/client.js`. Files marked `??` by Git are not included in a patch of tracked files.

1. Import `database/migrations/001_api_tokens.sql` and `database/migrations/002_lookup_cache.sql` into the configured application database. New installations use `database/schema.sql` instead. These additive migrations create `tApiToken` and `tLookupCache` and preserve users/settings.
2. Enable PHP `pdo_mysql`. Cache and token persistence use the existing `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_DATABASE` settings, with optional `DB_PORT` (default 3306). All backend workers must connect to the same database server for named-lock coordination. The runtime database account needs SELECT/INSERT/UPDATE/DELETE access to the new tables; table creation belongs to deployment.
3. Copy the definitions from `config/cache.example.php` into private `config/config.php` if you want explicit overrides. Code defaults are:

```php
define('LOOKUP_CACHE_NAMESPACE', 'qfinder:v1:');
define('OPENALEX_CACHE_TTL_SECONDS', 3600);
define('WOS_CACHE_TTL_SECONDS', 86400);
define('API_TOKEN_TTL_SECONDS', 28800);
```

4. Serve the backend over HTTPS and preserve Authorization headers through proxies/FastCGI. The Apache `.htaccess` includes forwarding. Load/distribute the updated `www/extension` directory and sign in again. Other deployments must adjust `base` in `www/extension/client.js` and the settings link in `index.html` from the current institutional URL.
5. Verify institutional login, both search entry points, expiry, revocation, and repeated-query cache reuse in staging. The automated tests do not authenticate against institutional LDAP or consume licensed API quotas.

### OpenAlex access and HTTPS troubleshooting

Copy the optional settings from `config/upstream.example.php` into private `config/config.php`. Set `OPENALEX_API_KEY` to your own key from [OpenAlex settings](https://openalex.org/settings/api). It is sent only by the backend in an Authorization header, never to the extension or in a cache key. According to the [current authentication documentation](https://help.openalex.org/api/authentication/), basic anonymous use is supported with a smaller budget; anonymous search can also be paused during service overload. A server key is recommended for deployment.

The XAMPP CA bundle can be outdated. `classes/UpstreamHttp.php` enables Windows native certificate trust when available, keeping certificate and hostname verification enabled for both OpenAlex and Clarivate. For a custom trust store, set `UPSTREAM_CA_BUNDLE` to an existing, trusted PEM file; otherwise maintain PHP's `curl.cainfo`/system trust store. Never work around a certificate error by disabling TLS verification.

The popup distinguishes upstream TLS, connection, timeout, authentication, quota and service errors. These use HTTP 502/503/504, so an upstream authentication problem does not log the widget user out. Server logs include only provider, reason, HTTP status and cURL error number, without API keys, URLs or response bodies. Failed requests are not cached.

Run `php tests/upstream.php` for offline error-handling checks. To diagnose the actual server connection, run `php tests/upstream.php --live`: it calls OpenAlex once by DOI and once by title using the private server key if configured. It does not access LDAP, the database or Clarivate. Deploy `classes/UpstreamHttp.php` with the updated presenter.

### Cache behavior

`tLookupCache` stores `cacheKey` (SHA-256), `payload` (JSON text), and `expiresAt` (Unix seconds), with indexes on the key and expiry. Keys include namespace and full upstream request identity; Clarivate identities also contain a hash of the API credential. API credentials, user tokens, and personal display settings are not stored in the cached payload. Settings are loaded and applied separately for each authenticated request.

Entries expire after one hour for OpenAlex and 24 hours for Clarivate by default. Empty successful responses and Clarivate 404 responses are cached for at most five minutes. Expiry is checked on every read using the database clock. Each miss removes at most 100 expired rows; no background service is required. For periodic housekeeping, a database administrator may additionally run `DELETE FROM tLookupCache WHERE expiresAt <= UNIX_TIMESTAMP() LIMIT 1000` on a schedule. Expired rows retained between cleanups are never served. Change `LOOKUP_CACHE_NAMESPACE` to invalidate active entries without touching users or settings.

For a miss, MySQL/MariaDB `GET_LOCK` coordinates the same resource across connections, waits up to one second, and is followed by another cache check. The lock is released in `finally` or automatically on connection termination. No transaction is held open during the external request. Contention and cache database failures return HTTP 503 rather than an uncached API fallback. Transient errors and malformed payloads are not cached as successful results. Individual upstream calls have a 20-second timeout. This implementation targets one shared database server, not coordination across independent database nodes.

### Token lifecycle and popup search

Each successful LDAP login creates a random 256-bit bearer token with an absolute eight-hour lifetime by default. `tApiToken` stores only its SHA-256 hash, user ID, issuance/expiry time and revocation time. Every settings/lookup request validates these records and active account status before using cache or external services. Login returns `token`, `expiresAt` (Unix seconds), and `expiresIn`; token transport uses `Authorization: Bearer`, never query URLs. Extension storage is restricted to trusted extension contexts. Local checks and an alarm clear expired data, while the backend enforces expiry regardless of browser state.

`POST /ajax/revokeToken` idempotently revokes the supplied session. Logout clears local data after server confirmation; failures remain visible and can be retried. Results from a session ended during lookup are discarded. Web administration PHP sessions remain separate. Administrators can revoke a user's token rows or disable the account (`tUser.state = 0`).

The popup accepts article titles/DOIs, submitted with Search or Enter. Typing alone does not query an API. Empty/oversized queries and duplicate pending requests are rejected. Both popup and context-menu searches use the same authenticated service-worker handler. The limit is 2000 UTF-8 bytes. OCR and dedicated journal-title matching are not implemented; retrieved journal metrics do not prove that the individual article is indexed in WoS.

### Regression checks

```sh
php tests/backend.php
php tests/upstream.php
node --test tests/extension.cjs
```

Token unit checks use SQLite (`pdo_sqlite` needed only for these tests). Extension tests use simulated HTTP/storage and a popup DOM harness. To exercise the real database cache and token SQL on an isolated MySQL/MariaDB server, use PowerShell:

```powershell
$env:QF_TEST_MYSQL_DSN = 'mysql:host=127.0.0.1;port=33379;charset=utf8mb4'
$env:QF_TEST_MYSQL_USER = 'root'
# Set QF_TEST_MYSQL_PASSWORD if the test server requires it.
php tests/mysql.php
```

This suite never reads private application credentials. It creates a random `qfinder_test_*` schema, applies the shipped schema and migrations, checks separate connections, cache expiry, negative results, contention, errors and token revocation, and drops only its own test schema. Its test account needs CREATE/DROP DATABASE privileges. Tests do not establish production capacity; LDAP, licensed upstream services and real browser deployment still require institutional verification.

The MySQL suite also runs the actual lookup/settings/revocation endpoint source in an isolated PHP child process, with seeded cache entries and `curl_init` disabled. This verifies status codes, JSON, current display settings and token enforcement on cache hits without external API calls; it does not test Apache routing or live LDAP.
