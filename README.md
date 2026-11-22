# nframework

<a href="https://deepwiki.com/nlared/nframework"><img src="https://deepwiki.com/badge.svg" alt="Ask DeepWiki"></a>

PHP framework for building MongoDB-backed web applications quickly: plain PHP pages, form components bound
to MongoDB documents, AJAX forms, server-side DataTables, user accounts with 2FA, an admin panel, and
helpers for Excel/Word/PDF exports, images, background jobs and Mexican SAT (CFDI / e.firma) integration.

## Features

- **Page model without boilerplate** – a page is a `.php` file that does `require 'include.php';`. The
  framework provides the MongoDB client, configuration, session, current user, layout and AJAX JSON responses.
- **Form components** – `inputText`, `inputNumber`, `inputDate`, `inputDateTime`, `Select` (with AJAX search),
  `inputCheckBox(s)`, `inputRadios`, `inputMCE` (TinyMCE), `inputFile(s)`, `Signature_pad`, `mapmarker`,
  `inputaddress`… rendered with Metro UI 5.
- **Databinding** – `dataset` maps a MongoDB document to a form; `$dataset->save()` validates and stores
  the posted values with the right BSON types. `embededArray` edits arrays inside a document.
- **Tables** – `Table` (DataTables) with in-memory data or server-side paging/search over a collection or
  aggregation pipeline (`$table->Ajax([...])`).
- **Users and permissions** – login, sign-up with e-mail activation, password reset, TOTP 2FA, API tokens,
  Google / Facebook / Microsoft login, groups (`$user->in()`), permissions (`$user->can()`), `requireGroup()`.
- **Security by default** – origin-based CSRF check on every write request, CSRF tokens in forms,
  hardened session cookies, trusted-proxy aware client IP, IP/host/path blacklists, rate limiting,
  Mongo-injection helpers, safe redirects.
- **Routing** – `.php` files are served directly; everything else goes through `router.php`
  ([Mezon Router](https://github.com/alexdodonov/mezon-router)): built-in routes, CMS pages created in the
  admin panel, and your own routes in `crouter.php`.
- **Admin panel** (`/admin/`) – site settings, security rules, users and groups, pages, menus, themes,
  media and file manager, admin tables (`nftables`), statistics, error log, workers, SAT certificates,
  Git/Composer updates and a system diagnostic.
- **Exports** – Excel (PhpSpreadsheet), Word (PhpWord), PDF (Dompdf or LibreOffice/unoconv), Word templates.
- **Images** – resize, WebP conversion and PDF pages as images through built-in routes (Intervention Image v4).
- **Background work** – `bgprocess` for long shell commands and a MongoDB job queue (`Job` + `job_worker.php`).
- **i18n** – `es-MX` and `en-US` texts selected from the browser language.
- **Caching** – local file cache for config, rules, pages and menus (`nfCacheRemember()`), compiled Twig
  templates, and HTTP caching helpers (`serveFile()`, `serveContent()`, `testcache()`).

## Requirements

- PHP **8.3+** (8.4+ to run the test suite, which uses PHPUnit 13)
- PHP extensions: `mongodb`, `intl`, `gd`, `curl`, `zip`, `mbstring`, `openssl`, `xml` (`dom`, `simplexml`,
  `xmlreader`, `xmlwriter`, `xsl`), `pdo_sqlite`, `imagick`
- MongoDB server (a replica set if you use transactions)
- Composer, as `includes/composer.phar` (also used by Admin → Update)
- Nginx or Apache
- Optional: Ghostscript (PDF → image), `pdftk`, LibreOffice/`unoconv` (Word/Excel → PDF)

`php nframework/test.php` lists whatever is missing with the command to install it.

## Installation

Full guide (in Spanish): [`docs/install.php`](docs/install.php), `/docs/install.php` on a development site.

### Automated (Debian/Ubuntu)

`install.sh` has two modes:

- **Single site** (`--mode=single`): the cloned repository *is* the site's document root; the framework files
  (`admin/`, `nframework/`, `router.php`...) live next to the site's pages.
  ```bash
  git clone https://github.com/nlared/nframework.git /var/www/www.example.com
  cd /var/www/www.example.com
  ./install.sh --mode=single --host=www.example.com
  ```
- **Multisite** (`--mode=multi`): the framework lives in its own folder and each vhost gets its own document
  root (`--docroot`, created if missing) with symlinks to `/admin`, `/nframework` and `/router.php` (plus the
  login background images; `--docs` also links `/docs`). All sites share `includes/` (code, vendor and
  `config.php`, one `case` block per domain). Run it once per site:
  ```bash
  sudo git clone https://github.com/nlared/nframework.git /opt/nframework && cd /opt/nframework
  ./install.sh --mode=multi --host=www.example.com --docroot=/var/www/www.example.com
  ./install.sh --mode=multi --host=shop.example.com --docroot=/var/www/shop.example.com --db=shop
  ```

Without `--mode` it asks (with `--yes`, single site).

[`install.sh`](install.sh) runs the manual steps below and skips the ones already done. It asks before
running `apt-get` or writing to `/etc/php` (`--yes` answers for you):

1. installs the PHP extensions, Ghostscript, `git` and `unzip` with `apt-get` (`--php=8.4` picks the PHP
   version, `--no-apt` skips this step);
2. downloads `includes/composer.phar` from getcomposer.org, verifying the installer signature;
3. runs `php composer.phar install --ignore-platform-reqs` in `includes/` (`--no-dev` leaves out PHPUnit/Pint);
4. creates `includes/config.php` with a block for `--host`, or adds that block before `default:` in an existing
   one (backup in `config.php.bak`); `--db=` and `--mongo=` set the database name and connection string;
5. adds `includes/` to PHP's `include_path` (CLI, FPM and Apache) in `conf.d/99-nframework.ini`;
6. multisite: creates the document root, the symlinks and a sample `index.php` (existing files are never
   overwritten);
7. runs `php nframework/test.php --host=... --docroot=...` to install the database and report what is still missing.

It finishes by printing an nginx `server` block for the site. It does not install MongoDB or nginx.

### Manual

1. **Clone the repository** into the web root of the site:
   ```bash
   git clone https://github.com/nlared/nframework.git
   cd nframework
   ```

2. **Install Composer as `includes/composer.phar`** ([official instructions](https://getcomposer.org/download/);
   check the installer signature published there):
   ```bash
   cd includes
   php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
   php composer-setup.php
   php -r "unlink('composer-setup.php');"
   ```
   `composer.phar` is ignored by git. Keep it up to date with `php composer.phar self-update`.

3. **Install the runtime dependencies** (they live in `includes/`, not in the repository root):
   ```bash
   php composer.phar install --ignore-platform-reqs
   cd ..
   ```
   `--ignore-platform-reqs` installs the packages even if the PHP running Composer lacks an extension or is a
   different version from the one serving the site (CLI and FPM often load different `php.ini` files). The
   framework still needs those extensions at runtime: `nframework/test.php` (step 6) reports any that are
   missing. Use the same flag for `update`, `require` and `remove`:
   ```bash
   cd includes
   php composer.phar update --ignore-platform-reqs
   php composer.phar require vendor/package --ignore-platform-reqs
   ```

4. **Create the configuration**:
   ```bash
   cp includes/config.example.php includes/config.php
   ```
   `config.php` selects a block by `$_SERVER['HTTP_HOST']`; unknown hosts are rejected. Every option is
   documented in [`includes/config.example.php`](includes/config.example.php):
   ```php
   case 'www.example.com':
       $config = [
           'mongo_connection_string' => 'mongodb://127.0.0.1',
           'sitedb' => 'example',
           'cookie_domain' => 'www.example.com',
           'url' => 'https://www.example.com',
       ];
       break;
   ```
   At runtime the document `{_id: 'site'}` of the `configs` collection (edited in Admin → Site / Security)
   is merged on top of this array.

5. **Configure the web server**
   - Document root at the repository root.
   - `includes/` in PHP's `include_path` (pages do `require 'include.php';`).
   - Every request that is not an existing file rewritten to `/router.php`.
   - The block from [`docs/security.php`](docs/security.php) ("Servidor web") so that `includes/`, `.git`,
     logs and hidden files are not downloadable.
   - PHP `error_log` outside the document root (a relative `error_log = php_errors.log` writes the log
     next to the script, where it can be downloaded).

6. **Install the database** (guest/admin users, groups, indexes) and check the environment:
   ```bash
   php nframework/test.php --host=www.example.com
   # multisite: php /opt/nframework/nframework/test.php --host=www.example.com --docroot=/var/www/www.example.com
   ```
   It checks PHP, extensions, Composer packages, `config.php`, MongoDB, `include_path`, opcache and the web
   server rules, and prints the command to fix each problem. If something essential is missing (the `mongodb`
   extension, `includes/vendor` or `config.php`) it stops before touching the database and exits with code 1.
   The same diagnostic is available in the browser at `/nframework/test.php` (only for admins once
   installed); run it there too, since PHP-FPM may load different extensions than the CLI.

## Quick start

Runnable examples of every component live in [`docs/`](docs/): open `/docs/` on a development site. Each
page shows the result next to the code that produces it.

### A page

```php
<?php
require 'include.php';            // $m, $config, $user, $nframework, $javas
$nframework->usecommon = true;     // wrap the output in the HTML layout (Metro UI)
$nframework->metas['title'] = 'My page';
?>
<div class="container">
    <h1>Hello <?= htmlspecialchars($user->name ?? 'guest') ?></h1>
</div>
```

Set `$requiresession = true;` **before** the `require` to send visitors without a session to `/`,
or call `$user->requireAuth()` / `requireGroup('admins')` after it.

### A form bound to MongoDB, saved over AJAX

```php
<?php
require 'include.php';
$nframework->usecommon = true;

$dataset = new dataset([
    'collection' => $m->{$config['sitedb']}->products,
    '_id' => $_GET['_id'],              // 24-hex ObjectId string
    'nameprefix' => 'data',             // fields are posted as data[field]
]);
$name  = new inputText(['dataset' => &$dataset, 'field' => 'name', 'caption' => 'Name', 'required' => true]);
$price = new inputNumber(['dataset' => &$dataset, 'field' => 'price', 'caption' => 'Price']);
$date  = new inputDate(['dataset' => &$dataset, 'field' => 'available', 'caption' => 'Available from']);

if ($nframework->isAjax()) {
    if (!csrfValidate()) {
        $result = ['error' => 'Session expired, reload the page.'];
    } elseif (($_POST['op'] ?? '') === 'save') {
        $result = ['error' => $dataset->save()];   // false = success
    }
    return;                                        // the framework sends json_encode($result)
}
?>
<?= secureform() ?>
    <?= $name ?> <?= $price ?> <?= $date ?>
    <button class="button primary secureop" value="save">Save</button>
</form>
```

`$result` keys understood by the browser: `error` (`false` = success toast, string = error dialog),
`ids` (`['elementId' => 'html']`) and `js` (code to run).

### A server-side table

```php
$table = new Table();
$table->Ajax([
    'id' => 'products',
    'db' => $config['sitedb'],
    'collection' => 'products',
    'header' => '<th>Name</th><th>Price</th>',
    'columns' => ['name', 'price'],
    'pipeline' => [['$match' => ['active' => true]]],
]);
echo $table;
```

### Custom routes

Create `crouter.php` next to `router.php`; `$router` is a Mezon router:

```php
<?php
$router->addRoute('/api/products/[s:id]', function (string $route, array $p) {
    global $m, $config, $user;
    $user->requireAuth();
    if (!isValidObjectId($p['id'])) { http_response_code(400); return; }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($m->{$config['sitedb']}->products->findOne(['_id' => toMongoId($p['id'])]));
}, 'GET');
```

Resolution order: existing `.php` file → built-in routes (`nframework/router.php`) → pages from
Admin → Pages → `crouter.php` → page `_404`.

### Background jobs

```php
// includes/class.ReportJob.php (loaded by the framework autoloader: class.<Name>.php)
class ReportJob extends Job
{
    public $queue = 'reports';
    public $maxAttempts = 5;
    public function __construct(public $payload = []) {}
    public function handle()
    {
        // Throwing an exception retries the job until maxAttempts is reached.
        generateReport($this->payload['month']);
    }
}

ReportJob::dispatch(['month' => '2026-09']);
```

Run a worker per queue: `HTTP_HOST=www.example.com php includes/job_worker.php reports 5`
(in CLI `HTTP_HOST` selects the block of `config.php`; without it the `localhost` block is used).

## Project structure

```
.
├── install.sh            # Automated installer (Debian/Ubuntu)
├── router.php            # Front controller: built-in routes, CMS pages, crouter.php, 404
├── includes/             # Framework core (must be in PHP's include_path)
│   ├── include.php          # Bootstrap: config, MongoDB, security, session, user, i18n, output
│   ├── functions.php        # Helpers (cache, security, Mongo, redirects, files...)
│   ├── class.Base.php       # Form components, dataset, datasetpdo, Icon, TreeView
│   ├── class.User.php       # Users, groups and permissions
│   ├── class.Table.php      # DataTables (local and server-side)
│   ├── class.*.php          # Dialog, Javas, Job, bgprocess, embededArray, XMLS, Sidebar...
│   ├── i18n/                # es-MX / en-US texts and legal templates
│   ├── job_worker.php       # Job queue worker (CLI)
│   ├── config.example.php   # Configuration reference (copy to config.php, not versioned)
│   ├── composer.json        # Runtime dependencies
│   └── composer.phar        # Composer (not versioned; see Installation)
├── nframework/           # Built-in endpoints: router, uploads, datatable, OAuth, Twig templates, installer
├── admin/                # Admin panel
├── docs/                 # Live documentation and examples
├── sat/                  # SAT (CFDI, e.firma, XSD) utilities
├── src/, stubs/          # Laravel package: artisan generators
└── tests/                # PHPUnit test suite
```

## Laravel artisan generators

The repository is also the Composer package `nlared/nframework`. In a Laravel project:

```bash
composer require nlared/nframework
php artisan make:databindingajax {name?} {--path=docs} {--force}
php artisan make:datatableajax {name?} {--path=docs} {--force}
```

They generate nframework pages from [`stubs/`](stubs/) (`make:databingajax` is kept as an alias).

## Testing

```bash
./run-tests.sh             # all tests
./run-tests.sh unit        # tests/Unit
./run-tests.sh feature     # tests/Feature
./run-tests.sh coverage    # HTML report in coverage/
./includes/vendor/bin/phpunit tests/Unit/FunctionsTest.php
```

The tests do not need a MongoDB server. See [tests/README.md](tests/README.md).

Code style: `./includes/vendor/bin/pint`.

## Working with AI assistants

[`AGENTS.md`](AGENTS.md) describes the framework's conventions for AI coding agents (Codex, Cursor, Copilot,
Gemini…); [`CLAUDE.md`](CLAUDE.md) imports it for Claude Code. Projects built on nframework can pull it into
their own files:

```markdown
<!-- site CLAUDE.md -->
@/var/www/dev.nlared.local/AGENTS.md

<!-- site AGENTS.md (the standard has no imports) -->
This site uses nframework: read /var/www/dev.nlared.local/AGENTS.md before working.
```

Machine-readable indexes: [`docs/components.json`](docs/components.json) lists every documentation page and
every component (class, source file, category, example page) — `docs/` builds its menu from it — and
[`llms.txt`](llms.txt) follows the [llms.txt](https://llmstxt.org) format.

## Upgrading

Behavior changes that affect sites built on nframework are listed in [UPGRADING.md](UPGRADING.md) (in Spanish),
with what to check in each site.

## Contributing

1. Create a branch from `master`: `git checkout -b feature/my-change`
2. Add or update the example in `docs/` when you change a component, and register it in `docs/components.json`.
3. Run `./run-tests.sh`.
4. Open a pull request.

## License

MIT – see [LICENSE](LICENSE).

## Acknowledgments

[MongoDB PHP Library](https://github.com/mongodb/mongo-php-library) ·
[Metro UI](https://metroui.org.ua/) ·
[Twig](https://twig.symfony.com/) ·
[Mezon Router](https://github.com/alexdodonov/mezon-router) ·
[PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) ·
[PhpWord](https://github.com/PHPOffice/PHPWord) ·
[Dompdf](https://github.com/dompdf/dompdf) ·
[PHPMailer](https://github.com/PHPMailer/PHPMailer) ·
[OTPHP](https://github.com/Spomky-Labs/otphp) ·
[Intervention Image](https://image.intervention.io/) ·
[phpcfdi](https://github.com/phpcfdi)
