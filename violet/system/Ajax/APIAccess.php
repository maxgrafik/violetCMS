<?php

/**
 * violetCMS - API Access Verification
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * - do various sanity checks (headers, method)
 * - verify provided JWT and/or handle login/refresh/logout
 * - return User object for valid requests, null for login
 * - throw '401 Unauthorized' APIError on failure
 */

namespace VioletCMS\Ajax;

use VioletCMS\VioletCMS;
use VioletCMS\Ajax\APIError;
use VioletCMS\Ajax\AjaxResponse;
use VioletCMS\Controller\Users;
use VioletCMS\Handler\JWT;
use VioletCMS\Handler\Log;
use VioletCMS\Utils;

class APIAccess
{
    private static $violet;
    private static $JWT;

    public static function verifyRequest()
    {
        self::$violet = new VioletCMS();
        self::$JWT = new JWT(self::$violet);

        $method = $_SERVER['REQUEST_METHOD'] ?? null;

        if ($method !== 'POST' && $method !== 'GET') {
            new APIError(405);
        }

        /**
         * https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html#verifying-origin-with-standard-headers
         */

        $target = Utils::getHost(self::$violet->config['Routes']['Domain']);
        if (!$target) {
            new APIError(400);
        }

        $origin  = isset($_SERVER['HTTP_ORIGIN'])  ? parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST) : null;
        $referer = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) : null;

        if ($origin) {
            if ($origin !== $target) {
                new APIError(400);
            }
        } elseif ($referer) {
            if ($referer !== $target) {
                new APIError(400);
            }
        } else {
            new APIError(400);
        }

        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? null;

        if ($requestedWith !== 'XMLHttpRequest') {
            new APIError(400);
        }

        if ($method === 'POST') {

            $contentTypeHeader = $_SERVER['CONTENT_TYPE'] ?? ';';

            list($contentType, $charset) = array_map('trim', explode(';', $contentTypeHeader));

            if (strtolower($contentType) === 'multipart/form-data') {
                if (!isset($_GET['q']) || $_GET['q'] !== 'upload') {
                    new APIError(400);
                }
            } elseif (strtolower($contentType) !== 'application/json' || strtolower($charset) !== 'charset=utf-8') {
                new APIError(400);
            }
        }

        if (!isset($_GET['q'])) {
            new APIError(400);
        }

        /**
         * Handle Login - we neither have a fingerprint nor a token yet
         */

        if ($method === 'POST' && $_GET['q'] === 'login') {
            return self::handleLogin();
        }

        /**
         * https://cheatsheetseries.owasp.org/cheatsheets/JSON_Web_Token_for_Java_Cheat_Sheet.html#token-sidejacking
         */

        $fingerprint = $_COOKIE['violetFP'] ?? null;
        if (!$fingerprint) {
            new APIError(401);
        }

        /**
         * Handle Token Refresh - the flow is different from here
         */

        if ($method === 'POST' && $_GET['q'] === 'refresh') {
            return self::handleTokenRefresh();
        }

        /**
         * Proceed with default flow
         */

        $accessToken = self::getBearerToken();
        if (!$accessToken || !self::$JWT->verifyAccessToken($accessToken)) {
            self::revokeCookie();
            new APIError(401);
        }

        $payload = self::$JWT->getPayload($accessToken);
        if (!$payload || !isset($payload->jti) || !isset($payload->sub)) {
            self::revokeCookie();
            new APIError(401);
        }

        $hash = hash('sha256', $fingerprint);
        if (!hash_equals($hash, $payload->jti)) {
            self::revokeCookie();
            new APIError(401);
        }

        /**
         * Handle Logout ...
         */

        if ($method === 'POST' && $_GET['q'] === 'logout') {
            return self::handleLogout($payload);
        }

        /**
         * ... or process request, returning $payload to AjaxDispatcher
         */

        return $payload;
    }

    private static function getBearerToken()
    {
        $headers = self::getAuthorizationHeader();
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    private static function getAuthorizationHeader()
    {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

    private static function handleLogin()
    {
        /**
         * Correct response code for failed login attemps
         * must be 200 OK with no data returned
         */

        /* No credentials given */

        $contents = file_get_contents('php://input');
        if (empty($contents)) {
            new APIError(200, '');
        }

        /* throttle all login attempts for 0.1-0.5 seconds */

        usleep(mt_rand(1, 5) * 100000);

        $credentials = json_decode($contents, true);
        if (empty($credentials)) {
            new APIError(200, '');
        }

        $email = $credentials['email'] ?? null;
        $pass  = $credentials['pass'] ?? null;

        if (!$email || !$pass) {
            new APIError(200, '');
        }

        /**
         * https://owasp.org/www-community/Slow_Down_Online_Guessing_Attacks_with_Device_Cookies
         */

        $lockTime = 600; // 10 minutes
        $maxAttempts = 3;

        $deviceCookie = $_COOKIE['violetID'] ?? null;
        $isTrustedClient = $deviceCookie && self::$JWT->verifyAccessToken($deviceCookie);

        $logHandler = new Log(self::$violet);

        // trusted client -> check if device cookie is locked
        if ($isTrustedClient) {
            // failed attempts within the last $lockTime for this cookie
            $failedLogins = $logHandler->getFailedLogins(null, $deviceCookie, time()-$lockTime, $maxAttempts);
            if ($failedLogins >= $maxAttempts) {
                new APIError(200, '');
            }
        }

        // untrusted client -> check if email/name is locked
        if (!$isTrustedClient) {
            $deviceCookie = null;
            // failed attempts within the last $lockTime for this user ($email)
            $failedLogins = $logHandler->getFailedLogins($email, null, time()-$lockTime, $maxAttempts);
            if ($failedLogins >= $maxAttempts) {
                new APIError(200, '');
            }
        }

        $usersController = new Users(self::$violet, null);
        $users = $usersController->getAllUsers();

        if (empty($users)) {
            new APIError(200, '');
        }

        /* check given credentials */

        $authenticated = false;

        /**
         * https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html#compare-password-hashes-using-safe-functions
         */

        foreach ($users as $shortName => $user) {
            $hashOK  = password_verify($pass, $user['hash']);
            $emailOK = hash_equals(md5($user['email']), md5($email));
            $enabled = $user['enabled'] ?? false;
            if ($hashOK && $emailOK && $enabled) {
                $authenticated = $user;
                $authenticated['shortname'] = $shortName;
            }
        }

        /**
         * OWASP recommends validating that cookie->sub represents the user
         * who is actually trying to authenticate. This however prevents using
         * different user names on the same machine, which may or may not
         * be suitable. Uncomment as needed.
         */

        // if ($isTrustedClient) {
        //     $payload = self::$JWT->getPayload($deviceCookie);
        //     if ($payload && $payload->sub !== $authenticated['shortname']) {
        //         $authenticated = false;
        //     }
        // }


        /* register failed login attempt */

        if ($authenticated === false) {
            $logHandler->login($email, $deviceCookie);
            new APIError(200, '');
        }


        /* successful login */

        $fingerprint = bin2hex(random_bytes(32));
        $hash = hash('sha256', $fingerprint);

        $accessToken = self::$JWT->createAccessToken(array(
            'jti'    => $hash,
            'sub'    => $authenticated['shortname'],
            'access' => $authenticated['access']
        ), 600);

        $refreshToken = self::$JWT->createRefreshToken(array(
            'jti' => $hash,
            'sub' => $authenticated['shortname']
        ), 3600);

        $deviceToken = self::$JWT->createAccessToken(array(
            'jti' => bin2hex(random_bytes(16)),
            'sub' => $authenticated['shortname']
        ), 3600*24*7);

        if (!$accessToken || !$refreshToken || !$deviceToken) { // just in case
            new APIError(500, 'Error creating tokens');
        }

        self::setCookie('violetFP', $fingerprint, 0); // valid for this session only
        self::setCookie('violetID', $deviceToken, 3600*24*7);

        $response = new AjaxResponse(array(
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'bearer',
            'expires'       => 600
        ));
        $response->sendJSON();

        return null;
    }

    private static function handleLogout($payload)
    {
        self::$JWT->revokeUserKey($payload->sub);
        self::revokeCookie();

        /**
         * 204 (No Content) or 205 (Reset Content) would be a suitable response
         * Due to inconsistent browser behaviour, we return 200 (OK) here
         */

        http_response_code(200);
        exit();
    }

    private static function handleTokenRefresh()
    {
        $refreshToken = self::getBearerToken();
        if (!$refreshToken || !self::$JWT->verifyRefreshToken($refreshToken)) {
            self::revokeCookie();
            new APIError(401);
        }

        $payload = self::$JWT->getPayload($refreshToken);
        if (!$payload || !isset($payload->jti) || !isset($payload->sub)) {
            self::revokeCookie();
            new APIError(401);
        }

        $fingerprint = $_COOKIE['violetFP'];

        $hash = hash('sha256', $fingerprint);
        if (!hash_equals($hash, $payload->jti)) {
            self::$JWT->revokeUserKey($payload->sub);
            self::revokeCookie();
            new APIError(401);
        }

        /**
         * Does this user exist and is still enabled?
         */

        $usersController = new Users(self::$violet, null);
        $user = $usersController->getUserByName($payload->sub);

        if (empty($user) || !$user['enabled']) {
            self::$JWT->revokeUserKey($payload->sub);
            self::revokeCookie();
            new APIError(401);
        }

        /**
         * Issue new accessToken and refreshToken
         */

        $accessToken = self::$JWT->createAccessToken(array(
            'jti'    => $payload->jti,
            'sub'    => $payload->sub,
            'access' => $user['access']
        ), 600);

        $refreshToken = self::$JWT->createRefreshToken(array(
            'jti' => $payload->jti,
            'sub' => $payload->sub
        ), 3600);

        if (!$accessToken || !$refreshToken) { // just in case
            new APIError(500, 'Error creating tokens');
        }

        $response = new AjaxResponse(array(
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'bearer',
            'expires'       => 600
        ));
        $response->sendJSON();

        return null;
    }

    private static function setCookie($name, $data, $lifetime)
    {
        $host = Utils::getHost(self::$violet->config['Routes']['Domain']);
        if (!$host) {
            return;
        }

        $options = array(
            'expires'  => $lifetime !== 0 ? time()+$lifetime : 0,
            'path'     => self::$violet->rootURL,
            'domain'   => $host,
            'secure'   => (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off'),
            'httponly' => true,
            'samesite' => 'Strict'
        );
        setcookie($name, $data, $options);
    }

    private static function revokeCookie()
    {
        $host = Utils::getHost(self::$violet->config['Routes']['Domain']);
        if (!$host) {
            return;
        }

        $options = array(
            'expires'  => 1,
            'path'     => self::$violet->rootURL,
            'domain'   => $host,
            'secure'   => (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off'),
            'httponly' => true,
            'samesite' => 'Strict'
        );
        setcookie('violetFP', '', $options);
    }
}
