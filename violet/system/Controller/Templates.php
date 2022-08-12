<?php

/**
 * violetCMS - Templates Controller
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * - Gets available templates of active Theme
 * - Loads named template files
 */

namespace VioletCMS\Controller;

use VioletCMS\Handler\File;

class Templates extends Controller
{
    private $TemplatesDir = null;
    private $SectionsDir = null;

    public function __construct(...$args) {
        parent::__construct(...$args);

        $activeThemeDir = $this->violet->themesDir . File::DS . ($this->violet->config['Theme'] ?? '');

        if (!is_dir($activeThemeDir)) {
            return;
        }

        $templatesDir = $activeThemeDir . File::DS . 'templates';

        if (!is_dir($templatesDir)) {
            return;
        }

        $this->TemplatesDir = $templatesDir;

        $sectionsDir = $templatesDir . File::DS . 'sections';

        if (!is_dir($templatesDir)) {
            return;
        }

        $this->SectionsDir = $sectionsDir;
    }

    public function get()
    {
        return $this->getAllTemplates();
    }

    public function set($json)
    {
        return false;
    }

    public function getPageTemplate($name)
    {
        if (!is_dir($this->TemplatesDir)) {
            return null;
        }

        $filePath = $this->TemplatesDir . File::DS . strtolower($name) . '.html';

        if (is_file($filePath)) {
            return file_get_contents($filePath);
        }

        return null;
    }

    public function getSectionTemplate($name)
    {
        if (!is_dir($this->SectionsDir)) {
            return null;
        }

        $filePath = $this->SectionsDir . File::DS . strtolower($name) . '.html';

        if (is_file($filePath)) {
            return file_get_contents($filePath);
        }

        return null;
    }

    public function getAllTemplates()
    {
        $templates = array();

        if (is_dir($this->TemplatesDir)) {

            $fileHandler = new File();

            $files = glob($this->TemplatesDir . File::DS . '*.html');

            ksort($files, SORT_NATURAL | SORT_FLAG_CASE);

            foreach ($files as $file) {

                $fileName = pathinfo($file, PATHINFO_BASENAME);
                $content = $fileHandler->getRawContentsOrNull($this->TemplatesDir, $fileName);

                if (!$content) {
                    continue;
                }

                $sections = array();

                if (preg_match_all('/{CONTENT}|{#([A-Z0-9-]+)#}/', $content, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $name = $match[1] ?? null;
                        $sections[] = $name ? strtolower($name) : null;
                    }
                }

                $templateName = pathinfo($file, PATHINFO_FILENAME);

                $templates[] = array(
                    'name'     => strtolower($templateName),
                    'sections' => $sections
                );
            }
        }

        return $templates;
    }

    public function loadTemplate($name)
    {
        $pageTemplate = $this->getPageTemplate($name);

        if (!$pageTemplate) {
            return null;
        }

        $sectionTemplates = array();

        if (preg_match_all('/{CONTENT}|{#([A-Z0-9-]+)#}/', $pageTemplate, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $placeholder = $match[0];
                $sectionName = $match[1] ?? null;
                if ($sectionName) {
                    $sectionTemplates[$placeholder] = $this->getSectionTemplate($sectionName);
                } else {
                    $sectionTemplates[$placeholder] = null;
                }
            }
        }

        return array(
            'page'     => $pageTemplate,
            'sections' => $sectionTemplates
        );
    }
}
