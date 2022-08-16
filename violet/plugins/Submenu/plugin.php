<?php

/**
 * violetCMS - Submenu Plugin
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS\Plugins;

use VioletCMS\Plugin;
use VioletCMS\Utils;

class SubmenuPlugin extends Plugin
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
        $pages = $this->getChildPages($value);

        if (!isset($pages) || empty($pages)) {
            return '';
        }

        $html = '<ul>';

        foreach ($pages as $child) {

            $url = rtrim($this->violet->rootURL, '/') . $this->violet->getCleanRoute($child['url']);
            $url = Utils::sanitizeURL($url);

            $title = Utils::sanitizeAttribute($child['title']);

            $html .= '<li><a href="' . $url . '">' . $title . '</a></li>';
        }

        $html .= '</ul>';

        return $html;
    }

    public function onContentLoaded($value)
    {
        $pages = $this->getChildPages($value);

        if (!isset($pages) || empty($pages)) {
            return '';
        }

        $content = '';

        foreach ($pages as $child) {
            $content .= "* [".$child['title']."](".$child['url'].")\n";
        }

        return $content;
    }

    private function getChildPages($url)
    {
        if ($url) {
            $url = $this->violet->getCleanRoute($url);
            $page = $this->getPageFromURL($url);
        } else {
            $page = $this->getPageFromURL($this->context->route);
        }

        if (!$page || empty($page['children'])) {
            return false;
        }

        $pages = array();

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

            $pages[] = array(
                'title' => $child['title'],
                'url'   => $child['url']
            );
        }

        return $pages;
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
