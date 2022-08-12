<?php

/**
 * violetCMS - Upload Controller
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * Handles upload of user files
 */

namespace VioletCMS\Controller;

use VioletCMS\Ajax\APIError;
use VioletCMS\Handler\File;
use VioletCMS\Handler\ZIP;

class Upload extends Controller
{
    private $fileHandler;

    public function get()
    {
        return false;
    }

    public function set($null, $args = null)
    {
        if (empty($args) || !isset($args['target'])) {
            new APIError(400, 'Invalid arguments');
        }

        $url   = $_POST['directory'] ?? null;
        $files = $_FILES['uploads'] ?? null;

        if (!isset($url) || !isset($files) || !isset($files['error'])) {

            // https://stackoverflow.com/questions/7852910/php-empty-post-and-files-when-uploading-larger-files/9908619#9908619

            if (intval($_SERVER['CONTENT_LENGTH']) > 0 && count($_POST) === 0) {
                new APIError(400, 'Upload failed. File probably exceeds PHP filesize limit.');
            } else {
                new APIError(400, 'Invalid parameters');
            }
        }

        $this->fileHandler = new File();

        $target = $args['target'];

        switch ($target) {
        case 'media':
            $targetDir = $this->violet->getPathFromURL($url, 'media');
            break;
        case 'plugins':
            $targetDir = $this->violet->pluginDir;
            break;
        default:
            new APIError(400, 'Invalid upload target');
        }

        $errMsg = '';

        foreach ($files['tmp_name'] as $index => $tmpPath) {

            $fileName = $files['name'][$index];

            switch ($files['error'][$index]) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errMsg .= "File " . $fileName . " exceeds PHP filesize limit\n";
                continue 2;
            default:
                $errMsg .= "Unknown error\n";
                continue 2;
            }

            /** @future set upload limit via violet.json config? */

            if ($files['size'][$index] > (5*1024*1024)) {
                $errMsg .= "File " . $fileName . " exceeds filesize limit\n";
                continue;
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($tmpPath);

            if ($target === 'plugins' && $mimeType !== 'application/zip') {
                $errMsg .= "Not a ZIP file\n";
                continue;
            } elseif (false !== strpos($mimeType, 'php') || pathinfo($fileName, PATHINFO_EXTENSION) === 'php') {
                $errMsg .= "No upload of PHP files\n";
                continue;
            }

            $targetPath = $this->fileHandler->getSafeFileName($targetDir, $fileName);

            if ($target === 'plugins') {
                $zipHandler = new ZIP($this->violet);
                $zipHandler->installPlugin($tmpPath);

            } else {

                if (!move_uploaded_file($tmpPath, $targetPath)) {
                    $errMsg .= "Could not upload file " . $fileName . "\n";
                    continue;
                }

                if (substr($mimeType, 0, 5) === 'image') {
                    $this->createThumbnail($targetPath);
                }
            }

        }

        if (!empty($errMsg)) {
            $response = array('error' => $errMsg);
        } else {
            $response = array('success' => $url);
        }

        return $response;
    }

    private function createThumbnail($file)
    {
        $imagetype = exif_imagetype($file);

        switch ($imagetype) {
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($file);
            break;
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($file);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($file);
            break;
        default:
            return;
        }

        if (!$image) {
            return;
        }

        // currently 'hardcoded' due to admin backend css layout for media
        $thumbMaxWidth  = 100;
        $thumbMaxHeight = 50;

        $width  = imagesx($image);
        $height = imagesy($image);

        $scale = min($thumbMaxWidth/$width, $thumbMaxHeight/$height);

        $targetWidth  = floor($width  * $scale);
        $targetHeight = floor($height * $scale);

        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);

        imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $filePath = pathinfo($file);

        $thumbsDir = $filePath['dirname'] . File::DS . 'thumbs';
        if (!file_exists($thumbsDir)) {
            $this->fileHandler->createDirectory($filePath['dirname'], 'thumbs');
        }

        $thumbnailPath = $thumbsDir . File::DS . $filePath['basename'] . '.jpg';

        imagejpeg($thumbnail, $thumbnailPath, 100);
    }

}
