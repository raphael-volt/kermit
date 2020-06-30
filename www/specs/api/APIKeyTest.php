<?php
namespace specs\api;

use PHPUnit\Framework\TestCase;

class APIKeyTest extends TestCase
{
    /**
     * @test APIKeyTest::shouldGenerateKey
     */
    function shouldGenerateKey() {
        $value = bin2hex(random_bytes(8));
        $this->assertEquals(16, strlen($value));
        
    }
}

