<?php

/**
 * violetCMS - GZIP Handler
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS\Handler;

use VioletCMS\Ajax\APIError;
use VioletCMS\Handler\File;

class GZIP
{
    private $violet;

    public function __construct(\VioletCMS\VioletCMS $violet)
    {
        $this->violet = $violet;
    }

    public function createBackup()
    {
        $archiveName = date('Y-m-d_His') . '-violetCMS';

        $archiveFile = $this->violet->backupDir . File::DS . $archiveName . '.tar';

        $archive = new \PharData($archiveFile);

        $this->addFolder($this->violet->rootDir, $archive, '/' . $archiveName);

        $archive->compress(\Phar::GZ);

        unlink($archiveFile);
    }

    private function addFolder($dir, &$archive, $currentPath)
    {
        if (!is_dir($dir)) {
            return;
        }

        try {
            $archive->addEmptyDir($currentPath);
        } catch (Exception $e) {
            new APIError(500, 'Error writing to Backup');
        }

        if ($handle = opendir($dir)) {

            while (false !== ($file = readdir($handle))) {

                /* hide non-files */
                if ($file === '.' || $file === '..') {
                    continue;
                }

                /* no invisible files except .htaccess */
                if (substr($file, 0, 1) === '.' && $file !== '.htaccess') {
                    continue;
                }

                $filePath = $dir . File::DS . $file;

                /* dont include the backups folder */
                if ($filePath === $this->violet->backupDir) {
                    continue;
                }

                if (is_link($filePath)) {
                    continue;
                }

                $localName = $currentPath . '/' . $file;

                if (is_dir($filePath)) {
                    $this->addFolder($filePath, $archive, $localName);
                } else {
                    try {
                        $archive->addFile($filePath, $localName);
                    } catch (Exception $e) {
                        new APIError(500, 'Error writing to Backup');
                    }
                }

            }
            closedir($handle);

        } else {
            new APIError(500, 'Error reading directory: '.$dir);
        }
    }

}
