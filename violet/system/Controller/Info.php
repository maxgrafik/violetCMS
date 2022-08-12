<?php

/**
 * violetCMS - Info Controller
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * Returns System Info
 */

namespace VioletCMS\Controller;

class Info extends Controller
{
    public function get()
    {
        if (function_exists('apache_get_modules')) {
            $modules = implode(', ', apache_get_modules());
        }

        $info = array(
            "Version" => phpversion(),
            "Sections" => array(
                "System"     => php_uname(),
                "Server"     => $_SERVER['SERVER_SOFTWARE'],
                "Modules"    => $modules ?? 'Info not available',
                "Extensions" => implode(', ', get_loaded_extensions())
            )
        );
        return $info;
    }

    public function set($json)
    {
        return false;
    }

}
