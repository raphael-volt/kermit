<?php
namespace src\services;

use src\vo\Thread;
use src\vo\ThreadPart;
use src\vo\User;
use src\vo\VoBase;
use src\vo\ApiKey;
use src\vo\ThreadData;
use src\vo\ThreadDataItem;
use src\vo\JPFile;
use src\vo\Vo;
use src\core\pdo\ApiPDO;

class DatabaseService
{

    const TN_THREAD = "jp_thread";

    const TN_THREAD_PART = "jp_thread_part";

    const TN_USER = "jp_user";

    const TN_APIKEY = "jp_apikey";

    const TN_FILE = "jp_file";

    private const ROUTES_MAP = [
        "thread" => [
            self::TN_THREAD,
            Thread::class
        ],
        "thread_part" => [
            self::TN_THREAD_PART,
            ThreadPart::class
        ],
        "user" => [
            self::TN_USER,
            User::class
        ]
    ];

    private const CLASS_MAP = [
        Thread::class => self::TN_THREAD,
        ThreadPart::class => self::TN_THREAD_PART,
        User::class => self::TN_USER,
        ApiKey::class => self::TN_APIKEY
    ];

    static function getClassByTableName(string $tableName)
    {
        $class = array_search($tableName, self::CLASS_MAP);
        if ($class !== false) {
            return $class;
        }
        return null;
    }

    static function getTableNameByClass(string $class)
    {
        if (array_key_exists($class, self::CLASS_MAP))
            return self::CLASS_MAP[$class];
        return null;
    }

    /**
     *
     * @param string $path
     * @return string|null
     */
    static function getTableByRoute($path)
    {
        if (array_key_exists($path, self::ROUTES_MAP))
            return self::ROUTES_MAP[$path][0];
        return null;
    }

    /**
     *
     * @param string $path
     * @return string|null
     */
    static function getClassByRoute($path)
    {
        if (array_key_exists($path, self::ROUTES_MAP))
            return self::ROUTES_MAP[$path][1];
        return null;
    }

    /**
     *
     * @var DatabaseService
     */
    private static $_instance;

    /**
     *
     * @return \src\services\DatabaseService
     */
    static function instance()
    {
        if (! self::$_instance)
            self::$_instance = new DatabaseService();
        return self::$_instance;
    }

    /**
     *
     * @var ApiPDO
     */
    private $_pdo;

    private function __construct()
    {
        $this->_pdo = ApiPDO::instance();
    }

    function getPDO()
    {
        return $this->_pdo;
    }

    /**
     *
     * @param Thread $thread
     * @return boolean
     */
    function addThread(Thread $thread)
    {
        $tb = self::TN_THREAD;
        $stmt = $this->exec("INSERT INTO  $tb (subject, user_id, last_part) VALUES (?, ?, ?)", $thread->subject, $thread->user_id, $thread->last_part);
        $n = $stmt->rowCount();
        if ($n) {
            $thread->id = $this->_pdo->lastInsertId($tb);
        }
        return $n > 0;
    }

    /**
     * Require a version property to be watchable.
     *
     * @deprecated
     * @param ThreadPart $threadPart
     * @return boolean
     */
    function concatStringContent(ThreadPart $threadPart)
    {
        // UPDATE categories SET code = CONCAT(code, '_standard') WHERE id = 1;
        $tb = self::TN_THREAD_PART;
        return $this->exec("UPDATE $tb SET content = CONCAT(content, ?) WHERE id = ?", $threadPart->content, $threadPart->id) !== NULL;
    }

    /**
     *
     * @param int $thread_id
     * @return boolean
     */
    function deleteThread(int $thread_id)
    {
        $tb = self::TN_THREAD;
        $stmt = $this->exec("DELETE FROM $tb WHERE id=?", $thread_id);
        return $stmt->rowCount() > 0;
    }

    /**
     *
     * @param ThreadPart $threadPart
     * @return boolean
     */
    function addThreadPart(ThreadPart $threadPart)
    {
        $tb = self::TN_THREAD_PART;
        $stmt = $this->exec("INSERT INTO $tb (thread_id, user_id, content, read_by) VALUES (?, ?, ?, ?)", $threadPart->thread_id, $threadPart->user_id, json_encode($threadPart->content, JSON_NUMERIC_CHECK), json_encode($threadPart->read_by, JSON_NUMERIC_CHECK));
        $n = $stmt->rowCount();
        if ($n) {
            $threadPart->id = $this->_pdo->lastInsertId($tb);
        }
        return $n > 0;
    }

    /**
     *
     * @param string $email
     * @return User|NULL
     */
    function getUserByEmail($email)
    {
        $tb = self::TN_USER;
        return $this->prepareAndFetch("SELECT * FROM $tb WHERE email=?", User::class, $email);
    }

    /**
     *
     * @param int $id
     * @return User|NULL
     */
    function getUserPicto($id)
    {
        $tb = self::TN_USER;
        return $this->prepareAndFetch("SELECT picto FROM $tb WHERE id=?", User::class, $id);
    }

    function getUserById($id)
    {
        $tb = self::TN_USER;
        return $this->prepareAndFetch("SELECT * FROM $tb WHERE id=?", User::class, $id);
    }

    /**
     *
     * @return User[]
     */
    function getUserList()
    {
        $tb = self::TN_USER;
        return $this->queryAndFetchAll("SELECT * FROM $tb ORDER BY name", User::class);
    }

    /**
     *
     * @param User $user
     * @return boolean
     */
    function addUser(User $user)
    {
        $tb = self::TN_USER;
        $stmt = $this->exec("INSERT INTO $tb (name,email,allow_sounds,notify_by_email,picto) VALUES (?,?,?,?,?)", $user->name, $user->email, $user->allow_sounds, $user->notify_by_email, $user->picto);
        $n = $stmt->rowCount();
        if ($n) {
            $user->id = (int) $this->_pdo->lastInsertId($tb);
        }
        return $n > 0;
    }

    /**
     *
     * @param User $user
     * @return boolean
     */
    function updateUser(User $user)
    {
        $stmt = $this->updateVO(self::TN_USER, $user);
        return $stmt->rowCount() > 0;
    }

    private function selectOne(string $tbname, string $key, int $id)
    {
        $stmt = $this->prepare("SELECT $key FROM $tbname WHERE id = ?");
        $stmt->execute([
            $id
        ]);
        if ($stmt->rowCount())
            return $stmt->fetchColumn(0);
        return null;
    }

    /**
     *
     * @param int $id
     * @return boolean
     */
    function deleteUser($id)
    {
        $tb = self::TN_USER;
        $stmt = $this->exec("DELETE FROM $tb WHERE id=?", $id);
        return $stmt->rowCount() > 0;
    }

    // /////////////////////////////////////////////////////////////////////////////////////////////
    /**
     *
     * @param int $id
     * @return \src\vo\JPFile|NULL
     */
    function getFileById($id)
    {
        $tb = self::TN_FILE;
        return $this->prepareAndFetch("SELECT * FROM $tb WHERE id=?", JPFile::class, $id);
    }

    /**
     *
     * @param JPFile $file
     * @return boolean
     */
    function addFile(JPFile $file)
    {
        $tb = self::TN_FILE;
        $stmt = $this->exec("INSERT INTO $tb (filename, filetype) VALUES (?, ?)", $file->filename, $file->filetype);
        $n = $stmt->rowCount();
        if ($n) {
            $file->id = (int) $this->_pdo->lastInsertId($tb);
        }
        return $n > 0;
    }

    /**
     *
     * @param JPFile $file
     * @return boolean
     */
    function updateFile(JPFile $file)
    {
        $stmt = $this->updateVO(self::TN_FILE, $file);
        return $stmt->rowCount() > 0;
    }

    /**
     *
     * @param int $id
     * @return boolean
     */
    function deleteFile($id)
    {
        $tb = self::TN_FILE;
        $stmt = $this->exec("DELETE FROM $tb WHERE id=?", $id);
        return $stmt->rowCount() > 0;
    }

    // /////////////////////////////////////////////////////////////////////////////////////////////

    /**
     *
     * @return Thread|NULL
     */
    function getThreadById($id)
    {
        $id = (int) $id;
        $tb = self::TN_THREAD;
        $thread = $this->prepareAndFetch("SELECT * FROM $tb WHERE id=?", Thread::class, $id);
        if (! $thread)
            return $thread;
        $tn = self::TN_THREAD_PART;
        $q = "SELECT read_by, id FROM $tn WHERE thread_id = ?";
        $rb = $this->prepareAndFetchAll($q, $id);
        $thread instanceof Thread;
        $thread->read_by = new \stdClass();
        foreach ($rb as $obj) {
            $thread->read_by->{$obj->id} = json_decode($obj->read_by);
        }
        return $thread;
    }

    /**
     *
     * @return Thread[]
     */
    function getThreadList()
    {
        $tb = self::TN_THREAD;
        $tbp = self::TN_THREAD_PART;
        $q = "SELECT t.*, tp.read_by, tp.id as tp_id FROM $tb as t left join $tbp as tp ON tp.thread_id = t.id ORDER BY t.id DESC";
        $rows = $this->queryAndFetchAll($q, \stdClass::class);
        $result = [];
        /**
         *
         * @var Thread $t
         */
        $t = null;
        foreach ($rows as $row) {
            $row->read_by = json_decode($row->read_by);
            if ($t == null || $t->id != $row->id) {
                $t = new Thread($row);
                $t->read_by = new \stdClass();
                $result[] = $t;
            }
            $t->read_by->{$row->tp_id} = $row->read_by;
        }
        // return $this->queryAndFetchAll("SELECT * FROM $tb ORDER BY last_part DESC", Thread::class);
        return $result;
    }

    /**
     *
     * @return Thread[]
     */
    function getThreadsNext($id)
    {
        $tb = self::TN_THREAD;
        $tbp = self::TN_THREAD_PART;
        $q = "SELECT t.*, tp.read_by, tp.id as tp_id FROM $tb as t left join $tbp as tp ON tp.thread_id = t.id 
WHERE t.id > $id
ORDER BY t.id";
        $rows = $this->queryAndFetchAll($q, \stdClass::class);

        $result = [];
        if (count($rows) == 0)
            return $result;
        /**
         *
         * @var Thread $t
         */
        $t = null;
        foreach ($rows as $row) {
            $row->read_by = json_decode($row->read_by);
            if ($t == null || $t->id != $row->id) {
                $t = new Thread($row);
                $t->read_by = new \stdClass();
                $result[] = $t;
            }
            $t->read_by->{$row->tp_id} = $row->read_by;
        }
        return $result;

        $tb = self::TN_THREAD;
        $query = "SELECT * FROM jp_thread WHERE id > ?
ORDER BY last_part DESC";
        $stmt = $this->exec($query, $id);
        if ($stmt && $stmt->rowCount()) {
            return $stmt->fetchAll(\PDO::FETCH_CLASS, Thread::class);
        }
        return [];
    }

    /**
     *
     * @param int $thread_id
     * @return ThreadPart[]
     */
    function getThreadPartList(int $thread_id)
    {
        $tb = self::TN_THREAD_PART;
        /**
         *
         * @var ThreadPart[] $parts
         */
        $parts = $this->prepareAndFetchAll("SELECT * FROM $tb WHERE thread_id=? ORDER BY id", $thread_id);
        foreach ($parts as $tp) {
            $tp->read_by = json_decode($tp->read_by);
            $tp->content = json_decode($tp->content);
        }
        return $parts;
    }

    /**
     *
     * @return \src\vo\Thread|NULL
     */
    function getLatestThread()
    {
        return $this->getLatest(self::TN_THREAD);
    }

    /**
     *
     * @return \src\vo\ThreadPart|NULL
     */
    function getLatestThreadPart()
    {
        $tp = $this->getLatest(self::TN_THREAD_PART);
        if ($tp) {
            $tp instanceof ThreadPart;
            $tp->read_by = json_decode($tp->read_by);
        }
        return $tp;
    }

    function setThreadReadBy($thread_id, $user_id)
    {
        $tb = self::TN_THREAD_PART;
        $threadParts = $this->prepareAndFetchAll("SELECT id, read_by FROM $tb WHERE thread_id=?", $thread_id);
        $updateStmt = $this->prepare("UPDATE $tb SET read_by=? WHERE id=?");
        foreach ($threadParts as $tp) {
            $tp = new ThreadPart($tp);
            if (! is_array($tp->read_by)) {
                $tp->read_by = [];
            }
            if (array_search($user_id, $tp->read_by) === false) {
                $tp->read_by[] = $user_id;
                $updateStmt->execute([
                    json_encode($tp->read_by, JSON_NUMERIC_CHECK),
                    $tp->id
                ]);
            }
        }
    }

    function getThreadData($thread_id)
    {
        $tnt = DatabaseService::TN_THREAD;
        $tntp = DatabaseService::TN_THREAD_PART;
        $q = "SELECT
    t.user_id as thread_user, t.subject, t.last_part,
    tp.id as part_id, tp.user_id, tp.content, tp.read_by FROM $tnt as t
LEFT JOIN $tntp as tp ON tp.thread_id = t.id
WHERE t.id = ?
ORDER BY tp.id";
        $rows = $this->prepareAndFetchAll($q, $thread_id);
        $data = new ThreadData();
        /**
         *
         * @var array ThreadDataItem[]
         */
        $contents = [];
        foreach ($rows as $row) {
            $ti = new ThreadDataItem();
            $ti->inserts = json_decode($row->content);
            $ti->read_by = json_decode($row->read_by);
            $ti->user_id = $row->user_id;
            $ti->id = $row->part_id;
            $contents[] = $ti;
        }
        /**
         *
         * @var ThreadDataItem $first
         */
        $first = array_shift($contents);
        $firstRow = array_shift($rows);

        $data->thread = new Thread();
        $data->thread->id = $thread_id;
        $data->thread->last_part = $firstRow->last_part;
        $data->thread->user_id = $first->user_id;
        $data->thread->subject = $firstRow->subject;
        $data->inserts = $first->inserts;
        $data->user_id = $first->user_id;
        $data->contents = $contents;
        return $data;
    }

    /**
     *
     * @return \src\vo\User|NULL
     */
    function getLatestUser()
    {
        return $this->getLatest(self::TN_USER);
    }

    /**
     *
     * @param string $tbname
     * @return number
     */
    function lastInsertId(string $tbname)
    {
        return (int) $this->query("SELECT coalesce(max(id), '0') as id FROM $tbname")->fetchColumn(0);
    }

    function getLatest($tableName)
    {
        $class = self::getClassByTableName($tableName);
        return $this->queryAndFetch("SELECT * FROM $tableName ORDER BY id DESC LIMIT 1;", $class);
    }

    function addApiKey(ApiKey $key)
    {
        $tb = self::TN_APIKEY;
        $stmt = $this->exec("INSERT INTO  $tb (value) VALUES (?)", $key->value);
        $n = $stmt->rowCount();
        if ($n) {
            $key->id = $this->_pdo->lastInsertId($tb);
        }
        return $n > 0;
    }

    function deleteApiKey($id)
    {
        $tb = self::TN_APIKEY;
        $stmt = $this->exec("DELETE FROM $tb WHERE id=?", $id);
        return $stmt->rowCount() > 0;
    }

    /**
     *
     * @param string $value
     * @return boolean
     */
    function getApiKeyExists(string $value)
    {
        $tb = self::TN_APIKEY;
        $stmt = $this->prepare("SELECT count(*) from $tb WHERE value=?");
        $stmt->execute([
            $value
        ]);
        if ($stmt->rowCount()) {
            $n = $stmt->fetchColumn(0);
            return intval($n) == 1;
        }
        return false;
    }

    /**
     *
     * @param string $query
     * @return \PDOStatement
     */
    function query($query)
    {
        return $this->_pdo->query($query);
    }

    /**
     *
     * @param string $query
     * @return \PDOStatement
     */
    function prepare($query)
    {
        return $this->_pdo->prepare($query);
    }

    /**
     *
     * @param string $query
     * @param string ...$params
     * @return \PDOStatement|null
     */
    function exec($query, ...$params)
    {
        $stmt = $this->_pdo->prepare($query);

        if ($stmt->execute($params))
            return $stmt;
        return null;
    }

    private function updateVO(string $tbname, Vo $vo)
    {
        return $this->_pdo->updateVO($tbname, $vo);
    }

    /**
     *
     * @param string $query
     * @param mixed ...$params
     * @return \stdClass[]
     */
    private function prepareAndFetchAll(string $query, ...$params)
    {
        $stmt = $this->exec($query, ...$params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     *
     * @param string $query
     * @param mixed ...$params
     * @return VoBase|null
     */
    private function prepareAndFetch(string $query, $class = null, ...$params)
    {
        if ($class == null)
            $class = \stdClass::class;
        $stmt = $this->exec($query, ...$params);
        if ($stmt->rowCount())
            return $stmt->fetchObject($class);
        return null;
    }

    /**
     *
     * @param string $query
     * @return VoBase[]
     */
    private function queryAndFetchAll(string $query, $class = null)
    {
        if ($class == null)
            $class = \stdClass::class;
        $stmt = $this->query($query);
        return $stmt->fetchAll(\PDO::FETCH_CLASS, $class);
    }

    /**
     *
     * @param string $query
     * @return VoBase|NULL
     */
    private function queryAndFetch(string $query, $class = null)
    {
        if ($class == null)
            $class = \stdClass::class;
        $stmt = $this->query($query);
        if ($stmt->rowCount())
            return $stmt->fetchObject($class);
        return null;
    }

    /**
     *
     * @param array|object $object
     * @return string
     */
    function serialize($object)
    {
        return json_encode($object, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
    }
}












