<?php
namespace src\services;

use src\core\pdo\ApiPDO;
use src\vo\ThreadPart;
use src\vo\JPFile;
use src\vo\User;

class UserService
{

    /**
     *
     * @var UserService
     */
    private static $_instance;

    /**
     *
     * @return \src\services\UserService
     */
    static function instance()
    {
        if (! self::$_instance) {
            self::$_instance = new UserService();
        }
        return self::$_instance;
    }

    /**
     *
     * @var FileService
     */
    private $fs;

    /**
     *
     * @var DatabaseService
     */
    private $db;

    /**
     *
     * @var ApiPDO
     */
    private $pdo;

    private function __construct()
    {
        $this->fs = FileService::instance();
        $this->db = DatabaseService::instance();
        $this->pdo = $this->db->getPDO();
    }

    /**
     *
     * @param int $id
     */
    function deleteUserThreads($id)
    {
        $threadService = ThreadService::instance();
        $pdo = $this->pdo;
        $tb = DatabaseService::TN_THREAD;
        $stmt = $pdo->prepareExec("SELECT id FROM $tb WHERE user_id=?", $id);
        $stmt->setFetchMode(\PDO::FETCH_COLUMN, 0);
        $ids = $stmt->fetchAll();
        foreach ($ids as $i) {
            $threadService->delete($i);
        }
        return $ids;
    }

    /**
     *
     * @param int $id
     * @return int[]
     */
    function deleteUserParts($id)
    {
        $fs = $this->fs;
        $threadService = ThreadService::instance();
        $pdo = $this->pdo;
        $tb = DatabaseService::TN_THREAD_PART;
        $stmt = $pdo->prepareExec("SELECT id, content, read_by FROM $tb WHERE user_id=?", $id);
        $stmt->setFetchMode(\PDO::FETCH_OBJ);
        $parts = $stmt->fetchAll();
        $ids = [];
        $tb = DatabaseService::TN_FILE;
        $getImgStmt = $pdo->prepare("SELECT filename FROM $tb WHERE id=?");
        $delImgStmt = $pdo->prepare("DELETE FROM $tb WHERE id=?");
        $tb = DatabaseService::TN_THREAD_PART;
        $delPartStmt = $pdo->prepare("DELETE FROM $tb WHERE id=?");
        foreach ($parts as $p) {
            $tp = new ThreadPart($p);
            $ids[] = $tp->id;
            $fids = $threadService->getPartContentFile($tp->content);
            if (count($fids)) {
                foreach ($fids as $i) {
                    $getImgStmt->execute([
                        $i
                    ]);
                    $img = $getImgStmt->fetchObject();
                    $fn = $fs->assetsImages($img->filename);
                    if (file_exists($fn) && is_file($fn)) {
                        @unlink($fn);
                    }
                    $delImgStmt->execute([
                        $i
                    ]);
                }
            }
            $delPartStmt->execute([
                $tp->id
            ]);
        }
        return $ids;
    }

    function cleanReadBy($id)
    {
        $pdo = $this->pdo;
        $tb = DatabaseService::TN_THREAD_PART;
        $getStmt = $pdo->prepare("SELECT id, read_by FROM $tb WHERE 1");
        $getStmt->execute();
        $parts = $getStmt->fetchAll(\PDO::FETCH_OBJ);
        $setStmt = $pdo->prepare("UPDATE $tb SET read_by=? WHERE id=?");
        foreach ($parts as $p) {
            $tp = new ThreadPart($p);
            $rb = $tp->read_by;
            $i = array_search($id, $rb);
            if ($i !== false) {
                array_splice($rb, $i, 1);
                $setStmt->execute([
                    json_encode($rb, JSON_NUMERIC_CHECK),
                    $tp->id
                ]);
            }
        }
    }

    function validateLastParts($partIds)
    {
        $pdo = $this->pdo;
        // get threads where the last part have been removed
        $tb = DatabaseService::TN_THREAD;
        // get a thread where the last_part was previously deleted
        $getThread = $pdo->prepare("SELECT id FROM $tb WHERE last_part=?");
        // update last_part
        $setThread = $pdo->prepare("UPDATE $tb SET last_part=? WHERE id=?");
        // get last thread_part
        $getLastPart = $pdo->prepare("SELECT tp.id as part_id FROM $tb as t
LEFT JOIN jp_thread_part as tp ON tp.thread_id = t.id
WHERE t.id = ?
ORDER BY tp.id DESC LIMIT 1");

        foreach ($partIds as $pi) {
            $getThread->execute([
                $pi
            ]);
            if ($getThread->rowCount()) {
                $i = $getThread->fetchColumn(0);
                $getLastPart->execute([
                    $i
                ]);
                if ($getLastPart->rowCount()) {
                    $last_part = $getLastPart->fetchColumn(0);
                    $setThread->execute([
                        $last_part,
                        $pi
                    ]);
                }
            }
        }
    }

    function deleteUser($id)
    {
        $pdo = $this->pdo;
        $fs = $this->fs;
        $tb = DatabaseService::TN_USER;
        $stmt = $pdo->prepareExec("SELECT picto FROM $tb WHERE id=?", $id);
        if (! $stmt->rowCount())
            return;
        $picto = $stmt->fetchColumn(0);
        $pdo->prepareExec("DELETE FROM $tb WHERE id=?", $id);
        if ($picto !== NULL) {
            $tb = DatabaseService::TN_FILE;
            $stmt = $pdo->prepareExec("SELECT filename FROM $tb WHERE id=?", $picto);
            if ($stmt->rowCount()) {
                $fn = $fs->assetsImages($stmt->fetchColumn(0));
                if (file_exists($fn) && is_file($fn)) {
                    @unlink($fn);
                }
                $pdo->prepareExec("DELETE FROM $tb WHERE id=?", $picto);
            }
        }
    }
    
    function add(User $new) {
        $db = $this->db;
        $current = AuthService::instance()->getUser();
        $db->addUser($new);
        $mailer = MailService::instance();
        $mailer->notifyAccountCreated($current, $new);
        $watch = WatchService::instance();
        $watch->setReload(false);
        $watch->save();
    }

    function delete($id)
    {
        $pdo = $this->pdo;
        $inTransaction = $pdo->getInTransaction();
        if (! $inTransaction)
            $pdo->beginTransaction();
        try {
            $user = $this->db->getUserById($id);
            $this->deleteUserThreads($id);
            $pIds = $this->deleteUserParts($id);
            $this->cleanReadBy($id);
            $this->validateLastParts($pIds);
            $this->deleteUser($id);

            if (! $inTransaction)
                $pdo->commit();

            $mailer = MailService::instance();
            $mailer->notifyAccountRemoved($user);
            $watch = WatchService::instance();
            $watch->setReload();
            $watch->save();
        } catch (\PDOException $e) {
            if (! $inTransaction)
                $pdo->rollBack();
            throw $e;
        }
    }

    function _delete($id)
    {
        $fs = $this->fs;
        $pdo = $this->pdo;
        $threadService = ThreadService::instance();
        $inTransaction = $pdo->getInTransaction();
        if (! $inTransaction)
            $pdo->beginTransaction();
        try {

            $tb = DatabaseService::TN_THREAD;
            $stmt = $pdo->prepareExec("SELECT id FROM $tb WHERE user_id=?", $id);
            $threads = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
            foreach ($threads as $i) {
                $threadService->delete($i);
            }
            $stmtDeleteThread = $pdo->prepare("DELETE FROM $tb WHERE user_id=?");
            $tb = DatabaseService::TN_THREAD_PART;
            $stmt = $pdo->prepare("SELECT read_by, content, id FROM $tb WHERE user_id=?");
            $stmt->execute([
                $id
            ]);
            $parts = $stmt->fetchAll(\PDO::FETCH_CLASS, \stdClass::class);

            $rbstmt = $pdo->prepare("UPDATE $tb SET read_by=? WHERE id=?");
            $parts_ids = array();
            $tbf = DatabaseService::TN_FILE;
            $stmtGetImg = $pdo->prepare("SELECT filename FROM $tbf WHERE id=?");
            $stmtDeleteImg = $pdo->prepare("DELETE FROM $tbf WHERE id=?");
            // delete all images in parts
            foreach ($parts as $p) {
                $content = json_decode($p->content);
                $ids = $threadService->getPartContentFile($content);
                foreach ($ids as $i) {
                    $stmtGetImg->execute([
                        $i
                    ]);
                    $fn = $fs->assetsImages($stmtGetImg->fetchColumn(0));
                    if (is_file($fn))
                        @unlink($fn);
                    $stmtDeleteImg->execute([
                        $i
                    ]);
                }
                /*
                 * $parts_ids[] = $p->id;
                 * $read_by = json_decode($p->read_by);
                 * $i = array_search($id, $read_by);
                 * if($i !== FALSE) {
                 * array_splice($read_by, $i, 1);
                 * $read_by = json_encode($read_by, JSON_NUMERIC_CHECK);
                 * $rbstmt->execute([$read_by, $p->id]);
                 * }
                 */
            }
            // delete all threads and parts created by this user
            $stmtDeleteThread->execute([
                $id
            ]);
            // threads last_part

            // get the latest thread_part
            $tb = DatabaseService::TN_THREAD;
            $stmtLastPart = $pdo->prepare("SELECT tp.id as part_id FROM $tb as t
LEFT JOIN jp_thread_part as tp ON tp.thread_id = t.id
WHERE t.id = ?
ORDER BY tp.id DESC LIMIT 1");
            // get threads where last_part was a previously deleted thread_part
            $stmt = $pdo->prepare("SELECT id from $tb WHERE last_part=?");
            // update last_part
            $stmtSet = $pdo->prepare("UPDATE $tb SET last_part=? WHERE id=?");
            foreach ($parts_ids as $i) {
                // $i => thread_part->id
                $stmt->execute([
                    $i
                ]);
                if ($stmt->rowCount()) {
                    // $i => thread->id
                    $i = $stmt->fetchColumn(0);
                    $stmtLastPart->execute([
                        $i
                    ]);
                    if ($stmtLastPart->rowCount()) {
                        $last_part = $stmtLastPart->fetchColumn(0);
                        $stmtSet->execute([
                            $last_part,
                            $i
                        ]);
                    }
                }
            }
            $tb = DatabaseService::TN_USER;
            $stmt = $pdo->prepareExec("DELETE FROM $tb WHERE id=?", $id);
            if (! $inTransaction)
                $pdo->commit();
            $watch = WatchService::instance();
            $watch->setReload();
            $watch->save();
        } catch (\PDOException $e) {
            if (! $inTransaction)
                $pdo->rollBack();
            throw $e;
        }
    }
}

