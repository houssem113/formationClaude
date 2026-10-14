<?php

// Routeur du serveur PHP intégré : sert les fichiers existants de public/ (assets de Swagger UI…)
// et envoie tout le reste à Symfony, y compris les URL avec un point (/api/docs.jsonopenapi).
$root = $_SERVER['DOCUMENT_ROOT'];
$path = parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);

if ('/' !== $path && is_file($root.$path)) {
    return false;
}

$_SERVER['SCRIPT_FILENAME'] = $root.'/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require $root.'/index.php';
