<?php

/**
 * violetCMS - Plugins Controller
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * - Get list of installed plugins and/or plugin info
 * - Load/invoke plugins on frontend requests
 */

namespace VioletCMS\Controller;

use VioletCMS\Ajax\APIError;
use VioletCMS\Handler\File;

class Plugins extends Controller
{
    private $Plugins = array();
    private $Events = array();

    public function get($args = null)
    {
        if (isset($args['name'])) {
            return $this->getConfig($args['name']);
        }

        return $this->getPluginList();
    }

    public function set($json, $args = null)
    {
        if (empty($args) || !isset($args['action']) || !isset($args['name'])) {
            new APIError(400, 'Invalid arguments');
        }

        if (!empty($json) && null === ($data = json_decode($json, true))) {
            new APIError(400, 'Invalid JSON');
        }

        switch ($args['action']) {
        case 'update':
            $this->setConfig($args['name'], $data);
            break;
        case 'delete':
            $this->deletePlugin($args['name']);
            break;
        default:
            new APIError(400, 'Action not supported');
        }

        return null;
    }

    public function loadPlugins($context)
    {
        if (!is_dir($this->violet->pluginDir)) {
            return;
        }

        $fileHandler = new File();

        $pluginDirs = glob($this->violet->pluginDir.File::DS.'*', GLOB_ONLYDIR);

        foreach ($pluginDirs as $pluginDir) {

            $pluginName   = pathinfo($pluginDir, PATHINFO_BASENAME);
            $pluginConfig = $fileHandler->getContentsOrNull($pluginDir, 'config.json');

            if (!$pluginConfig || !($pluginConfig['enabled'] ?? false)) {
                continue;
            }

            $filePath = $pluginDir . File::DS . 'plugin.php';

            if (!is_file($filePath) || false === (include_once $filePath)) {
                continue;
            }

            $pluginClass = 'VioletCMS\\Plugins\\' . ucfirst(strtolower($pluginName)) . 'Plugin';

            if (!class_exists($pluginClass, false)) {
                continue;
            }

            $ReflectionClass = new \ReflectionClass($pluginClass);

            $pluginInstance = $ReflectionClass->newInstance($this->violet, $pluginConfig['config'], $context);

            if (!method_exists($pluginInstance, 'getSubscribedEvents')) {
                unset($pluginInstance);
                continue;
            }

            $this->Plugins[$pluginClass] = $pluginInstance;

            $ReflectionMethod = new \ReflectionMethod($pluginInstance, 'getSubscribedEvents');

            $events = $ReflectionMethod->invoke($pluginInstance);

            foreach($events as $event => $pluginFunc) {
                $this->Events[$event][$pluginClass] = $pluginFunc;
            }
        }
    }

    public function invoke($event, &$content)
    {
        /* get subscribers for event */
        $eventSubscribers = $this->Events[$event] ?? null;

        if (!$eventSubscribers) {
            return;
        }

        /*
         * Search context for plugin tags {{pluginName[|pluginValue]}}
         * so we iterate only over specified plugins
         */
        if (preg_match_all('/{{([^|}]+)(?:\|([^}]+))?}}/', $content, $plugins, PREG_SET_ORDER)) {

            foreach($plugins as $plugin) {

                $pluginName = $plugin[1] ? trim($plugin[1]) : null;
                $pluginValue = $plugin[2] ?? null;

                if (!$pluginName) {
                    continue;
                }

                $pluginClass = 'VioletCMS\\Plugins\\' . ucfirst(strtolower($pluginName)) . 'Plugin';

                if (!class_exists($pluginClass, false)) {
                    continue;
                }

                $pluginFunc = $eventSubscribers[$pluginClass] ?? null;

                if (!$pluginFunc || !method_exists($pluginClass, $pluginFunc)) {
                    continue;
                }

                $ReflectionMethod = new \ReflectionMethod($pluginClass, $pluginFunc);

                $result = $ReflectionMethod->invoke($this->Plugins[$pluginClass], $pluginValue, $content);

                if ($result !== false) {
                    $content = str_replace($plugin[0], $result, $content);
                }
            }
        }
    }

    private function getPluginList()
    {
        if (!is_dir($this->violet->pluginDir)) {
            new APIError(500, 'No plugin directory');
        }

        $pluginList = array();

        $fileHandler = new File();

        $plugins = glob($this->violet->pluginDir.File::DS.'*', GLOB_ONLYDIR);
        foreach($plugins as $plugin) {

            $pluginName   = pathinfo($plugin, PATHINFO_BASENAME);
            $pluginDir    = $fileHandler->getDirectory($this->violet->pluginDir, $pluginName);
            $pluginConfig = $fileHandler->getContentsOrNull($pluginDir, 'config.json');

            if (!$pluginConfig) {
                continue;
            }

            $pluginList[] = array(
                'name'        => ucfirst($pluginName),
                'enabled'     => $pluginConfig['enabled'] ?? false,
                'hidden'      => $pluginConfig['hidden'] ?? false,
                'version'     => $pluginConfig['info']['version'] ?? '',
                'description' => $pluginConfig['info']['description'] ?? ''
            );
        }

        return $pluginList;
    }

    private function getConfig($pluginName)
    {
        $fileHandler = new File();

        $pluginDir = $fileHandler->getDirectory($this->violet->pluginDir, $pluginName);

        return $fileHandler->getContents($pluginDir, 'config.json');
    }

    private function setConfig($pluginName, $settings)
    {
        $fileHandler = new File();

        $pluginDir = $fileHandler->getDirectory($this->violet->pluginDir, $pluginName);

        $config = $fileHandler->getContents($pluginDir, 'config.json');

        foreach ($settings as $key => $value) {
            if ($key === 'enabled' || $key === 'config') {
                $config[$key] = $value;
            }
        }

        $fileHandler->putContents($pluginDir, 'config.json', $config);
    }

    private function deletePlugin($pluginName)
    {
        $fileHandler = new File();

        $pluginDir = $fileHandler->getDirectory($this->violet->pluginDir, $pluginName);

        $fileHandler->deleteDir($pluginDir);
    }
}
