<?php

/**
 * violetCMS - Abstract Controller Class
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS\Controller;

use VioletCMS\Handler\Log;

abstract class Controller
{
    protected $violet;
    protected $user;

    protected $logHandler;

    public function __construct(\VioletCMS\VioletCMS $violet, $user)
    {
        $this->violet = $violet;
        $this->user = $user;

        $this->logHandler = new Log($violet);
    }

    abstract public function get();
    abstract public function set($json);

}
