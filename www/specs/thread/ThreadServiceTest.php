<?php
namespace specs\thread;

use PHPUnit\Framework\TestCase;
use src\services\ThreadService;
use src\vo\Thread;
use src\vo\ThreadPart;
use src\services\DatabaseService;
use src\core\pdo\ApiPDO;
use src\services\FileService;

// chokidar www -i='www/specs/.phpunit.result.cache' --initial=true -c='docker exec chat-app phpunit -c specs/specs.xml --testsuite=thread-service'
class ThreadServiceTest extends TestCase
{

    /**
     *
     * @var ThreadService
     */
    static $service = null;

    /**
     * 
     * @var \stdClass
     */
    static $threadHook;
    static function setUpBeforeClass(): void
    {
        self::$service = ThreadService::instance();
    }

    /**
     *
     * @test ThreadServiceTest::shouldGetThreadServiceInstance
     */
    function shouldGetThreadServiceInstance()
    {
        $service = self::$service;
        $this->assertNotNull($service);
        return $service;
    }

    /**
     *
     * @test ThreadServiceTest::shouldGetThreadData
     * @depends shouldGetThreadServiceInstance
     */
    function shouldGetThreadData(ThreadService $service)
    {
        $fn = dirname(__DIR__) . DIRECTORY_SEPARATOR . "hook" . DIRECTORY_SEPARATOR . "thread-service-1.json";
        $this->assertFileExists($fn);
        $hook = json_decode(file_get_contents($fn));
        $hook = json_encode($hook, JSON_NUMERIC_CHECK);
        $hook = json_decode($hook);
        self::$threadHook = $hook;
        
        $thread = new Thread($hook->thread);
        $this->assertEquals(96, $thread->id);
        $parts = [];
        foreach ($hook->parts as $i) {
            $parts[] = new ThreadPart($i);
        }
        $this->assertCount(9, $parts);
        return $service;
        
            
    }

    /**
     *
     * @test ThreadServiceTest::shouldDeleteThreadIfExists
     * @depends shouldGetThreadData
     */
    function shouldDeleteThreadIfExists(ThreadService $service)
    {
        $thread = DatabaseService::instance()->getThreadById(96);
        if($thread) {
            $service->delete(96);
            $thread = DatabaseService::instance()->getThreadById(96);
        }
        $this->assertNull($thread);
        return $service;
    
        
    }
    /**
     *
     * @test ThreadServiceTest::shouldInsertThreadHook
     * @depends shouldDeleteThreadIfExists
     */
    function shouldInsertThreadHook(ThreadService $service)
    {
        $pdo = ApiPDO::instance();
        $pdo->beginTransaction();
        $hook = self::$threadHook;
        $fs = FileService::instance();
        $thread = new Thread($hook->thread);
        $this->assertEquals(96, $thread->id);
        $tb = DatabaseService::TN_FILE;
        $stmt = $pdo->prepare("INSERT INTO jp_file (id,filename,filetype) VALUES(?,?,?)");
        foreach ($hook->parts as $tp) {
            $content = json_decode($tp->content);
            foreach ($content as $op) {
                if($fs->isImageOp($op)) {
                    $this->assertTrue($stmt->execute([$op->insert->image,$op->image."hook.jpg",1]));
                }
            }
        }
        $tb = DatabaseService::TN_THREAD;//INSERT INTO tbl_name (col_A,col_B,col_C) VALUES (1,2,3)
        $stmt = $pdo->prepareExec("INSERT INTO $tb (id,subject,user_id,last_part) VALUES(?,?,?,?)", $thread->id, $thread->subject, $thread->user_id, $thread->last_part);
        $this->assertEquals(1, $stmt->rowCount());
        $tb = DatabaseService::TN_THREAD_PART;
        $stmt = $pdo->prepare("INSERT INTO $tb (id,thread_id,user_id,content,read_by) VALUES(?,?,?,?,?)");
        foreach ($hook->parts as $i) {
            $this->assertTrue($stmt->execute([$i->id, $i->thread_id, $i->user_id, $i->content, $i->read_by]));
        }
        $pdo->commit();
        return $service;       
    }

    /**
     *
     * @test ThreadServiceTest::shouldDeleteThreadHook
     * @depends shouldInsertThreadHook
     */
    function shouldDeleteThreadHook(ThreadService $service)
    {
        $thread = DatabaseService::instance()->getThreadById(96);
        $this->assertNotNull($thread);
        $service->delete(96);
        $thread = DatabaseService::instance()->getThreadById(96);
        $this->assertNull($thread);
    }
}

