<?php

/**
 * violetCMS - Dashboard Controller
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * Get access statistics
 */

namespace VioletCMS\Controller;

use VioletCMS\Ajax\APIError;
use VioletCMS\Controller\Sitemap;

class Dashboard extends Controller
{
    public function get()
    {
        $logFileName = date('Y-m') . '.log';

        $logFile = $this->violet->baseDir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . $logFileName;

        if (!is_file($logFile)) {
            return array(
                'statistics'    => array(
                    'today' => 0,
                    'week'  => 0,
                    'month' => 0,
                    'byDay' => array(0,0,0,0,0,0,0)
                ),
                'topTenURLs'   => array(),
                'lastModified' => array()
            );
        }

        $timestamp = time();

        $today       = (int) date('d', $timestamp);
        $lastOfMonth = (int) date('t', $timestamp);
        $weekDay     = (int) date('w', $timestamp);
        $weekStart   = max(1, $today-$weekDay);
        $weekEnd     = min($lastOfMonth, $today+(6-$weekDay));

        $accessTodayTotal = 0;
        $accessWeekTotal  = 0;
        $accessMonthTotal = 0;
        $accessWeekByDay  = array(0,0,0,0,0,0,0);

        $topTenURLs = array();
        $lastModified = array();

        $regexPattern = '/(\S+)\s(\S+)\s(\S+)\s\[([^]]+)]\s"([^"]+)"\s(\d{3})\s(\d{1,})\s"([^"]+)"\s"([^"]+)"/';

        $handle = fopen($logFile, 'r');
        if ($handle) {
            while (($logEntry = fgets($handle)) !== false) {

                if (preg_match($regexPattern, $logEntry, $matches)) {
                    //$remoteIP    = $matches[1] ?? null;
                    $requestTime = \DateTime::createFromFormat('d/M/Y:H:i:s O', $matches[4]);
                    $request     = isset($matches[5]) ? preg_replace('/\S+\s([^\s]+)\s\S+/', '$1', $matches[5]) : null;
                    $statusCode  = $matches[6] ?? null;
                    //$referer     = $matches[8] ?? null;
                    //$userAgent   = $matches[9] ?? null;

                    $day = (int) $requestTime->format('d');

                    if ($statusCode == 200) {

                        $accessMonthTotal += 1;

                        if ($day >= $weekStart && $day <= $weekEnd) {
                            $accessWeekTotal += 1;
                            $accessWeekByDay[$day-$weekStart] += 1;
                        }

                        if ($day === $today) {
                            $accessTodayTotal += 1;
                        }

                        if (!isset($topTenURLs[$request])) { $topTenURLs[$request] = 0; }
                        $topTenURLs[$request] += 1;
                    }
                }

            }
            fclose($handle);
        } else {
            new APIError(500, 'Cannot open log file');
        }

        arsort($topTenURLs);
        array_splice($topTenURLs, 10);

        foreach ($topTenURLs as $key => &$value) {
            $value = array(
                'url' => $key,
                'count' => $value
            );
        }

        $this->getLastModified($this->violet->pagesDir, $lastModified);

        arsort($lastModified);
        array_splice($lastModified, 3);

        $sitemapController = new Sitemap($this->violet, null);

        foreach ($lastModified as $path => &$value) {
            $page = $sitemapController->getPageFromPath($path);
            $value = array(
                'page' => $page['title'],
                'url'  => $page['url'],
                'date' => date('D, j M Y H:i:s O', $value)
            );
        }

        return array(
            'statistics'    => array(
                'today' => $accessTodayTotal,
                'week'  => $accessWeekTotal,
                'month' => $accessMonthTotal,
                'byDay' => $accessWeekByDay
            ),
            'topTenURLs'   => array_values($topTenURLs),
            'lastModified' => array_values($lastModified)
        );
    }

    public function set($json) {}

    private function getLastModified($dir, &$pages)
    {
        if (is_dir($dir)) {

            $pageFile = $dir . DIRECTORY_SEPARATOR . 'page.md';
            $children = glob($dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

            if (is_file($pageFile)) {
                if (false !== ($timestamp = filemtime($pageFile))) {
                    $pages[$dir] = $timestamp;
                }
            }

            if (!empty($children)) {
                foreach ($children as $child) {
                    $this->getLastModified($child, $pages);
                }
            }
        }
    }

}
