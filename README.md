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
- PHP extensions: `mongodb`, `gd` (or `imagick`), `mbstring`, `openssl`, `json`, `intl`
- MongoDB server (a replica set if you use transactions)
- Composer
- Apache or Nginx
- Optional: `pdftk`, LibreOffice/`unoconv` (Word/Excel → PDF), Ghostscript/Imagick (PDF → image)

## Installation

1. **Clone the repository** into the web root of the site:
   ```bash
   git clone https://github.com/nlared/nframework.git
   cd nframework
   ```

2. **Install the runtime dependencies** (they live in `includes/`, not in the repository root):
   ```bash
   cd includes && composer install && cd ..
   ```

3. **Create the configuration**:
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

4. **Configure the web server**
   - Document root at the repository root.
   - `includes/` in PHP's `include_path` (pages do `require 'include.php';`).
   - Every request that is not an existing file rewritten to `/router.php`.
   - PHP `error_log` outside the document root (a relative `error_log = php_errors.log` writes the log
     next to the script, where it can be downloaded).

5. **Install the database** (guest/admin users, groups, indexes) and check the environment:
   ```bash
   php nframework/test.php --host=www.example.com
   ```
   The same diagnostic is available in the browser at `/nframework/test.php` (only for admins once installed).

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
├── router.php            # Front controller: built-in routes, CMS pages, crouter.php, 404
├── includes/             # Framework core (must be in PHP's include_path)
│   ├── include.php          # Bootstrap: config, MongoDB, security, session, user, i18n, output
│   ├── functions.php        # Helpers (cache, security, Mongo, redirects, files...)
│   ├── class.Base.php       # Form components, dataset, datasetArray, Icon, TreeView
│   ├── class.User.php       # Users, groups and permissions
│   ├── class.Table.php      # DataTables (local and server-side)
│   ├── class.*.php          # Dialog, Javas, Job, bgprocess, embededArray, XMLS, Sidebar...
│   ├── i18n/                # es-MX / en-US texts and legal templates
│   ├── job_worker.php       # Job queue worker (CLI)
│   ├── config.example.php   # Configuration reference (copy to config.php, not versioned)
│   └── composer.json        # Runtime dependencies
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

## Working with Claude Code

[`CLAUDE.md`](CLAUDE.md) describes the framework's conventions for AI assistants. Projects built on
nframework can import it from their own `CLAUDE.md`:

```markdown
@/var/www/dev.nlared.local/CLAUDE.md
```

## Contributing

1. Create a branch from `master`: `git checkout -b feature/my-change`
2. Add or update the example in `docs/` when you change a component.
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
