<?php

/**
 * violetCMS - Restricted Backup Downloads
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
 * Register Autoloader
 */

require $autoload;

$loader = new \Psr4AutoloaderClass;

$loader->register();

$loader->addNamespace('VioletCMS', __DIR__ . DS . 'system');
$loader->addNamespace('Vendor', __DIR__ . DS . 'vendor');


/**
 * Verify request
 */

use VioletCMS\Handler\Download;

Download::verifyRequest();

?>
