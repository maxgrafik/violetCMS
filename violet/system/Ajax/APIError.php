<?php

/**
 * violetCMS - API Errors
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * Stops script execution with a given error code
 */

namespace VioletCMS\Ajax;

class APIError
{
    private $httpErrorCodes = [
        200 => 'OK',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        423 => 'Locked',
        500 => 'Internal Server Error'
    ];

    public function __construct($errCode, $errMsg = null)
    {
        if ($errCode == 401) {
            header("WWW-Authenticate: Bearer");
        }

        http_response_code($errCode);
        exit($errMsg ?? $this->httpErrorCodes[$errCode]);
    }
}
