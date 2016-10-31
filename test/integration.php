<?php

if (!getenv("SHORTPIXEL_KEY")) {
    exit("Set the SHORTPIXEL_KEY environment variable.\n");
}

class ClientIntegrationTest extends PHPUnit_Framework_TestCase {
    static private $tempDir;

    static public function setUpBeforeClass() {
        \ShortPixel\setKey(getenv("SHORTPIXEL_KEY"));

        $tmp = tempnam(sys_get_temp_dir(), "shortpixel-php");
        if(file_exists($tmp)) unlink($tmp);
        mkdir($tmp);
        if (is_dir($tmp)) {
            self::$tempDir = $tmp;
        }
    }

    public function testShouldCompressFromFile() {
        $unoptimizedPath = __DIR__ . "/data/shortpixel.png";
        $result = \ShortPixel\fromFiles($unoptimizedPath)->refresh()->wait(300)->toFiles(self::$tempDir);

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

        $this->delTree(self::$tempDir);
    }

    public function testShouldCompressFromFiles() {
        $unoptimizedPath = __DIR__ . "/data/shortpixel.png";
        $unoptimizedPath2 = __DIR__ . "/data/cc.jpg";
        $result = \ShortPixel\fromFiles(array($unoptimizedPath, $unoptimizedPath2))->refresh()->wait(300)->toFiles(self::$tempDir);

        if(count($result->succeeded) == 2) {
            $data = $result->succeeded[0];
            $savedFile = $data->SavedFile;
            $size = filesize($savedFile);
            $contents = fread(fopen($savedFile, "rb"), $size);

            $this->assertEquals($data->LossySize, $size);

            // removes EXIF
            $this->assertNotContains("Copyright ShortPixel", $contents);
        } elseif(count($result->failed)) {
            $this->throwException("Failed");
        } elseif(count($result->same)) {
            $this->throwException("Optimized image is same size and shouldn't");
        } elseif(count($result->pending)) {
            echo("LossyFromURL - did not finish");
        }
        $this->delTree(self::$tempDir);
    }

    public function testShouldNotCompressFromFolderWithoutPersister() {
        \ShortPixel\ShortPixel::setOptions(array("persist_type" => null));
        $folderPath = __DIR__ . "/data/images1";
        try {
            \ShortPixel\fromFolder($folderPath);
            $this->throwException("Persist is not set up but fromFolder did not throw the Persist exception.");
        } catch (\ShortPixel\PersistException $ex) {
            echo("PersistException thrown.");
        }
    }

    public function testShouldGracefullyFixCorruptedTextPersisterFile() {
        \ShortPixel\ShortPixel::setOptions(array("persist_type" => "text"));
        $sourceFolder = __DIR__ . "/data/txt-persist-corrupt";
        $folderPath = self::$tempDir;
        try {
            $this->recurseCopy($sourceFolder, $folderPath);
            $cmd = \ShortPixel\fromFolder($folderPath);
            $files = $cmd->getData()["files"];
            $this->assertEquals(1, count($files));
            $this->assertEquals(substr($files[0], -24), "c3rgfb8dr5xyjcgx3o1w.jpg");
        } finally {
            $this->delTree(self::$tempDir);
        }
    }

    public function testShouldCompressJPGsFromFolderWithTextPersister() {
        \ShortPixel\ShortPixel::setOptions(array("persist_type" => "text"));
        $sourceFolder = __DIR__ . "/data/images-jpg";
        $folderPath = self::$tempDir;
        try {
            $this->recurseCopy($sourceFolder, $folderPath);
            $result = \ShortPixel\fromFolder($folderPath)->wait(300)->toFiles($folderPath);

            if(count($result->succeeded) > 0) {
                foreach($result->succeeded as $res) {
                    $this->assertTrue(\ShortPixel\isOptimized($res->SavedFile));
                }
            }
            if(count($result->failed)) {
                $this->throwException("Failed");
            }
            if(count($result->same)) {
                $this->throwException("Optimized image is same size and shouldn't");
            }
            if(count($result->pending)) {
                echo("LossyFromURL - did not finish");
            }
        } finally {
            $this->delTree(self::$tempDir);
        }
    }

    public function testShouldCompressManyFromFolderWithTextPersister() {
        \ShortPixel\ShortPixel::setOptions(array("persist_type" => "text"));
        $sourceFolder = __DIR__ . "/data/images-many";
        $folderPath = self::$tempDir;
        try {
            $this->recurseCopy($sourceFolder, $folderPath);

            $imageCount = 0;
            $tries = 0;

            while($imageCount < 24 && $tries < 5) {
                $result = \ShortPixel\fromFolder($folderPath)->wait(300)->toFiles($folderPath);
                $tries++;

                if(count($result->succeeded) > 0) {
                    $imageCount += count($result->succeeded);
                } elseif(count($result->failed)) {
                    $this->throwException("Failed");
                } elseif(count($result->same)) {
                    $this->throwException("Optimized image is same size and shouldn't");
                } elseif(count($result->pending)) {
                    echo("LossyFromURL - did not finish");
                }
            }
            $this->assertEquals(24, $imageCount);
        } finally {
            $this->delTree($folderPath);
        }
    }

    public function testShouldCompressSubfolderWithTextPersister() {
        \ShortPixel\ShortPixel::setOptions(array("persist_type" => "text"));
        $sourceFolder = __DIR__ . "/data/images-subfolders";
        $folderPath = self::$tempDir;
        try {
            $this->recurseCopy($sourceFolder, $folderPath);

            $imageCount = 0;
            $tries = 0;

            while($imageCount < 28 && $tries < 6) {
                $result = \ShortPixel\fromFolder($folderPath)->wait(300)->toFiles($folderPath);
                $tries++;

                if(count($result->succeeded) > 0) {
                    $imageCount += count($result->succeeded);
                } elseif(count($result->failed)) {
                    $this->throwException("Failed");
                } elseif(count($result->same)) {
                    $this->throwException("Optimized image is same size and shouldn't");
                } elseif(count($result->pending)) {
                    echo("LossyFromURL - did not finish");
                }
            }
            $this->assertEquals(28, $imageCount);
            $this->assertTrue(\ShortPixel\isOptimized( $folderPath . "/sub"));
        } finally {
            $this->delTree($folderPath);
        }
    }

    public function testShouldSkipAlreadyProcessedFromFolderWithTextPersister()
    {
        \ShortPixel\ShortPixel::setOptions(array("persist_type" => "text"));
        $sourceFolder = __DIR__ . "/data/images-opt-txt";
        $folderPath = self::$tempDir;
        try {
            $this->recurseCopy($sourceFolder, $folderPath);
            $cmd = \ShortPixel\fromFolder($folderPath);
            $files = $cmd->getData()["files"];
            $this->assertEquals(1, count($files));
            $this->assertEquals(substr($files[0], -12), "mistretz.jpg");
        } finally {
            $this->delTree(self::$tempDir);
        }
    }

    public function testIsOptimizedWithTextPersister()
    {
        \ShortPixel\ShortPixel::setOptions(array("persist_type" => "text"));
        $optimizedFile = __DIR__ . "/data/images-opt-txt/cerbu.jpg";
        $this->assertTrue(\ShortPixel\isOptimized($optimizedFile));
    }

    /* EXIF Persister currently deactivated server side

        public function testShouldCompressPNGsFromFolderWithExifPersister() {

            $this->markTestSkipped('EXIF persister not available currently'); return;

            \ShortPixel\ShortPixel::setOptions(array("persist_type" => "exif"));
            $sourceFolder = __DIR__ . "/data/images1";
            $folderPath = self::$tempDir;
            $this->recurseCopy($sourceFolder, $folderPath);
            $result = \ShortPixel\fromFolder($folderPath)->wait(300)->toFiles($folderPath);

            if(count($result->succeeded) > 0) {

            } elseif(count($result->failed)) {
                $this->throwException("Failed");
            } elseif(count($result->same)) {
                $this->throwException("Optimized image is same size and shouldn't");
            } elseif(count($result->pending)) {
                echo("LossyFromURL - did not finish");
            }

            $this->delTree($folderPath);
        }

        public function testShouldCompressJPGsFromFolderWithExifPersister() {
            \ShortPixel\ShortPixel::setOptions(array("persist_type" => "exif"));
            $sourceFolder = __DIR__ . "/data/images-jpg";
            $folderPath = self::$tempDir;
            $this->recurseCopy($sourceFolder, $folderPath);
            $result = \ShortPixel\fromFolder($folderPath)->wait(300)->toFiles($folderPath);

            if(count($result->succeeded) > 0) {

            } elseif(count($result->failed)) {
                $this->throwException("Failed");
            } elseif(count($result->same)) {
                $this->throwException("Optimized image is same size and shouldn't");
            } elseif(count($result->pending)) {
                echo("LossyFromURL - did not finish");
            }

            $this->delTree($folderPath);
        }

        public function testShouldSkipAlreadyProcessedJPGsFromFolderWithExifPersister()
        {
            \ShortPixel\ShortPixel::setOptions(array("persist_type" => "exif"));
            $sourceFolder = __DIR__ . "/data/images-jpg-part";
            $folderPath = self::$tempDir;
            $this->recurseCopy($sourceFolder, $folderPath);
            $cmd = \ShortPixel\fromFolder($folderPath);
            $files = $cmd->getData()["files"];
            $this->assertEquals(count($files), 1);
            $this->assertEquals(substr($files[0], -22), "final referinta-07.jpg");
        }

        public function testShouldSkipAlreadyProcessedPMGsFromFolderWithExifPersister()
        {
            \ShortPixel\ShortPixel::setOptions(array("persist_type" => "exif"));
            $sourceFolder = __DIR__ . "/data/images1part";
            $folderPath = self::$tempDir;
            $this->recurseCopy($sourceFolder, $folderPath);
            $cmd = \ShortPixel\fromFolder($folderPath);
            $files = $cmd->getData()["files"];
            $this->assertEquals(count($files), 3);
            sort($files);
            $this->assertEquals(substr($files[0], -8), "1-12.png");
        }
    */

    public function testShouldCompressLossyFromUrl() {
        \ShortPixel\ShortPixel::setOptions(array("persist_type" => null));
        $result = \ShortPixel\fromUrls("https://shortpixel.com/img/tests/wrapper/shortpixel.png")->refresh()->wait(300)->toFiles(self::$tempDir);

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
        $this->delTree(self::$tempDir);
    }

    public function testShouldCompressLossyFromUrls()
    {
        \ShortPixel\ShortPixel::setOptions(array("persist_type" => null));
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
        $this->delTree(self::$tempDir);
    }

    public function testShouldResizeJpg() {
        \ShortPixel\ShortPixel::setOptions(array("persist_type" => null));
        $source = \ShortPixel\fromUrls("https://shortpixel.com/img/tests/wrapper/cc3.jpg");
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
        $this->delTree(self::$tempDir);
    }

    public function testShouldPreserveExifJpg() {
        \ShortPixel\ShortPixel::setOptions(array("persist_type" => null));
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
        $this->delTree(self::$tempDir);
    }

    public function testShoulGenerateWebPFromJpg() {
        \ShortPixel\ShortPixel::setOptions(array("persist_type" => null));
        $source = \ShortPixel\fromUrls("https://shortpixel.com/img/tests/wrapper/cc4.jpg");
        $result = $source->refresh()->generateWebP()->wait(120)->toFiles(self::$tempDir);

        if(count($result->succeeded)) {
            $data = $result->succeeded[0];
            $savedFile = $data->WebPSavedFile;
            $size = filesize($savedFile);

            // size is correct
            $this->assertEquals($data->WebPLossySize, filesize($savedFile));
        } elseif(count($result->same)) {
            $this->throwException("Optimized image is same size and shouldn't");
        } elseif(count($result->pending)) {
            $this->throwException("testShouldPreserveExifJpg - did not finish");
        } else {
            $this->throwException("Failed");
        }
        $this->delTree(self::$tempDir);
    }

    public function testShouldReturnInaccessibleURL() {
        \ShortPixel\ShortPixel::setOptions(array("persist_type" => null));
        $source = \ShortPixel\fromUrls("https://shortpixel.com/img/not-present.jpg");
        $result = $source->toFiles(self::$tempDir);

        //TODO remove the -106, it's a hack
        if(!count($result->failed) || ($result->failed[0]->Status->Code != -202 && $result->failed[0]->Status->Code != -106)) {
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
        \ShortPixel\setKey('1ek71vnK0Xok3S2B3VYQ'); //this is a key with 0 credits
        try {
            $source = \ShortPixel\fromUrls("https://raw.githubusercontent.com/short-pixel-optimizer/shortpixel-php/master/test/data/cc.jpg");
            $result = $source->toFiles(self::$tempDir);
        } catch(\ShortPixel\AccountException $ex) {
            if($ex->getCode() == -403) {
                return;
            }
        }
        throw new \ShortPixel\ClientException("No Quota Exceeded message.");
    }

    protected function recurseCopy($source, $dest) {
        foreach (
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST) as $item
        ) {
            if ($item->isDir()) {
                mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            } else {
                copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            }
        }
    }

    public static function delTree($dir, $keepBase = true) {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::delTree("$dir/$file", false) : unlink("$dir/$file");
        }
        return $keepBase ? true : rmdir($dir);
    }
}
