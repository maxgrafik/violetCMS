<?php

/**
 * violetCMS - File Handler
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS\Handler;

use VioletCMS\Ajax\APIError;

class File
{
    public const DS = DIRECTORY_SEPARATOR;

    /**
     * Getting File Contents
     *
     * @param $dir       Parent directory
     * @param $fileName  Name of the file to deal with
     *
     * @public getRawContents        Get file contents w/o processing
     * @public getRawContentsOrNull  Get file contents w/o processing or null on error
     * @public getContents           Get file contents as parsed JSON
     * @public getContentsOrNull     Get file contents as parsed JSON or null on error
     * @public getDirectory          Get child directory
     */

    public function getRawContents($dir, $fileName)
    {
        if (!is_dir($dir)) {
            new APIError(400, 'Directory not found: '.$dir);
        }

        $sanitizedFileName = $this->sanitizeFileName($fileName);

        if (empty($sanitizedFileName)) {
            new APIError(400, 'Invalid file name: '.$fileName);
        }

        $filePath = $dir . File::DS . $sanitizedFileName;

        if (!is_file($filePath)) {
            new APIError(400, 'File not found: '.$sanitizedFileName);
        }

        return file_get_contents($filePath);
    }

    public function getRawContentsOrNull($dir, $fileName)
    {
        if (!is_dir($dir)) {
            return null;
        }

        $sanitizedFileName = $this->sanitizeFileName($fileName);

        if (empty($sanitizedFileName)) {
            return null;
        }

        $filePath = $dir . File::DS . $sanitizedFileName;

        if (!is_file($filePath)) {
            return null;
        }

        return file_get_contents($filePath);
    }

    public function getContents($dir, $fileName)
    {
        $json = $this->getRawContents($dir, $fileName);

        if (!$json || null === ($data = json_decode($json, true))) {
            new APIError(500, 'Invalid JSON: '.$fileName);
        }

        return $data;
    }

    public function getContentsOrNull($dir, $fileName)
    {
        $json = $this->getRawContentsOrNull($dir, $fileName);

        if (!$json || null === ($data = json_decode($json, true))) {
            return null;
        }

        return $data;
    }

    public function getDirectory($dir, $dirName)
    {
        if (!is_dir($dir)) {
            new APIError(400, 'Directory not found: '.$dir);
        }

        $sanitizedDirName = $this->sanitizeFileName($dirName);

        if (empty($sanitizedDirName)) {
            new APIError(400, 'Invalid directory name: '.$dirName);
        }

        $filePath = $dir . File::DS . $sanitizedDirName;

        if (!is_dir($filePath)) {
            new APIError(400, 'Directory not found: '.$sanitizedDirName);
        }

        return $filePath;
    }


    /**
     * Putting File Contents
     *
     * @param $dir       Parent directory
     * @param $fileName  Name of the file to deal with
     * @param $data      The data to write
     *
     * @public putRawContents  Put file contents w/o processing
     * @public putContents     Encode data as JSON and put file contents
     */

    public function putRawContents($dir, $fileName, $data)
    {
        if (!is_dir($dir)) {
            new APIError(400, 'Directory not found: '.$dir);
        }

        $sanitizedFileName = $this->sanitizeFileName($fileName);

        if (empty($sanitizedFileName)) {
            new APIError(400, 'Invalid file name: '.$fileName);
        }

        $filePath = $dir . File::DS . $sanitizedFileName;

        if (false === file_put_contents($filePath, $data, LOCK_EX)) {
            new APIError(500, 'Error writing file: '.$sanitizedFileName);
        }
    }

    public function putContents($dir, $fileName, $data)
    {
        if (false === ($json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))) {
            new APIError(500, 'Invalid JSON: '.$fileName);
        }

        $this->putRawContents($dir, $fileName, $json);
    }


    /**
     * Deleting files or directories
     *
     * @param $dir         Parent directory
     * @param $fileName    Name of the file to deal with
     *
     * @public deleteFile  Delete the given file
     * @public deleteDir   Recursively delete given directory
     */

    public function deleteFile($dir, $fileName)
    {
        if (!is_dir($dir)) {
            new APIError(400, 'Directory not found: '.$dir);
        }

        $sanitizedFileName = $this->sanitizeFileName($fileName);

        if (empty($sanitizedFileName)) {
            new APIError(400, 'Invalid file name: '.$fileName);
        }

        $filePath = $dir . File::DS . $sanitizedFileName;

        if (!is_file($filePath)) {
            new APIError(400, 'File not found: '.$sanitizedFileName);
        }

        unlink($filePath);
    }

    public function deleteDir($dir)
    {
        if (!is_dir($dir)) {
            new APIError(400, 'Directory not found: '.$dir);
        }

        if ($handle = opendir($dir)) {

            while (false !== ($file = readdir($handle))) {

                if ($file === '.' || $file === '..') {
                    continue;
                }

                $filePath = $dir . File::DS . $file;

                if (is_dir($filePath)) {
                    $this->deleteDir($filePath);
                } else {
                    unlink($filePath);
                }

            }
            closedir($handle);
            rmdir($dir);

        } else {
            new APIError(500, 'Error reading directory: '.$dir);
        }
    }


    /**
     * Creating directories
     *
     * @param $parentDir  Parent directory
     * @param $name       Name of the child directory
     *
     * @public createDirectory  Safely create new directory
     */

    public function createDirectory($parentDir, $dirName)
    {
        if (!is_dir($parentDir)) {
            new APIError(400, 'Directory not found: '.$parentDir);
        }

        $sanitizedDirName = $this->sanitizeFileName($dirName);

        if (empty($sanitizedDirName)) {
            new APIError(400, 'Invalid directory name: '.$dirName);
        }

        $dirPath = $parentDir . File::DS . $sanitizedDirName;

        if (false === mkdir($dirPath, 0755)) {
            new APIError(500, 'Error creating directory: '.$sanitizedDirName);
        }

        return $dirPath;
    }


    /**
     * Dealing with file names & paths
     *
     * @public sanitizeFileName    Strip invalid chars from file name
     * @public getUniqueFileName   Get unique name for file in directory
     * @public getSafeFileName     Same as above, without complaining about invalid file names
     */

    public function sanitizeFileName($name)
    {
        /**
         * Yes, we're quite strict here
         */

        $fileName = preg_replace('/[^A-Za-z0-9-_.]/', '', pathinfo($name, PATHINFO_FILENAME));
        $fileExtension = preg_replace('/[^a-z0-9]/', '', pathinfo($name, PATHINFO_EXTENSION));

        $fileName = trim($fileName, '.-');

        if (empty($fileName)) {
            return null;
        }

        // limit length
        $fileName = substr($fileName, 0, 255 - ($fileExtension ? strlen($fileExtension) + 1 : 0));

        return $fileName . (empty($fileExtension) ? '' : '.' . $fileExtension);
    }

    public function getUniqueFileName($dir, $suggestedName)
    {
        if (!is_dir($dir)) {
            new APIError(400, 'Directory not found: '.$dir);
        }

        $sanitizedFileName = $this->sanitizeFileName($suggestedName);

        if (empty($sanitizedFileName)) {
            new APIError(400, 'Invalid file name: '.$suggestedName);
        }

        $fileName = pathinfo($sanitizedFileName, PATHINFO_FILENAME);
        $fileExtension = pathinfo($sanitizedFileName, PATHINFO_EXTENSION);

        if ($handle = opendir($dir)) {

            $files = array();

            while (false !== ($file = readdir($handle))) {
                $files[] = $file;
            }
            closedir($handle);

            if (in_array($sanitizedFileName, $files)) {
                $counter = 1;
                $sanitizedFileName = $fileName . '-' . $counter . (empty($fileExtension) ? '' : '.' . $fileExtension);
                while (in_array($sanitizedFileName, $files)) {
                    $counter++;
                    $sanitizedFileName = $fileName . '-' . $counter . (empty($fileExtension) ? '' : '.' . $fileExtension);
                }
            }
            return $sanitizedFileName;

        } else {
            new APIError(500, 'Error reading directory: '.$dir);
        }
    }

    public function getSafeFileName($dir, $suggestedName)
    {
        if (!is_dir($dir)) {
            new APIError(400, 'Directory not found: '.$dir);
        }

        $fileName = $this->sanitizeFileName($suggestedName);

        if (empty($fileName)) {
            $fileExtension = preg_replace('/[^a-z0-9]/', '', pathinfo($suggestedName, PATHINFO_EXTENSION));
            $fileName = 'untitled' . (empty($fileExtension) ? '' : '.' . $fileExtension);
        }

        $fileSafeName = $this->getUniqueFileName($dir, $fileName);

        return $dir . File::DS . $fileSafeName;
    }

    public function getRelativePath($path, $basePath)
    {
        if (0 === strpos($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }
        return $path;
    }
}
