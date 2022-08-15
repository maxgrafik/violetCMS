<?php

/**
 * violetCMS - Menu Plugin
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS\Plugins;

use VioletCMS\Plugin;
use VioletCMS\Utils;

class MenuPlugin extends Plugin
{
    public function getSubscribedEvents()
    {
        return [
            'onTemplateLoaded' => 'onTemplateLoaded'
        ];
    }

    public function onTemplateLoaded()
    {
        return $this->buildMenu($this->context->sitemap);
    }

    private function buildMenu($pages)
    {
        if (!isset($pages) || empty($pages)) {
            return '';
        }

        $html = '<ul>';

        foreach ($pages as $page) {

            if (!$page || !isset($page['title']) || !isset($page['url'])) {
                continue;
            }

            /** check published state and dates */
            if (isset($page['published']) && $page['published'] === false) {
                continue;
            }

            $today = date("Y-m-d");
            $visible       = $page['visible'] ?? false;
            $publishDate   = $page['publishDate'] ?? null;
            $unpublishDate = $page['unpublishDate'] ?? null;

            if (!$visible) {
                continue;
            }

            if (isset($publishDate) && $publishDate > $today) {
                continue;
            }

            if (isset($unpublishDate) && $unpublishDate <= $today) {
                continue;
            }

            $url = $this->violet->rootURL . $this->violet->getCleanRoute($page['url']);
            $url = Utils::sanitizeURL($url);

            $menuTitle = Utils::sanitizeAttribute($page['title']);

            $html .= '<li><a href="' . $url . '">' . $menuTitle . '</a>';

            /** should sub menu items be shown? */
            if ($this->config['Show Submenus'] && !empty($page['children'])) {
                $html .= $this->buildMenu($page['children']);
            }

            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

}
