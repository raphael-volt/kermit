<?php
use PHPUnit\Framework\TestCase;
use src\services\FileService;
use src\vo\JPFile;
use function PHPUnit\assertEquals;
use function PHPUnit\assertCount;

class ThreadImageTest extends TestCase
{

    static $optList;

    /*
     * chokidar www \
     * -i='www/specs/.phpunit.result.cache' \
     * -i='www/assets' \
     * --initial=true \
     * -c 'docker exec chat-app phpunit -c specs/specs.xml --testsuite=img-thread --color=always'
     * chokidar www -i='www/specs/.phpunit.result.cache' -i='www/assets' --initial=true -c 'docker exec chat-app phpunit -c specs/specs.xml --testsuite=img-thread --color=always'
     * chokidar www -i='www/specs/.phpunit.result.cache' --initial=true -c='docker exec chat-app phpunit -c specs/specs.xml --testsuite=img-thread --color=always'
     */
    // chokidar www -i='www/specs/.phpunit.result.cache' -i='www/assets' --initial=true -c 'docker exec chat-app phpunit -c specs/specs.xml --testsuite=img-thread --color=always'
    // www/assets/downloads/thread-1.json
    /**
     *
     * @test ThreadImageTest::shouldResolveSize
     */
    function shouldResolveSize()
    {
        $fs = FileService::instance();
        $sizes = $fs->resolveDestSizes(100, 200, 100, 100);
        $this->assertEquals(50, $sizes[0]);
        $this->assertEquals(100, $sizes[1]);

        $sizes = $fs->resolveDestSizes(200, 100, 100, 100);
        $this->assertEquals(100, $sizes[0]);
        $this->assertEquals(50, $sizes[1]);

        $sizes = $fs->resolveDestSizes(100, 200, 50, 0);
        $this->assertEquals(50, $sizes[0]);
        $this->assertEquals(100, $sizes[1]);

        $sizes = $fs->resolveDestSizes(100, 200, 100, 0);
        $this->assertEquals(100, $sizes[0]);
        $this->assertEquals(200, $sizes[1]);

        $sizes = $fs->resolveDestSizes(100, 200, 0, 200);
        $this->assertEquals(100, $sizes[0]);
        $this->assertEquals(200, $sizes[1]);
        $sizes = $fs->resolveDestSizes(100, 200, 0, 100);
        $this->assertEquals(50, $sizes[0]);
        $this->assertEquals(100, $sizes[1]);
    }

    /**
     *
     * @test ThreadImageTest::shouldResolveSize
     */
    function shouldLoadJson()
    {
        $this->assertFileExists("/var/www/html/assets/downloads/thread-1.json");
        $json = json_decode(file_get_contents("/var/www/html/assets/downloads/thread-1.json"));
        $this->assertObjectHasAttribute("ops", $json);
        $this->assertIsArray($json->ops);
        $this->assertCount(7, $json->ops);
        return $json;
    }

    /**
     *
     * @test ThreadImageTest::shouldFindImages
     * @depends shouldLoadJson
     */
    function shouldFindImages($json)
    {
        $this->assertIsArray($json->ops);
        $this->assertCount(7, $json->ops);
        $images = [];
        $optList = [];
        foreach ($json->ops as $op) {
            $insert = $op->insert;
            if (is_string($insert))
                continue;
            if (is_object($insert)) {
                $attr = null;
                $img = null;
                $width = NAN;
                if (property_exists($op, "attributes")) {
                    $attr = $op->attributes;
                    if (property_exists($attr, "width")) {
                        $width = $attr->width;
                    }
                }
                if (property_exists($insert, "image")) {
                    $img = $insert->image;
                }
                if ($img) {
                    $optList[] = $op;
                    $images[] = [
                        $img,
                        $width
                    ];
                }
            }
        }
        self::$optList = $optList;
        $this->assertCount(3, $images);
        $this->assertEquals(191, $images[0][1]);
        $this->assertEquals(100, $images[1][1]);
        $this->assertNan($images[2][1]);
        return $images;
    }

    /**
     *
     * @test ThreadImageTest::shouldSaveJpegWithResize
     * @depends shouldFindImages
     */
    function shouldSaveJpegWithResize($images)
    {
        $img = $images[0][0];
        $destWidth = $images[0][1];
        $fs = FileService::instance();
        $img = $fs->decodeImage($img);
        $this->assertNotFalse($img);
        $this->assertEquals("jpg", $img[0]);
        $fn = $fs->getFilename(JPFile::TYPE_IMAGE, "threadimagetest-1-orig.jpg");
        file_put_contents($fn, $img[1]);
        $src = imagecreatefromstring($img[1]);
        $width = (int) imagesx($src);
        $height = (int) imagesy($src);
        $this->assertEquals(400, $width);
        $this->assertEquals(400, $height);
        $dest = imagecreatetruecolor($destWidth, $destWidth);
        imagecopyresampled($dest, $src, 0, 0, 0, 0, $destWidth, $destWidth, $width, $width);
        imagedestroy($src);
        $fn = $fs->getFilename(JPFile::TYPE_IMAGE, "threadimagetest-1-resized.jpg");
        imagejpeg($dest, $fn, 100);
        imagedestroy($dest);

        $img = imagecreatefromjpeg($fn);
        $width = (int) imagesx($img);
        $height = (int) imagesy($img);
        $this->assertEquals(191, $width);
        $this->assertEquals(191, $height);

        return $images;
    }

    /**
     *
     * @test ThreadImageTest::shouldSavePNG
     * @depends shouldSaveJpegWithResize
     */
    function shouldSavePng($images)
    {
        $img = $images[2][0];
        $fs = FileService::instance();
        $img = $fs->decodeImage($img);
        $this->assertNotFalse($img);
        $this->assertEquals("png", $img[0]);
        $fn = $fs->getFilename(JPFile::TYPE_IMAGE, "threadimagetest-3-orig.png");
        file_put_contents($fn, $img[1]);

        $src = imagecreatefromstring($img[1]);
        $width = (int) imagesx($src);
        $height = (int) imagesy($src);
        $this->assertEquals(400, $width);
        $this->assertEquals(400, $height);

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);
        $alpha_image = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $alpha_image);
        imagecopyresampled($image, $src, 0, 0, 0, 0, $width, $width, $width, $width);
        imagedestroy($src);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $fn = $fs->getFilename(JPFile::TYPE_IMAGE, "threadimagetest-3-frompng.png");
        imagepng($image, $fn);
        imagedestroy($image);
        $src = imagecreatefrompng($fn);
        $width = (int) imagesx($src);
        $height = (int) imagesy($src);
        $this->assertEquals(400, $width);
        $this->assertEquals(400, $height);
        return $images;
    }

    /**
     *
     * @test ThreadImageTest::shouldSavePngWwithResize
     * @depends shouldSavePng
     */
    function shouldSavePngWwithResize($images)
    {
        $img = $images[1][0];
        $destWidth = $images[1][1];
        $this->assertEquals(100, $destWidth);
        $fs = FileService::instance();
        $img = $fs->decodeImage($img);
        $this->assertNotFalse($img);
        $this->assertEquals("png", $img[0]);
        $fn = $fs->getFilename(JPFile::TYPE_IMAGE, "threadimagetest-2-orig.png");
        file_put_contents($fn, $img[1]);
        $src = imagecreatefromstring($img[1]);
        $width = (int) imagesx($src);
        $height = (int) imagesy($src);
        $this->assertEquals(200, $width);
        $this->assertEquals(200, $height);

        $image = imagecreatetruecolor($destWidth, $destWidth);
        imagealphablending($image, true);
        $alpha_image = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $alpha_image);
        imagecopyresampled($image, $src, 0, 0, 0, 0, $destWidth, $destWidth, $width, $width);
        imagedestroy($src);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $fn = $fs->getFilename(JPFile::TYPE_IMAGE, "threadimagetest-2-frompng.png");
        imagepng($image, $fn);
        imagedestroy($image);
        $src = imagecreatefrompng($fn);
        $width = (int) imagesx($src);
        $height = (int) imagesy($src);
        $this->assertEquals($destWidth, $width);
        $this->assertEquals($destWidth, $height);
    }

    /**
     *
     * @test ThreadImageTest::serviceShouldSaveOp
     * @depends shouldSavePngWwithResize
     */
    function serviceShouldSaveJpgWithResize()
    {
        $op = self::$optList[0];
        $fs = FileService::instance();
        $fn = "fs-1";
        $result = $fs->saveInsert($op, $fn);
        $this->assertNotFalse($result);
        $this->assertEquals("fs-1.jpg", $result);
        $fn = $fs->getFilename(JPFile::TYPE_IMAGE, $result);
        $this->assertFileExists($fn);
    }

    /**
     *
     * @test ThreadImageTest::serviceShouldSavePngWithResize
     * @depends serviceShouldSaveJpgWithResize
     */
    function serviceShouldSavePngWithResize()
    {
        $op = self::$optList[1];
        $fs = FileService::instance();
        $fn = "fs-2";
        $result = $fs->saveInsert($op, $fn);
        $this->assertNotFalse($result);
        $this->assertEquals("fs-2.png", $result);
        $fn = $fs->getFilename(JPFile::TYPE_IMAGE, $result);
        $this->assertFileExists($fn);
    }

    /**
     *
     * @test ThreadImageTest::serviceShouldSavePng
     * @depends serviceShouldSavePngWithResize
     */
    function serviceShouldSavePng()
    {
        $op = self::$optList[2];
        $fs = FileService::instance();
        $fn = "fs-3";
        $result = $fs->saveInsert($op, $fn);
        $this->assertNotFalse($result);
        $this->assertEquals("fs-3.png", $result);
        $fn = $fs->getFilename(JPFile::TYPE_IMAGE, $result);
        $this->assertFileExists($fn);
    }
}






















