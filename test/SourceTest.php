<?php

require_once("helper.php");

use ShortPixel\Source;

class SourceTest extends TestCase {

    public function setUp() {
        parent::setUp();
        $this->dummyFile = __DIR__ . "/data/dummy.png";
    }

    public function testFromFilesWithValidFile() {
        $source = new Source();
        $commander = $source->fromFiles($this->dummyFile);
        $data = $commander->getData();
        $this->assertEquals($data['files'][0], $this->dummyFile);
    }

    /**
     * @expectedException \ShortPixel\ClientException
     * @expectedExceptionMessageRegExp /^File not found/
     */
    public function testFromFilesWithFileNotFound() {
        $source = new Source();
        $source->fromFiles(__DIR__ . "/data/not-present.png");
    }

    /**
     * @expectedException \ShortPixel\ClientException
     * @expectedExceptionMessage Maximum 10 local images allowed per call.
     */
    public function testFromFilesWithTooManyFiles() {
        $source = new Source();
        $files = array();
        for($i = 0; $i < 11; $i++) {
            $files[] = __DIR__ . "/data/dummy" . $i . ".png";
        }
        $source->fromFiles($files);
    }

    public function testFromUrlsWithOneUrl() {
        $source = new Source();
        $url = "https://shortpixel.com/img/tests/wrapper/shortpixel.png";
        $commander = $source->fromUrls($url);
        $data = $commander->getData();
        $this->assertEquals($data['urllist'][0], $url);
    }

    /**
     * @expectedException \ShortPixel\ClientException
     * @expectedExceptionMessage Maximum 100 images allowed per call.
     */
    public function testFromUrlsWithTooManyUrls() {
        $source = new Source();
        $urls = array();
        for($i = 0; $i < 101; $i++) {
            $urls[] = "https://shortpixel.com/img/tests/wrapper/shortpixel" . $i . ".png";
        }
        $source->fromUrls($urls);
    }
}