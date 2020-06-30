<?php
namespace specs\thread;

use PHPUnit\Framework\TestCase;
use src\services\DatabaseService;
use src\vo\User;
use src\services\UserService;
use src\core\pdo\ApiPDO;

class UserServiceTest extends TestCase
{

    /**
     *
     * @test UserServiceTest::shouldDeleteAlain
     */
    function shouldDeleteAlain()
    {
        $i = 43;
        $db = DatabaseService::instance();
        $user = $db->getUserById($i);
        $pdo = ApiPDO::instance();
        $pdo->beginTransaction();
        
        if ($user) {
            $srv = UserService::instance();
            try {
                $srv->delete($i);
                $pdo->commit();
                $user = $db->getUserById($i);
            } catch (\PDOException $e) {
                $pdo->rollBack();
            }
        }
        $this->assertNull($user);
    }
}

