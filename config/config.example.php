<?php
/**
 * CONFIGURACIÓN DE CERAMISHOP
 * ---------------------------------------------------------------
 * 1. Copia este archivo como  config/config.php
 * 2. Ajusta los valores a tu entorno (local o producción).
 * 3. NUNCA subas config/config.php al repositorio (ya está en .gitignore).
 *
 * Solo necesitas escribir las claves que quieras cambiar: el resto
 * toma los valores por defecto de includes/bootstrap.php.
 * También puedes usar variables de entorno (ver includes/bootstrap.php).
 */
return [
    // 'desarrollo' o 'produccion'. En producción no se muestran detalles técnicos
    // y se activan cookies seguras / HTTPS si 'forzar_https' es true.
    'entorno' => 'desarrollo',

    // URL pública del sitio, sin "/" final. Se usa en sitemap, Open Graph,
    // datos estructurados y enlaces de la API. Si se deja vacía se calcula
    // automáticamente a partir de la petición.
    // Ejemplo producción: 'https://www.ceramicentro.com'
    'url_sitio' => '',

    // Redirigir HTTP -> HTTPS (activar SOLO cuando el certificado SSL exista).
    'forzar_https' => false,
    // Enviar cabecera HSTS (solo tiene efecto si la petición llega por HTTPS).
    'hsts' => false,

    'mongo' => [
        'uri'           => 'mongodb://localhost:27017',
        'base_de_datos' => 'ceramicentro_mongo',
    ],

    // Clave secreta para firmar enlaces de descarga de la app.
    // Genera una con:  php -r "echo bin2hex(random_bytes(32));"
    'clave_app' => 'CAMBIAR-POR-UNA-CLAVE-ALEATORIA-LARGA',

    // Correo del formulario de contacto (Gmail requiere "contraseña de aplicación").
    // Si se deja vacío, los mensajes se guardan en MongoDB (colección mensajes_contacto)
    // y se pueden consultar desde el panel administrativo.
    'smtp' => [
        'host'      => 'smtp.gmail.com',
        'puerto'    => 587,
        'usuario'   => 'ceramicentro6@gmail.com',
        'clave'     => '', // <- contraseña de aplicación de Gmail (solo en config.php)
        'remitente' => '',
        'destino'   => '',
    ],

    'empresa' => [
        'nombre'      => 'CERAMICENTRO',
        'tienda'      => 'CERAMISHOP',
        // Número de WhatsApp en formato internacional sin "+" ni espacios.
        'whatsapp'    => '573134322830',
        'correo'      => 'ceramicentro6@gmail.com',
        'telefono'    => '',
        'direccion'   => '',
        'ciudad'      => '',
        'facebook'    => '',
        'instagram'   => '',
    ],

    // Analítica (Google Analytics 4). Vacío = desactivada.
    // Solo se carga si el visitante acepta las cookies analíticas.
    'analitica' => [
        'ga4_id' => '',
    ],

    // API de la app Android.
    'api' => [
        // Orígenes permitidos (CORS). La app Capacitor usa https://localhost.
        'origenes' => ['https://localhost', 'http://localhost', 'capacitor://localhost'],
        // true = acepta peticiones sin token (apps instaladas antes de esta versión).
        // Déjalo en false cuando todos usen la app actualizada.
        'modo_legado' => false,
        // Duración del token de sesión de la app (días).
        'dias_token' => 30,
    ],

    // Minutos de inactividad antes de cerrar la sesión web.
    'minutos_sesion' => 120,
];
