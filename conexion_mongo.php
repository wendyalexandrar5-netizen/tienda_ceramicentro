<?php
/**
 * Conexión a MongoDB (base de datos de CERAMISHOP).
 * Los datos de conexión se leen de config/config.php (ver config/config.example.php).
 */
require_once __DIR__ . '/includes/bootstrap.php';

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;

if (!function_exists('mongo')) {
    function mongo(): MongoDB\Database
    {
        static $db = null;
        if ($db === null) {
            $client = new Client(
                (string)config('mongo.uri', 'mongodb://localhost:27017'),
                [
                    // Falla rápido si el servidor MongoDB no responde (evita páginas "congeladas")
                    'serverSelectionTimeoutMS' => 5000,
                    'connectTimeoutMS'         => 5000,
                ],
                ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
            );
            $db = $client->selectDatabase((string)config('mongo.base_de_datos', 'ceramicentro_mongo'));
        }
        return $db;
    }
}

if (!function_exists('nowUTC')) {
    function nowUTC(): UTCDateTime
    {
        return new UTCDateTime((int)(microtime(true) * 1000));
    }
}
