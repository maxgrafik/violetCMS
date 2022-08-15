<?php

/**
 * violetCMS - Table Of Contents Plugin
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS\Plugins;

use VioletCMS\Plugin;
use VioletCMS\Handler\File;

class TableOfContentsPlugin extends Plugin
{
    private $fileHandler;

    public function getSubscribedEvents()
    {
        return [
            'onContentLoaded' => 'onContentLoaded'
        ];
    }

    public function onContentLoaded()
    {
        $this->fileHandler = new File();

        $page = $this->getPageFromURL($this->context->route);

        if (!$page || empty($page['children'])) {
            return '';
        }

        $content = '';

        foreach ($page['children'] as $child) {

            if (!$child || !isset($child['title']) || !isset($child['url'])) {
                continue;
            }

            /** check published state and dates */
            if (isset($child['published']) && $child['published'] === false) {
                continue;
            }

            $today = date("Y-m-d");
            $visible       = $child['visible'] ?? false;
            $publishDate   = $child['publishDate'] ?? null;
            $unpublishDate = $child['unpublishDate'] ?? null;

            if ($visible === false && $this->config['Hide invisible pages']) {
                continue;
            }

            if (isset($publishDate) && $publishDate > $today) {
                continue;
            }

            if (isset($unpublishDate) && $unpublishDate <= $today) {
                continue;
            }

            $content .= "## ".$child['title']."\n";
            $content .= $this->getSummary($child['url'])."\n";
            $content .= "[".$this->config['Link text']."](".$child['url'].")\n";
        }

        return $content;
    }

    private function getSummary($url)
    {
        $content = $this->fileHandler->getRawContentsOrNull($this->violet->getPathFromURL($url), 'page.md');

        if ($content) {
            if (preg_match('/{#([^#]+)#}/', $content, $match)) {
                return trim($match[1]);
            } else {
                return ''; // no comment
            }
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
