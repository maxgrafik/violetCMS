<?php

/**
 * violetCMS - Include Page Plugin
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS\Plugins;

use VioletCMS\Plugin;
use VioletCMS\Handler\File;
use Vendor\Parsedown\ParsedownViolet;

class IncludePagePlugin extends Plugin
{
    public function getSubscribedEvents()
    {
        return [
            'onTemplateLoaded' => 'onTemplateLoaded',
            'onContentLoaded' => 'onContentLoaded'
        ];
    }

    public function onTemplateLoaded($value)
    {
        $content = $this->getPageContent($value);

        $homeRoute = $this->violet->config['Routes']['HideInURL'] ? $this->violet->config['Routes']['Home'] : '';

        $Parsedown = new ParsedownViolet($this->violet->rootURL, $homeRoute);
        $Parsedown->setSafeMode($this->violet->config['Markdown']['EscapeHTML'] ?? true);
        $Parsedown->setUrlsLinked($this->violet->config['Markdown']['AutoURLLinks'] ?? false);
        $Parsedown->setBreaksEnabled($this->violet->config['Markdown']['AutoLineBreak'] ?? true);

        return $Parsedown->text($content);
    }

    public function onContentLoaded($value)
    {
        return $this->getPageContent($value);
    }

    private function getPageContent($url)
    {
        $route = $this->violet->getCleanRoute($url);
        $page = $this->getPageFromURL($route);

        if (!$page) {
            return 'Page not found: '.$url;
        }

        $fileHandler = new File();

        $content = $fileHandler->getRawContentsOrNull($this->violet->getPathFromURL($page['url']), 'page.md');

        if ($content) {
            if (preg_match('/^---(.*?)---$(.*)/sm', $content, $matches)) {
                $content = ltrim($matches[2]);
            } else {
                $content = ltrim($content);
            }

            $pageSections = array_map('trim', explode('~~~section-marker~~~', $content));

            return $pageSections[0];
        }

        return ''; // no content at all
    }

    private function getPageFromURL($url, $nodes = null)
    {
        $nodes = $nodes ?? $this->context->sitemap;

        foreach($nodes as &$node) {
            $route = $this->violet->getCleanRoute($node['url']);
            if ($route === $url) {
                return $node;
            }
            if (!empty($node['children'])) {
                if ($tmp = $this->getPageFromURL($url, $node['children'])) {
                    return $tmp;
                }
            }
        }
        return null;
    }

}
