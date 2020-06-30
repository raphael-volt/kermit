<?php
use PHPUnit\Framework\TestCase;
use src\services\DatabaseService;
use src\vo\Thread;
use src\vo\ThreadPart;
use src\vo\User;

/**
 * DatabaseService test case.
 */
class DatabaseServiceTest extends TestCase
{

    /**
     *
     * @var DatabaseService
     */
    private $databaseService;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseService = DatabaseService::instance();
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown(): void
    {
        // TODO Auto-generated DatabaseServiceTest::tearDown()
        $this->databaseService = null;

        parent::tearDown();
    }

    /**
     *
     * @test DatabaseService::shouldGetTableByRoute()
     */
    public function shouldGetTableByRoute()
    {
        $this->assertEquals("jp_thread", DatabaseService::getTableByRoute("thread"));
        $this->assertEquals("jp_thread_part", DatabaseService::getTableByRoute("thread_part"));
        $this->assertEquals("jp_user", DatabaseService::getTableByRoute("user"));
    }

    /**
     *
     * @test DatabaseServiceTest::shouldNotGetTableByRoute
     * @depends shouldGetTableByRoute
     */
    function shouldNotGetTableByRoute()
    {
        $this->assertNull(DatabaseService::getTableByRoute("foo"));
        $this->assertNull(DatabaseService::getTableByRoute(""));
        $this->assertNull(DatabaseService::getTableByRoute(null));
        $this->assertNull(DatabaseService::getTableByRoute(0));
    }

    /**
     *
     * @test DatabaseService::getClassByRoute()
     *
     * @depends shouldNotGetTableByRoute
     */
    public function shouldGetClassByRoute()
    {
        $this->assertEquals(Thread::class, DatabaseService::getClassByRoute("thread"));
        $this->assertEquals(ThreadPart::class, DatabaseService::getClassByRoute("thread_part"));
        $this->assertEquals(User::class, DatabaseService::getClassByRoute("user"));
    }

    /**
     *
     * @test DatabaseServiceTest::shouldNotGetClassByRoute
     * @depends shouldGetClassByRoute
     */
    function shouldNotGetClassByRoute()
    {
        $this->assertNull(DatabaseService::getClassByRoute("foo"));
        $this->assertNull(DatabaseService::getClassByRoute(""));
        $this->assertNull(DatabaseService::getClassByRoute(null));
        $this->assertNull(DatabaseService::getClassByRoute(0));
    }

    /**
     *
     * @test DatabaseService::instance()
     *
     * @depends shouldNotGetClassByRoute
     */
    public function shouldGetInstance()
    {
        $this->assertNotNull($this->databaseService);
    }

    /**
     *
     * @test DatabaseServiceTest::shouldGetUsersList
     * @depends shouldGetInstance
     */
    function shouldGetUsersList()
    {
        $db = $this->databaseService;
        $users = $db->getUserList();
        $this->assertIsArray($users);
        $maxId = 0;
        foreach ($users as $user) {
            $this->assertInstanceOf(User::class, $user);
            if ($user->id > $maxId)
                $maxId = $user->id;
        }
        return $maxId;
    }

    /**
     *
     * @test DatabaseServiceTest::shouldCreateUsers
     * @depends shouldGetUsersList
     */
    function shouldCreateUsers(int $maxId)
    {
        $db = $this->databaseService;
        $ids = [];
        for ($i = 0; $i < 3; $i ++) {
            $user = new User();
            $user->name = "test $i";
            $user->email = "user{$i}@test.com";
            $this->assertTrue($db->addUser($user));
            $this->assertNotNull($user->id);
            $this->assertGreaterThan($maxId, $user->id);
            $ids[] = $user->id;
        }
        return $ids;
    }

    /**
     *
     * @test DatabaseServiceTest::shouldCreateThread
     * @depends shouldCreateUsers
     */
    function shouldCreateThread($ids)
    {
        $db = $this->databaseService;
        foreach ($ids as $id) {
            $user = $db->getUserById($id);
            $thread = new Thread();
            $thread->user_id = $id;
            $thread->subject = "Hello from $user->name";
            $this->assertTrue($db->addThread($thread));
            $threadPart = new ThreadPart();
            $threadPart->content = "How are You?";
            $threadPart->thread_id = $thread->id;
            $threadPart->user_id = $user->id;
            $db->addThreadPart($threadPart);
        }
        return $ids;
    }

    /**
     *
     * @test DatabaseServiceTest::shouldCreateTreadParts
     * @depends shouldCreateThread
     */
    function shouldCreateTreadParts($ids)
    {
        $db = $this->databaseService;
        foreach ($ids as $id) {
            $user = $db->getUserById($id);
            $stmt = $db->exec("SELECT * FROM jp_thread WHERE user_id=?", $id);
            $thread = $stmt->fetchObject(Thread::class);
            $thread instanceof Thread;
            foreach ($ids as $i) {
                if ($i == $id)
                    continue;
                $u = $db->getUserById($i);
                $tp = new ThreadPart();
                $tp->content = "$u->name feels good! And you $user->name";
                $tp->thread_id = $thread->id;
                $tp->user_id = $i;
                $this->assertTrue($db->addThreadPart($tp));
            }
        }
        return $ids;
    }

    /**
     *
     * @test DatabaseServiceTest::shouldDeleteThread
     * @depends shouldCreateTreadParts
     */
    function shouldDeleteThread($ids)
    {
        $id = $ids[0];
        $db = $this->databaseService;
        $stmt = $db->exec("SELECT * FROM jp_thread WHERE user_id=?", $id);
        $thread = $stmt->fetchObject(Thread::class);
        $thread instanceof Thread;
        $this->assertTrue($db->deleteThread($thread->id));
        $pdo = $db->getPDO();
        $sTP = $pdo->prepare("select count(*) from jp_thread_part WHERE thread_id=?");
        $sTP->execute([
            $thread->id
        ]);
        $this->assertEquals(0, (int) $sTP->fetchColumn(0));
        return $ids;
    }

    /**
     *
     * @test DatabaseServiceTest::shouldUpdateUser
     * @depends shouldDeleteThread
     */
    function shouldUpdateUser(array $ids)
    {
        $db = $this->databaseService;
        $user = $db->getUserById($ids[0]);
        $newEmail = "new@test.com";
        $newName = "newName";
        $user->name = $newName;
        $user->email = $newEmail;
        $this->assertTrue($db->updateUser($user));
        $byEmail = $db->getUserByEmail($newEmail);
        $this->assertEquals($user->id, $byEmail->id);
        return $ids;
    }

    /**
     *
     * @test DatabaseServiceTest::shouldDeleteUsers
     * @depends shouldUpdateUser
     */
    function shouldDeleteUsers(array $ids)
    {
        $db = $this->databaseService;
        $currentUsers = $db->getUserList();
        foreach ($ids as $id) {
            $this->assertTrue($db->deleteUser($id));
        }
        $newUsers = $db->getUserList();
        $this->assertEquals(count($newUsers), count($currentUsers) - count($ids));
        return $ids;
    }

    /**
     *
     * @test DatabaseServiceTest::shouldHaveDeletedThreads
     * @depends shouldDeleteUsers
     */
    function shouldHaveDeletedThreads($ids)
    {
        $pdo = $this->databaseService->getPDO();
        $sT = $pdo->prepare("select count(*) from jp_thread WHERE user_id=?");
        $sTP = $pdo->prepare("select count(*) from jp_thread_part WHERE user_id=?");
        foreach ($ids as $id) {
            $sT->execute([
                $id
            ]);
            $sTP->execute([
                $id
            ]);
            $this->assertEquals(0, (int) $sT->fetchColumn(0));
            $this->assertEquals(0, (int) $sTP->fetchColumn(0));
        }
    }

    /**
     *
     * @test DatabaseServiceTest::shouldGetTableByClass
     * @depends shouldHaveDeletedThreads
     */
    function shouldGetTableByClass()
    {
        $this->assertEquals("jp_user", DatabaseService::getTableNameByClass(User::class));
        $this->assertEquals("jp_thread", DatabaseService::getTableNameByClass(Thread::class));
        $this->assertEquals("jp_thread_part", DatabaseService::getTableNameByClass(ThreadPart::class));
        $this->assertNull(DatabaseService::getTableNameByClass("foo"));
        $this->assertNull(DatabaseService::getTableNameByClass(0));
        $this->expectException("TypeError");
        $this->assertNull(DatabaseService::getTableNameByClass(null));
    }

    /**
     *
     * @test DatabaseServiceTest::shouldGetClasByTable
     * @depends shouldGetTableByClass
     */
    function shouldGetClasByTable()
    {
        $this->assertEquals(User::class, DatabaseService::getClassByTableName("jp_user"));
        $this->assertEquals(Thread::class, DatabaseService::getClassByTableName("jp_thread"));
        $this->assertEquals(ThreadPart::class, DatabaseService::getClassByTableName("jp_thread_part"));
        $this->assertNull(DatabaseService::getClassByTableName("foo"));
        $this->assertNull(DatabaseService::getClassByTableName(0));
        $this->expectException("TypeError");
        $this->assertNull(DatabaseService::getClassByTableName(null));
    }
}

