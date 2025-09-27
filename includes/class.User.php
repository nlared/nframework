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

    public function create($info)
    {
        global $config;
        $info['username'] = strtolower($info['username']);
        $this->info = $this->m->{$config['sitedb']}->users->findOne(['username' => $info['username']]);
        if ($this->info['activationcode'] != '') {
            header('Location: /account/activate');
            exit();
        }

        if ($this->info) {
            $this->info['error'] = 'Cuenta ya existe';
        } else {
            $info['password'] = hash('sha512', $info['password']); // hash
            $info['activationcode'] = uniqid();
            $this->m->{$this->db}->users->insertOne($info);
            $this->info = (array) $this->m->{$this->db}->users->findOne($info);
        }
    }

    public function data()
    {
        return $info;
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
            $result = '
        		<a href="#" class="app-bar-item">
                        <img src="/images/resize/users/32/32/'.$this->info['_id'].'.png" alt="user picture" class="avatar">
                        <span class="ml-2 app-bar-name">'.$this->info['nombres'].'</span>
                    </a>
                    <div class="d-menu context drop-down place-right" data-role="dropdown" id="logindrop">
			<div class="p-3 bg-white fg-black text-center" style="width:300px">
                            <img src="/images/resize/users/120/120/'.$this->info['_id'].'.png" alt="user picture" class="avatar">
                            <div class="h4 mb-0">'.$this->info['nombres'].'</div>
                            <div>'.$this->title.'</div>
                        </div>
                        <div class="bg-white d-flex flex-justify-between flex-equal-items p-2">
                            <a href="/account/myprofile.php" class="button flat-button fg-black">
                            	<span class="mif-profile icon"></span>&nbsp;Perfil</a>
                            <a href="/account/cpassword.php" class="button flat-button fg-black">
                            	<span class="mif-key"></span>&nbspContraseña</a>
                            
                        </div>
                        <div class="bg-white d-flex flex-justify-between flex-equal-items p-2 bg-light">
                            <a href="#" class="button fg-black mr-1">
                            	<span class="mif-bug"></span>&nbsp;Reportar un problema</a>
                            <a href="/account/logout" class="button fg-black">
                            	<span class="mif-exit"></span>&nbsp;Salir</a>
                        </div>
                    </div>
         ';

        } else {
            $result = '<a href="#" class="app-bar-item">
        <span class="mif-enter icon"></span>
        <span class="visible-md">&nbsp;Iniciar</span>
        </a>
        <div class="d-menu context drop-down place-right" data-role="dropdown" id="logindrop" >
			<div class="p-3 " style="width:300px">
                <form method="POST" data-role="validator" action="/account/login">
                	<input type="hidden" name="CSRFToken" value="'.csrfToken('/account/login').'">
                    <h4 class="text-light">Iniciar sesión...</h4>
                    
                    <div class="frm-group">
                    	<label>Usuario</label>
                        <input name="login[username]" data-role="input" data-prepend="<span class=\'mif-account-circle\'></span>"  
                        type="text" data-validate="required">
                    </div>
                    <div class="frm-group">
                    	<label>Contraseña</label>
                        <input name="login[password]" data-role="input" data-prepend="<span class=\'mif-lock\'></span>" 
                        type="password" data-validate="required">
                    </div>
                    <label class="input-control checkbox small-check">
                        <input name="login[remember]" type="checkbox">
                        <span class="check"></span>
                        <span class="caption">Recordar me</span>
                    </label>
                   
                   <button class="button mini js-push-btn"></button><br>
                         '.$themeswitcher.'
		           <div class="d-inline-flex">
                		<button class="button" onclick="Metro.getPlugin(\'#logindrop\',\'dropdown\').close();">Cerrar</button>'.
                    	 ($config['canregister'] ?
                       '<button href="/account/new.php" class="button">Registrate</button>'
                       :
                           ''
                       ).
                       '<button name="op" value="Iniciar" class="button" type="submit">Iniciar</button>
                       </div>
                </form>
            </div>
		</div>';
			
            $resgult = '
                    <div class="button dropdown-toggle">Dropdown</div>
                     <div class="dropdown keep-open" data-role="dropdown" id="logindrop" data-no-close="true" style="width:300px">
                        <form method="POST" data-role="validator" action="/account/login.php">
		                	<input type="hidden" name="CSRFToken" value="'.csrfToken('/account/login.php').'">
		                    <h4 class="text-light">Iniciar sesión...</h4>
		                    <div class="frm-group">
		                        <input name="login[username]" data-label="Usuario" data-role="input" data-prepend="<span class=\'mif-user\'></span>"  
		                        type="text" data-validate="required">
		                    </div>
		                    <div class="frm-group">
		                        <input name="login[password]" data-label="Contraseña" data-role="input" data-prepend="<span class=\'mif-lock\'></span>" 
		                        type="password" data-validate="required">
		                    </div>
		                    <label class="input-control checkbox small-check">
		                        <input name="login[remember]" type="checkbox">
		                        <span class="check"></span>
		                        <span class="caption">Recordar me</span>
		                    </label>
		                    '.$themeswitcher.'
		                	<div class="d-inline-flex">
		                		<button class="button w-50" onclick="Metro.getPlugin(\'#logindrop\',\'dropdown\').close();">Cerrar</button>
			                    <button class="button w-50" name="op" value="Iniciar" type="submit">Iniciar</button>
		                    </div>
		                   
		                   <button class="button mini js-push-btn"></button><br>
		                   '.
                               ($config['canregister'] ? '<button href="/account/new.php" class="button">Registrate</button>' : '').
                                '
                		</form>
                    </div>
                </div>';

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
