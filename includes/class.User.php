<?php

class User implements ArrayAccess
{
    public $info;

    private $m;

    private $db;

    public $notifications;

    public function __construct($info)
    {
        global $m, $config;
        $this->m = $m;
        $this->info = [];
        $find = $info;
        if ($config['sitedb'] != '') {
            $this->db = $config['sitedb']; // /checar usuario no injection
            if (isset($info['_id'])) {
                $info['_id'] = tomongoid($info['_id']);
            }
            if (isset($info['password'])) {
                $passwords = [];
                foreach ($config['users']['algos'] as $algo) {
                    $passwords[] = hash($algo, $info['password']);
                }
                $find['password'] = ['$in' => $passwords];
            }
            $info = $this->m->{$config['sitedb']}->users->findOne($find);
            if (! empty($info)) {
                $id = (string) $info->_id;
                $this->info = mongotoarray($info);
                $this->info['_id'] = $id;
                if (! empty($this->info['activationcode']) && $this->info['activationcode'] != $info['activationcode']) {
                    header('Location: /account/activate.php');
                    exit();
                }

            }
        }
        $this->notifications = new Notifications;
    }

    public function requireAuth()
    {
        $_SESSION['nframework']['logiopage'] = $_SERVER['DOCUMENT_URI'];
        if ($this->info['username'] == 'guest') {
            header('location: /account/login.php');
            exit();
        }
    }

    public function can($verb)
    {
        return $this->info['permissions'][$verb] == 'on';
    }

    public function in($verb)
    {
        global $config;
        $f = $this->m->{$config['sitedb']}->usersgroups->findOne([
            'users' => tomongoid($this->info['_id'] ),
            'name' => $verb,
        ]);
        return !empty($f);
    }

    public static function create($info) : User
    {
        global $config , $m;
        $info['username'] = strtolower($info['username']);
        $info['password'] = hash('sha512', $info['password']); // hash
        $info['_id'] = new MongoDB\BSON\ObjectId();
        $m->{$config['sitedb']}->users->insertOne($info);
        return new User(['_id' =>  $info['_id']]);         
    }         

    public function data()
    {
        return $this->info;
    }

    public function gravatar($width = '', $height = '')
    {
        return '/images/resize/users/32/32/'.$this->info['_id'].'.png';
    }

    public function usermenu()
    {
        global $themecolor,$config,$themeswitcher;
		
	
		
        $addtheme = ' '.$themecolor;
        if ($this->info['username'] != 'guest' && $this->info['username'] != '') {
            $result = <<<HTML
                <a href="#" class="app-bar-item">
                    <img src="/images/resize/users/32/32/{$this->info['_id']}.png" alt="user picture" class="avatar">
                    <span class="ml-2 app-bar-name">{$this->info['username']}</span>
                </a>
                <div class="d-menu context drop-down place-right" data-role="dropdown" id="logindrop">
                    <div class="p-3 bg-white fg-black text-center" style="width:300px">
                        <img src="/images/resize/users/120/120/{$this->info['_id']}.png" alt="user picture" class="avatar">
                        <div class="h4 mb-0">{$this->info['username']}</div>
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
<div class="d-menu context drop-down place-right" data-role="dropdown" id="logindrop" >
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
            <button class="button mini js-push-btn"></button><br>
            {$themeswitcher}
            <div class="d-inline-flex">
                <button class="button" onclick="Metro.getPlugin('#logindrop','dropdown').close();">Cerrar</button>
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

    public function __isset($name)
    {
        return isset($this->info[$name]);
    }

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
                    ['$unset' => [$name => '']]);
        }
    }

    public function __get($name)
    {
        $result = null;
        switch ($name) {
            case 'fullname':
                $result = $this->info['nombres'].' '.
                $this->info['primerap'].' '.
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

    public function __debugInfo()
    {
        return [
            'db' => $this->db,
            'info' => $this->info,
        ];
    }

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

    public function offsetExists(mixed $name): bool
    {
        return isset($this->info[$name]);
    }

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
                    ['$unset' => [$name => '']]);
        }
    }

    public function offsetGet(mixed $name): mixed
    {
        $result = null;
        switch ($name) {
            case 'fullname':
                $result = $this->info['nombres'].' '.
                $this->info['primerap'].' '.
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
