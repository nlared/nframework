<?php

/**
 * Usuario de la colección users; el actual está en $user (el invitado tiene username = 'guest'). Los campos
 * del documento se leen como propiedades ($user->email) o como arreglo ($user['email']); _id se devuelve como
 * texto. Asignar una propiedad la guarda de inmediato con $set y unset() hace $unset; username y _id no se
 * pueden cambiar así. Las contraseñas nunca se buscan en la base: se verifican con nfPasswordVerify().
 *
 *   $user->requireAuth();
 *   if ($user->in('admins') || $user->can('reportes')) { ... }
 *   $user->telefono = $tel;                       // updateOne inmediato
 *   $otro = new User(['username' => $correo]);    // o ['_id' => $id]
 *   $u = User::authenticate($correo, $clave);     // User|null
 *
 * @implements ArrayAccess<string, mixed>
 * @property-read string $_id Id del usuario en texto ('' si no existe).
 * @property-read string $fullname nombres + primerap + segundoap.
 * @property string $username
 * @property mixed $permissions Mapa permiso => bool (ver can()).
 */
class User implements ArrayAccess
{
    /** @var array<string, mixed> Documento del usuario (con _id en texto); [] si no se encontró. */
    public $info;
    /** @var MongoDB\Client */
    private $m;
    /** @var string Base del sitio. */
    private $db;
    /** @var array<string, true>|null Grupos del usuario, cargados una vez por petición. */
    private ?array $groupCache = null;
    /** @var Notifications */
    public $notifications;
    /**
     * Busca el usuario con los criterios de $info (findOne en users). Un _id en texto se convierte a ObjectId;
     * 'password' no se consulta: se verifica contra el hash. Si no hay coincidencia el objeto queda vacío
     * (isLoggedIn() = false).
     *
     * @global MongoDB\Client $m
     * @global class_config $config
     * @param array<string, mixed> $info Filtro: ['_id' => $id], ['username' => $correo], opcionalmente con 'password'.
     */
    public function __construct($info)
    {
        global $m, $config;
        $this->m = $m;
        $this->info = [];
        $find = $info;
        if ($config['sitedb'] != '') {
            $this->db = $config['sitedb']; // /checar usuario no injection
            if (isset($info['_id'])) {
                // Se busca con el ObjectId: antes $find conservaba el texto y new User(['_id' => '...'])
                // (p.ej. /account/twofa con $_SESSION['tmp_user']) nunca encontraba al usuario.
                if (isValidObjectId($info['_id'])) {
                    $find['_id'] = tomongoid($info['_id']);
                }
            }
            $password = null;
            if (array_key_exists('password', $find)) {
                // La contraseña nunca se busca en la base de datos: se verifica después con nfPasswordVerify().
                $password = (string) $find['password'];
                unset($find['password']);
            }
            $info = $this->m->{$config['sitedb']}->users->findOne($find);
            if (!empty($info) && $password !== null && !nfPasswordVerify($password, $info['password'] ?? null)) {
                $info = null;
            }
            if (! empty($info)) {
                $id = (string) $info->_id;
                $this->info = mongotoarray($info);
                $this->info['_id'] = $id;
                if (! empty($this->info['activationcode']) && $this->info['activationcode'] != $info['activationcode']) {
                    header('Location: /account/activate');
                    exit();
                }
            }
        }
        $this->notifications = new Notifications;
    }
    /**
     * Autentica por usuario (sin distinguir mayúsculas) y contraseña.
     * Si el hash almacenado es legado (sha512 sin sal) se migra a password_hash().
     *
     * @global MongoDB\Client $m
     * @global class_config $config
     * @param string $username
     * @param string $password Contraseña en claro.
     * @return User|null null si no existe o la contraseña no coincide.
     */
    public static function authenticate($username, $password): ?User
    {
        global $m, $config;
        if (!is_string($username) || !is_string($password) || trim($username) === '' || $password === '') {
            return null;
        }
        $doc = $m->{$config['sitedb']}->users->findOne([
            'username' => new MongoDB\BSON\Regex('^' . preg_quote(trim($username), '/') . '$', 'i'),
        ]);
        if (empty($doc) || $doc['username'] === 'guest') {
            return null;
        }
        $stored = $doc['password'] ?? null;
        if (!nfPasswordVerify($password, $stored) && !nfPasswordVerify(trim($password), $stored)) {
            return null;
        }
        if (password_needs_rehash((string) $stored, PASSWORD_DEFAULT)) {
            $m->{$config['sitedb']}->users->updateOne(['_id' => $doc['_id']], ['$set' => ['password' => nfPasswordHash($password)]]);
        }
        return new User(['_id' => $doc['_id']]);
    }

    /**
     * true si hay un usuario real (no el invitado 'guest').
     *
     * @return bool
     */
    public  function isLoggedIn(): bool
    {
        return !empty($this->info['username']) && $this->info['username'] != 'guest';
    }
    /**
     * Sin sesión guarda la URL actual en $_SESSION['login_redirect'], redirige a /account/login y termina.
     *
     * @return void
     */
    public function requireAuth()
    {
        if (!$this->isLoggedIn()) {
            $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /account/login');
            exit();
        }
    }

    /**
     * Valor booleano de permissions[$verb] del documento del usuario (acepta true/false, 1/0, 'true'/'false',
     * 'on'/'off'). false si no existe.
     *
     * @param string $verb Nombre del permiso.
     * @return bool
     */
    public function can($verb)
    {
        $val = isset($this->info['permissions']) && array_key_exists($verb, (array)$this->info['permissions'])
            ? $this->info['permissions'][$verb]
            : false;
        // Support common boolean representations (true/false, 1/0, "true"/"false", "on"/"off")
        $filtered = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $filtered === null ? (bool) $val : $filtered;
    }

    /**
     * true si el usuario está en el grupo $verb (colección usersgroups, campo users). Los grupos se consultan una
     * sola vez por petición.
     *
     * @global class_config $config
     * @param string $verb Nombre del grupo ('admins', 'developers'...).
     * @return bool
     */
    public function in($verb)
    {
        global $config;
        if (empty($this->info['_id'])) {
            return false;
        }
        // Una sola consulta por petición trae todos los grupos del usuario; in('admins'),
        // in('developers'), etc. se responden después sin volver a MongoDB.
        if ($this->groupCache === null) {
            $this->groupCache = [];
            foreach ($this->m->{$config['sitedb']}->usersgroups->find(
                ['users' => tomongoid($this->info['_id'])],
                ['projection' => ['_id' => 0, 'name' => 1]]
            ) as $group) {
                $this->groupCache[(string) ($group['name'] ?? '')] = true;
            }
        }
        return isset($this->groupCache[(string) $verb]);
    }

    /**
     * Inserta un usuario: username en minúsculas y password con nfPasswordHash(). No valida duplicados ni longitud
     * (NF_PASSWORD_MIN_LENGTH): hágalo antes.
     *
     * @global class_config $config
     * @global MongoDB\Client $m
     * @param array<string, mixed> $info Con 'username' y 'password' en claro, más los campos que se quieran guardar.
     * @return User
     */
    public static function create($info): User
    {
        global $config, $m;
        $info['username'] = strtolower($info['username']);
        $info['password'] = nfPasswordHash((string) $info['password']);
        $info['_id'] = new MongoDB\BSON\ObjectId();
        $m->{$config['sitedb']}->users->insertOne($info);
        return new User(['_id' =>  $info['_id']]);
    }

    /**
     * Documento completo del usuario como arreglo.
     *
     * @return array<string, mixed>
     */
    public function data()
    {
        return $this->info;
    }

    /**
     * URL de la foto de perfil ($height = $width si se omite). Usa AVIF o WebP según el navegador
     * (nfImageFormat()); la ruta /images/pngto{formato}/users/ la sirve nframework/router.php.
     *
     * @param int $width
     * @param int|null $height
     * @return string URL relativa.
     */
    public function gravatar($width = 32, $height = null)
    {
        $width = max(1, min(2048, (int) $width ?: 32));
        $height = $height === null || $height === '' ? $width : max(1, min(2048, (int) $height ?: $width));
        $format = nfImageFormat();
        return '/images/pngto' . $format . '/users/' . $width . '/' . $height . '/' . $this->info['_id'] . '.' . $format;
    }

    /**
     * HTML del menú de usuario de la plantilla: iniciar sesión / registro para invitados; perfil, tema y cerrar
     * sesión para usuarios.
     *
     * @global string $themecolor
     * @global class_config $config
     * @global ThemeSwitcher $themeswitcher
     * @return string HTML.
     */
    public function usermenu()
    {
        global $themecolor, $config, $themeswitcher;
        $addtheme = ' ' . $themecolor;
        $csrfToken = csrfToken('/account/login');
        // Marcador de la sesión para main.js (reenvía la suscripción push solo si cambia); no revela el id.
        $pushSync = substr(hash('sha256', session_id()), 0, 16);
        $registerButton = '';
        if ($this->info['username'] != 'guest' && $this->info['username'] != '') {
            $safeUsername = htmlspecialchars((string) $this->info['username'], ENT_QUOTES, 'UTF-8');
            $avatar32 = htmlspecialchars($this->gravatar(32), ENT_QUOTES, 'UTF-8');
            $avatar120 = htmlspecialchars($this->gravatar(120), ENT_QUOTES, 'UTF-8');
            $result = <<<HTML
                <a href="#" class="app-bar-item">
                    <img src="{$avatar32}" alt="user picture" class="avatar">
                    <span class="ml-2 app-bar-name">{$safeUsername}</span>
                </a>
                <div class="d-menu context drop-down place-right" data-role="dropdown" data-stay-on-click="true" id="logindrop">
                    <div class="p-3 bg-white fg-black text-center" style="width:300px">
                        <img src="{$avatar120}" alt="user picture" class="avatar">
                        <div class="h4 mb-0">{$safeUsername}</div>
                        <div>{$this->title}</div>
                    </div>
                    <div class="bg-white d-flex flex-justify-between flex-equal-items p-2">
                        <a href="/account/myprofile.php" class="button flat-button fg-black">
                            <span class="mif-profile icon"></span>&nbsp;Perfil</a>
                        <a href="/account/cpassword.php" class="button flat-button fg-black">
                            <span class="mif-key"></span>&nbspContraseña</a>
                    </div>
                    <div class="bg-white d-flex flex-justify-between flex-equal-items p-2">
                        {$themeswitcher}
                    </div>
                    <div class="bg-white d-flex flex-justify-between flex-equal-items p-2 bg-light">
                        <a href="#" class="button fg-black mr-1">
                            <span class="mif-bug"></span>&nbsp;Reportar un problema</a>
                        <a href="/account/logout" class="button fg-black">
                            <span class="mif-exit"></span>&nbsp;Salir</a>
                    </div>
                </div>
HTML;
        } else {
            $result = <<<HTML
<a href="#" class="app-bar-item">
    <span class="mif-enter icon"></span>
    <span class="visible-md">&nbsp;Iniciar</span>
</a>
<div class="d-menu context drop-down place-right" data-role="dropdown" data-stay-on-click="true" id="logindrop">
    <div class="p-3 " style="width:300px">
        <form method="POST" data-role="validator" action="/account/login">
            <input type="hidden" name="CSRFToken" value="{$csrfToken}">
            <h4 class="text-light">Iniciar sesión...</h4>
            <div class="frm-group">
                <label>Usuario</label>
                <input name="login[username]" data-role="input" data-prepend="<span class='mif-account-circle'></span>"  
                type="text" data-validate="required">
            </div>
            <div class="frm-group">
                <label>Contraseña</label>
                <input name="login[password]" data-role="input" data-prepend="<span class='mif-lock'></span>" 
                type="password" data-validate="required">
            </div>
            <label class="input-control checkbox small-check">
                <input name="login[remember]" type="checkbox">
                <span class="check"></span>
                <span class="caption">Recordar me</span>
            </label>
            <button type="button" class="button mini js-push-btn" data-sync="{$pushSync}"></button><br>
            {$themeswitcher}
            <div class="d-inline-flex">
                <button type="button" class="button" onclick="Metro.getPlugin('#logindrop','dropdown').close();">Cerrar</button>
                {$registerButton}
                <button name="op" value="Iniciar" class="button" type="submit">Iniciar</button>
            </div>
        </form>
    </div>
</div>
HTML;
        }

        return $result;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->info[$name]);
    }

    /**
     * Guarda el campo en memoria y en MongoDB ($set) si cambió. username y _id se ignoran.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'username':
            case '_id':
                return true;
                break;
            default:
                if ($this->info[$name] != $value) {
                    $this->info[$name] = $value;
                    $this->m->{$this->db}->users->updateOne(
                        ['_id' => tomongoid($this->info['_id'])],
                        ['$set' => [$name => $value]]
                    );
                }
        }
    }

    /**
     * Quita el campo en memoria y en MongoDB ($unset). username y _id se ignoran.
     *
     * @param string $name
     * @return void
     */
    public function __unset($name)
    {
        switch ($name) {
            case 'username':
            case '_id':
                return true;
                break;
            default:
                unset($this->info[$name]);
                $this->m->{$this->db}->users->updateOne(
                    ['_id' => tomongoid($this->info['_id'])],
                    ['$unset' => [$name => '']]
                );
        }
    }

    /**
     * @param string $name Campo del documento, '_id' (texto) o 'fullname'.
     * @return mixed null si el campo no existe.
     */
    public function __get($name)
    {
        $result = null;
        switch ($name) {
            case 'fullname':
                $result = $this->info['nombres'] . ' ' .
                    $this->info['primerap'] . ' ' .
                    $this->info['segundoap'];
                break;
            case '':
                $result = false;
                break;
            case '_id':
                $result = (string) $this->info['_id'];
                break;
            default:
                // if ($this->info)) {
                if (array_key_exists($name, $this->info)) {
                    //     if (property_exists( $this->info,$name)) {
                    $result = $this->info[$name];
                    //   }
                }
        }

        return $result;
    }

    /**
     * @return array{db: string, info: array<string, mixed>}
     */
    public function __debugInfo()
    {
        return [
            'db' => $this->db,
            'info' => $this->info,
        ];
    }

    /**
     * Igual que __set(): $user['campo'] = $valor.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $name, mixed $value): void
    {
        switch ($name) {
            case 'username':
            case '_id':
                break;
            default:
                if ($this->info[$name] != $value) {
                    $this->info[$name] = $value;
                    $this->m->{$this->db}->users->updateOne(
                        ['_id' => $this->info['_id']],
                        ['$set' => [$name => $value]]
                    );
                }
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function offsetExists(mixed $name): bool
    {
        return isset($this->info[$name]);
    }

    /**
     * Igual que __unset(): unset($user['campo']).
     *
     * @param string $name
     * @return void
     */
    public function offsetUnset(mixed $name): void
    {
        switch ($name) {
            case 'username':
            case '_id':
                break;
            default:
                unset($this->info[$name]);
                $this->m->{$this->db}->users->updateOne(
                    ['_id' => $this->info['_id']],
                    ['$unset' => [$name => '']]
                );
        }
    }

    /**
     * Igual que __get(): $user['campo'].
     *
     * @param string $name
     * @return mixed
     */
    public function offsetGet(mixed $name): mixed
    {
        $result = null;
        switch ($name) {
            case 'fullname':
                $result = $this->info['nombres'] . ' ' .
                    $this->info['primerap'] . ' ' .
                    $this->info['segundoap'];
                break;
            case '':
                $result = false;
                break;
            case '_id':
                $result = (string) $this->info['_id'];
                break;
            default:
                // if ($this->info)) {
                if (array_key_exists($name, $this->info)) {
                    //     if (property_exists( $this->info,$name)) {
                    $result = $this->info[$name];
                    //   }
                }
        }

        return $result;
    }
}
