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
        $html = '<ul>';

        if ($value) {
            $page = $this->getPageFromURL($value);
        } else {
            $page = $this->getPageFromURL($this->context->route);
        }

        $pages = $this->getChildPages($page);

        foreach ($pages as $child) {

            $url = $this->violet->rootURL . $this->violet->getCleanRoute($child['url']);
            $url = Utils::sanitizeURL($url);

            $title = Utils::sanitizeAttribute($child['title']);

            $html .= '<li><a href="' . $url . '">' . $title . '</a></li>';
        }

        $html .= '</ul>';

        return $html;
    }

    public function onContentLoaded($value)
    {
        $content = '';

        if ($value) {
            $url = $this->violet->rootURL . $this->violet->getCleanRoute($value);
            $url = Utils::sanitizeURL($url);
            $page = $this->getPageFromURL($url);
        } else {
            $page = $this->getPageFromURL($this->context->route);
        }

        $pages = $this->getChildPages($page);

        foreach ($pages as $child) {
            $content .= "* [".$child['title']."](".$child['url'].")\n";
        }

        return $content;
    }

    private function getChildPages($node)
    {
        $pages = array();

        foreach ($node['children'] as $child) {

            /** check published state and dates */
            if ($child['published'] === false) {
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
            if (count($node['children']) > 0) {
                if ($tmp = $this->getPageFromURL($url, $node['children'])) {
                    return $tmp;
                }
            }
        }
        return null;
    }

}
