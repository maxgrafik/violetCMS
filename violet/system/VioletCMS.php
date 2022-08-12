<?php

/**
 * violetCMS
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS;

use VioletCMS\Controller\Config;

class VioletCMS
{
    public $rootDir    = null;
    public $baseDir    = null;
    public $configDir  = null;
    public $pagesDir   = null;
    public $pluginDir  = null;
    public $userDir    = null;
    public $themesDir  = null;
    public $mediaDir   = null;
    public $backupDir  = null;

    public $rootURL    = null;
    public $baseURL    = null;
    public $themeURL   = null;
    public $mediaURL   = null;

    public $config     = null;

    public function __construct()
    {
        /* Define Paths */
        $this->definePaths();

        /* Load config */
        $config = new Config($this, null);
        $this->config = $config->get();

        /* Define frontend URLs */
        $this->defineURLs();
    }

    private function definePaths()
    {
        /**
         * Define various paths we need for the frontend and API calls
         * Note: we use '...Dir' and '...Path' to specify file system paths
         *       and URL for frontend relative URIs
         *       ... well at least we try to be consistent ;)
         *
         * @public  $baseDir    The CMS base directory
         * @public  $rootDir    The root directory we're running from
         * @public  $pagesDir   This is where the content lives
         * @public  $pluginDir  The plugins directory
         * @public  $themesDir  The themes directory
         * @public  $mediaDir   The media directory
         * @public  $backupDir  The backup directory
         */
        $this->baseDir = dirname(dirname(__FILE__));
        $this->rootDir = dirname($this->baseDir);

        $this->configDir = $this->baseDir . DIRECTORY_SEPARATOR . 'config';
        $this->pagesDir  = $this->baseDir . DIRECTORY_SEPARATOR . 'pages';
        $this->pluginDir = $this->baseDir . DIRECTORY_SEPARATOR . 'plugins';
        $this->userDir   = $this->baseDir . DIRECTORY_SEPARATOR . 'users';
        $this->themesDir = $this->rootDir . DIRECTORY_SEPARATOR . 'themes';
        $this->mediaDir  = $this->rootDir . DIRECTORY_SEPARATOR . 'media';
        $this->backupDir = $this->baseDir . DIRECTORY_SEPARATOR . 'backups';
    }

    private function defineURLs()
    {
        $activeTheme = $this->config['Theme'] ?? 'violet';

        /**
         * Define URLs
         * @public  $rootURL    The root URL we're running from
         * @public  $baseURL    The CMS base URL
         * @public  $themeURL   The frontend URL to the ACTIVE(!) Theme
         * @public  $mediaURL   The frontend URL to the media folder
         *
         * Unfortunately $_SERVER['DOCUMENT_ROOT'] is unreliable in some
         * shared hosting environments, therefore this hacky solution
         */
        $this->rootURL = '/' . ltrim(preg_replace('/(\/index|\/violet\/api|\/violet\/download)\.php/', '', $_SERVER['SCRIPT_NAME']), '/');
        $this->baseURL  = $this->rootURL . '/violet';
        $this->themeURL = $this->rootURL . '/themes/' . $activeTheme;
        $this->mediaURL = $this->rootURL . '/media/';
    }

    public function getCleanRoute($route)
    {
        $routeSegments = explode('/', ltrim($route, '/'));

        // strip 'home' route if configured

        $routeHome = trim($this->config['Routes']['Home'], '/');
        $routeStart = reset($routeSegments);
        $routeEnd = end($routeSegments);

        if ($routeStart === $routeHome && $this->config['Routes']['HideInURL']) {
            array_shift($routeSegments);
        }

        // strip ~.html/~.php part

        if ($routeEnd === '' || preg_match('/\.(html|php)$/mi', $routeEnd)) {
            array_pop($routeSegments);
        }

        $cleanRoute = array();

        foreach ($routeSegments as $segment) {
            if ('.' === $segment) {
                continue;
            } elseif ('..' === $segment) {
                array_pop($cleanRoute);
            } elseif (empty($segment)) {
                continue;
            } else {
                $cleanRoute[] = $segment;
            }
        }

        return '/' . implode('/', $cleanRoute);
    }

    public function getPathFromURL($url, $root = 'pages')
    {
        $url = Utils::sanitizeURL($url);

        $pathSegments = explode('/', ltrim($url, '/'));

        $pathEnd = end($pathSegments);

        $cleanPath = array();

        foreach ($pathSegments as $segment) {
            if ('.' === $segment) {
                continue;
            } elseif ('..' === $segment) {
                array_pop($cleanPath);
            } elseif (empty($segment)) {
                continue;
            } else {
                $cleanPath[] = $segment;
            }
        }

        if (isset($root) && false !== ($first = reset($cleanPath)) && $first === $root) {
            array_shift($cleanPath);
        }

        $path = empty($cleanPath) ? '' : DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $cleanPath);

        if ($root === 'pages') {
            return $this->pagesDir . $path;
        } elseif ($root === 'media') {
            return $this->mediaDir . $path;
        }

        return null;
    }
}
