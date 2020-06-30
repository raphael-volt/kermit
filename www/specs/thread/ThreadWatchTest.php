<?php
namespace specs\thread;

use PHPUnit\Framework\TestCase;
use src\services\DatabaseService;
use src\vo\ThreadData;
use function PHPUnit\assertEquals;
use src\core\http\HTTPRequest;
use src\vo\ThreadPart;
use src\services\WatchService;
use src\services\FileService;

/*
 * chokidar www -i='www/specs/.phpunit.result.cache' --initial=true -c='docker exec chat-app phpunit -c specs/specs.xml --testsuite=thread-watch --color=always'
 */
class ThreadWatchTest extends TestCase
{

    /**
     * DONE
     *
     * function setLastThreadPart()
     * {
     * $this->assertTrue(true);
     * $db = DatabaseService::instance();
     * $l = $db->getThreadList();
     * $l[0]->last_part;
     * $pdo = $db->getPDO();
     * $tb = DatabaseService::TN_THREAD;
     * $s = $pdo->prepare("UPDATE $tb SET last_part=? WHERE id=?");
     * foreach ($l as $t) {
     * $s->execute([$t->last_part, $t->id]);
     * }
     * }
     */
    /**
     *
     * @test ThreadWatchTest::shouldUnserializeThreadTree
     *
     */
    function shouldUnserializeThreadTree()
    {
        $input = '{"thread":{"subject":"Hook"},"inserts":[{"insert":"Contents\n"}]}';
        $tree = new ThreadData(json_decode($input));
        $this->assertEquals('Hook', $tree->thread->subject);
    }

    /**
     *
     * @test ThreadWatchTest::shouldGetParams
     *
     */
    function shouldGetParams()
    {
        $url = 'http://foo.com/api/bar/1?k=1&p=2';
        $path = parse_url($url, PHP_URL_PATH);
        $this->assertEquals('/api/bar/1', $path);
        $params = parse_url($url, PHP_URL_QUERY);
        $this->assertEquals("k=1&p=2", $params);
        $get = [];
        parse_str($params, $get);
        $this->assertArrayHasKey("k", $get);
        $this->assertArrayHasKey("p", $get);
        $this->assertEquals("1", $get["k"]);
        $this->assertEquals("2", $get["p"]);
        $url = 'http://foo.com/api/bar/1';
        $params = parse_url($url, PHP_URL_QUERY);
        $this->assertEquals(0, strlen($params));
        // contents=0&ids=63&ids=61&ids=62&ids=60&ids=59&ids=58&ids=57&ids=49&ids=44&ids=34&ids=48&ids=36&ids=47&ids=46&ids=43&ids=42&ids=41&ids=40&ids=35&ids=33&ids=31&ids=30&ids=29&ids=28&ids=27&ids=26&ids=25&ids=24&ids=23&ids=22&ids=21&ids=20&ids=19&ids=18&ids=17&ids=16&ids=15&ids=14&ids=13&ids=12&ids=11&ids=10&ids=9
        $req = new HTTPRequest("/api/thread/12?contents=0");
        $this->assertIsArray($req->params);
        $this->assertArrayHasKey("contents", $req->params);
        $this->assertEquals("0", $req->params['contents']);

        $this->assertTrue($req->hasParam('contents'));
        $this->assertEquals("0", $req->getParam("contents"));

        $req = new HTTPRequest("/api/thread/12");
        $this->assertFalse($req->hasParam('contents'));
    }

    /**
     *
     * @test ThreadWatchTest::shouldGetLatestThreadPart
     * @depends shouldGetParams
     */
    function shouldGetLatestThreadPart()
    {
        $fn = FileService::instance()->assets("users-status.json");
        if(file_exists($fn))    
            unlink($fn);
        
        $db = DatabaseService::instance();
        $tp = $db->getLatestThreadPart();
        $this->assertInstanceOf(ThreadPart::class, $tp);
        $this->assertIsArray($tp->read_by);
        $n = count($tp->read_by);
        $this->assertGreaterThan(0, $n);
        $this->assertTrue(is_int($tp->read_by[0]));
        $i = array_search($tp->user_id, $tp->read_by);
        $this->assertNotFalse($i);

        $watch = WatchService::instance();
        $value = new \stdClass();
        $value->status = '';
        $value->user_id = $tp->user_id;
        $dif = $watch->check($value);
        $this->assertNotNull($dif);
        $this->assertInstanceOf(\stdClass::class, $dif);
        foreach ([
            "thread",
            "thread_part",
            "thread_user",
            "users"
        ] as $key) {
            $this->assertTrue(property_exists($dif, $key));
        }
        $this->assertEquals($tp->user_id, $dif->thread_user);
        $this->assertEquals($tp->thread_id, $dif->thread);
        $this->assertEquals($tp->id, $dif->thread_part);
        $this->assertIsArray($dif->users);
        $n = count($dif->users);
        $this->assertGreaterThan(0, $n);
        $this->assertTrue(is_int($dif->users[0]));
        $i = array_search($tp->user_id, $dif->users);
        $this->assertNotFalse($i);
        $watch->save();
        $this->assertFileExists($fn);
    }
}

