<?php

/**
 * violetCMS - Ajax Request
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 */

namespace VioletCMS\Ajax;

class AjaxRequest
{
    public $user = null;
    public $controller = null;
    public $action = null;
    public $args = null;

	public function __construct($user)
    {
        $this->user = $user;

        if (isset($_GET['q'])) {

            /* cleanup query */
            $query = preg_replace('/[^a-z,]/', '', $_GET['q']);

            if (strpos($query, ',') === false) {

                /* if the query does not contain commas, it's a simple one word request */
                $this->controller[] = 'VioletCMS\\Controller\\' . ucfirst(strtolower($query));

            } else {

                /* multiple requests at once - but we limit the number, i.e. no q=,,,,,,,,,,... attempts */
                $queries = explode(',', $query, 5);
                foreach ($queries as $q) {
                    $this->controller[] = 'VioletCMS\\Controller\\' . ucfirst(strtolower($q));
                }
            }

            $this->args = $_GET;
            unset($this->args['q']);

            /* method stays the same even for a multi-request */
            $this->action = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'set' : 'get';

        }
    }

}
