<?php

/**
 * violetCMS - JSON Web Tokens
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * Simplyfied JSON Web Token implementation
 *
 * Note:
 * The signing key is stored as .jwtkey in the current directory
 * The user keys are stored in the users directory as {shortname}.key
 */

namespace VioletCMS\Handler;

use VioletCMS\Handler\File;

class JWT
{
    private $violet;
    private $fileHandler;

    private $JWTKey;

    private $defaultExpTime = 600;  // default expiration time in seconds = 10 minutes
    private $leeway = 5;            // account for clock skew (5 seconds) may be to short


    /**
     * Constructor
     */

    public function __construct(\VioletCMS\VioletCMS $violet)
    {
        $this->violet = $violet;
        $this->fileHandler = new File();

        $this->JWTKey = $this->getKey();
    }


    /**
     * Create Access Token
     */

    public function createAccessToken($payload, $expTime = null)
    {
        return $this->createToken($payload, $this->JWTKey, $expTime);
    }


    /**
     * Create Refresh Token - signed with individual user key
     * The key will be renewed on every request so only the last
     * issued refresh token is valid
     */

    public function createRefreshToken($payload, $expTime = null)
    {
        $userKey = $this->setUserKey($payload['sub']);
        return $this->createToken($payload, $userKey, $expTime);
    }


    /**
     * Create Token
     */

    private function createToken($payload, $key, $expTime = null)
    {
        if (empty($key) || empty($payload) || !is_array($payload)) {
            return null;
        }

        $payload["iat"] = time();
        $payload["exp"] = time() + ($expTime ?? $this->defaultExpTime);

        $segments = array();
        $segments[] = $this->urlsafeB64Encode(json_encode(array('typ' => 'JWT', 'alg' => 'HS256')));
        $segments[] = $this->urlsafeB64Encode(json_encode($payload));

        $msg = implode('.', $segments);

        $signature = hash_hmac('SHA256', $msg, $key, true);

        $segments[] = $this->urlsafeB64Encode($signature);

        return implode('.', $segments);
    }


    /**
     * Create Nonce (for backup dowloads)
     */

    public function createNonce($payload)
    {
        $payload["iat"] = time();

        $msg = $this->urlsafeB64Encode(json_encode($payload));

        $signature = hash_hmac('SHA256', $msg, $this->JWTKey, true);

        return $msg . '.' . $this->urlsafeB64Encode($signature);
    }


    /**
     * Verify Access Token
     */

    public function verifyAccessToken($jwt)
    {
        return $this->verifyToken($jwt, $this->JWTKey);
    }


    /**
     * Verify Refresh Token
     * Revoke user key on error which invalidates ALL refresh tokens for this user
     */

    public function verifyRefreshToken($jwt)
    {
        $payload = $this->getPayload($jwt);
        if (empty($payload) || !isset($payload->sub)) {
            return false;
        }

        $userKey = $this->getUserKey($payload->sub);
        if (empty($userKey)) {
            return false;
        }

        if (!$this->verifyToken($jwt, $userKey)) {
            $this->revokeUserKey($payload->sub);
            return false;
        }

        return true;
    }


    /**
     * Verify Token
     */

    private function verifyToken($jwt, $key)
    {
        $segments = explode('.', $jwt);
        if (count($segments) !== 3) {
            return false;
        }

        list($head, $body, $crypto) = $segments;

        if (null === ($header = json_decode($this->urlsafeB64Decode($head)))) {
            return false;
        }

        if (null === ($payload = json_decode($this->urlsafeB64Decode($body)))) {
            return false;
        }

        if (false === ($signature = $this->urlsafeB64Decode($crypto))) {
            return false;
        }

        if (empty($header->alg)) {
            return false;
        }

        if ($header->alg !== 'HS256') {
            return false;
        }

        $hash = hash_hmac('SHA256', $head.'.'.$body, $key, true);
        if (!hash_equals($signature, $hash)) {
            return false;
        }

        if (!isset($payload->iat) || $payload->iat > (time() + $this->leeway)) {
            return false;
        }

        if (!isset($payload->exp) || $payload->exp < time()) {
            return false;
        }

        return true;
    }


    /**
     * Verify Nonce
     */

    public function verifyNonce($nonce)
    {
        $segments = explode('.', $nonce);
        if (count($segments) !== 2) {
            return false;
        }

        list($msg, $crypto) = $segments;

        if (null === ($payload = json_decode($this->urlsafeB64Decode($msg)))) {
            return false;
        }

        if (false === ($signature = $this->urlsafeB64Decode($crypto))) {
            return false;
        }

        $hash = hash_hmac('SHA256', $msg, $this->JWTKey, true);
        if (!hash_equals($signature, $hash)) {
            return false;
        }

        if (!isset($payload->iat) || ($payload->iat + 300) < time()) {
            return false;
        }

        return $payload;
    }


    /**
     * Helper Functions
     *
     * - getPayload
     * - urlsafeB64Decode
     * - urlsafeB64Encode
     */

    public function getPayload($jwt)
    {
        $segments = explode('.', $jwt);
        if (count($segments) !== 3) {
            return null;
        }

        if (null === ($payload = json_decode($this->urlsafeB64Decode($segments[1])))) {
            return null;
        }

        return $payload;
    }

    private function urlsafeB64Decode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    private function urlsafeB64Encode($input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }


    /**
     * Key Handling Functions
     */

    private function getKey()
    {
        $keyFile = dirname(__FILE__) . File::DS . '.jwtkey';

        if (is_file($keyFile)) {
            $keyHex = file_get_contents($keyFile);
            if (!empty($keyHex) && false !== ($key = hex2bin($keyHex))) {
                return $key;
            }
        }

        $key = random_bytes(64);

        file_put_contents($keyFile, bin2hex($key));

        return $key;
    }

    private function getUserKey($shortName)
    {
        $userDir = $this->violet->userDir;
        $keyHex  = $this->fileHandler->getRawContentsOrNull($userDir, $shortName.'.key');

        if (!empty($keyHex) && false !== ($userKey = hex2bin($keyHex))) {
            return $userKey;
        }

        return null;
    }

    private function setUserKey($shortName)
    {
        $userDir = $this->violet->userDir;
        $userKey = random_bytes(64);

        // no fail-safe measures here - $shortName is guaranteed to exist

        $this->fileHandler->putRawContents($userDir, $shortName.'.key', bin2hex($userKey));

        return $userKey;
    }

    public function revokeUserKey($shortName)
    {
        // $shortName is not guaranteed to be valid or even exist

        $fileName = $this->fileHandler->sanitizeFileName($shortName);
        if (empty($fileName)) {
            return;
        }

        $userDir = $this->violet->userDir;
        $keyFile = $userDir . File::DS . $fileName . '.key';

        if (is_file($keyFile)) {
            unlink($keyFile);
        }
    }
}
