<?php
/**
 * Sugerencias de domicilio para inputaddress a partir del DENUE de INEGI (ver class.Denue.php).
 * GET ?field=street&q=texto&state=...&municipality=...&lat=..&lng=..&located=1 → {items: [{label, value, address}]}
 * Solo responde a sesiones en las que se mostró un inputaddress con autocompletado (el token no
 * queda expuesto como proxy abierto) y con límite de peticiones por IP.
 */
require_once 'include.php';

header('Cache-Control: no-store, max-age=0');
$result = ['items' => []];

if (!$nframework->isAjax() || empty($_SESSION['nfdenue'])) {
    http_response_code(403);
    return;
}
if (!nflogAttempt('denue:' . $ip, 150, 300)) {
    http_response_code(429);
    return;
}

$field = is_string($_GET['field'] ?? null) ? $_GET['field'] : '';
$q = is_string($_GET['q'] ?? null) ? mb_substr($_GET['q'], 0, 100) : '';
$context = [];
foreach (array_merge(Denue::FIELDS, ['country', 'lat', 'lng', 'located']) as $key) {
    $context[$key] = is_string($_GET[$key] ?? null) ? mb_substr($_GET[$key], 0, 150) : '';
}

try {
    $result['items'] = Denue::suggest($field, $q, $context);
} catch (Throwable $e) {
    error_log('Denue: ' . $e->getMessage());
}
