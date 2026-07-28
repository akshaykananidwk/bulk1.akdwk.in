<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($_SERVER['DOCUMENT_ROOT'] . $path)) {
    return false; // serve static
}
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
