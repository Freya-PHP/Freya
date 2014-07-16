<?php

define('FREYA_START', microtime(true));
        
/*
|--------------------------------------------------------------------------
| Set PHP's error reporting
|--------------------------------------------------------------------------
| Here we need to make sure that PHP is set to report all errors
| regardless of level. Freya's custom error handling will determine what
| gets logged or reported to the screen.
|
*/
    
error_reporting(-1);

/*
|--------------------------------------------------------------------------
| Register The Composer Auto Loader
|--------------------------------------------------------------------------
| Composer provides a convenient, automatically generated class loader
| for our application. We just need to utilize it! We'll require it
| into the script here so that we do not have to worry about the
| loading of any our classes "manually". Feels great to relax.
|
*/

require __DIR__ . '/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Register The Freya Auto Loader
|--------------------------------------------------------------------------
| We register an auto-loader "behind" the Composer loader that can load
| model classes on the fly, even if the autoload files have not been
| regenerated for the application. We'll add it to the stack here.
|
*/

$autoloader = new \Freya\Helpers\ClassLoader;
$autoloader->addPrefix('Freya', __DIR__ . '/../app/Freya');
$autoloader->register();

/*
|--------------------------------------------------------------------------
| Setup Patchwork UTF-8 Handling
|--------------------------------------------------------------------------
|
| The Patchwork library provides solid handling of UTF-8 strings as well
| as provides replacements for all mb_* and iconv type functions that
| are not available by default in PHP. We'll setup this stuff here.
|
*/

\Patchwork\Utf8\Bootup::initMbstring();

/*
|--------------------------------------------------------------------------
| Create our application
|--------------------------------------------------------------------------
| The first thing we will do is create a new Freya application instance
| which serves as the "glue" for all the components of Freya, and is
| the IoC container for the system binding all of the various parts.
|
*/
    
$app = new \Freya\Freya;

/*
|--------------------------------------------------------------------------
| Detect The Application Environment
|--------------------------------------------------------------------------
|
| Laravel takes a dead simple approach to your application environments
| so you can just specify a machine name for the host that matches a
| given environment, then we will automatically detect it for you.
|
*/

$envs       = require __DIR__ . '/config/environments.php';
$app['env'] = $app->detectEnvironment($envs);

/*
|--------------------------------------------------------------------------
| Bind Paths
|--------------------------------------------------------------------------
|
| Here we are binding the paths configured in paths.php to the app. You
| should not be changing these here. If you need to change these you
| may do so within the paths.php file and they will be bound here.
|
*/

$app['config'] = function($app) {
    return new \Freya\Helpers\Config(
        $app->getConfigLoader(), $app['env']
    );
};

/*
|--------------------------------------------------------------------------
| Register Application Exception Handling
|--------------------------------------------------------------------------
|
| We will go ahead and register the application exception handling here
| which will provide a great output of exception details and a stack
| trace in the case of exceptions while an application is running.
|
*/

$app->startExceptionHandling();

$run = new \Whoops\Run();
        $run->register();
        
        $prettyPageHandler = new \Whoops\Handler\PrettyPageHandler();
        $run->pushHandler($prettyPageHandler);
        
        $jsonResponseHandler = new \Whoops\Handler\JsonResponseHandler();
        $jsonResponseHandler->onlyForAjaxRequests(true);
        $run->pushHandler($jsonResponseHandler);


/*
|--------------------------------------------------------------------------
| Set default timezone
|--------------------------------------------------------------------------
| This is mandatory if we don't want PHP to throw a warning.
|
*/
        
date_default_timezone_set($app['config']['app.timezone']);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;
