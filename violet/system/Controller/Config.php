<?php

/**
 * violetCMS - Config Controller
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * Get/Set CMS configuration
 */

namespace VioletCMS\Controller;

use VioletCMS\Ajax\APIError;
use VioletCMS\Handler\File;

class Config extends Controller
{
    public function get()
    {
        $fileHandler = new File();

        return $fileHandler->getContentsOrNull($this->violet->configDir, 'violet.json');
    }

    public function set($json)
    {
        $fileHandler = new File();

        if (null === ($data = json_decode($json, true))) {
            new APIError(400, 'Invalid JSON');
        }

        $fileHandler->putContents($this->violet->configDir, 'violet.json', $data);

        return null;
    }

}
