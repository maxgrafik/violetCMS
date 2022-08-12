<?php

/**
 * violetCMS - Meta Plugin
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS\Plugins;

use VioletCMS\Plugin;
use VioletCMS\Utils;

class MetaPlugin extends Plugin
{
    public function getSubscribedEvents()
    {
        return [
            'onTemplateLoaded' => 'setMetaTags'
        ];
    }

    public function setMetaTags($value)
    {
        $page = $this->context->page['Frontmatter'] ?? null;

        switch (strtolower($value)) {
        case 'title':
            $title = $this->violet->config['Website']['Title'];
            $pageTitle = $page['title'] ?? '';
            if (!$this->context->is404 && $pageTitle) {
                $title .= ' · ' . $pageTitle;
            }
            return Utils::sanitizeAttribute($title);
        case 'description':
            $description = $page['description'] ?? $this->violet->config['Website']['Description'] ?? '';
            return Utils::sanitizeAttribute($description);
        case 'keywords':
            $keywords = $page['keywords'] ?? $this->violet->config['Website']['Keywords'] ?? '';
            return Utils::sanitizeAttribute($keywords);
        case 'robots':
            $robots = $page['robots'] ?? '';
            return Utils::sanitizeAttribute($robots);
        case 'properties':
            $tags = $page['meta'] ?? $this->violet->config['Website']['Meta'] ?? null;
            $meta = '';
            foreach ($tags as $tag) {
                if ($tag['name'] && $tag['content']) {
                    $tag['name']    = Utils::sanitizeAttribute($tag['name']);
                    $tag['content'] = Utils::sanitizeAttribute($tag['content']);
                    if (substr($tag['name'], 0, 8) === 'twitter:') {
                        $meta .= '<meta name="'.$tag['name'].'" content="'.$tag['content'].'">'."\n";
                    } else {
                        $meta .= '<meta property="'.$tag['name'].'" content="'.$tag['content'].'">'."\n";
                    }
                }
            }
            return $meta;
        case 'canonicalurl':
            $url = $page['canonicalURL'] ?? '';
            $url = $url ? Utils::sanitizeURL($url) : '';
            $url = $url ? '<link rel="canonical" href="'.$url.'">'."\n" : '';
            return $url;
        case 'themeurl':
            return $this->violet->themeURL;
        case 'rooturl':
            return $this->violet->rootURL;
        }

        return false;
    }

}
