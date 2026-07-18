# WOS Widget / Q-Quartile Finder

**Current Version:** `1.0`

Web application and companion browser extension for quickly retrieving journal quartiles and related bibliometric metrics. The project looks up a DOI or text query, resolves the record through OpenAlex, and then enriches it with metrics from the Clarivate Web of Science Journals API.

## What the project does

- looks up an article by DOI or text query
- resolves the journal, ISSN, publication year, and metrics year
- displays the `Q1-Q4` quartile and additional metrics such as `JIF`, `JCI`, `Article Influence`, or `EigenFactor`
- stores user preferences for which metrics should be displayed
- authenticates users through LDAP and assigns each user their own API token
- serves a Chrome/Chromium extension that can read DOI values from a page and trigger searches from the context menu

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

1. The user enters a DOI or a text query.
2. `presenters/api.php` calls OpenAlex and tries to resolve the matching record.
3. The application extracts the ISSN and publication year from the result.
4. It then calls the Clarivate Web of Science API using the ISSN.
5. The application returns the quartile, bibliometric metrics, and optional user display settings.

## Main URLs and endpoints

- `/` default application entry point; currently redirects into administration
- `/user/signIn` login page
- `/user/login` login processing
- `/admin` administration page for user metric display settings
- `/admin/saveSettings` saves or resets settings
- `/ajax/logIn` JSON login for the browser extension
- `/ajax/userSettings?token=...` returns saved settings for a given token
- `/api?q=...` main API query for quartile and metrics lookup
- `/presenters/api.php?q=...` direct entry point currently used by the browser extension

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

`tUser` stores the email, token, status, permissions, and the timestamp of the last action. `tSettings` stores a JSON payload with metric display preferences bound to a specific user through `FK_userID`.

## Operational notes

- Local and server autoloading is switched by `REMOTE_ADDR` between `autoload.php` and `autoload_linux.php`.
- Sessions are stored in the local `./tmp` directory.
- The LDAP server is hardcoded to the internal address `172.25.4.10`, so login will not work outside the target network without modification.
- If LDAP is unavailable, the login flow must be adjusted or temporarily disabled.

## Security notes

- Production configuration is not versioned in the repository, which is correct. Do not commit keys or passwords.
- The code contains a hardcoded special login exception in `presenters/user.php` and `presenters/ajax.php`. I strongly recommend removing it before production deployment.
- In `presenters/api.php`, SSL verification is disabled for some cURL requests. If your environment allows it, standard certificate validation should be re-enabled.

## Development and maintenance

- PHP dependencies are defined in `composer.json`.
- The Node side is minimal, and `package.json` currently only contains Chrome API type support.
- There are currently no automated tests in the project, so after larger changes it is best to manually verify login, `/api`, `/ajax/userSettings`, and the browser extension.

## License

Usage terms are described in `Licence.txt`. A Czech version is also available in `Licence_CZ.txt`. The repository is currently documented conservatively as proprietary software because the codebase did not previously declare a clear open-source license.
