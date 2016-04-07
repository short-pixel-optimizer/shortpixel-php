<?php

if (!getenv("SHORTPIXEL_KEY")) {
    exit("Set the SHORTPIXEL_KEY environment variable.\n");
}

class ClientIntegrationTest extends PHPUnit_Framework_TestCase {
    static private $optimized;
    static private $tempDir;

    static public function setUpBeforeClass() {
        \ShortPixel\setKey(getenv("SHORTPIXEL_KEY"));

        $unoptimizedPath = __DIR__ . "/data/shortpixel.png";
        self::$optimized = \ShortPixel\fromFile($unoptimizedPath);

        $tmp = tempnam(sys_get_temp_dir(), "shortpixel-php");
        if (file_exists($tmp)) { unlink($tmp); }
        mkdir($tmp);
        if (is_dir($tmp)) self::$tempDir = $tmp;
    }

/*    public function testShouldCompressFromFile() {
        $this->assertTrue(true);
        return;
        $result = self::$optimized->toFiles(self::$tempDir);

        $size = filesize($path);
        $contents = fread(fopen($path, "rb"), $size);

        $this->assertGreaterThan(1000, $size);
        $this->assertLessThan(1500, $size);

        // width == 137
        $this->assertContains("\0\0\0\x89", $contents);
        $this->assertNotContains("Copyright ShortPixel", $contents);
    }
*/
    public function testShouldCompressLossyFromUrls() {
        $source = \ShortPixel\fromUrls("https://shortpixel.com/img/tests/wrapper/shortpixel.png");
        $result = $source->toFiles(self::$tempDir);

        if(count($result->succeeded)) {
            $data = $result->succeeded[0];
            $savedFile = $data->SavedFile;
            $size = filesize($savedFile);
            $contents = fread(fopen($savedFile, "rb"), $size);

            $this->assertEquals($data->LossySize, $size);

            /* width == 100 */
            $this->assertContains("\0\0\0\x64", $contents);
            $this->assertNotContains("Copyright ShortPixel", $contents);
        }
    }

    /*
    public function testShouldResize() {
        $source = \ShortPixel\fromUrls("https://shortpixel.com/img/tests/wrapper/shortpixel.png");
        $result = $source->resize(50, 50)->toFiles(self::$tempDir);

        if(count($result->succeeded)) {
            $data = $result->succeeded[0];
            $savedFile = $data->SavedFile;
            $size = filesize($savedFile);
            $contents = fread(fopen($savedFile, "rb"), $size);

            $this->assertEquals($data->LossySize, $size);

            // width == 50
            $this->assertContains("\0\0\0\x32", $contents);
            $this->assertNotContains("Copyright ShortPixel", $contents);
        }
    }

    public function testShouldPreserveMetadata() {
        $path = tempnam(sys_get_temp_dir(), "shortpixel-php");
        self::$optimized->preserve("copyright", "creation")->toFiles($path);

        $size = filesize($path);
        $contents = fread(fopen($path, "rb"), $size);

        $this->assertGreaterThan(1000, $size);
        $this->assertLessThan(2000, $size);

        // width == 137
        $this->assertContains("\0\0\0\x89", $contents);
        $this->assertContains("Copyright ShortPixel", $contents);
    }
    */
}
