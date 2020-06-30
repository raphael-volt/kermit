<?php
namespace specs\api;

use PHPUnit\Framework\TestCase;
use src\core\http\HTTPRequest;
use src\vo\Thread;

class HTTPRequestTest extends TestCase
{
    /**
     *
     * @test PathTest::shouldResolveRoute
     */
    function shouldResolveRoute()
    {
        
        $request = new HTTPRequest("/api/thread");
        $this->assertTrue($request->valid());
        $this->assertEquals("thread", $request->routeName);
        $this->assertEquals("jp_thread", $request->tableName);
        $this->assertEquals(Thread::class, $request->returnType);
        $this->assertNull($request->routeId);
        
        $this->assertTrue($request->setUri("/api/thread_part"));
        $this->assertTrue($request->setUri("/api/user"));
    }

    /**
     *
     * @test PathTest::shouldResolveRouteWithId
     * @depends shouldResolveRoute
     */
    function shouldResolveRouteWithId()
    {
        $request = new HTTPRequest("/api/thread/21");
        $this->assertTrue($request->valid());
        $this->assertEquals("thread", $request->routeName);
        $this->assertEquals("jp_thread", $request->tableName);
        $this->assertEquals(Thread::class, $request->returnType);
        $this->assertEquals(21, $request->routeId);
        
        $this->assertTrue($request->setUri("/api/thread_part/1"));
        $this->assertTrue($request->setUri("/api/user/1"));
    }
    /**
     *
     * @test HTTPRequestTest::shouldNotResolveRoute
     * @depends shouldResolveRouteWithId
     */
    function shouldNotResolveRoute()
    {
        $request = new HTTPRequest("/api/bar");
        $this->assertFalse($request->valid());
        $this->assertFalse($request->setUri("/api/bar/1"));
        $this->assertFalse($request->valid());
        $this->assertFalse($request->setUri("/user/1"));
        $this->assertFalse($request->valid());
        $this->assertNull($request->returnType);
        $this->assertNull($request->routeId);
        $this->assertNull($request->routeName);
        $this->assertNull($request->tableName);
    
    
    }
    
}

