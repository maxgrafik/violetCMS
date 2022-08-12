<?php

/**
 * violetCMS - Sitemap Controller
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * - Handles sitemap creation/update
 * - Handles creating/moving/deleting pages
 * - Provides sitemap related utility functions
 */

namespace VioletCMS\Controller;

use VioletCMS\Ajax\APIError;
use VioletCMS\Controller\Page;
use VioletCMS\Handler\File;

class Sitemap extends Controller
{
    private $Sitemap = null;

    private $fileHandler;
    private $pageController;

    public function __construct(...$args)
    {
        parent::__construct(...$args);

        $this->fileHandler = new File();

        /* try loading the sitemap.json */
        $this->Sitemap = $this->fileHandler->getContentsOrNull($this->violet->pagesDir, 'sitemap.json');

        /* no luck? rebuild sitemap */
        if (!$this->Sitemap) {

            $this->pageController = new Page($this->violet, null);

            $this->Sitemap = $this->buildSitemap($this->violet->pagesDir);
            $this->saveSitemap();
        }
    }

    public function get()
    {
        return $this->Sitemap;
    }

    public function set($json, $args = null)
    {
        if (empty($args) || !isset($args['action'])) {
            new APIError(400, 'Invalid arguments');
        }

        if (null === ($data = json_decode($json, true))) {
            new APIError(400, 'Invalid JSON');
        }

        switch ($args['action']) {
        case 'create':
            return $this->createNode($data);
            break;
        case 'move':
            return $this->moveNode($data);
            break;
        case 'delete':
            return $this->deleteNode($data);
            break;
        default:
            new APIError(400, 'Action not supported');
        }

        return null;
    }

    /**
     * Public Functions
     *
     * @public update           Used solely by Page Controller to update visibility etc.
     * @public getPageFromURL   Alias for 'getNodeFromURL' - used by Request Handler
     * @public getPageFromPath  Convert path to url & getNodeFromURL - used by Dashboard
     */

    public function update($url, $data)
    {
        $this->updateNode($url, $data);
    }

    public function getPageFromURL($url)
    {
        return $this->getNodeFromURL($url, $this->Sitemap, true);
    }

    public function getPageFromPath($path)
    {
        $url = str_replace($this->violet->pagesDir, '', $path);
        $url = str_replace(File::DS, '/', $url);
        return $this->getNodeFromURL($url, $this->Sitemap);
    }


    /**
     * Get node from sitemap for given url
     *
     * @param  $url          The URL to search for
     * @param  $nodes        The array of nodes to search within
     * @param  $cleanRoute   This boolean specifies whether we compare
     *                       the node's full URL or a cleaned URL
     *                       (cleaned = home route stripped)
     * @return               The node (if found) or null
     */

    private function &getNodeFromURL($url, &$nodes, $cleanRoute = false)
    {
        $null = null;
        foreach($nodes as &$node) {
            $nodeURL = $cleanRoute ? $this->violet->getCleanRoute($node['url']) : $node['url'];
            if ($nodeURL === $url) {
                return $node;
            }
            if (count($node['children']) > 0) {
                if ($tmp = &$this->getNodeFromURL($url, $node['children'], $cleanRoute)) {
                    return $tmp;
                }
            }
        }
        return $null;
    }

    private function buildSitemap($dir, $parentURL = '')
    {
        if (is_dir($dir)) {

            $node = array();

            $pageFile = $dir . File::DS . 'page.md';

            if (is_file($pageFile)) {

                $pageURL = $parentURL . '/' . pathinfo($dir, PATHINFO_BASENAME);

                $page = $this->pageController->get([
                    'url'   => $pageURL,
                    'draft' => false
                ]);

                $node = array(
                    'url'           => $pageURL,
                    'title'         => $page['Frontmatter']['title'] ?? 'Untitled',
                    'published'     => $page['Frontmatter']['published'] ?? false,
                    'publishDate'   => $page['Frontmatter']['publishDate'] ?? null,
                    'unpublishDate' => $page['Frontmatter']['unpublishDate'] ?? null,
                    'visible'       => $page['Frontmatter']['visible'] ?? false,
                    'children'      => array()
                );
            }

            $children = glob($dir . File::DS . '*', GLOB_ONLYDIR);

            if (!empty($children)) {

                sort($children, SORT_NATURAL | SORT_FLAG_CASE);

                foreach ($children as $child) {
                    if (isset($node['children'])) {
                        $node['children'][] = $this->buildSitemap($child, $pageURL ?? '');
                    } else {
                        $node[] = $this->buildSitemap($child, $pageURL ?? '');
                    }
                }
            }

            return $node;
        }
    }

    private function saveSitemap()
    {
        if (!isset($this->Sitemap)) {
            new APIError(500, 'Error: No sitemap data');
        }

        $this->fileHandler->putContents($this->violet->pagesDir, 'sitemap.json', $this->Sitemap);
    }

    private function createNode($data)
    {
        if (!isset($data['title']) || !isset($data['slug'])) {
            new APIError(400, 'Invalid data');
        }

        $slug      = $this->fileHandler->getUniqueFileName($this->violet->pagesDir, $data['slug']);
        $title     = $data['title'];
        $template  = $data['template'] ?? 'default';
        $visible   = $data['visible'] ?? false;
        $published = $data['published'] ?? false;

        $pageDir = $this->fileHandler->createDirectory($this->violet->pagesDir, $slug);

        $node = array(
            'url'           => '/' . $slug,
            'title'         => $title,
            'published'     => $published,
            'publishDate'   => null,
            'unpublishDate' => null,
            'visible'       => $visible,
            'children'      => array()
        );


        /**
         * Creating the page.md at this point should
         * actually be the job of the Page Controller
         * which in turn would have to return a success
         * or error message
         * I guess it's less expensive to do it here
         */

        $fileContents = "---\n";
        $fileContents .= "title: " . $title . "\n";
        $fileContents .= "template: " . $template . "\n";
        $fileContents .= "robots: index, follow\n";
        $fileContents .= "published: " . ($published ? 'true' : 'false') . "\n";
        $fileContents .= "visible: " . ($visible ? 'true' : 'false') . "\n";
        $fileContents .= "---\n";
        $fileContents .= "# " . $title;

        $this->fileHandler->putRawContents($pageDir, 'page.md', $fileContents);

        $this->Sitemap[] = $node;
        $this->saveSitemap();

        return $this->Sitemap;
    }

    private function spliceNode($url, &$nodes)
    {
        foreach ($nodes as $index => &$node) {
            if ($node['url'] === $url) {
                return array_splice($nodes, $index, 1);
            }
            if (count($node['children']) > 0) {
                if ($tmp = $this->spliceNode($url, $node['children'])) {
                    return $tmp;
                }
            }
        }
        return false;
    }

    private function insertNode($url, $index, $data, &$nodes)
    {
        if ($url === '/') {
            array_splice($nodes, $index, 0, $data);
            return true;
        }
        foreach ($nodes as &$node) {
            if ($node['url'] === $url) {
                array_splice($node['children'], $index, 0, $data);
                return true;
            }
            if (count($node['children']) > 0) {
                if ($tmp = $this->insertNode($url, $index, $data, $node['children'])) {
                    return true;
                }
            }
        }
        return false;
    }

    private function updateNode($url, $data)
    {
        $node = &$this->getNodeFromURL($url, $this->Sitemap);

        if ($node) {

            $node['url']           = $data['url'] ?? $url;
            $node['title']         = $data['title'] ?? 'Untitled';
            $node['published']     = $data['published'] ?? false;
            $node['publishDate']   = $data['publishDate'] ?? null;
            $node['unpublishDate'] = $data['unpublishDate'] ?? null;
            $node['visible']       = $data['visible'] ?? false;

            if (isset($node['children'])) {
                $this->updateChildren($node['children'], $node['url']);
            }

            $this->saveSitemap();
            return true;
        }
        return false;
    }

    private function updateChildren(&$nodes, $parentURL)
    {
        foreach ($nodes as &$node) {
            $URLSegments  = explode('/', $node['url']);
            $node['url'] = $parentURL . '/' . end($URLSegments);

            if (isset($node['children'])) {
                $this->updateChildren($node['children'], $node['url']);
            }
        }
    }

    private function moveNode($args)
    {
        $source = $args['source'] ?? null;
        $target = $args['target'] ?? null;
        $index  = $args['index']  ?? null;

        if (!isset($source) || !isset($target) || !isset($index)) {
            new APIError(400, 'Invalid data');
        }

        $sourceDir = $this->violet->getPathFromURL($source);
        $targetDir = $this->violet->getPathFromURL($target);

        if (!is_dir($sourceDir) || !is_dir($targetDir)) {
            new APIError(400, 'Directories not found');
        }

        $sourceName = pathinfo($sourceDir, PATHINFO_BASENAME);
        $sourceParentDir = pathinfo($sourceDir, PATHINFO_DIRNAME);

        if ($sourceParentDir !== $targetDir) {

            // physically move the source - we're not just sorting within the directory

            $sourceName = $this->fileHandler->getUniqueFileName($targetDir, $sourceName);

            rename($sourceDir, $targetDir . File::DS . $sourceName);
        }

        // splice node from its current position in the tree
        if ($node = $this->spliceNode($source, $this->Sitemap)) {

            // get data from spliced node for updateNode
            $data = array(
                'url'           => rtrim($target, '/') . '/' . $sourceName,
                'title'         => $node[0]['title'],
                'published'     => $node[0]['published'],
                'publishDate'   => $node[0]['publishDate'],
                'unpublishDate' => $node[0]['unpublishDate'],
                'visible'       => $node[0]['visible']
            );

            // insert node into new position
            // where $target is the url of the parent node
            // (or '/' if root)

            $targetIndex = (int) $index;

            if ($this->insertNode($target, $targetIndex, $node, $this->Sitemap)) {

                // update node which also saves the updated sitemap
                if ($this->updateNode($node[0]['url'], $data)) {

                    // return updated sitemap
                    return $this->Sitemap;
                }
            }
        }
    }

    private function deleteNode($args)
    {
        $url = $args['url'] ?? null;

        if (!$url || $url == '') {
            new APIError(400, 'Invalid data');
        }

        $node = $this->spliceNode($url, $this->Sitemap);

        if (!$node) {
            new APIError(400, 'Page not found');
        }

        $pageDir = $this->violet->getPathFromURL($url);

        if (!is_dir($pageDir)) {
            new APIError(400, 'Invalid URL');
        }

        $this->fileHandler->deleteDir($pageDir);

        $this->saveSitemap();
        return $this->Sitemap;
    }

}
