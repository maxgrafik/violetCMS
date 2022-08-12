<?php

/**
 * violetCMS - Backup Controller
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * - Get list of backups
 * - Create/delete backups
 */

namespace VioletCMS\Controller;

use VioletCMS\Ajax\APIError;
use VioletCMS\Handler\File;
use VioletCMS\Handler\JWT;
use VioletCMS\Handler\ZIP;
use VioletCMS\Handler\GZIP;
use VioletCMS\Utils;

class Backups extends Controller
{
    public function get()
    {
        $JWT = new JWT($this->violet);

        $dir = $this->violet->backupDir;

        if (!is_dir($dir)) {
            $fileHandler = new File();
            $fileHandler->createDirectory($this->violet->baseDir, 'backups');
            $dir = $this->violet->baseDir . File::DS . 'backups';
        }

        $backupFiles = glob($dir.File::DS.'*.{zip,tar.gz}', GLOB_BRACE);

        $backupList = array();

        foreach ($backupFiles as $file) {

            $downloadToken = $JWT->createNonce(array(
                'name' => pathinfo($file, PATHINFO_BASENAME)
            ));
            $downloadURL = $this->violet->baseURL . File::DS . 'download.php?' . $downloadToken;

            $backupList[] = array(
                /* Anything but RFC2822 date is handled inconsistently by browsers */
                'date' => date('D, j M Y H:i:s O', filemtime($file)),
                'size' => Utils::formatBytes(filesize($file)),
                'name' => pathinfo($file, PATHINFO_BASENAME),
                'url'  => $downloadURL
            );
        }

        return $backupList;
    }

    public function set($json, $args = null)
    {
        if (empty($args) || !isset($args['action'])) {
            new APIError(400, 'Invalid arguments');
        }

        switch ($args['action']) {
        case 'create':
            $this->createBackup();
            break;
        case 'delete':
            $this->deleteBackup($args);
            break;
        default:
            new APIError(400, 'Action not supported');
        }

        return null;
    }

    private function createBackup()
    {
        if (class_exists('ZipArchive', false)) {

            // Use zip extension if available
            $zipHandler = new ZIP($this->violet);
            $zipHandler->createBackup();

        } else {

            // else try gzip
            $gzipHandler = new GZIP($this->violet);
            $gzipHandler->createBackup();

        }
    }

    private function deleteBackup($args)
    {
        if (!isset($args['name'])) {
            new APIError(400, 'Invalid arguments');
        }

        $fileHandler = new File();

        $fileHandler->deleteFile($this->violet->backupDir, $args['name']);
    }

}
