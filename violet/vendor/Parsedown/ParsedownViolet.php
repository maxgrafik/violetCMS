<?php

/**
 * violetCMS - Parsedown Violet Extension
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * - Removes home route from links if configured
 * - Prepends rootURL to internal links/images
 * - prevents plugin markers {{...|...}} and
 *   comments {#...#} to be parsed as paragraphs
 */

namespace Vendor\Parsedown;

use Vendor\Parsedown\Parsedown;
use Vendor\Parsedown\ParsedownExtra;

class ParsedownViolet extends ParsedownExtra
{
    private $rootURL;
    private $homeURL;

    function __construct($rootURL = "", $homeURL = "")
    {
        parent::__construct();

        $this->BlockTypes['{'][] = 'VioletPlugin';
        $this->rootURL = $rootURL;
        $this->homeURL = $homeURL;
    }

    protected function inlineLink($Excerpt)
    {
        $Inline = parent::inlineLink($Excerpt);

        if (!isset($Inline['element']['attributes']['href'])) { return $Inline; }

        $href = $Inline['element']['attributes']['href'];

        if (substr($href, 0, strlen($this->homeURL)) === $this->homeURL) {
            $href = substr($href, strlen($this->homeURL));
            $href = $href ?: '/';
        }

        if (substr($href, 0, 1) === '/' && substr($href, 0, strlen($this->rootURL)) !== $this->rootURL) {
            $href = $this->rootURL . $href;
        }

        $Inline['element']['attributes']['href'] = $href;

        return $Inline;
    }

    protected function inlineImage($Excerpt)
    {
        $Inline = parent::inlineImage($Excerpt);

        if (!isset($Inline['element']['attributes']['src'])) { return $Inline; }

        $src = $Inline['element']['attributes']['src'];

        if (substr($src, 0, 1) === '/' && substr($src, 0, strlen($this->rootURL)) !== $this->rootURL) {
            $Inline['element']['attributes']['src'] = $this->rootURL . $src;
        }

        return $Inline;
    }

    protected function blockVioletPlugin($Line)
    {
        if (preg_match('/^{{[^|}]+(\|[^}]+)?}}$/', $Line['text'], $matches)) {
            $Block = array(
                'isComment' => false,
                'complete' => true,
                'element' => array(
                    'name' => null,
                    'text' => $matches[0] . "\n"
                )
            );
            return $Block;
        }

        if (preg_match('/^{#[^}]+#}$/', $Line['text'], $matches)) {
            $Block = array(
                'isComment' => true,
                'complete' => true,
                'element' => array(
                    'name' => null,
                    'text' => ''
                )
            );
            return $Block;
        }

        if (preg_match('/^{{/', $Line['text'], $matches)) {
            $Block = array(
                'isComment' => false,
                'element' => array(
                    'name' => null,
                    'text' => $Line['body'] . "\n"
                )
            );
            return $Block;
        }

        if (preg_match('/^{#/', $Line['text'], $matches)) {
            $Block = array(
                'isComment' => true,
                'element' => array(
                    'name' => null,
                    'text' => ''
                )
            );
            return $Block;
        }

        return;
    }

    protected function blockVioletPluginContinue($Line, $Block)
    {
        if (isset($Block['complete'])) {
            return;
        }

        if (preg_match('/^[^}]*}}$/', $Line['text'], $matches)) {
            $Block['complete'] = true;
            $Block['element']['text'] .= $matches[0];
            return $Block;
        }

        if (preg_match('/^[^}]*#}$/', $Line['text'], $matches)) {
            $Block['complete'] = true;
            return $Block;
        }

        if (!$Block['isComment']) {
            $Block['element']['text'] .= $Line['body'] . "\n";
        }

        return $Block;
    }

    protected function blockVioletPluginComplete($Block)
    {
        return $Block;
    }

}
