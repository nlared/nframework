<?php

use MongoDB\BSON\UTCDateTime;
use PhpCfdi\Credentials\Certificate;
use PhpCfdi\Credentials\Credential;

/**
 * Selector de certificados del SAT: e.firma (FIEL), CSD o cualquiera de los dos.
 *
 * - storage 'client': el .cer y el .key (cifrado con su contraseña) se guardan en el localStorage del
 *   navegador (clave "llaves", el mismo formato que sat/sing.php). La llave nunca sale del navegador y la
 *   firma se hace con jsrsasign: nfEfirma['<id>'].sign(cadena).
 * - storage 'server': el .cer, el .key y la contraseña se guardan cifrados (AES-256-GCM con
 *   $config['efirma_key']) en la colección efirmas. Antes de guardar se exige una firma autógrafa con
 *   Signature_pad como constancia de la autorización. Se firma desde PHP con Efirma::sign() o
 *   Efirma::credential(); el navegador nunca puede pedir firmas de una llave del servidor.
 *
 * El valor elegido viaja en el input oculto `name`: "client:<nocert>" o "server:<_id>".
 */
#[AllowDynamicProperties]
class Efirma
{
    public const COLLECTION = 'efirmas';

    public const TIPOS = ['fiel', 'csd', 'any'];

    public const STORAGES = ['client', 'server'];

    public const ENDPOINT = '/nframework/efirma.php';

    public $id = '';

    public $name = '';

    public $caption = '';

    public $tipo = 'any';       // 'fiel' | 'csd' | 'any'

    public $storage = 'client'; // 'client' | 'server'

    public $rfc = '';           // si no está vacío, solo se aceptan certificados de este RFC

    public $onSelect = '';      // JS con la variable `info` ({value, source, nocert, rfc, tipo, nombre, vence})

    public $consent = '';       // texto que el usuario acepta al guardar en el servidor

    public $value = '';         // selección inicial: "client:<nocert>" o "server:<_id>"

    public function __construct($options = [])
    {
        global $nframework;
        foreach ($options as $option => $value) {
            $this->{$option} = $value;
        }
        if (! in_array($this->tipo, self::TIPOS, true)) {
            throw new InvalidArgumentException('Efirma: tipo debe ser fiel, csd o any');
        }
        if (! in_array($this->storage, self::STORAGES, true)) {
            throw new InvalidArgumentException('Efirma: storage debe ser client o server');
        }
        $this->rfc = strtoupper(trim((string) $this->rfc));
        if ($this->id === '') {
            $this->id = $this->name !== ''
                ? str_replace(['[', ']', '.'], ['_', '', '_'], (string) $this->name)
                : 'efirma'.$nframework->counters('efirma');
        }
        if ($this->name === '') {
            $this->name = $this->id;
        }
        if ($this->consent === '') {
            $this->consent = 'Autorizo que este sitio conserve mi '.self::tipoLabel($this->tipo).
                ' cifrada en su servidor y la use para firmar en mi nombre las operaciones que yo solicite.';
        }
    }

    /* ------------------------------------------------------------------------------------------
     * Utilidades puras (sin MongoDB): se prueban en tests/Unit/EfirmaTest.php
     * ---------------------------------------------------------------------------------------- */

    public static function tipoLabel(string $tipo): string
    {
        return ['fiel' => 'e.firma', 'csd' => 'CSD', 'any' => 'e.firma o CSD'][$tipo] ?? $tipo;
    }

    /** 'fiel' o 'csd'. Igual que phpcfdi: solo los CSD tienen OU (sucursal). */
    public static function tipoOf(Certificate $certificate): string
    {
        return $certificate->satType()->isFiel() ? 'fiel' : 'csd';
    }

    public static function tipoAllowed(string $required, string $actual): bool
    {
        return $required === 'any' || $required === $actual;
    }

    /** Número de certificado del SAT (20 dígitos) a partir del serial. */
    public static function noCertificado(Certificate $certificate): string
    {
        return $certificate->serialNumber()->bytes();
    }

    /**
     * Revisa que el certificado sirva para el componente. Devuelve el mensaje de error o null.
     * La validación de la cadena del SAT se hace aparte (satIssued) porque depende de la configuración.
     */
    public static function checkCertificate(Certificate $certificate, string $tipo, string $rfc = '', ?DateTimeImmutable $now = null): ?string
    {
        $actual = self::tipoOf($certificate);
        if (! self::tipoAllowed($tipo, $actual)) {
            return 'Se esperaba '.self::tipoLabel($tipo).' y el archivo es '.self::tipoLabel($actual).'.';
        }
        if ($rfc !== '' && strtoupper($certificate->rfc()) !== strtoupper($rfc)) {
            return 'El certificado no pertenece al RFC '.$rfc.'.';
        }
        if (! $certificate->validOn($now)) {
            return 'El certificado no está vigente (vence '.$certificate->validToDateTime()->format('Y-m-d').').';
        }

        return null;
    }

    /** Datos públicos del certificado que se envían al navegador. */
    public static function certificateInfo(Certificate $certificate): array
    {
        return [
            'nocert' => self::noCertificado($certificate),
            'rfc' => $certificate->rfc(),
            'nombre' => $certificate->legalName(),
            'sucursal' => $certificate->branchName(),
            'tipo' => self::tipoOf($certificate),
            'vence' => $certificate->validToDateTime()->format('Y-m-d'),
        ];
    }

    /** AES-256-GCM: base64('g' . IV(12) . TAG(16) . datos). */
    public static function encrypt(string $plain, string $key): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $data = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'nfefirma');
        if ($data === false) {
            throw new RuntimeException('No se pudo cifrar la e.firma');
        }

        return base64_encode('g'.$iv.$tag.$data);
    }

    public static function decrypt(string $blob, string $key): string
    {
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < 29 || $raw[0] !== 'g') {
            throw new RuntimeException('e.firma cifrada inválida');
        }
        $plain = openssl_decrypt(substr($raw, 29), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 1, 12), substr($raw, 13, 16), 'nfefirma');
        if ($plain === false) {
            throw new RuntimeException('No se pudo descifrar la e.firma (¿cambió efirma_key?)');
        }

        return $plain;
    }

    /* ------------------------------------------------------------------------------------------
     * Dependen de $config / MongoDB
     * ---------------------------------------------------------------------------------------- */

    /** Llave de cifrado de $config['efirma_key'] (32 bytes en base64). Solo en config.php, nunca en MongoDB. */
    public static function secretKey(): string
    {
        global $config;
        // fileValue(): $config combina el documento de MongoDB encima del archivo; la llave solo se toma del archivo.
        $value = $config instanceof class_config ? $config->fileValue('efirma_key') : ($config['efirma_key'] ?? '');
        $key = base64_decode((string) $value, true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('Configure efirma_key en includes/config.php para guardar e.firmas en el servidor');
        }

        return $key;
    }

    /** true si el certificado fue emitido por el SAT según $config['sat_ca_bundle']. */
    public static function satIssued(Certificate $certificate): bool
    {
        global $config;
        $cabundle = (string) ($config['sat_ca_bundle'] ?? '');

        return $cabundle !== '' && file_exists($cabundle)
            && openssl_x509_checkpurpose($certificate->pem(), X509_PURPOSE_ANY, [$cabundle]) === true;
    }

    private static function collection()
    {
        global $m, $config;

        return $m->{$config['sitedb']}->{self::COLLECTION};
    }

    private static function filter($id, $owner): ?array
    {
        if (! isValidObjectId($id)) {
            return null;
        }
        $filter = ['_id' => toMongoId($id)];
        if ($owner !== null) {
            $filter['owner'] = $owner;
        }

        return $filter;
    }

    /** e.firmas del servidor de un usuario (solo datos públicos). */
    public static function listFor($owner, string $tipo = 'any', string $rfc = ''): array
    {
        $filter = ['owner' => $owner];
        if ($tipo !== 'any') {
            $filter['tipo'] = $tipo;
        }
        if ($rfc !== '') {
            $filter['rfc'] = strtoupper($rfc);
        }
        $items = [];
        foreach (self::collection()->find($filter, ['sort' => ['rfc' => 1, 'vence' => -1]]) as $doc) {
            $items[] = self::publicDoc($doc);
        }

        return $items;
    }

    public static function publicDoc($doc): array
    {
        return [
            'value' => 'server:'.$doc['_id'],
            'source' => 'server',
            'id' => (string) $doc['_id'],
            'nocert' => (string) $doc['nocert'],
            'rfc' => (string) $doc['rfc'],
            'nombre' => (string) ($doc['nombre'] ?? ''),
            'sucursal' => (string) ($doc['sucursal'] ?? ''),
            'tipo' => (string) $doc['tipo'],
            'vence' => $doc['vence'] instanceof UTCDateTime ? $doc['vence']->toDateTime()->format('Y-m-d') : '',
        ];
    }

    /** Datos públicos de una e.firma del servidor, o null. $owner = null solo en procesos sin usuario (CLI). */
    public static function find($id, $owner = null): ?array
    {
        $filter = self::filter($id, $owner);
        $doc = $filter ? self::collection()->findOne($filter) : null;

        return $doc ? self::publicDoc($doc) : null;
    }

    /** Credential de phpcfdi lista para firmar (p. ej. new Fiel(Efirma::credential($id)) en descarga masiva). */
    public static function credential($id, $owner = null): Credential
    {
        $filter = self::filter($id, $owner);
        $doc = $filter ? self::collection()->findOne($filter) : null;
        if (! $doc) {
            throw new RuntimeException('e.firma no encontrada');
        }
        $secret = json_decode(self::decrypt((string) $doc['secret'], self::secretKey()), true);
        self::collection()->updateOne(['_id' => $doc['_id']], ['$set' => ['used_at' => new UTCDateTime]]);

        return Credential::create(base64_decode((string) $doc['cer']), base64_decode((string) $secret['key']), (string) $secret['pass']);
    }

    /** Firma binaria de $data con una e.firma del servidor. */
    public static function sign($id, string $data, int $algorithm = OPENSSL_ALGO_SHA256, $owner = null): string
    {
        return self::credential($id, $owner)->sign($data, $algorithm);
    }

    /** Separa "client:<nocert>" / "server:<_id>" del input. Devuelve [source, ref] o null. */
    public static function parseValue($value): ?array
    {
        if (! is_string($value) || ! preg_match('/^(client|server):([0-9A-Za-z]{1,64})$/', $value, $match)) {
            return null;
        }

        return [$match[1], $match[2]];
    }

    /* ------------------------------------------------------------------------------------------
     * Componente
     * ---------------------------------------------------------------------------------------- */

    public function __toString(): string
    {
        global $nframework, $javas;
        $id = $this->id;
        $firmaPath = sys_get_temp_dir().'/nfefirma_'.bin2hex(random_bytes(8)).'.png';
        $_SESSION['nfefirma'][$id] = [
            'tipo' => $this->tipo,
            'storage' => $this->storage,
            'rfc' => $this->rfc,
            'consent' => $this->consent,
            'firma_path' => $firmaPath,
        ];
        addVarToGarbage('nfefirma\\'.$id, time() + (60 * 60));

        $nframework->jss['101'] = 'https://cdnjs.cloudflare.com/ajax/libs/jsrsasign/10.4.1/jsrsasign-all-min.min.js';
        if (empty($nframework->onces['Efirma'])) {
            $nframework->onces['Efirma'] = true;
            $javas->addjs(self::sharedJs());
        }

        $conf = json_encode([
            'id' => $id,
            'tipo' => $this->tipo,
            'storage' => $this->storage,
            'rfc' => $this->rfc,
            'endpoint' => self::ENDPOINT,
            'csrf' => csrfToken(self::ENDPOINT),
        ]);
        $javas->addjs('nfEfirmaInit('.$conf.', function (info) {'.$this->onSelect."\n});", 'ready');

        $firmaHtml = '';
        if ($this->storage === 'server') {
            $pad = new Signature_pad([
                'name' => $id.'_pad',
                'path' => $firmaPath,
                'backgroundColor' => 'rgb(255,255,255)',
                'onSuccess' => 'nfEfirma["'.$id.'"].padSaved = true; $("#'.$id.'_padok").removeClass("d-none");',
            ]);
            $firmaHtml = '<p class="text-small">'.htmlspecialchars($this->consent).'</p>'.$pad.
                '<div id="'.$id.'_padok" class="fg-green d-none"><span class="mif-checkmark"></span> Firma registrada</div>';
        }

        $e = fn ($s) => htmlspecialchars((string) $s);
        $donde = $this->storage === 'server' ? 'se guarda cifrada en el servidor' : 'se queda en este navegador';

        return ($this->caption !== '' ? '<label>'.$e($this->caption).'</label>' : '').'
<div class="nf-efirma" id="'.$id.'_box">
    <input type="hidden" name="'.$e($this->name).'" id="'.$id.'" value="'.$e(self::parseValue($this->value) ? $this->value : '').'">
    <ul class="items-list" id="'.$id.'_list"></ul>
    <div id="'.$id.'_empty" class="text-muted">No hay '.self::tipoLabel($this->tipo).' registrada.</div>
    <button type="button" class="button" onclick="nfEfirma[\''.$id.'\'].openAdd()"><span class="mif-key"></span> Agregar '.self::tipoLabel($this->tipo).'</button>
</div>
<div class="dialog" data-role="dialog" id="'.$id.'_dlg" data-width="520" data-on-open="nfWindowResize()">
    <div class="dialog-title">Agregar '.self::tipoLabel($this->tipo).'</div>
    <div class="dialog-content">
        <p class="text-small">La llave privada '.$donde.'.</p>
        <div id="'.$id.'_info" class="text-bold"></div>
        <div class="form-group"><label>Certificado (.cer)</label>
            <input type="file" id="'.$id.'_cer" accept=".cer" data-role="file"></div>
        <div class="form-group"><label>Llave privada (.key)</label>
            <input type="file" id="'.$id.'_key" accept=".key" data-role="file"></div>
        <div class="form-group"><label>Contraseña</label>
            <input type="password" id="'.$id.'_pass" data-role="input" autocomplete="off"></div>
        '.$firmaHtml.'
    </div>
    <div class="dialog-actions">
        <button type="button" class="button js-dialog-close">Cancelar</button>
        <button type="button" class="button primary" id="'.$id.'_save" onclick="nfEfirma[\''.$id.'\'].save()">Guardar</button>
    </div>
</div>';
    }

    /** JS común a todos los selectores de la página. */
    private static function sharedJs(): string
    {
        return <<<'JS'
var nfEfirma = {};
function nfEfirmaTipo(subject) { return /\/OU=/.test(subject || '') ? 'csd' : 'fiel'; }
function nfEfirmaLlaves() { return Metro.storage.getItem('llaves', {}) || {}; }
function nfEfirmaB64(file) {
    return new Promise(function (ok, fail) {
        var fr = new FileReader();
        fr.onload = function () { ok(fr.result); };
        fr.onerror = fail;
        fr.readAsBinaryString(file);
    });
}
function nfEfirmaInit(conf, onSelect) {
    var id = conf.id, items = {}, unlocked = {};
    function post(data) {
        data.append('id', id);
        data.append('CSRFToken', conf.csrf);
        return $.ajax({url: conf.endpoint, type: 'post', data: data, processData: false, contentType: false, dataType: 'json'})
            .then(function (r) { if (r.error) { return $.Deferred().reject(r.error); } return r; },
                  function () { return 'No se pudo contactar al servidor.'; });
    }
    function fail(msg) { Swal.fire({icon: 'error', title: 'e.firma', text: String(msg)}); }
    function render() {
        var $l = $('#' + id + '_list').empty(), sel = $('#' + id).val(), n = 0;
        Object.values(items).forEach(function (it) {
            n++;
            var $li = $('<li>').toggleClass('current', it.value === sel)
                .append($('<span class="label">').text(it.rfc + ' · ' + it.nombre))
                .append($('<span class="second-label">').text((it.tipo === 'csd' ? 'CSD ' + (it.sucursal || '') : 'e.firma') + ' · ' + it.nocert + ' · vence ' + it.vence))
                .on('click', function () { api.select(it.value); });
            $('<button type="button" class="button small flat-button place-right">').append('<span class="mif-bin">')
                .on('click', function (ev) { ev.stopPropagation(); api.remove(it); }).appendTo($li);
            $l.append($li);
        });
        $('#' + id + '_empty').toggle(n === 0);
        if (!items[sel]) { $('#' + id).val(''); }
        if (n === 1 && !sel) { api.select(Object.keys(items)[0]); }
    }
    function load() {
        items = {};
        if (conf.storage === 'client') {
            var llaves = nfEfirmaLlaves();
            Object.keys(llaves).forEach(function (nocert) {
                var l = llaves[nocert], tipo = l.tipo || nfEfirmaTipo(l.subject);
                var rfc = l.rfc || ((/uniqueIdentifier=([A-Z&Ñ0-9]+)/.exec(l.subject || '') || [])[1] || '');
                if ((conf.tipo !== 'any' && conf.tipo !== tipo) || (conf.rfc && conf.rfc !== rfc)) { return; }
                items['client:' + nocert] = {value: 'client:' + nocert, source: 'client', nocert: nocert, rfc: rfc,
                    nombre: l.nombre || ((/\/O=([^/]+)/.exec(l.subject || '') || [])[1] || ''), sucursal: l.sucursal || '',
                    tipo: tipo, vence: l.vence || l.notAfter || ''};
            });
            render();
        } else {
            var fd = new FormData(); fd.append('op', 'list');
            post(fd).then(function (r) { r.items.forEach(function (it) { items[it.value] = it; }); render(); }, fail);
        }
    }
    function askPassword() {
        return Swal.fire({title: 'Contraseña de la llave privada', input: 'password', showCancelButton: true,
            inputAttributes: {autocomplete: 'off'}}).then(function (r) {
            if (!r.isConfirmed) { throw 'Operación cancelada'; }
            return r.value;
        });
    }
    var api = {
        padSaved: false,
        selected: function () { return items[$('#' + id).val()] || null; },
        certificate: function () {
            var it = api.selected();
            return it && it.source === 'client' ? nfEfirmaLlaves()[it.nocert].cert : null;
        },
        select: function (value) {
            if (!items[value]) { return; }
            $('#' + id).val(value).trigger('change');
            render();
            onSelect(items[value]);
        },
        reload: load,
        openAdd: function () {
            $('#' + id + '_info').text('');
            api.padSaved = false;
            $('#' + id + '_padok').addClass('d-none');
            Metro.dialog.open('#' + id + '_dlg');
        },
        /** Firma una cadena (o arreglo de cadenas) con la llave del navegador. Devuelve base64. */
        sign: function (data, alg) {
            var it = api.selected();
            if (!it) { return Promise.reject('Seleccione una ' + (conf.tipo === 'csd' ? 'CSD' : 'e.firma') + '.'); }
            if (it.source !== 'client') { return Promise.reject('Las llaves guardadas en el servidor solo firman desde PHP (Efirma::sign).'); }
            var llave = nfEfirmaLlaves()[it.nocert];
            var key = unlocked[it.nocert] ? Promise.resolve(unlocked[it.nocert]) : askPassword().then(function (pass) {
                try { unlocked[it.nocert] = KEYUTIL.getKey(llave.key, pass); } catch (e) { throw 'Contraseña incorrecta.'; }
                return unlocked[it.nocert];
            });
            return key.then(function (prv) {
                var one = function (s) {
                    var sig = new KJUR.crypto.Signature({alg: alg || 'SHA256withRSA'});
                    sig.init(prv);
                    sig.updateString(s);
                    return hextob64(sig.sign());
                };
                return Array.isArray(data) ? data.map(one) : one(data);
            });
        },
        lock: function () { unlocked = {}; },
        remove: function (it) {
            Swal.fire({title: '¿Eliminar ' + it.rfc + ' ' + it.nocert + '?', icon: 'warning', showCancelButton: true}).then(function (r) {
                if (!r.isConfirmed) { return; }
                if (it.source === 'client') {
                    var llaves = nfEfirmaLlaves();
                    delete llaves[it.nocert];
                    Metro.storage.setItem('llaves', llaves);
                    delete unlocked[it.nocert];
                    load();
                } else {
                    var fd = new FormData(); fd.append('op', 'delete'); fd.append('efirma', it.id);
                    post(fd).then(load, fail);
                }
            });
        },
        save: function () {
            var cer = $('#' + id + '_cer')[0].files[0], key = $('#' + id + '_key')[0].files[0], pass = $('#' + id + '_pass').val();
            if (!cer || !key || !pass) { fail('Seleccione el .cer, el .key y escriba la contraseña.'); return; }
            if (conf.storage === 'server' && !api.padSaved) { fail('Firme y presione Guardar en el recuadro de firma.'); return; }
            var fd = new FormData();
            fd.append('cer', cer);
            if (conf.storage === 'server') {
                fd.append('op', 'store'); fd.append('key', key); fd.append('pass', pass);
                post(fd).then(function (r) {
                    $('#' + id + '_pass').val('');
                    Metro.dialog.close('#' + id + '_dlg');
                    items[r.item.value] = r.item;
                    render();
                    api.select(r.item.value);
                }, fail);
                return;
            }
            // Cliente: el servidor solo revisa el certificado (público); la llave se abre aquí.
            fd.append('op', 'inspect');
            post(fd).then(function (r) {
                return Promise.all([nfEfirmaB64(cer), nfEfirmaB64(key)]).then(function (raw) {
                    var certPEM = KJUR.asn1.ASN1Util.getPEMStringFromHex(rstrtohex(raw[0]), 'CERTIFICATE');
                    var keyPEM = KJUR.asn1.ASN1Util.getPEMStringFromHex(rstrtohex(raw[1]), 'ENCRYPTED PRIVATE KEY');
                    var prv;
                    try { prv = KEYUTIL.getKey(keyPEM, pass); } catch (e) { throw 'Contraseña incorrecta.'; }
                    var reto = KJUR.crypto.Util.getRandomHexOfNbytes(16);
                    var s = new KJUR.crypto.Signature({alg: 'SHA256withRSA'}); s.init(prv); s.updateString(reto);
                    var v = new KJUR.crypto.Signature({alg: 'SHA256withRSA'}); v.init(certPEM); v.updateString(reto);
                    if (!v.verify(s.sign())) { throw 'La llave privada no corresponde al certificado.'; }
                    var x509 = new X509(); x509.readCertPEM(certPEM);
                    var info = r.info, llaves = nfEfirmaLlaves();
                    llaves[info.nocert] = {key: keyPEM, cert: certPEM, subject: x509.getSubjectString(), notAfter: x509.getNotAfter(),
                        tipo: info.tipo, rfc: info.rfc, nombre: info.nombre, sucursal: info.sucursal, vence: info.vence};
                    Metro.storage.setItem('llaves', llaves);
                    unlocked[info.nocert] = prv;
                    $('#' + id + '_pass').val('');
                    Metro.dialog.close('#' + id + '_dlg');
                    load();
                    api.select('client:' + info.nocert);
                });
            }).catch(fail);
        }
    };
    nfEfirma[id] = api;
    $('#' + id + '_cer').on('change', function () {
        var f = this.files[0];
        if (!f) { return; }
        var fd = new FormData(); fd.append('op', 'inspect'); fd.append('cer', f);
        post(fd).then(function (r) {
            $('#' + id + '_info').text(r.info.rfc + ' · ' + r.info.nombre + ' · ' + (r.info.tipo === 'csd' ? 'CSD' : 'e.firma') + ' · vence ' + r.info.vence);
        }, function (msg) { $('#' + id + '_info').text(''); fail(msg); });
    });
    load();
}
JS;
    }
}
