<?php

/**
 * violetCMS - Page Controller
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * Get/Set Page Content and Frontmatter
 */

namespace VioletCMS\Controller;

use VioletCMS\Ajax\APIError;
use VioletCMS\Controller\Sitemap;
use VioletCMS\Handler\File;

class Page extends Controller
{
    private $Content = null;
    private $Frontmatter = array();

    public function get($args = null)
    {
        if (empty($args) || !isset($args['url'])) {
            new APIError(400, 'Invalid arguments');
        }

        $pageDir = $this->violet->getPathFromURL($args['url']);

        if (!is_dir($pageDir)) {
            new APIError(400, 'Invalid URL');
        }

        $fileHandler = new File();

        if (isset($args['draft']) && $args['draft'] === true) {
            $content = $fileHandler->getRawContents($pageDir, 'draft.md');
        } else {
            $content = $fileHandler->getRawContents($pageDir, 'page.md');
        }

        $this->parseContent($content);

        return array('Frontmatter' => $this->Frontmatter, 'Content' => $this->Content, 'rootURL' => $this->violet->rootURL);
    }

    public function set($json, $args = null)
    {
        if (empty($args) || !isset($args['url'])) {
            new APIError(400, 'Invalid arguments');
        }

        $pageDir  = $this->violet->getPathFromURL($args['url']);

        if (!is_dir($pageDir)) {
            new APIError(400, 'Invalid URL');
        }

        $fileHandler = new File();

        if (isset($args['draft']) && $args['draft'] === 'false') {
            if (is_file($pageDir . File::DS . 'draft.md')) {
                $fileHandler->deleteFile($pageDir, 'draft.md');
            }
            return null;
        }

        if (null === ($data = json_decode($json, true))) {
            new APIError(400, 'Invalid JSON');
        }

        if (!isset($data['Content']) || !isset($data['Frontmatter'])) {
            new APIError(400, 'Invalid data');
        }

        $rawData = $this->parseData($data);

        if (isset($args['draft']) && $args['draft'] === 'true') {
            $fileHandler->putRawContents($pageDir, 'draft.md', $rawData);
            return null;
        }

        $fileHandler->putRawContents($pageDir, 'page.md', $rawData);

        /**
         * Pass frontmatter to Sitemap to update visibility & published state
         */

        $sitemapController = new Sitemap($this->violet, $this->user);
        $sitemapController->update($args['url'], $data['Frontmatter']);

        return null;
    }

    private function parseContent($content)
    {
        if (preg_match('/^---(.*?)---$(.*)/sm', $content, $matches)) {
            $this->parseFrontmatter(trim($matches[1]));
            $this->Content = ltrim($matches[2]);
        } else {
            $this->Content = ltrim($content);
        }
    }

    /**
     * Naive implementation of a YAML frontmatter parser
     * This is likely enough for now
     */
    private function parseFrontmatter($data)
    {
        $vars = explode("\n", $data);
        foreach ($vars as $var) {
            list($key, $value) = explode(':', $var, 2);
            $value = trim($value);
            if ($value === 'true' || $value === 'false') {
                $this->Frontmatter[trim($key)] = ($value === 'true' ? true : false);
            } else {
                $this->Frontmatter[trim($key)] = $value;
            }
        }
    }

    private function parseData($data)
    {
        $content      = $data['Content'] ?? '';
        $frontmatter  = $data['Frontmatter'];

        $fileContents = "---\n";
        foreach ($frontmatter as $key => $value) {
            if (is_null($value) || is_object($value)) {
                continue;
            } elseif (is_bool($value)) {
                $fileContents .= $key . ": " . ($value ? "true" : "false") . "\n";
            } elseif (is_array($value)) {
                $fileContents .= $key . ": " . implode(",", $value) . "\n";
            } else {
                $fileContents .= $key . ": " . $value . "\n";
            }
        }
        $fileContents .= "---\n";
        $fileContents .= $content;

        return $fileContents;
    }
}
