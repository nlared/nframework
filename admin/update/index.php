<?php
require '../common2.php';
requireGroup('admins');

// Raíz del repositorio y carpeta de composer (includes).
$repoDir = dirname(__DIR__, 2);
$composerDir = $repoDir . '/includes';

/**
 * Ejecuta un comando sin shell (argv) y devuelve código de salida y salida combinada.
 * HOME y COMPOSER_HOME se fijan porque www-data normalmente no tiene un HOME escribible.
 */
function updRun(array $cmd, string $cwd, bool $stderr = true): array
{
    global $repoDir;
    // Un directorio por usuario del sistema: si otro usuario (p. ej. desde la terminal) creó
    // el de www-data, composer no podría escribir su caché.
    $home = sys_get_temp_dir() . '/nf-update-home-' . (function_exists('posix_geteuid') ? posix_geteuid() : get_current_user());
    if (!is_dir($home)) {
        @mkdir($home, 0700, true);
    }
    $env = [
        'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        'HOME' => $home,
        'COMPOSER_HOME' => $home . '/composer',
        'COMPOSER_NO_INTERACTION' => '1',
        'GIT_TERMINAL_PROMPT' => '0',
        // safe.directory: el repo pertenece a otro usuario distinto al del servidor web
        // (también lo necesita composer, que invoca git internamente).
        'GIT_CONFIG_COUNT' => '1',
        'GIT_CONFIG_KEY_0' => 'safe.directory',
        'GIT_CONFIG_VALUE_0' => $repoDir,
        'LANG' => 'C.UTF-8',
    ];
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => $stderr ? ['redirect', 1] : ['file', '/dev/null', 'w']], $pipes, $cwd, $env);
    if (!is_resource($proc)) {
        return ['code' => -1, 'output' => 'No se pudo ejecutar el comando.'];
    }
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return ['code' => proc_close($proc), 'output' => rtrim((string) $output)];
}

function updGit(array $args): array
{
    global $repoDir;
    return updRun(array_merge(['git', '-C', $repoDir], $args), $repoDir);
}

function updComposer(array $args, bool $stderr = true): array
{
    global $composerDir;
    // Misma versión de PHP que el sitio: /usr/bin/php puede ser otra versión con otras extensiones.
    $php = 'php';
    foreach ([PHP_BINDIR . '/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, PHP_BINDIR . '/php'] as $candidate) {
        if (is_executable($candidate)) {
            $php = $candidate;
            break;
        }
    }
    $r = updRun(array_merge([$php, $composerDir . '/composer.phar'], $args, ['--no-interaction', '--no-ansi', '--working-dir=' . $composerDir]), $composerDir, $stderr);
    if ($r['code'] !== 0 && preg_match('/Could not delete|could not be (created|written)|is not writable|Permission denied/i', $r['output'])) {
        $owner = function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '') : get_current_user();
        $r['output'] .= "\n\n>>> El usuario del servidor web ({$owner}) no puede modificar algunos archivos de composer.\n"
            . ">>> Ejecuta en el servidor: sudo chown -R {$owner}: " . $composerDir . '/vendor ' . $composerDir . '/composer.json ' . $composerDir . '/composer.lock';
    }
    return $r;
}

function updValidPackage($name): bool
{
    return is_string($name) && preg_match('~^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$~', $name) === 1;
}

if ($nframework->isAjax()) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }
    set_time_limit(0);
    ignore_user_abort(true);
    $action = $_POST['action'] ?? '';
    $package = $_POST['package'] ?? '';
    $version = trim((string) ($_POST['version'] ?? ''));
    // Opcional: ignora versión de PHP y extensiones requeridas (ext-*) del servidor.
    $platform = ($_POST['ignore_platform'] ?? '') === '1' ? ['--ignore-platform-reqs'] : [];

    if (in_array($action, ['composer_update_pkg', 'composer_require', 'composer_remove'], true) && !updValidPackage($package)) {
        $result = ['success' => false, 'output' => 'Nombre de paquete inválido (formato vendor/paquete).'];
        exit;
    }
    if ($version !== '' && !preg_match('/^[A-Za-z0-9.*^~<>=|,@ -]{1,64}$/', $version)) {
        $result = ['success' => false, 'output' => 'Restricción de versión inválida.'];
        exit;
    }

    switch ($action) {
        case 'git_status':
            $branch = updGit(['rev-parse', '--abbrev-ref', 'HEAD']);
            $upstream = updGit(['rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{u}']);
            $counts = updGit(['rev-list', '--left-right', '--count', 'HEAD...@{u}']);
            [$ahead, $behind] = $counts['code'] === 0 ? array_map('intval', preg_split('/\s+/', $counts['output'])) : [0, 0];
            $result = [
                'success' => $branch['code'] === 0,
                'branch' => $branch['output'],
                'upstream' => $upstream['code'] === 0 ? $upstream['output'] : '',
                'ahead' => $ahead,
                'behind' => $behind,
                'remote' => updGit(['remote', '-v'])['output'],
                'status' => updGit(['status', '--short'])['output'],
                'log' => updGit(['log', '-15', '--pretty=format:%h  %ad  %an  %s', '--date=short'])['output'],
                'incoming' => $upstream['code'] === 0 ? updGit(['log', '--pretty=format:%h  %ad  %an  %s', '--date=short', 'HEAD..@{u}'])['output'] : '',
            ];
            break;
        case 'git_fetch':
            $r = updGit(['fetch', '--prune']);
            $result = ['success' => $r['code'] === 0, 'output' => $r['output'] ?: 'Fetch completado.'];
            break;
        case 'git_pull':
            // Solo fast-forward: nunca crea merges ni pisa cambios locales.
            $r = updGit(['pull', '--ff-only']);
            $result = ['success' => $r['code'] === 0, 'output' => $r['output']];
            break;
        case 'composer_info':
            $ver = updComposer(['--version']);
            $show = updComposer(['show', '--direct', '--format=json'], false);
            $installed = json_decode($show['output'], true);
            $result = [
                'success' => $ver['code'] === 0,
                'version' => $ver['output'],
                'packages' => $installed['installed'] ?? [],
                'output' => is_array($installed) ? '' : $show['output'],
            ];
            break;
        case 'composer_outdated':
            $r = updComposer(array_merge(['outdated', '--direct', '--format=json'], $platform), false);
            $data = json_decode($r['output'], true);
            $result = [
                'success' => is_array($data),
                'packages' => $data['installed'] ?? [],
                'output' => is_array($data) ? '' : $r['output'],
            ];
            break;
        case 'composer_install':
            $r = updComposer(array_merge(['install', '--no-dev', '--optimize-autoloader'], $platform));
            $result = ['success' => $r['code'] === 0, 'output' => $r['output']];
            break;
        case 'composer_update':
            $r = updComposer(array_merge(['update', '--no-dev', '--optimize-autoloader'], $platform));
            $result = ['success' => $r['code'] === 0, 'output' => $r['output']];
            break;
        case 'composer_update_pkg':
            $r = updComposer(array_merge(['update', $package, '--with-dependencies', '--no-dev', '--optimize-autoloader'], $platform));
            $result = ['success' => $r['code'] === 0, 'output' => $r['output']];
            break;
        case 'composer_require':
            $r = updComposer(array_merge(['require', $version === '' ? $package : $package . ':' . $version, '--update-no-dev', '--optimize-autoloader'], $platform));
            $result = ['success' => $r['code'] === 0, 'output' => $r['output']];
            break;
        case 'composer_remove':
            $r = updComposer(array_merge(['remove', $package, '--update-no-dev', '--optimize-autoloader'], $platform));
            $result = ['success' => $r['code'] === 0, 'output' => $r['output']];
            break;
        case 'composer_dump':
            $r = updComposer(['dump-autoload', '--optimize', '--no-dev']);
            $result = ['success' => $r['code'] === 0, 'output' => $r['output']];
            break;
        case 'composer_validate':
            $r = updComposer(['validate', '--no-check-publish']);
            $result = ['success' => $r['code'] === 0, 'output' => $r['output']];
            break;
        case 'composer_selfupdate':
            $r = updComposer(['self-update']);
            $result = ['success' => $r['code'] === 0, 'output' => $r['output']];
            break;
        default:
            http_response_code(400);
            $result = ['success' => false, 'output' => 'Acción desconocida.'];
    }
    exit;
}

$nframework->usecommon = true;
?>
<div class="container p-5">
    <div class="box shadow-large">
        <div class="box-title"><?= $nframework->language['updates'] ?></div>
        <ul data-role="tabs" data-expand="true">
            <li><a href="#upd-git"><span class="mif-github"></span> Git</a></li>
            <li><a href="#upd-composer"><span class="mif-cube"></span> Composer</a></li>
        </ul>
        <div class="border bd-default no-border-top p-3">
            <div id="upd-git">
                <div class="mb-2">
                    <button class="button" onclick="updGitStatus()"><span class="mif-loop2"></span> Actualizar estado</button>
                    <button class="button" onclick="updRun('git_fetch', {}, updGitStatus)"><span class="mif-download"></span> Fetch</button>
                    <button class="button primary" onclick="updConfirm('¿Actualizar el código con git pull (--ff-only)?', 'git_pull', {}, updGitStatus)"><span class="mif-cloud-download"></span> Pull</button>
                </div>
                <table class="table compact subcompact">
                    <tr><th style="width:160px">Rama</th><td id="upd-branch"></td></tr>
                    <tr><th>Upstream</th><td id="upd-upstream"></td></tr>
                    <tr><th>Adelante / Atrás</th><td id="upd-aheadbehind"></td></tr>
                    <tr><th>Remotos</th><td><pre id="upd-remote" class="stay-on m-0"></pre></td></tr>
                </table>
                <h6>Cambios locales</h6>
                <pre id="upd-status" class="stay-on bg-light p-2"></pre>
                <h6>Commits pendientes por descargar</h6>
                <pre id="upd-incoming" class="stay-on bg-light p-2"></pre>
                <h6>Últimos commits</h6>
                <pre id="upd-log" class="stay-on bg-light p-2"></pre>
            </div>
            <div id="upd-composer">
                <div class="mb-2" id="upd-composer-version"></div>
                <div class="mb-2">
                    <label title="Ignora la versión de PHP y las extensiones (ext-*) que exigen los paquetes">
                        <input type="checkbox" id="upd-ignore-platform"> Ignorar requisitos de plataforma (--ignore-platform-reqs)
                    </label>
                </div>
                <div class="mb-2">
                    <button class="button" onclick="updComposerInfo()"><span class="mif-loop2"></span> Recargar</button>
                    <button class="button" onclick="updComposerOutdated()"><span class="mif-search"></span> Buscar actualizaciones</button>
                    <button class="button" onclick="updConfirm('¿Ejecutar composer install?', 'composer_install', {}, updComposerInfo)"><span class="mif-download"></span> Install</button>
                    <button class="button primary" onclick="updConfirm('¿Ejecutar composer update de TODOS los paquetes?', 'composer_update', {}, updComposerInfo)"><span class="mif-cloud-download"></span> Update todo</button>
                    <button class="button" onclick="updRun('composer_dump')"><span class="mif-files-empty"></span> Dump autoload</button>
                    <button class="button" onclick="updRun('composer_validate')"><span class="mif-checkmark"></span> Validate</button>
                    <button class="button" onclick="updConfirm('¿Actualizar composer.phar?', 'composer_selfupdate', {}, updComposerInfo)"><span class="mif-upload"></span> Self-update</button>
                </div>
                <div class="row mb-2">
                    <div class="cell-md-5"><input type="text" id="upd-pkg" data-role="input" placeholder="vendor/paquete"></div>
                    <div class="cell-md-3"><input type="text" id="upd-ver" data-role="input" placeholder="versión (opcional) ej. ^2.0"></div>
                    <div class="cell-md-4">
                        <button class="button success" onclick="updRequire()"><span class="mif-plus"></span> Require</button>
                    </div>
                </div>
                <table class="table compact striped">
                    <thead><tr><th>Paquete</th><th>Instalada</th><th>Disponible</th><th>Descripción</th><th style="width:110px">Opciones</th></tr></thead>
                    <tbody id="upd-packages"></tbody>
                </table>
            </div>
        </div>
        <h6 class="mt-4">Salida</h6>
        <pre id="upd-output" class="stay-on bg-dark fg-white p-2" style="max-height:400px;overflow:auto;white-space:pre-wrap"></pre>
    </div>
</div>
<script>
    var updOutdated = {};

    function updEsc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    function updPost(action, data) {
        return $.ajax({
            url: 'index.php',
            type: 'post',
            dataType: 'json',
            data: $.extend({
                action: action,
                ignore_platform: $('#upd-ignore-platform').is(':checked') ? '1' : '0'
            }, data || {})
        });
    }

    function updRun(action, data, after) {
        var $buttons = $('#upd-git button, #upd-composer button').prop('disabled', true);
        $('#upd-output').text('Ejecutando ' + action + '...');
        return updPost(action, data).done(function (r) {
            $('#upd-output').text(r.output || (r.success ? 'OK' : 'Error'));
            toast(r.success ? 'Completado' : 'Error, revisa la salida');
            if (after) after();
        }).fail(function (xhr) {
            $('#upd-output').text('Error ' + xhr.status + ': ' + xhr.responseText);
        }).always(function () {
            $buttons.prop('disabled', false);
        });
    }

    function updConfirm(question, action, data, after) {
        if (confirm(question)) updRun(action, data, after);
    }

    function updGitStatus() {
        updPost('git_status').done(function (r) {
            $('#upd-branch').text(r.branch);
            $('#upd-upstream').text(r.upstream || '(sin upstream)');
            $('#upd-aheadbehind').html(r.ahead + ' / ' + (r.behind > 0 ? '<span class="fg-red text-bold">' + r.behind + '</span>' : r.behind));
            $('#upd-remote').text(r.remote);
            $('#upd-status').text(r.status || 'Sin cambios locales.');
            $('#upd-incoming').text(r.incoming || 'Nada pendiente (usa Fetch para consultar el remoto).');
            $('#upd-log').text(r.log);
        });
    }

    function updRenderPackages(packages) {
        var html = '';
        $.each(packages, function (i, p) {
            var latest = updOutdated[p.name];
            html += '<tr><td>' + updEsc(p.name) + '</td><td>' + updEsc(p.version) + '</td><td>' +
                (latest ? '<span class="fg-orange text-bold">' + updEsc(latest) + '</span>' : '') +
                '</td><td>' + updEsc(p.description) + '</td><td>' +
                '<button class="button small" title="Actualizar" onclick="updPkg(\'composer_update_pkg\', \'' + updEsc(p.name) + '\')"><span class="mif-cloud-download"></span></button> ' +
                '<button class="button small alert" title="Eliminar" onclick="updPkg(\'composer_remove\', \'' + updEsc(p.name) + '\')"><span class="mif-cross"></span></button>' +
                '</td></tr>';
        });
        $('#upd-packages').html(html);
    }

    var updPackages = [];

    function updComposerInfo() {
        updPost('composer_info').done(function (r) {
            $('#upd-composer-version').text(r.version);
            updPackages = r.packages || [];
            updRenderPackages(updPackages);
            if (r.output) $('#upd-output').text(r.output);
        });
    }

    function updComposerOutdated() {
        $('#upd-output').text('Consultando paquetes desactualizados...');
        updPost('composer_outdated').done(function (r) {
            updOutdated = {};
            $.each(r.packages || [], function (i, p) {
                updOutdated[p.name] = p.latest;
            });
            updRenderPackages(updPackages);
            $('#upd-output').text(r.output || (Object.keys(updOutdated).length + ' paquete(s) con actualización disponible.'));
        });
    }

    function updPkg(action, name) {
        var verb = action === 'composer_remove' ? 'Eliminar' : 'Actualizar';
        updConfirm('¿' + verb + ' ' + name + '?', action, {package: name}, updComposerInfo);
    }

    function updRequire() {
        var pkg = $.trim($('#upd-pkg').val());
        if (!pkg) return;
        updConfirm('¿Instalar ' + pkg + '?', 'composer_require', {package: pkg, version: $.trim($('#upd-ver').val())}, updComposerInfo);
    }

    $(function () {
        updGitStatus();
        updComposerInfo();
    });
</script>
