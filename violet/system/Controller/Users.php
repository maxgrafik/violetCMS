<?php

/**
 * violetCMS - User Accounts Controller
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license    MIT License
 *
 * - Get list of user accounts
 * - Get user account details
 * - Get/Set user prefs
 */

namespace VioletCMS\Controller;

use VioletCMS\Ajax\APIError;
use VioletCMS\Handler\File;
use VioletCMS\Utils;

class Users extends Controller
{
    public function get($args = null)
    {
        if (isset($args['name'])) {

            $user = $this->getUserByName($args['name']);

            if (empty($user)) {
                new APIError(400, 'User not found');
            }

            unset($user['hash']); // don't transmit password hash!
            return $user;
        }

        $users = $this->getAllUsers();

        if (empty($users)) {
            new APIError(400, 'No users found');
        }

        $userList = array();
        foreach ($users as $shortName => $user) {
            $userList[] = array(
                'shortname' => $shortName,
                'name'      => $user['name'],
                'title'     => $user['title'],
                'enabled'   => $user['enabled']
            );
        }

        return $userList;
    }

    public function set($json, $args = null)
    {
        if (empty($args) || !isset($args['action']) || !isset($args['name'])) {
            new APIError(400, 'Invalid arguments');
        }

        if (!empty($json) && null === ($data = json_decode($json, true))) {
            new APIError(400, 'Invalid JSON');
        }

        switch ($args['action']) {
        case 'create':
            $this->createUser($args['name'], $data);
            break;
        case 'update':
            $this->updateUser($args['name'], $data);
            break;
        case 'delete':
            $this->deleteUser($args['name']);
            break;
        default:
            new APIError(400, 'Action not supported');
        }

        return null;
    }

    public function getPrefs()
    {
        if ($this->user === null) {
            new APIError(403);
        }

        $user = $this->getUserByName($this->user->sub);

        if (empty($user)) {
            new APIError(400, 'User not found');
        }

        return array(
            'name'     => $user['name'],
            'title'    => $user['title'],
            'email'    => $user['email'],
            'language' => $user['language']
        );
    }

    public function setPrefs($data)
    {
        $mustReAuthenticate = false;

        if ($this->user === null) {
            new APIError(403);
        }

        if (null === ($prefs = json_decode($data, true))) {
            new APIError(400, 'Invalid JSON');
        }

        $user = $this->getUserByName($this->user->sub);

        if (empty($user)) {
            new APIError(400, 'User not found');
        }

        $user['name']     = $prefs['name']     ?? $user['name'];
        $user['title']    = $prefs['title']    ?? $user['title'];
        $user['language'] = $prefs['language'] ?? $user['language'];

        if (isset($prefs['email']) && $prefs['email'] !== $user['email']) {
            $user['email'] = $prefs['email'];
            $mustReAuthenticate = true;
        }

        if (isset($prefs['password'])) {
            $user['hash'] = password_hash($prefs['password'], PASSWORD_DEFAULT);
            $mustReAuthenticate = true;
        }

        $fileHandler = new File();

        $fileHandler->putContents($this->violet->userDir, $this->user->sub.'.json', $user);

        if ($mustReAuthenticate) {
            /**
             * Because we have successfully updated the prefs, we MUST return 200 OK.
             * But since the user now has new credentials, the JWT should be invalid.
             * Unfortunately we cannot revoke JWTs - which are valid until expired.
             * We can only revoke the fingerprint cookie, causing an unexpected login
             * prompt for the next API call (= bad user experience).
             * So, we're kindly asking the client to re-authenticate and hope, this
             * won't be ignored.
             */

            $options = array(
                'expires'  => 1,
                'path'     => $this->violet->rootURL,
                'domain'   => Utils::getHost(),
                'secure'   => (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off'),
                'httponly' => true,
                'samesite' => 'Strict'
            );
            setcookie('violetFP', '', $options);

            return array('success' => 'must re-authenticate');
        }

        return null;
    }

    public function getUserByName($shortName)
    {
        $fileHandler = new File();

        return $fileHandler->getContentsOrNull($this->violet->userDir, $shortName.'.json');
    }

    public function getAllUsers()
    {
        $dir = $this->violet->userDir;

        if (!is_dir($dir)) {
            return null;
        }

        $fileHandler = new File();

        $users = array();

        $files = glob($dir . File::DS . '*.json');

        foreach ($files as $file) {
            $shortName = pathinfo($file, PATHINFO_FILENAME);
            $user = $fileHandler->getContentsOrNull($dir, $shortName.'.json');
            if (!empty($user)) {
                $users[$shortName] = $user;
            }
        }

        return $users;
    }

    private function createUser($shortName, $data)
    {
        if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
            new APIError(400, 'Invalid data');
        }

        $user = array(
            'name'     => $data['name'],
            'title'    => '',
            'email'    => $data['email'],
            'language' => 'en',
            'enabled'  => false,
            'hash'     => password_hash($data['password'], PASSWORD_DEFAULT),
            'access'   => array()
        );

        $shortName = strtolower(preg_replace('/[^A-Za-z0-9-_]/', '', ($shortName ?: $data['name']))) ?: 'user';

        $fileHandler = new File();

        $fileName = $fileHandler->getUniqueFileName($this->violet->userDir, $shortName.'.json');

        $fileHandler->putContents($this->violet->userDir, $fileName, $user);
    }

    private function updateUser($shortName, $data)
    {
        $fileHandler = new File();

        $user = $fileHandler->getContents($this->violet->userDir, $shortName.'.json');

        $user['name']     = $data['name']     ?? $user['name'];
        $user['title']    = $data['title']    ?? $user['title'];
        $user['email']    = $data['email']    ?? $user['email'];
        $user['language'] = $data['language'] ?? $user['language'];
        $user['enabled']  = $data['enabled']  ?? $user['enabled'];

        if (isset($data['password'])) {
            $user['hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $user['access']   = $data['access'] ?? $user['access'];

        $fileHandler->putContents($this->violet->userDir, $shortName.'.json', $user);
    }

    private function deleteUser($shortName)
    {
        $fileHandler = new File();

        $fileHandler->deleteFile($this->violet->userDir, $shortName.'.json');
    }

}
