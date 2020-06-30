<?php
namespace src\services;

use src\vo\User;
use src\vo\ApiKey;

class AuthService
{

    const KEY_LENGTH = 8;

    /**
     *
     * @var AuthService
     */
    private static $_instance;

    /**
     *
     * @return \src\services\AuthService
     */
    static function instance()
    {
        if (! self::$_instance)
            self::$_instance = new AuthService();
        return self::$_instance;
    }

    /**
     *
     * @param int $length
     * @return \src\vo\ApiKey
     */
    private static function generateKey(int $length)
    {
        $key = new ApiKey();
        $key->value = bin2hex(random_bytes($length));
        return $key;
    }

    private function __construct()
    {}

    /**
     *
     * @var User
     */
    private $_user;

    /**
     *
     * @return \src\vo\User
     */
    function getUser()
    {
        return $this->_user;
    }

    /**
     *
     * @param string $input
     * @return boolean
     */
    function validate(string $input)
    {
        $db = DatabaseService::instance();
        $this->_user = null;
        $input = base64_decode($input);
        $input = explode(":", $input);
        if (count($input) == 2 && $db->getApiKeyExists($input[1])) {
            $this->_user = $db->getUserByEmail($input[0]);
        }
        return $this->_user != null;
    }
}

