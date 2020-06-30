<?php
namespace src\core\pdo;

use PDO, PDOStatement;
use src\vo\Vo;
use src\services\FileService;

class ApiPDO extends PDO
{

    const THREAD = "jp_thread";

    const THREAD_PART = "jp_thread_part";

    const USER = "jp_user";

    const APIKEY = "jp_apikey";

    const FILE = "jp_file";

    /**
     *
     * @var ApiPDO
     */
    private static $pdo;

    static function instance()
    {
        if (! self::$pdo)
            self::$pdo = new ApiPDO();
        return self::$pdo;
    }

    private $_isDevMode;

    function isDevMode()
    {
        return $this->_isDevMode;
    }

    private function __construct()
    {
        $isDev = $_SERVER["SERVER_NAME"] != "jp.api.ketmie.com";
        $user = "dbuser";
        $pwd = "dbuserpwd";
        $host = "db";
        $db = "thread";
        if (! $isDev) {
            $cfg = FileService::instance()->getConfig()->db;
            $user = $cfg->user;
            $pwd = $cfg->password;
            $host = $cfg->host;
            $db = $cfg->name;
        }
        $this->_isDevMode = $isDev;
        $dsn = "mysql:host=$host; dbname=$db";
        parent::__construct($dsn, $user, $pwd, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            PDO::ATTR_STRINGIFY_FETCHES => FALSE,
            PDO::ATTR_EMULATE_PREPARES => FALSE,
            PDO::ATTR_STRINGIFY_FETCHES => false
        ]);
    }

    /**
     *
     * @param string $query
     * @param mixed ...$params
     * @return PDOStatement
     */
    function prepareExec($query, ...$params)
    {
        $stmt = $this->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    function getKeyValue(string $tbname, int $id, string $key)
    {
        $stmt = $this->prepareExec("SELECT $key FROM $tbname WHERE id = ?", $id);
        if ($stmt->rowCount())
            return $stmt->fetchColumn(0);
        return null;
    }

    /**
     *
     * @param string $tbname
     * @param Vo $vo
     * @return PDOStatement
     */
    function updateVO(string $tbname, Vo $vo)
    {
        $keys = [];
        $params = [];
        foreach ($vo as $key => $value) {
            if ($value === null || $key == "id")
                continue;
            $keys[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $vo->id;
        $keys = implode(', ', $keys);
        return $this->prepareExec("UPDATE $tbname SET $keys WHERE id = ?", ...$params);
    }

    function getFilename($id)
    {
        return $this->getKeyValue(self::FILE, $id, "filename");
    }

    private $_inTransaction = false;

    function getInTransaction(): bool
    {
        return $this->_inTransaction;
    }

    function beginTransaction(): bool
    {
        $this->_inTransaction = true;
        return parent::beginTransaction();
    }

    function commit(): bool
    {
        $this->_inTransaction = false;
        return parent::commit();
    }
}

