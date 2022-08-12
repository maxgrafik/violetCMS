<?php

/**
 * violetCMS - Ajax Request Dispatcher
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * Dispatches API requests to corresponding controllers
 */

namespace VioletCMS\Ajax;

use VioletCMS\VioletCMS;
use VioletCMS\Ajax\APIError;
use VioletCMS\Ajax\AjaxResponse;

class AjaxDispatcher
{
    public function dispatch(AjaxRequest $request)
    {
        if (empty($request->controller)) {
            new APIError(400);
        }

        $violet = new VioletCMS();
        $result = array();

        /**
         * Component dependencies
         * Some permissions need to be added for using the admin backend
         */

        $dependencies = array();
        $dependencies['Config']  = ['Sitemap','Themes','Info'];
        $dependencies['Sitemap'] = ['Templates'];
        $dependencies['Page']    = ['Sitemap','Templates','Plugins','Media'];


        /* required read permissions */

        $readPermissions = array_merge($request->user->access, ['Dashboard','Userprefs','Config']);
        foreach ($dependencies as $key => $value) {
            if (in_array($key, $request->user->access)) {
                $readPermissions = array_merge($readPermissions, $value);
            }
        }

        /* required write permissions */

        $writePermissions = array_merge($request->user->access, ['Userprefs']);
        if (in_array('Media', $request->user->access)) {
            $writePermissions[] = 'Upload';
        }

        try {

            foreach ($request->controller as $rc) {

                if (!class_exists($rc)) {
                    new APIError(400);
                }

                $ReflectionClass = new \ReflectionClass($rc);
                $className = $ReflectionClass->getShortName();

                if ($request->action === 'get' && !in_array($className, $readPermissions)) {
                    new APIError(403);
                } elseif ($request->action === 'set' && !in_array($className, $writePermissions)) {
                    new APIError(403);
                }

                $controller = $ReflectionClass->newInstance($violet, $request->user);

                if (!method_exists($controller, $request->action)) {
                    new APIError(400);
                }

                $ReflectionMethod = new \ReflectionMethod($controller, $request->action);

                if ($request->action === 'set') {
                    $data = ($className !== 'Upload') ? file_get_contents('php://input') : null;
                    $result[$className] = $ReflectionMethod->invoke($controller, $data, $request->args);
                } else {
                    $result[$className] = $ReflectionMethod->invoke($controller, $request->args);
                }

                if ($result[$className] === false) {
                    new APIError(403);
                }

            }

            if (isset($result)) {
                $response = new AjaxResponse($result);
                $response->sendJSON();
            } else {
                /* no result, no error, just 200 OK */
                http_response_code(200);
                die();
            }

        } catch (\ReflectionException $e) {
            new APIError(500);
		}
    }

}
