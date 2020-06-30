<?php
namespace specs\api;

use PHPUnit\Framework\TestCase;
use hook\HookService;
use src\vo\User;
use src\services\DatabaseService;

class JPUsersTest extends TestCase
{

    /**
     *
     * @var HookService
     */
    static $hook;

    static function setUpBeforeClass(): void
    {
        self::$hook = new HookService();
    }

    /**
     *
     * @test JPUsersTest::shouldGetJPUsers
     */
    function shouldGetJPUsers()
    {
        $hook = self::$hook;
        $users = $hook->getJPUsersFromJson();
        $this->assertCount(28, $users);
        return $users;
    }

    /**
     *
     * @param User[] $users
     * @test JPUsersTest::allJPUsersShouldExists
     * @depends shouldGetJPUsers
     */
    function allJPUsersShouldExists($users)
    {
        $db = DatabaseService::instance();
        $hook = self::$hook;
        $users = $hook->checkJPUsers();
        foreach ($users as $user) {
            $this->assertInstanceOf(User::class, $db->getUserByEmail($user->email));
            $this->assertNotNull($user->id);
        }
        return $users;
    }

    /**
     *
     * @param User[] $users
     * @test JPUsersTest::shouldGetOrCreateWelcomMessage
     * @depends allJPUsersShouldExists
     */
    function shouldGetOrCreateWelcomMessage($users)
    {
        $hook = self::$hook;
        $db = DatabaseService::instance();
        $stmt = $db->getPDO()->prepare("SELECT count(*) from jp_thread where subject=? and user_id=?");
        foreach ($users as $user) {
            $subject = "Hello from $user->name";
            $stmt->execute([
                $subject,
                $user->id
            ]);
            $n = $stmt->fetchColumn(0);
            if ($n == 0) {

                $thread = $hook->createThread($user->id, $subject);
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
        }
        $this->assertTrue(true);
    }
}

