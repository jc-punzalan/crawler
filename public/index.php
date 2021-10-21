<?php

use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Phalcon\Di\FactoryDefault;
use Phalcon\Loader;
use Phalcon\Mvc\Application;
use Phalcon\Url as UrlProvider;
use Phalcon\Mvc\View;

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

// Register an autoloader
$loader = new Loader();
$loader->registerDirs(
    [
        APP_PATH . '/controllers/',
        APP_PATH . '/models/',
    ]
)->register();

// Create a DI
$di = new FactoryDefault();

// Setting up the view component
$di['view'] = function () {
    $view = new View();
    $view->setViewsDir(APP_PATH . '/views/');
    return $view;
};

// Setup a base URI so that all generated URIs include the "crawler" folder
$di['url'] = function () {
    $url = new UrlProvider();
    $url->setBaseUri('/');
    return $url;
};

require_once '../vendor/autoload.php';

// Handle the request
try {
    $application = new Application($di);
    echo $application->handle($_SERVER['REQUEST_URI'])->getContent();
} catch (Exception $e) {
    echo "Exception: ", $e->getMessage();
}
