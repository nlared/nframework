<?php
/*
 * Página anterior del administrador de llaves del SAT. Su contenido vive ahora en /sat/llaves.php (mismas
 * llaves en localStorage, clave "llaves"); se conserva esta ruta para no romper enlaces existentes.
 */
header('Location: /sat/llaves.php', true, 302);
exit;
