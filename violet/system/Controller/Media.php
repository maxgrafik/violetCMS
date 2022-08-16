<?php

/**
 * violetCMS - Media Controller
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * Handles renaming/moving/deleting of user media files
 */

namespace VioletCMS\Controller;

use VioletCMS\Ajax\APIError;
use VioletCMS\Handler\File;

class Media extends Controller
{
    private $fileHandler;

    public function __construct(...$args)
    {
        parent::__construct(...$args);
        $this->fileHandler = new File();
    }


    public function get($args = null)
    {
        if (empty($args) || !isset($args['url'])) {
            new APIError(400);
        }

        return $this->getMediaFiles($args['url']);
    }

    public function set($json, $args = null)
    {
        if (empty($args) || !isset($args['action'])) {
            new APIError(400, 'Invalid arguments');
        }

        if (null === ($data = json_decode($json, true))) {
            new APIError(400, 'Invalid JSON');
        }

        switch ($args['action']) {
        case 'createdir':
            return $this->createDirectory($data);
            break;
        case 'rename':
            return $this->renameMediaFile($data);
            break;
        case 'move':
            return $this->moveMediaFiles($data);
            break;
        case 'delete':
            return $this->deleteMediaFiles($data);
            break;
        default:
            new APIError(400, 'Action not supported');
        }

        return null;
    }

    private function getMediaFiles($url)
    {
        $currentDir = $this->violet->getPathFromURL($url, 'media');

        if (is_dir($currentDir) && ($handle = opendir($currentDir))) {

            $files = array();

            while (false !== ($file = readdir($handle))) {

                /* hide invisible and non-files */
                if (substr($file, 0, 1) === '.') {
                    continue;
                }

                /* hide thumbs directories */
                if ($file === 'thumbs') {
                    continue;
                }

                $filePath = $currentDir . File::DS . $file;

                if (is_link($filePath)) {
                    continue;
                }

                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($filePath);

                /* hide php files */
                if (false !== strpos($mimeType, 'php') || pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
                    continue;
                }

                $fileURL = $this->fileHandler->getRelativePath($filePath, $this->violet->rootDir);
                $fileURL = str_replace(File::DS, '/', $fileURL);

                if (is_file($filePath)) {
                    $files[] = array('name' => $file, 'type' => $mimeType, 'url' => $fileURL);
                } elseif (is_dir($filePath)) {
                    $files[] = array('name' => $file, 'type' => 'directory', 'url' => $fileURL);
                }

            }
            closedir($handle);

            $currentURL = $this->fileHandler->getRelativePath($currentDir, $this->violet->rootDir);
            $currentURL = str_replace(File::DS, '/', $currentURL);

            return array('files' => $files, 'currentURL' => $currentURL, 'rootURL' => $this->violet->rootURL);
        }
    }

    private function createDirectory($args)
    {
        $url = $args['target'] ?? null;

        if (!$url) {
            new APIError(400, 'Invalid parameters');
        }

        $dir = $this->violet->getPathFromURL($url, 'media');

        if (!is_dir($dir)) {
            new APIError(400, 'Invalid URL');
        }

        $dirName = $this->fileHandler->getUniqueFileName($dir, 'untitled');

        $this->fileHandler->createDirectory($dir, $dirName);
    }

    private function renameMediaFile($args)
    {
        $file = $args['file'] ?? null;
        $name = $args['name'] ?? null;

        if (!$file || !$name) {
            new APIError(400, 'Invalid parameters');
        }

        $fileOldPath = $this->violet->getPathFromURL($file, 'media');

        if (!file_exists($fileOldPath)) {
            new APIError(400, 'Invalid URL');
        }

        $dir = pathinfo($fileOldPath, PATHINFO_DIRNAME);
        $fileNewPath = $this->fileHandler->getSafeFileName($dir, $name);

        rename($fileOldPath, $fileNewPath);

        $thumbDir = pathinfo($fileOldPath, PATHINFO_DIRNAME) . File::DS . 'thumbs';
        $thumbOldPath = $thumbDir . File::DS . pathinfo($fileOldPath, PATHINFO_BASENAME) . '.jpg';

        if (file_exists($thumbDir) && file_exists($thumbOldPath)) {
            $thumbNewPath = $thumbDir . File::DS . pathinfo($fileNewPath, PATHINFO_BASENAME) . '.jpg';
            rename($thumbOldPath, $thumbNewPath);
        }
    }

    private function moveMediaFiles($args)
    {
        $url = $args['target'] ?? null;
        $fileList = $args['fileList'] ?? null;

        if (!$url || empty($fileList)) {
            new APIError(400, 'Invalid parameters');
        }

        $dir = $this->violet->getPathFromURL($url, 'media');

        if (!is_dir($dir)) {
            new APIError(400, 'Invalid target URL');
        }

        foreach ($fileList as $file) {

            $sourcePath = $this->violet->getPathFromURL($file, 'media');

            if (!file_exists($sourcePath)) {
                continue;
            }

            $targetPath = $this->fileHandler->getSafeFileName($dir, pathinfo($file, PATHINFO_BASENAME));

            rename($sourcePath, $targetPath);

            // move thumbnail (if any)

            if (!is_dir($sourcePath)) {
                $sourceThumbsDir = pathinfo($sourcePath, PATHINFO_DIRNAME) . File::DS . 'thumbs';
                $targetThumbsDir = pathinfo($targetPath, PATHINFO_DIRNAME) . File::DS . 'thumbs';

                $sourceThumbPath = $sourceThumbsDir . File::DS . pathinfo($sourcePath, PATHINFO_BASENAME) . '.jpg';
                $targetThumbPath = $targetThumbsDir . File::DS . pathinfo($targetPath, PATHINFO_BASENAME) . '.jpg';

                if (file_exists($sourceThumbPath)) {
                    if (!file_exists($targetThumbsDir)) {
                        $this->fileHandler->createDirectory(pathinfo($targetPath, PATHINFO_DIRNAME), 'thumbs');
                    }
                    rename($sourceThumbPath, $targetThumbPath);
                }
            }
        }
    }

    private function deleteMediaFiles($args)
    {
        $fileList = $args['fileList'] ?? null;

        if (empty($fileList)) {
            new APIError(400, 'Invalid parameters');
        }

        foreach ($fileList as $file) {
            $path = $this->violet->getPathFromURL($file, 'media');

            if (is_dir($path)) {
                $this->fileHandler->deleteDir($path);
            } elseif (is_file($path)) {

                // delete thumb first (if any)
                $thumbDir = pathinfo($path, PATHINFO_DIRNAME) . File::DS . 'thumbs';
                $thumbnail = $thumbDir . File::DS . pathinfo($path, PATHINFO_BASENAME) . '.jpg';
                if (file_exists($thumbnail)) {
                    unlink($thumbnail);
                }

                unlink($path);
            }
        }
    }

}
