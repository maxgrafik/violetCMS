<?php

/**
 * violetCMS - Download Handler
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS\Handler;

use VioletCMS\VioletCMS;
use VioletCMS\Ajax\APIError;
use VioletCMS\Handler\JWT;
use VioletCMS\Handler\File;

class Download
{
    public static function verifyRequest()
    {
        $violet = new VioletCMS();
        $JWT = new JWT($violet);

        /**
         * Get download token and verify
         */

        $token = $_SERVER['QUERY_STRING'] ?? null;

        if (!$token || false === ($payload = $JWT->verifyNonce($token))) {
            new APIError(403, $token ? 'Download link expired' : 'Forbidden');
        }

        /**
         * Get file from backupDir
         */

        $fileHandler = new File();

        $filePath = $violet->backupDir . File::DS . $fileHandler->sanitizeFileName($payload->name);

        if (!is_file($filePath)) {
            new APIError(404, 'File not found');
        }

        /**
         * Send file as attachment
         */

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($filePath).'"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private', false);
        header('Pragma: public');
        header('Expires: 0');
        readfile($filePath);
    }
}
