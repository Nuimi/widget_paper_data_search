<?php
use Tracy\Debugger;
require_once 'common_functions.php';

$whitelist = [
    '127.0.0.1',
    '::1'
];

if (in_array($_SERVER['REMOTE_ADDR'], $whitelist))
{
    require_once 'autoload.php';
} else {
    require_once 'autoload_linux.php';
}

Debugger::enable('localhost');


try {
    require_once 'config/config.php';
    Debugger::$showBar = LOCALE;


    dibi::connect(CONNECT);

    $panel = new \Dibi\Bridges\Tracy\Panel();
    $panel->register(dibi::getConnection());
} catch (\Dibi\Exception $e) {
    echo $e;
}

session_save_path("./tmp");
session_start();

$container = new \Presenters\Container();

$REQUEST_URI = &$_SERVER['REQUEST_URI'];
if ($REQUEST_URI == null)
{
    $argv = &$argv;
    $REQUEST_URI = $argv[1];
}

preg_match('~^([^?]+)~', $REQUEST_URI, $url);
$urls = explode('/', $url[1]);

if (!isset($urls[1]) || !strlen($urls[1]))
{
    $urls[1] = "Home";
}

if (!array_key_exists('csrf_token', $_SESSION) || empty($_SESSION['csrf_token']))
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
try {
    moduleLoader($container, $urls);
} catch (Exception $e){
    echo $e;
}
