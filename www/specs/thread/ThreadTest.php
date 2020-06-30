<?php
namespace specs\thread;

use PHPUnit\Framework\TestCase;
use hook\HookService;

class ThreadTest extends TestCase
{

    /**
     *
     * @var HookService
     */
    private static $hook;

    static function setUpBeforeClass(): void
    {
        self::$hook = new HookService();
    }

    static function tearDownAfterClass(): void
    {
        self::$hook->deleteAllUsers();
    }

    /**
     *
     * @test ThreadTest::shouldHaveHookService
     */
    function shouldHaveHookService()
    {
        $this->assertInstanceOf(HookService::class, self::$hook);
    }

    /**
     *
     * @test ThreadTest::shouldCreateUsers
     * @depends shouldHaveHookService
     */
    function shouldCreateUsers()
    {
        $hook = self::$hook;
        for ($i = 1; $i < 5; $i ++) {
            $hook->createUser($i);
        }
        $this->assertCount(4, $hook->users);
        $first = $hook->users[0];
        $this->assertNotNull($first->id);
        $id = $first->id;
        for ($i = 1; $i < 4; $i ++) {
            $next = $hook->users[$i];
            $this->assertEquals($id + $i, $next->id);
        }
    }

    /**
     *
     * @test ThreadTest::shouldCreateThreads
     * @depends shouldCreateUsers
     */
    function shouldCreateThreads()
    {
        $hook = self::$hook;
        $users = $hook->users;
        foreach ($users as $user) {
            $thread = $hook->createThread($user->id, "First message of $user->name");
            $hook->createThreadPart($user->id, $thread->id, "My email address is $user->email");
            foreach ($users as $other) {
                if ($other == $user)
                    continue;
                $hook->createThreadPart($other->id, $thread->id, "OK $user->name, here is my email $other->email");
                $hook->createThreadPart($other->id, $thread->id, "My phone: 001122{$other->id}");
                $hook->createThreadPart($user->id, $thread->id, "THX $other->name, I will update my contacts list");
                $hook->createThreadPart($other->id, $thread->id, "My to ;)");
            }
        }
        $this->assertCount(4, $hook->threads);
        $this->assertCount(52, $hook->threadParts);
        
    }
}

