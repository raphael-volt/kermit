<?php
use src\services\AuthService;
use PHPUnit\Framework\TestCase;
use src\vo\User;
use src\services\DatabaseService;

/**
 * AuthService test case.
 */
class AuthServiceTest extends TestCase
{

    const APIKEY = "22317bca3371399e";

    /**
     *
     * @var AuthService
     */
    private $authService;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // TODO Auto-generated AuthServiceTest::setUp()

        $this->authService = AuthService::instance();
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown(): void
    {
        // TODO Auto-generated AuthServiceTest::tearDown()
        $this->authService = null;

        parent::tearDown();
    }

    /**
     *
     * @test AuthService::shouldHaveInstance()
     */
    public function shouldHaveInstance()
    {
        $this->assertInstanceOf(AuthService::class, $this->authService);
    }

    /**
     *
     * @test AuthServiceTest::shouldCreateUser
     * @depends shouldHaveInstance
     */
    function shouldCreateUser()
    {
        $user = new User();
        $user->name = "auth-user";
        $user->email = "auth-user@test.com";
        $db = DatabaseService::instance();
        $this->assertTrue($db->addUser($user));
        return $user;
    }

    /**
     *
     * @test AuthServiceTest::shouldVaidate
     * @depends shouldCreateUser
     */
    function shouldVaidate(User $user)
    {
        $hash = base64_encode("$user->email:" . self::APIKEY);
        $this->assertTrue($this->authService->validate($hash));
        $authUser = $this->authService->getUser();

        $this->assertEquals($user->id, $authUser->id);
        return $user;
    }

    /**
     *
     * @test AuthServiceTest::shouldNotValidateAfterDeleteUser
     * @depends shouldVaidate
     */
    function shouldNotValidateAfterDeleteUser(User $user)
    {
        $db = DatabaseService::instance();
        $db->deleteUser($user->id);
        $hash = base64_encode("$user->email:" . self::APIKEY);
        $this->assertFalse($this->authService->validate($hash));
        $this->assertNull($this->authService->getUser());
    }
}
