<?php

/**
 * violetCMS - Request Handler
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * Processes frontend requests
 */

namespace VioletCMS\Handler;

use VioletCMS\VioletCMS;
use VioletCMS\Controller\Sitemap;
use VioletCMS\Controller\Page;
use VioletCMS\Controller\Plugins;
use VioletCMS\Controller\Templates;
use VioletCMS\Handler\Log;
use Vendor\Parsedown\ParsedownViolet;
use VioletCMS\Utils;

class Request
{
    protected $violet;

    private $sitemapController;
    private $pageController;
    private $pluginsController;
    private $templatesController;

    private $logHandler;

    private $context;

    public function __construct()
    {
        $this->violet = new VioletCMS();

        $this->sitemapController   = new Sitemap($this->violet, null);
        $this->pageController      = new Page($this->violet, null);
        $this->pluginsController   = new Plugins($this->violet, null);
        $this->templatesController = new Templates($this->violet, null);

        $this->logHandler = new Log($this->violet);

        $this->context = (object) [
            'route'   => null,
            'query'   => null,
            'draft'   => false,
            'is404'   => false,
            'sitemap' => $this->sitemapController->get(),
            'page'    => null
        ];
    }

    public function process()
    {
        /* split request_uri */
        list($request, $query) = array_pad(explode('?', $_SERVER['REQUEST_URI']), 2, null);

        /* make the request CMS relative */
        $request = str_replace($this->violet->rootURL, '', $request);

        /* cleanup route */
        $route = $this->violet->getCleanRoute($request);

        if ($route !== $request) {
            $this->redirect301($route, $query);
        }

        $this->context->route = $route;
        $this->context->query = $query;
        $this->context->draft = ($query === 'draft' ? true : false);

        /* load plugins and inject context */
        $this->pluginsController->loadPlugins($this->context);

        /* process route */
        echo $this->processRoute($route, $query);
    }

    private function processRoute($route, $query = null)
    {
        /* get page info from sitemap */
        $pageInfo = $this->sitemapController->getPageFromURL($route);

        /* not found? */
        if (!$pageInfo) {
            $this->redirect404();
        }

        /* check published state and dates */
        if (!$this->context->draft) {

            if ($pageInfo['published'] === false) {
                $this->redirect404();
            }

            $today = date("Y-m-d");
            $publishDate   = $pageInfo['publishDate'] ?? null;
            $unpublishDate = $pageInfo['unpublishDate'] ?? null;

            if (isset($publishDate) && $publishDate > $today) {
                $this->redirect404();
            }

            if (isset($unpublishDate) && $unpublishDate <= $today) {
                $this->redirect404();
            }
        }

        /* get page contents */
        $page = $this->pageController->get([
            'url'   => $pageInfo['url'],
            'draft' => $this->context->draft
        ]);

        /* check redirect */
        if (isset($page['Frontmatter']['redirectURL'])) {
            $this->redirect301($page['Frontmatter']['redirectURL'], null);
        }

        $this->context->page = $page;


        /* load template */
        $templateName = $page['Frontmatter']['template'] ?? null;

        if (!$templateName) {
            $this->redirect404('No template specified.');
        }

        $template = $this->templatesController->loadTemplate($templateName);

        if (!$template) {
            $this->redirect404('Template "' . $templateName . '" does not exist.');
        }


        /* setup Markdown parser */
        $homeRoute = $this->violet->config['Routes']['HideInURL'] ? $this->violet->config['Routes']['Home'] : '';

        $Parsedown = new ParsedownViolet($this->violet->rootURL, $homeRoute);
        $Parsedown->setSafeMode($this->violet->config['Markdown']['EscapeHTML'] ?? true);
        $Parsedown->setUrlsLinked($this->violet->config['Markdown']['AutoURLLinks'] ?? false);
        $Parsedown->setBreaksEnabled($this->violet->config['Markdown']['AutoLineBreak'] ?? true);


        /**
         * split page content into sections
         * the order of these should be in sync with the sections specified in page template
         */
        $pageContents = array_map('trim', explode('~~~section-marker~~~', $page['Content']));

        /* handle each template section */
        foreach ($template['sections'] as $key => &$sectionTemplate) {

            /* invoke plugins for section template */
            if (isset($sectionTemplate)) {
                $this->pluginsController->invoke('onTemplateLoaded', $sectionTemplate);
            }

            /* get section content */
            $index = array_search($key, array_keys($template['sections']), true);

            $sectionContent = $pageContents[$index] ?? '';

            /* invoke plugins for content */
            $this->pluginsController->invoke('onContentLoaded', $sectionContent);

            /* parse markdown */
            $sectionContent = $Parsedown->text($sectionContent);

            /* finish section */
            if (isset($sectionTemplate)) {
                $sectionTemplate = str_replace('{CONTENT}', $sectionContent, $sectionTemplate);
            } else {
                $sectionTemplate = $sectionContent;
            }
        }

        /* invoke plugins for page template */
        $this->pluginsController->invoke('onTemplateLoaded', $template['page']);

        /* bring everything together */
        if (preg_match_all('/{CONTENT}|{#[A-Z0-9-]+#}/', $template['page'], $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $placeholder = $match[0];
                $content = $template['sections'][$placeholder] ?? null;
                if (isset($content)) {
                    $template['page'] = str_replace($placeholder, $content, $template['page']);
                }
            }
        }

        /* invoke onBeforeRender plugins */
        $this->pluginsController->invoke('onBeforeRender', $template['page']);

        /* remove any leftover unused placeholders */
        $finalHTML = preg_replace('/{#[A-Za-z0-9-]+#}/', '', $template['page']);

        /* log access if not draft */
        if (!$this->context->draft) {
            $this->logHandler->access(200, strlen($finalHTML));
        }

        /* we're done */
        return $finalHTML;
    }

    private function redirect301($route, $query)
    {
        if (substr($route, 0, 1) === '/') {
            $newRoute = $this->violet->rootURL . $route . ($query ? '?'.$query : '');
        } else {
            $newRoute = $route . ($query ? '?'.$query : '');
        }

        $this->logHandler->access(301, 0);

        $newRoute = Utils::sanitizeURL($newRoute);

        header("Location: " . $newRoute);
        http_response_code(301);
        die();
    }

    private function redirect404($msg = null)
    {
        if (!$this->context->is404) {

            $errorRoute = $this->violet->config['Routes']['Redirect404'];

            /* don't run into a 404 loop */
            $this->context->is404 = true;
            $this->context->route = $errorRoute;

            if ($errorRoute === '/error') {

                $html = $this->templatesController->getPageTemplate('error');

                if (!empty($html)) {

                    $html = str_replace('{CONTENT}', ($msg ?? '404 – Not found'), $html);

                    $this->pluginsController->invoke('onTemplateLoaded', $html);
                    $this->pluginsController->invoke('onBeforeRender', $html);

                    $this->logHandler->access(404, strlen($html));

                    http_response_code(404);
                    echo $html;
                    die();
                }

            } elseif ($errorRoute) {

                /* don't repeat yourself */
                $html = $this->processRoute($errorRoute);

                $this->logHandler->access(404, strlen($html));

                http_response_code(404);
                echo $html;
                die();
            }
        }

        $this->logHandler->access(404, 0);

        http_response_code(404);
        die($msg ?? '<h1>404 - Not found</h1>');
    }

}
