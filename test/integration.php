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
    public function testShouldCompressLossyFromUrl() {
        $source = \ShortPixel\fromUrls("https://shortpixel.com/img/tests/wrapper/shortpixel.png");
        $result = $source->refresh()->wait(300)->toFiles(self::$tempDir);

        if(count($result->succeeded)) {
            $data = $result->succeeded[0];
            $savedFile = $data->SavedFile;
            $size = filesize($savedFile);
            $contents = fread(fopen($savedFile, "rb"), $size);

            $this->assertEquals($data->LossySize, $size);

            // removes EXIF
            $this->assertNotContains("Copyright ShortPixel", $contents);
        } elseif(count($result->same)) {
            $this->throwException("Optimized image is same size and shouldn't");
        } elseif(count($result->pending)) {
            echo("LossyFromURL - did not finish");
        } else {
            $this->throwException("Failed");
        }
    }

    public function testShouldCompressLossyFromUrls()
    {
        $source = \ShortPixel\fromUrls(array(
            "https://shortpixel.com/img/tests/wrapper/cc2.jpg",
            "https://shortpixel.com/img/tests/wrapper/shortpixel.png"
        ));
        $result = $source->refresh()->wait(300)->toFiles(self::$tempDir);

        if (count($result->succeeded) + count($result->pending) != 2) {
            throw new \ShortPixel\ClientException("Some failed images");
        } elseif(count($result->pending)) {
            echo("LossyFromURLs - did not finish");
        }
    }
    public function testShouldResizeJpg() {
        $source = \ShortPixel\fromUrls("https://shortpixel.com/img/tests/wrapper/cc2.jpg");
        //$result = $source->resize(50, 50)->toFiles(self::$tempDir);
        $result = $source->refresh()->resize(100, 100)->wait(120)->toFiles(self::$tempDir);

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
        } elseif(count($result->same)) {
            $this->throwException("Optimized image is same size and shouldn't");
        } elseif(count($result->pending)) {
            $this->throwException("testShouldResizeJpg - did not finish");
        } else {
            $this->throwException("Failed");
        }
    }

    public function testShouldPreserveExifJpg() {
        $source = \ShortPixel\fromUrls("https://shortpixel.com/img/tests/wrapper/cc.jpg");
        $result = $source->refresh()->keepExif()->wait(90)->toFiles(self::$tempDir);

        if(count($result->succeeded)) {
            $data = $result->succeeded[0];
            $savedFile = $data->SavedFile;
            $size = filesize($savedFile);

            // size is correct
            $this->assertEquals($data->LossySize, filesize($savedFile));
            //EXIF is removed
            $exif = exif_read_data($savedFile);
            $this->assertContains("EXIF", $exif['SectionsFound']);
        } elseif(count($result->same)) {
            $this->throwException("Optimized image is same size and shouldn't");
        } elseif(count($result->pending)) {
            $this->throwException("testShouldPreserveExifJpg - did not finish");
        } else {
            $this->throwException("Failed");
        }
    }

    public function testShouldReturnInaccessibleURL() {
        $source = \ShortPixel\fromUrls("https://shortpixel.com/img/not-present.jpg");
        $result = $source->toFiles(self::$tempDir);

        if(!count($result->failed) || $result->failed[0]->Status->Code != -202) {
            throw new \ShortPixel\ClientException("Image does not exist but did not show up as failed.");
        }
    }

    public function testShouldReturnTooManyURLs() {
        $tooMany = array();
        for($i = 0; $i < 101; $i++) {
            $tooMany[] = "https://shortpixel.com/img/not-present{$i}.jpg";
        }
        try {
            \ShortPixel\fromUrls($tooMany);
        }
        catch (\ShortPixel\ClientException $ex) {
            return;
        }
        throw new \ShortPixel\ClientException("More than 100 images but no exception thrown.");
    }
    public function testShouldReturnQuotaExceeded() {

    }
}
