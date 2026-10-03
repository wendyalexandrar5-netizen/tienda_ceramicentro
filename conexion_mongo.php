<?php
require __DIR__ . '/vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;

function mongo(): MongoDB\Database {
    static $db = null;
    if ($db === null) {
        $client = new Client("mongodb://localhost:27017", [], [
            'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']
        ]);
        $db = $client->selectDatabase('ceramicentro_mongo');
    }
    return $db;
}

function nowUTC(): UTCDateTime {
    return new UTCDateTime((int)(microtime(true) * 1000));
}
