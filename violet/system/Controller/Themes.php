<?php

/**
 * violetCMS - Themes Controller
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * Currently only gets a list of installed Themes
 * or better said: a list of folders in 'themes'
 */

namespace VioletCMS\Controller;

use VioletCMS\Ajax\APIError;

class Themes extends Controller
{
    public function get()
    {
        $dir = $this->violet->themesDir;

        if (!is_dir($dir)) {
            new APIError(500, 'No Themes directory');
        }

        return array_map(function($file) {
            return pathinfo($file, PATHINFO_FILENAME);
        }, glob($dir.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR));
    }

    public function set($json)
    {
        return false;
    }

}
