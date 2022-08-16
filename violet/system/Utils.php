<?php

/**
 * violetCMS - Utility Functions
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS;

class Utils
{
    /**
     * https://stackoverflow.com/questions/2510434/format-bytes-to-kilobytes-megabytes-gigabytes
     */

    public static function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * https://stackoverflow.com/questions/1459739/php-serverhttp-host-vs-serverserver-name-am-i-understanding-the-ma/8909559#8909559
     */

    public static function getHost()
    {
        $sources = array('HTTP_X_FORWARDED_HOST', 'HTTP_HOST', 'SERVER_NAME');
        $sourceTransformations = array(
            "HTTP_X_FORWARDED_HOST" => function($value) {
                $elements = explode(',', $value);
                return trim(end($elements));
            }
        );
        $host = '';
        foreach ($sources as $source) {
            if (!empty($host)) {
                break;
            };
            if (empty($_SERVER[$source])) {
                continue;
            }
            $host = $_SERVER[$source];
            if (array_key_exists($source, $sourceTransformations)) {
                $host = $sourceTransformations[$source]($host);
            }
        }

        $host = preg_replace('/:\d+$/', '', $host);
        return trim($host);
    }

    public static function sanitizeURL($url)
    {
        $url = self::canonicalize($url);
        while (preg_match('/%[0-9a-fA-F]{2}/', $url)) {
            $url = urldecode($url);
        }
        $url = filter_var($url, FILTER_SANITIZE_URL);

        if (false !== ($parsed = parse_url($url))) {

            $scheme   = isset($parsed['scheme'])   ? $parsed['scheme'] . '://' : '';
            $host     = isset($parsed['host'])     ? $parsed['host']           : '';
            $port     = isset($parsed['port'])     ? ':' . $parsed['port']     : '';
            $user     = isset($parsed['user'])     ? $parsed['user']           : '';
            $pass     = isset($parsed['pass'])     ? ':' . $parsed['pass']     : '';
            $path     = isset($parsed['path'])     ? $parsed['path']           : '';
            $query    = isset($parsed['query'])    ? '?' . $parsed['query']    : '';
            $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

            if ($scheme && $scheme !== 'http://' && $scheme !== 'https://') {
                return '';
            }

            $path = implode("/", array_map("rawurlencode", explode("/", $path)));

            $pass = ($user || $pass) ? $pass . '@' : '';

            return $scheme.$user.$pass.$host.$port.$path.$query.$fragment;
        }

        return '';
    }

    public static function sanitizeAttribute($attr)
    {
        $attr = self::canonicalize($attr);
        return htmlspecialchars($attr, ENT_QUOTES, 'utf-8');
    }


    /**
     * https://stackoverflow.com/questions/2764781/how-to-decode-numeric-html-entities-in-php
     */

    public static function canonicalize($string)
    {
        $string = preg_replace_callback('/&#x([0-9a-fA-F]+);?/i', ['self', 'chr_utf8_callback_hex'], $string);
        $string = preg_replace_callback('/&#([0-9]+);?/', ['self', 'chr_utf8_callback_dec'], $string);
        $string = html_entity_decode($string, ENT_QUOTES, 'utf-8');
        $string = str_ireplace('&apos;', "'", $string);

        return filter_var($string, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
    }

    /**
     * Callback helper
     */

    private static function chr_utf8_callback_hex($matches)
    {
        return self::chr_utf8(hexdec($matches[1]));
    }

    private static function chr_utf8_callback_dec($matches)
    {
        return self::chr_utf8($matches[1]);
    }

    /**
    * Multi-byte chr(): Will turn a numeric argument into a UTF-8 string.
    *
    * @param mixed $num
    * @return string
    */

    private static function chr_utf8($num)
    {
        if ($num < 128) return chr($num);
        if ($num < 2048) return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
        if ($num < 65536) return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
        if ($num < 2097152) return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
        return '';
    }

}
