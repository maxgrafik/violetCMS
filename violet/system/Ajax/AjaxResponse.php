<?php

/**
 * violetCMS - Ajax Response
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS\Ajax;

class AjaxResponse
{
    private $response;

    public function __construct($data = null)
    {
        $this->response = $data;
    }

    public function send()
    {
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-cache, no-store");
        header("Pragma: no-cache");

        http_response_code(200);
        echo $this->response;
    }

    public function sendJSON()
    {
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-cache, no-store");
        header("Pragma: no-cache");

        http_response_code(200);
        echo json_encode($this->response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
