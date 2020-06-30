<?php
namespace specs\thread;

use PHPUnit\Framework\TestCase;
use hook\HookService;
use src\services\FileService;

class ThreadContentTest extends TestCase
{

    static $inserts;

    /**
     *
     * {@inheritdoc}
     * @see \PHPUnit\Framework\TestCase::setUpBeforeClass()
     */
    public static function setUpBeforeClass(): void
    {
        $hook = new HookService();
        self::$inserts = $hook->getThreadWithImage();
    }

    /**
     *
     * @test
     */
    function shouldGetHook()
    {
        $inserts = self::$inserts;
        $this->assertIsArray($inserts);
        $this->assertCount(5, $inserts);
    }

    /**
     *
     * @test ThreadContentTest::shouldGetMimeType
     * @depends shouldGetHook
     */
    function shouldGetMimeType()
    {
        $inserts = self::$inserts;
        $imgInsert = $inserts[1];
        $service = FileService::instance();
        $this->assertTrue($service->isImageInsert($imgInsert));

        $insert = $imgInsert->insert; // assert(pr$jpgInsert);
        $data = $service->decodeImage($insert->image);
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertEquals("png", $data[0]);

        $imgInsert = $inserts[3];
        $service = FileService::instance();
        $this->assertTrue($service->isImageInsert($imgInsert));

        $insert = $imgInsert->insert; // assert(pr$jpgInsert);
        $dataJPG = $service->decodeImage($insert->image);
        $this->assertIsArray($dataJPG);
        $this->assertCount(2, $dataJPG);
        $this->assertEquals("jpeg", $dataJPG[0]);

        return $data[1];
    }

    /**
     *
     * @test
     * @depends shouldGetMimeType
     */
    function shouldCreateImagePNG($data)
    {
        $filename = dirname(__DIR__, 2) . "/tmp/orig.thread-with-image.png";

        if (! file_exists($filename))
            file_put_contents($filename, $data);

        $filename = dirname(__DIR__, 2) . "/tmp/thread-with-image.png";
        if (file_exists($filename))
            unlink($filename);
        $srv = FileService::instance();
        $srv->createPNG($filename, $data);
        $this->assertFileExists($filename);
    }

    /**
     *
     * @test ThreadContentTest::shouldResolveImageSizes
     * @depends shouldCreateImagePNG
     */
    function shouldResolveImageSizes()
    {
        $srv = FileService::instance();
        $sizes = $srv->resolveDestWidth(200, 100, 0, 0);
        $this->assertEquals(200, $sizes[0]);
        $this->assertEquals(100, $sizes[1]);

        $sizes = $srv->resolveDestWidth(200, 100, 100, 0);
        $this->assertEquals(100, $sizes[0]);
        $this->assertEquals(50, $sizes[1]);

        $sizes = $srv->resolveDestWidth(200, 100, 0, 50);
        $this->assertEquals(100, $sizes[0]);
        $this->assertEquals(50, $sizes[1]);

        $sizes = $srv->resolveDestWidth(200, 100, 100, 100);
        $this->assertEquals(100, $sizes[0]);
        $this->assertEquals(50, $sizes[1]);

        $sizes = $srv->resolveDestWidth(200, 100, 20, 20);
        $this->assertEquals(20, $sizes[0]);
        $this->assertEquals(10, $sizes[1]);
    }

    /**
     *
     * @test
     * @depends shouldResolveImageSizes
     */
    function shouldCreateImagePNGResample()
    {
        $inserts = self::$inserts;
        $imgInsert = $inserts[1];
        $service = FileService::instance();
        $insert = $imgInsert->insert; // assert(pr$jpgInsert);
        $data = $service->decodeImage($insert->image);
        $filename = dirname(__DIR__, 2) . "/tmp/thread-with-image-resample.png";
        if (file_exists($filename))
            unlink($filename);

        $service->createPNG($filename, $data[1], 100);
        $this->assertFileExists($filename);
        $sizes = getimagesize($filename);
        $this->assertEquals(100, $sizes[0]);
        // 241x153
        $this->assertEquals(63, $sizes[1]);
    }

    /**
     *
     * @test ThreadContentTest::shouldCreateImageJPG
     * @depends shouldCreateImagePNGResample
     */
    function shouldCreateImageJPG()
    {
        $service = FileService::instance();
        $imgInsert = self::$inserts[3];
        $this->assertObjectHasAttribute("attributes", $imgInsert);
        $this->assertObjectHasAttribute("width", $imgInsert->attributes);
        $destWidth = (int) $imgInsert->attributes->width;
        $this->assertEquals(267, $destWidth);
        $insert = $imgInsert->insert;
        $dataJPG = $service->decodeImage($insert->image);
        $this->assertIsArray($dataJPG);
        $this->assertCount(2, $dataJPG);
        $this->assertEquals("jpeg", $dataJPG[0]);
        $filename = dirname(__DIR__, 2) . "/tmp/thread-with-image-resample.jpg";
        if (file_exists($filename))
            unlink($filename);

        $service->createJPEG(dirname(__DIR__, 2) . "/tmp/thread-with-image.jpg", $dataJPG[1]);
        $service->createJPEG($filename, $dataJPG[1], $destWidth);
        $this->assertFileExists($filename);
        $sizes = getimagesize($filename);
        $this->assertEquals($destWidth, $sizes[0]);
    }
}

