<?php

/**
 * violetCMS - ZIP Handler
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS\Handler;

use VioletCMS\Ajax\APIError;
use VioletCMS\Handler\File;

class ZIP
{
    private $violet;

    public function __construct(\VioletCMS\VioletCMS $violet)
    {
        $this->violet = $violet;
    }

    public function installPlugin($zipFile)
    {
        if (!class_exists('ZipArchive', false)) {
            new APIError(500, 'ZIP Extension not available');
        }

        $zip = new \ZipArchive;

        if (false === $zip->open($zipFile)) {
            new APIError(400, 'Cannot read ZIP file');
        }

        $numFiles = $zip->numFiles;

        /**
         * A plugin may come with additional files
         * but 100 is probably too much
         */
        if ($numFiles === 0 || $numFiles > 100) {
            $zip->close();
            new APIError(400, 'Suspicious ZIP content');
        }

        $fileHandler = new File();

        $zipEntries = array();
        $pluginRoot = null;

        for ($i = 0; $i < $numFiles; $i++) {

            $zipEntry = str_replace('\\', '/', $zip->getNameIndex($i));

            $pathSegments = explode('/', $zipEntry);

            /* no leading slash allowed */
            if (empty(reset($pathSegments))) {
                $zip->close();
                new APIError(400, 'Invalid ZIP format');
            }

            /* skip directories */
            if (empty(end($pathSegments))) {
                continue;
            }

            /**
             * empty path (count == 0) or
             * root entry (count == 1) which is not a directory
             * or nested too deep (count > 5)
             */
            if (count($pathSegments) < 2 || count($pathSegments) > 5) {
                $zip->close();
                new APIError(400, 'Invalid Plugin format');
            }

            /* allowed file types */
            $fileExtension = strtolower(pathinfo(end($pathSegments), PATHINFO_EXTENSION));
            if (!in_array($fileExtension, ['php','json','html','css','js','txt','md'])) {
                $zip->close();
                new APIError(400, 'Suspicious ZIP content');
            }

            /* unzipped file > 1MB */
            $stat = $zip->statIndex($i, \ZipArchive::FL_UNCHANGED);
            if ($stat['size'] > (1024*1024)) {
                $zip->close();
                new APIError(400, 'Suspicious ZIP content');
            }

            /* skip macOS stuff */
            if (in_array('__MACOSX', $pathSegments)) {
                continue;
            }

            /* check path */
            foreach ($pathSegments as &$pathSegment) {

                /* skip entries with invisible files */
                if (substr($pathSegment, 0, 1) === '.') {
                    continue 2;
                }

                /* empty path segment */
                if (empty($pathSegment)) {
                    $zip->close();
                    new APIError(400, 'Invalid ZIP format');
                }

                /* sanitize file names */
                $pathSegment = $fileHandler->sanitizeFileName($pathSegment);
            }

            /* different root entries (must only be one) */
            $pluginRoot = $pluginRoot ?? $pathSegments[0];
            if ($pluginRoot !== $pathSegments[0]) {
                $zip->close();
                new APIError(400, 'Invalid Plugin format');
            }

            $targetPath = implode(File::DS, $pathSegments);

            $zipEntries[$zip->getNameIndex($i)] = $targetPath;
        }

        /**
         * check if there's even a plugin.php/config.json
         */

        $hasPlugin = false;
        $hasConfig = false;
        foreach ($zipEntries as $targetPath) {
            $baseName = pathinfo($targetPath, PATHINFO_BASENAME);
            $hasPlugin = ($baseName === 'plugin.php') ? true : $hasPlugin;
            $hasConfig = ($baseName === 'config.json') ? true : $hasConfig;
        }

        if (!$hasPlugin || !$hasConfig) {
            $zip->close();
            new APIError(400, 'Invalid Plugin format');
        }

        /**
         * build directory hierarchy and copy ZIP entry to target
         */

        foreach ($zipEntries as $zipEntry => $targetPath) {

            $targetDir = pathinfo($targetPath, PATHINFO_DIRNAME);

            $dir = $this->violet->pluginDir;
            $seg = explode(File::DS, $targetDir);

            while($seg) {
                if (!is_dir($dir . File::DS . $seg[0])) {
                    $dir = $fileHandler->createDirectory($dir, $seg[0]);
                } else {
                    $dir = $dir . File::DS . $seg[0];
                }
                array_shift($seg);
            }

            copy('zip://'.$zipFile.'#'.$zipEntry, $this->violet->pluginDir . File::DS . $targetPath);

        }

        $zip->close();
    }

    public function createBackup()
    {
        $zipName = date('Y-m-d_His') . '-violetCMS';

        $zipFile = $this->violet->backupDir . File::DS . $zipName . '.zip';

        $zip = new \ZipArchive;

        if (false === $zip->open($zipFile, \ZipArchive::CREATE)) {
            new APIError(500, 'Cannot create Backup');
        }

        $this->zipFolder($this->violet->rootDir, $zip, $zipName);

        $zip->close();
    }

    private function zipFolder($dir, &$zipFile, $localRoot)
    {
        if (!is_dir($dir)) {
            return;
        }

        $localDirName = $localRoot . '/';

        if (false === $zipFile->addEmptyDir($localDirName)) {
            $zipFile->close();
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

                $localPath = $localDirName . $file;

                if (is_dir($filePath)) {
                    $this->zipFolder($filePath, $zipFile, $localPath);
                } else {
                    if (false === $zipFile->addFile($filePath, $localPath)) {
                        $zipFile->close();
                        new APIError(500, 'Error writing to Backup');
                    }
                }

            }
            closedir($handle);

        } else {
            $zipFile->close();
            new APIError(500, 'Error reading directory: '.$dir);
        }
    }

}
