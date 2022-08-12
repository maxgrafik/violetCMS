<?php

/**
 * violetCMS - Search Plugin
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS\Plugins;

use VioletCMS\Plugin;
use VioletCMS\Handler\File;

class SearchPlugin extends Plugin
{
    private $fileHandler;

    public function getSubscribedEvents()
    {
        return [
            'onContentLoaded' => 'onContentLoaded',
            'onTemplateLoaded' => 'onTemplateLoaded'
        ];
    }

    public function onContentLoaded()
    {
        $needle = $_GET['q'] ?? null;
        $content = '';

        if ($needle) {

            $this->fileHandler = new File();

            $result = $this->search($this->context->sitemap, $needle);

            foreach ($result as $url => $title) {
                $content .= "* [".$title."](".$url.")\n";
            }
        }

        return $content;
    }

    public function onTemplateLoaded()
    {
        $html = '';
        $html .= '<form accept-charset="UTF-8" method="get" action="'.$this->violet->rootURL.$this->config['Searchresult Page'].'" class="searchform">';
        $html .= '<input type="text" name="q" value="">';
        $html .= '</form>';

        return $html;
    }

    private function search($pages, $needle)
    {
        $result = array();

        foreach ($pages as $page) {

            /** don't include search result page */
            if ($page['url'] === $this->config['Searchresult Page']) {
                continue;
            }

            /** check published state and dates */
            if ($page['published'] === false) {
                continue;
            }

            $today = date("Y-m-d");
            $visible       = $page['visible'] ?? false;
            $publishDate   = $page['publishDate'] ?? null;
            $unpublishDate = $page['unpublishDate'] ?? null;

            if ($visible === false && $this->config['Hide invisible pages']) {
                continue;
            }

            if (isset($publishDate) && $publishDate > $today) {
                continue;
            }

            if (isset($unpublishDate) && $unpublishDate <= $today) {
                continue;
            }

            $content = $this->fileHandler->getRawContentsOrNull($this->violet->getPathFromURL($page['url']), 'page.md');

            if ($content) {

                if (preg_match('/^---(.*?)---$(.*)/sm', $content, $matches)) {
                    $haystack = trim($matches[2]);
                } else {
                    $haystack = trim($content);
                }

                if (false !== stripos($haystack, $needle)) {
                    $result[$page['url']] = $page['title'];
                }
            }

            if ($page['children']) {
                $result = array_merge($result, $this->search($page['children'], $needle));
            }

        }

        return $result;
    }

}
