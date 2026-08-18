<?php

session_start();
require_once 'vendor/autoload.php';
require_once 'src/classes/Main.php';

if (file_exists(__DIR__ . '/.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}

$main = new Main();
$main->run();
