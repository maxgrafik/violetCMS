<?php

/**
 * violetCMS - Logger
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS\Handler;

class Log
{
    private $violet;
    private $logDir;

    public function __construct(\VioletCMS\VioletCMS $violet)
    {
        $this->violet = $violet;
        $this->logDir = $this->violet->baseDir . DIRECTORY_SEPARATOR . 'logs';

        if (!is_dir($this->logDir)) {
            if (false === mkdir($this->logDir, 0755)) {
                new APIError(500, 'Error creating logs directory');
            }
        }
    }

    public function access($status = 200, $bytes = 0)
    {
        $logFileName = date('Y-m') . '.log';

        $logFile = $this->logDir . DIRECTORY_SEPARATOR . $logFileName;

        $logEntry = array();

        $logEntry[] = $_SERVER['REMOTE_ADDR'] ?? 'xxx.xxx.xxx.xxx';
        $logEntry[] = '-';
        $logEntry[] = '-';
        $logEntry[] = '['.date('d/M/Y:H:i:s O', $_SERVER['REQUEST_TIME']).']';
        $logEntry[] = '"'.$_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI'].' '.$_SERVER['SERVER_PROTOCOL'].'"';
        $logEntry[] = $status;
        $logEntry[] = $bytes;
        $logEntry[] = '"'.($_SERVER['HTTP_REFERER'] ?? '-').'"';
        $logEntry[] = '"'.($_SERVER['HTTP_USER_AGENT'] ?? '-').'"';

        file_put_contents($logFile, implode(' ', $logEntry)."\n", FILE_APPEND | LOCK_EX);
    }

    public function login($loginName, $deviceCookie)
    {
        $logFile = $this->logDir . DIRECTORY_SEPARATOR . 'logins.log';

        $name   = preg_replace('/[^A-Za-z0-9@.]/', '', $loginName);
        $cookie = $deviceCookie ?? '';

        $logEntry = '"' . time() . '","' . $name . '","' . $cookie . "\"\n";

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function getFailedLogins($loginName, $deviceCookie, $timestamp, $maxAttempts)
    {
        $logFile = $this->logDir . DIRECTORY_SEPARATOR . 'logins.log';

        if (!file_exists($logFile)) {
            return 0;
        }

        $failedAttempts = 0;
        $overallAttempts = 0;

        if (false !== ($handle = fopen($logFile, 'r'))) {
            while (false !== ($data = fgetcsv($handle))) {

                // looking for cookie lock
                if ($deviceCookie) {
                    if ((int) $data[0] >= $timestamp && $data[2] === $deviceCookie) {
                        $failedAttempts++;
                    }

                // looking for account lock
                } elseif ($loginName) {
                    if ((int) $data[0] >= $timestamp && $data[1] === preg_replace('/[^A-Za-z0-9@.]/', '', $loginName)) {
                        $failedAttempts++;
                    }
                }

                // dont loop any longer than necessary
                if ($failedAttempts >= $maxAttempts) {
                    break;
                }

                // overall attempts within lock time
                // we may decide to block all access if the overall number of failed logins
                // exceeds a certain threshold: e.g. userCount * maxAttempts
                /*
                $threshold = ???;
                if ((int) $data[0] >= $timestamp) {
                    $overallAttempts++;
                    if ($overallAttempts >= $threshold) {
                        $failedAttempts = $threshold;
                        break;
                    }
                }
                */
            }
            fclose($handle);
        }

        return $failedAttempts;
    }

    public function debug($msg)
    {
        $logFile = $this->logDir . DIRECTORY_SEPARATOR . 'debug.log';

        $timestamp = date("Y-m-d H:i:s");

        $logEntry = $timestamp . "\n";

        if (is_array($msg) || is_object($msg)) {
            $logEntry .= print_r($msg, true);
        } else {
            $logEntry .= $msg;
        }

        $logEntry .= "\n\n";

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
