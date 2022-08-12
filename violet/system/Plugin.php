<?php

/**
 * violetCMS - Abstract Plugin Class
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS;

abstract class Plugin
{
    protected $violet;
    protected $config;
    protected $context;

    public function __construct(\VioletCMS\VioletCMS $violet, $config, $context)
    {
        $this->violet  = $violet;
        $this->context = $context;

        if ($config) {
            foreach ($config as $option) {
                $optionName = $option['label'] ?? null;
                $optionValue = $option['value'] ?? null;
                if (isset($optionName) && isset($optionValue)) {
                    $this->config[$optionName] = $optionValue;
                }
            }
        }
    }

    abstract public function getSubscribedEvents();
}
