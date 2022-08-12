<?php

/**
 * violetCMS - Date Plugin
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * This plugin uses JSON data from the Unicode CLDR Project (http://cldr.unicode.org)
 * https://github.com/unicode-cldr/cldr-json
 * Copyright © 1991-2020 Unicode, Inc. All rights reserved.
 */

namespace VioletCMS\Plugins;

use VioletCMS\Plugin;

class DatePlugin extends Plugin
{
    private $CLDRData;

    public function getSubscribedEvents()
    {
        return [
            'onContentLoaded'  => 'formatDate'
        ];
    }

    public function formatDate($value)
    {
        list($date, $locale) = array_pad(explode('@', $value), 2, null);

        if (!$locale) {
            $locale = 'en';
        }

        if ($date === 'published') {
            $date = $this->context->page['Frontmatter']['publishDate'] ?? $this->context->page['Frontmatter']['date'] ?? null;
        }

        if ($date === 'today') {
            $date = date("Y-m-d");
        }

        if (!$date) {
            return '';
        }

        $date = strtotime($date);
        $data = $this->getCLDR($locale);

        if ($data) {

            $dateFormat = $data["main"][$locale]["dates"]["calendars"]["gregorian"]["dateFormats"]["long"];
            $monthNames = $data["main"][$locale]["dates"]["calendars"]["gregorian"]["months"]["format"]["wide"];

            if (preg_match_all("/'[^']+'|y{1,4}|M{1,4}|d{1,2}|./", $dateFormat, $matches, PREG_SET_ORDER)) {

                $result = array();

                foreach ($matches as $match) {
                    switch ($match[0]) {
                    case 'yy':
                        $result[] = date('y', $date);
                        break;
                    case 'y':
                    case 'yyy':
                    case 'yyyy':
                        $result[] = date('Y', $date);
                        break;
                    case 'M':
                        $result[] = date('n', $date);
                        break;
                    case 'MM':
                        $result[] = date('M', $date);
                        break;
                    case 'MMMM':
                        $result[] = $monthNames[(int) date('n', $date)];
                        break;
                    case 'd':
                        $result[] = date('j', $date);
                        break;
                    case 'dd':
                        $result[] = date('d', $date);
                        break;
                    default:
                        $result[] = str_replace("'", "", $match[0]);
                    }
                }
                return implode('', $result);
            }
        }

        // fallback
        return date('F j, Y', $date);
    }

    private function getCLDR(&$locale)
    {
        $loc = explode('-', str_replace('_', '-', $locale));
        $locale = strtolower($loc[0]);

        if (isset($this->CLDRData[$locale])) {
            return $this->CLDRData[$locale];
        }

        // try loading from file
        $CLDRDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'i18n';
        $localeFile = $CLDRDir . DIRECTORY_SEPARATOR . $locale . '.json';

        if (!file_exists($localeFile)) {
            return false;
        }

        $json = file_get_contents($localeFile);
        $this->CLDRData[$locale] = json_decode($json, true);

        return $this->CLDRData[$locale];
    }

}
