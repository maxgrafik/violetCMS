<?php

/**
 * violetCMS - API
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS;

const DS = DIRECTORY_SEPARATOR;

$autoload = __DIR__ . DS . 'vendor' . DS . 'autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    exit('Autoloader not found');
}


/**
 * Setting HTTP response code to 500 (Internal Server Error)
 * prevents clients picking up any error message as 200 (OK)
 * Other controllers may set this to 200/40x appropriately
 */

http_response_code(500);


/**
 * Additionally we set up some handlers to log errors
 */

set_error_handler('VioletCMS\APIErrorHandler', E_ALL);
register_shutdown_function('VioletCMS\APIShutdown');


/**
 * Register Autoloader
 */

require $autoload;

$loader = new \Psr4AutoloaderClass;

$loader->register();

$loader->addNamespace('VioletCMS', __DIR__ . DS . 'system');
$loader->addNamespace('Vendor', __DIR__ . DS . 'vendor');


/**
 * Verify & Process API Request
 *
 * APIAccess::verifyRequest either
 * - returns null (user logs in),
 * - throws an APIError (400/401/403/405)
 * - returns user object (access granted)
 *
 * The user object is then added to the AjaxRequest
 * so AjaxDispatcher can decide whether to accept it
 */

use VioletCMS\Ajax\APIAccess;
use VioletCMS\Ajax\AjaxRequest;
use VioletCMS\Ajax\AjaxDispatcher;

$user = APIAccess::verifyRequest();

if (isset($user)) {
    $dispatcher = new AjaxDispatcher();
    $dispatcher->dispatch(new AjaxRequest($user));
}


/**
 * Log Errors
 */

function APIErrorHandler($errNo, $errMsg)
{
    $timestamp = date("Y-m-d H:i:s");
    $errLog = $timestamp . "\t" . $errNo . "\n" . $errMsg . "\n\n";
    file_put_contents(__DIR__ . DS . 'logs' . DS . 'error.log', $errLog, FILE_APPEND | LOCK_EX);
    die();
}

function APIShutdown()
{
    $err = error_get_last();
    if ($err && $err['type'] === E_ERROR) {
        $timestamp = date("Y-m-d H:i:s");
        $errLog = $timestamp . "\n" . $err['message'] . "\n\n";
        file_put_contents(__DIR__ . DS . 'logs' . DS . 'error.log', $errLog, FILE_APPEND | LOCK_EX);
    }
}
