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
        $source = \ShortPixel\fromUrls("https://raw.githubusercontent.com/short-pixel-optimizer/shortpixel-php/master/test/data/shortpixel.png");
        $result = $source->toFiles(self::$tempDir);

        if(count($result->succeeded)) {
            $data = $result->succeeded[0];
            $savedFile = $data->SavedFile;
            $size = filesize($savedFile);
            $contents = fread(fopen($savedFile, "rb"), $size);

            $this->assertEquals($data->LossySize, $size);

            // removes EXIF
            $this->assertNotContains("Copyright ShortPixel", $contents);
        }
    }


    public function testShouldResizeJpg() {
        $source = \ShortPixel\fromUrls("https://raw.githubusercontent.com/short-pixel-optimizer/shortpixel-php/master/test/data/cc.jpg");
        //$result = $source->resize(50, 50)->toFiles(self::$tempDir);
        $result = $source->resize(100, 100)->toFiles(self::$tempDir);

        if(count($result->succeeded)) {
            $data = $result->succeeded[0];
            $savedFile = $data->SavedFile;
            $size = filesize($savedFile);

            // size is correct
            $this->assertEquals($data->LossySize, filesize($savedFile));
            // width == 100
            $imageSize = getimagesize($savedFile);
            $this->assertEquals(min($imageSize[0], $imageSize[1]), 100);
            //EXIF is removed
            $exif = exif_read_data($savedFile);
            $this->assertNotContains("EXIF", $exif['SectionsFound']);
        }
    }

    public function testShouldPreserveExifJpg() {
        $source = \ShortPixel\fromUrls("https://raw.githubusercontent.com/short-pixel-optimizer/shortpixel-php/master/test/data/cc2.jpg");
        $result = $source->keepExif()->toFiles(self::$tempDir);

        if(count($result->succeeded)) {
            $data = $result->succeeded[0];
            $savedFile = $data->SavedFile;
            $size = filesize($savedFile);

            // size is correct
            $this->assertEquals($data->LossySize, filesize($savedFile));
            //EXIF is removed
            $exif = exif_read_data($savedFile);
            $this->assertContains("EXIF", $exif['SectionsFound']);
        }
    }
}
