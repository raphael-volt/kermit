<?php
namespace src\services;

use src\vo\JPFile;
use src\core\pdo\ApiPDO;

class ThreadService
{

    /**
     *
     * @var ThreadService
     */
    private static $_instance;

    /**
     *
     * @return \src\services\ThreadService
     */
    static function instance()
    {
        if (! self::$_instance) {
            self::$_instance = new ThreadService();
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

    function getPartContentFile($content)
    {
        $files = [];
        $fs = $this->fs;
        foreach ($content as $op) {
            if ($fs->isImageOp($op)) {
                $files[] = $op->insert->image;
            }
        }
        return $files;
    }

    /**
     *
     * @param int $thread_id
     * @return JPFile[]
     */
    function getThreadFiles($thread_id)
    {
        $files = [];
        $db = $this->db;
        $parts = $db->getThreadPartList($thread_id);
        $fs = $this->fs;
        foreach ($parts as $tp) {
            foreach ($tp->content as $op) {
                if ($fs->isImageOp($op)) {
                    $files[] = $db->getFileById($op->insert->image);
                }
            }
        }
        return $files;
    }

    function delete($id)
    {
        $fs = $this->fs;
        $pdo = $this->pdo;
        $wasInTransation = $pdo->getInTransaction();
        if (! $wasInTransation) {
            $pdo->beginTransaction();
        }
        $images = $this->getThreadFiles($id);
        $tb = DatabaseService::TN_FILE;
        $stmt = $this->pdo->prepare("DELETE FROM $tb WHERE id=?");
        try {
            foreach ($images as $f) {
                $fn = $fs->assetsImages($f->filename);
                if (is_file($fn)) {
                    try {
                        @unlink($fn);
                    } catch (\Exception $e) {}
                }
                $stmt->execute([
                    $f->id
                ]);
            }
            $tb = DatabaseService::TN_THREAD;
            $pdo->prepareExec("DELETE FROM $tb WHERE id=?", $id);
            $pdo->commit();
        } catch (\PDOException $e) {
            if (! $wasInTransation) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

