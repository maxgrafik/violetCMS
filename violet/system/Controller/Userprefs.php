<?php

/**
 * violetCMS - User Prefs
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * Get/Set user preferences
 */

namespace VioletCMS\Controller;

use VioletCMS\Controller\Users;

class Userprefs extends Controller
{
    public function get()
    {
        $usersController = new Users($this->violet, $this->user);
        return $usersController->getPrefs();
    }

    public function set($json)
    {
        $usersController = new Users($this->violet, $this->user);
        return $usersController->setPrefs($json);
    }

}
