<?php
/*
 * Ejemplo de includes/config.php
 * ------------------------------
 * Copie este archivo como includes/config.php y ajústelo. config.php no se versiona (.gitignore).
 *
 * De dónde sale $config:
 *   1. Este archivo (se elige el bloque según el dominio de la petición).
 *   2. Encima se combina el documento {_id: 'site'} de la colección `configs` en MongoDB, que es lo que
 *      se edita en Admin → Sitio / Seguridad / SAT. Si una clave está en ambos lados, gana MongoDB,
 *      EXCEPTO las listas trusted_proxies y allowed_redirect_hosts, que se SUMAN: lo que se pone aquí
 *      no se puede quitar desde el panel.
 *
 * Rendimiento: el documento de MongoDB se lee en cada petición de todos modos, así que poner una opción
 * aquí o en el panel cuesta lo mismo. Use este archivo para lo que depende del servidor/infraestructura
 * (conexión, proxies, rutas del sistema) y el panel para lo que cambia el administrador del sitio.
 */

switch ($_SERVER['HTTP_HOST'] ?? 'localhost') {

    case 'www.ejemplo.com':
    case 'ejemplo.com':
        $config = [
            // --- Obligatorias -------------------------------------------------------------------------
            'mongo_connection_string' => 'mongodb://127.0.0.1',
            'sitedb' => 'ejemplo',
            // Dominio de la cookie de sesión. Fíjelo; no use $_SERVER['HTTP_HOST'] en producción.
            'cookie_domain' => 'www.ejemplo.com',

            // --- url ----------------------------------------------------------------------------------
            // URL pública del sitio. Los correos de activación y de restablecer contraseña, robots.txt,
            // sitemap.xml y el manifiesto toman el dominio de aquí (nfSiteHost()). Si falta, se usa la
            // cabecera Host, que el visitante puede falsificar ("password reset poisoning").
            // También editable en Admin → Sitio.
            'url' => 'https://www.ejemplo.com',

            // --- trusted_proxies ----------------------------------------------------------------------
            // IPs o rangos CIDR de los proxies desde los que se aceptan X-Forwarded-For / X-Forwarded-Proto.
            // No hace falta para proxies en 127.0.0.1 o redes privadas (10/8, 172.16/12, 192.168/16): ya son
            // de confianza. Póngalo solo si el proxy llega desde una IP pública.
            // Síntoma de que falta: todos los visitantes aparecen con la IP del proxy.
            // Admin → Seguridad muestra REMOTE_ADDR y X-Forwarded-For de su propia petición para averiguarlo.
            // También editable en Admin → Seguridad (se suma a esta lista).
            'trusted_proxies' => [
                // '203.0.113.10',          // balanceador con IP pública
                // '173.245.48.0/20',       // un rango de Cloudflare (lista completa: https://www.cloudflare.com/ips/)
                // '2400:cb00::/32',        // también IPv6
            ],

            // --- allowed_redirect_hosts ---------------------------------------------------------------
            // Dominios EXTERNOS a los que se puede volver tras el login (?login_redirect=... → ?uid=...).
            // Solo el dominio, sin https:// ni rutas. Las rutas propias (/admin/) y el dominio de 'url'
            // siempre están permitidos. Si no usa login entre sitios, déjelo vacío.
            // También editable en Admin → Seguridad (se suma a esta lista).
            'allowed_redirect_hosts' => [
                // 'sso.ejemplo.com',
                // 'portal.ejemplo.com',
            ],

            // --- CSRF -----------------------------------------------------------------------------------
            // Todo POST/PUT/PATCH/DELETE cuyo Origin (o Referer) sea de otro dominio se rechaza con 403.
            // Los webhooks servidor a servidor no envían esas cabeceras y no se ven afectados.
            // csrf_trusted_origins: dominios externos cuyos formularios sí pueden enviar POST aquí
            //   (p.ej. una pasarela de pago que regresa con POST). Se suman a allowed_redirect_hosts.
            // csrf_exempt_paths: prefijos de ruta que se omiten por completo.
            // 'csrf_trusted_origins' => ['pagos.ejemplo.com'],
            // 'csrf_exempt_paths' => ['/webhooks/'],

            // --- Seguridad / mantenimiento ------------------------------------------------------------
            // 'hsts_max_age' => 15552000,     // Envía Strict-Transport-Security en HTTPS (180 días). Una vez
            //                                 // enviado el navegador ya no acepta HTTP en este dominio.
            // 'enable_web_terminal' => false, // /admin/terminal.php (shell en el navegador, solo grupo developers).
            // 'acme_challenge_dir' => '/var/www/letsencrypt/.well-known/acme-challenge', // tokens HTTP-01
            // 'nfuristats_ttl_days' => 90,    // nframework/test.php crea un índice TTL que borra estadísticas viejas.

            // --- Caché local ----------------------------------------------------------------------------
            // Configuración, reglas de seguridad, páginas y menús se guardan en archivos locales para no
            // consultarlos a MongoDB en cada petición. Un POST de un admin a /admin/ la vacía; otros servidores
            // que compartan la base ven los cambios a más tardar en cache_ttl segundos.
            // 'cache_ttl' => 60,               // 0 desactiva
            // 'cache_dir' => '/var/cache/nframework',  // por defecto {tmp}/nframework_cache_{uid}
            // 'twig_cache' => false,           // desactiva la caché de plantillas Twig compiladas

            // --- e.firma del SAT ----------------------------------------------------------------------
            // Directorio (formato `openssl rehash`) o archivo PEM con las AC del SAT. Admin → SAT lo crea y
            // lo configura solo en /var/lib/nframework/sat; defínalo aquí únicamente para usar otra ruta.
            // 'sat_ca_bundle' => '/var/lib/nframework/sat',
            // Paquete del que Admin → SAT descarga las AC (por defecto el de producción del SAT).
            // 'sat_ca_url' => 'http://omawww.sat.gob.mx/tramitesyservicios/Paginas/documentos/Cert_Prod.zip',

            // --- Otras opcionales ---------------------------------------------------------------------
            // 'github_token' => '',      // Admin → Update (repositorio privado)
            // 'session_key' => '',       // Se genera solo en MongoDB la primera vez; no lo ponga aquí salvo
            //                            // que varios sitios deban compartir los tokens ?uid=.
        ];
        break;

    // Desarrollo local
    case 'dev.ejemplo.local':
        $config = [
            'mongo_connection_string' => 'mongodb://127.0.0.1',
            'sitedb' => 'ejemplo_dev',
            'cookie_domain' => 'dev.ejemplo.local',
            'url' => 'http://dev.ejemplo.local',
        ];
        break;

    // Cualquier otro dominio se rechaza: un bloque `default` que acepte todo permitiría usar el sitio
    // con un Host arbitrario.
    default:
        http_response_code(400);
        exit('Dominio no configurado.');
}
