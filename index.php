<?php

/**
 * violetCMS - yet another flat file CMS
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS;

const DS = DIRECTORY_SEPARATOR;


/* autoloader */

$autoload = __DIR__ . DS . 'violet' . DS . 'vendor' . DS . 'autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    exit('Autoloader not found');
}


/* register autoloader */

require $autoload;

$loader = new \Psr4AutoloaderClass;

$loader->register();

$loader->addNamespace('VioletCMS', __DIR__ . DS . 'violet' . DS . 'system');
$loader->addNamespace('Vendor', __DIR__ . DS . 'violet' . DS . 'vendor');


/* process request */

use VioletCMS\Handler\Request;

$request = new Request();

try {
    $request->process();
} catch (\Exception $e) {
    http_response_code(500);
    exit($e->getMessage());
}
