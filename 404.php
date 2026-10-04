<?php
/** Página 404 personalizada (Apache la usa para rutas inexistentes, ver .htaccess). */
require_once __DIR__ . '/includes/bootstrap.php';
no_encontrado('La página que buscas no existe o fue movida. Revisa la dirección o usa las opciones de abajo.');
