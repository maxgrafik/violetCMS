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
use VioletCMS\Utils;

class Config extends Controller
{
    public function get()
    {
        $config = $this->getDefaultConfig();

        $fileHandler = new File();

        $data = $fileHandler->getContentsOrNull($this->violet->configDir, 'violet.json');
        if ($data) {
            $this->setConfig($config, $data);
        }

        return $config;
    }

    public function set($json)
    {
        if (null === ($data = json_decode($json, true))) {
            new APIError(400, 'Invalid JSON');
        }

        $config = $this->getDefaultConfig();
        $this->setConfig($config, $data);

        $fileHandler = new File();

        $fileHandler->putContents($this->violet->configDir, 'violet.json', $config);

        return null;
    }

    private function getDefaultConfig()
    {
        $config = array(
            'Website' => array(
                'Title'       => 'violetCMS',
                'Description' => 'yet another flat file CMS',
                'Keywords'    => '',
                'Meta'        => array()
            ),
            'Routes' => array(
                'Domain'      => null,
                'Home'        => '',
                'HideInURL'   => true,
                'Redirect404' => '/error'
            ),
            'Theme'        => 'violet',
            'Maintainance' => false,
            'Markdown' => array(
                'AutoLineBreak' => true,
                'AutoURLLinks'  => true,
                'EscapeHTML'    => true
            )
        );
        return $config;
    }

    private function setConfig(&$defaultConfig, $data)
    {
        foreach ($defaultConfig as $key => &$value) {
            if (is_array($value)) {
                if ($key === 'Meta' && isset($data[$key]) && is_array($data[$key])) {
                    foreach($data[$key] as $meta) {
                        $name    = $meta['name'] ?? null;
                        $content = $meta['content'] ?? null;
                        if ($name && $content) {
                            $value[] = array('name' => $name, 'content' => $content);
                        }
                    }
                } elseif (isset($data[$key])) {
                    $this->setConfig($value, $data[$key]);
                }
            } else {
                $defaultConfig[$key] = $data[$key] ?? $value;
            }
        }
    }
}
