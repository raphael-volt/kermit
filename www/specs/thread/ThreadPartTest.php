<?php
use PHPUnit\Framework\TestCase;
use src\services\DatabaseService;
use src\vo\Thread;

/**
 * chokidar www -i='www/specs/.phpunit.result.cache' --initial=true -c='docker exec chat-app phpunit -c specs/specs.xml --testsuite=thread-part'
 *
 * @author raphael
 *        
 */
class ThreadPartTest extends TestCase
{

    /**
     *
     * @test ThreadPartTest::shouldValidateReadBy
     */
    function shouldValidateReadBy()
    {
        $db = DatabaseService::instance();
        $tb = DatabaseService::TN_THREAD_PART;
        $stmt = $db->prepare("UPDATE $tb SET read_by=? WHERE id=?");
        $this->assertNotNull($db);
        $threads = $db->getThreadList();
        foreach ($threads as $t) {
            $users = [
                $t->user_id
            ];
            $parts = $db->getThreadPartList($t->id);
            foreach ($parts as $tp) {
                if (array_search($tp->user_id, $users) === false) {
                    $users[] = $tp->user_id;
                }
            }
            foreach ($parts as $tp) {
                $readby = $tp->read_by;
                $save = false;
                foreach ($users as $id) {
                    if (array_search($id, $readby) === false) {
                        $save = true;
                    }
                }
                if ($save) {
                    $stmt->execute([
                        json_encode($users, JSON_NUMERIC_CHECK),
                        $tp->id
                    ]);
                }
            }
        }
    }

    /**
     *
     * @test ThreadPartTest::shouldSetReadBy
     * @depends shouldValidateReadBy
     */
    function shouldSetReadBy()
    {
        $db = DatabaseService::instance();
        $tb = DatabaseService::TN_THREAD_PART;
        $stmt = $db->prepare("UPDATE $tb SET read_by=? WHERE id=?");
        $thread = $db->getLatestThread();
        $this->assertInstanceOf(Thread::class, $thread);
        $user_id = 100000;
        $db->setThreadReadBy($thread->id, $user_id);
        $updated = $db->getThreadPartList($thread->id);
        foreach ($updated as $tp) {
            $i = array_search($user_id, $tp->read_by);
            $this->assertNotFalse($i);
            array_splice($tp->read_by, $i, 1);
            $stmt->execute([
                json_encode($tp->read_by, JSON_NUMERIC_CHECK),
                $tp->id
            ]);
        }
        $updated = $db->getThreadPartList($thread->id);
        foreach ($updated as $tp) {
            $i = array_search($user_id, $tp->read_by);
            $this->assertFalse($i);
        }
    }
}

