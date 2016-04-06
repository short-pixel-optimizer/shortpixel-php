<?php

use ShortPixel\CurlMock;

class ShortPixelSourceTest extends TestCase {
    private $dummyFile;

    public function setUp() {
        parent::setUp();
        $this->dummyFile = __DIR__ . "/data/dummy.png";
    }

    public function testWithInvalidApiKeyFromFileShouldThrowAccountException() {
        ShortPixel\setKey("invalid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 401, "body" => '{"error":"Unauthorized","message":"Credentials are invalid"}'
        ));

        $this->setExpectedException("ShortPixel\AccountException");
        ShortPixel\Source::fromFile($this->dummyFile);
    }

    public function testWithInvalidApiKeyFromBufferShouldThrowAccountException() {
        ShortPixel\setKey("invalid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 401, "body" => '{"error":"Unauthorized","message":"Credentials are invalid"}'
        ));

        $this->setExpectedException("ShortPixel\AccountException");
        ShortPixel\Source::fromBuffer("png file");
    }

    public function testWithInvalidApiKeyFromUrlShouldThrowAccountException() {
        ShortPixel\setKey("invalid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 401, "body" => '{"error":"Unauthorized","message":"Credentials are invalid"}'
        ));

        $this->setExpectedException("ShortPixel\AccountException");
        ShortPixel\Source::fromUrl("http://example.com/test.jpg");
    }

    public function testWithValidApiKeyFromFileShouldReturnSource() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));

        $this->assertInstanceOf("ShortPixel\Source", ShortPixel\Source::fromFile($this->dummyFile));
    }

    public function testWithValidApiKeyFromFileShouldReturnSourceWithData() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));

        CurlMock::register("https://api.shortpixel.com/some/location", array(
            "status" => 200, "body" => "compressed file"
        ));

        $this->assertSame("compressed file", ShortPixel\Source::fromFile($this->dummyFile)->toBuffer());
    }

    public function testWithValidApiKeyFromBufferShouldReturnSource() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));

        $this->assertInstanceOf("ShortPixel\Source", ShortPixel\Source::fromBuffer("png file"));
    }

    public function testWithValidApiKeyFromBufferShouldReturnSourceWithData() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));

        CurlMock::register("https://api.shortpixel.com/some/location", array(
            "status" => 200, "body" => "compressed file"
        ));

        $this->assertSame("compressed file", ShortPixel\Source::fromBuffer("png file")->toBuffer());
    }

    public function testWithValidApiKeyFromUrlShouldReturnSource() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));

        $this->assertInstanceOf("ShortPixel\Source", ShortPixel\Source::fromUrl("http://example.com/testWithValidApiKey.jpg"));
    }

    public function testWithValidApiKeyFromUrlShouldReturnSourceWithData() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));

        CurlMock::register("https://api.shortpixel.com/some/location", array(
            "status" => 200, "body" => "compressed file"
        ));

        $this->assertSame("compressed file", ShortPixel\Source::fromUrl("http://example.com/testWithValidApiKey.jpg")->toBuffer());
    }

    public function testWithValidApiKeyFromUrlShouldThrowExceptionIfRequestIsNotOK() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 400, "body" => '{"error":"Source not found","message":"Cannot parse URL"}'
        ));

        $this->setExpectedException("ShortPixel\ClientException");
        ShortPixel\Source::fromUrl("file://wrong");
    }

    public function testWithValidApiKeyResultShouldReturnResult() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201,
            "headers" => array("Location" => "https://api.shortpixel.com/some/location"),
        ));

        $this->assertInstanceOf("ShortPixel\Result", ShortPixel\Source::fromBuffer("png file")->result());
    }

    public function testWithValidApiKeyPreserveShouldReturnSource() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));

        CurlMock::register("https://api.shortpixel.com/some/location", array(
            "status" => 200, "body" => "copyrighted file"
        ));

        $this->assertInstanceOf("ShortPixel\Source", ShortPixel\Source::fromBuffer("png file")->preserve("copyright", "location"));
        $this->assertSame("png file", CurlMock::last(CURLOPT_POSTFIELDS));
    }

    public function testWithValidApiKeyPreserveShouldReturnSourceWithData() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));

        CurlMock::register("https://api.shortpixel.com/some/location", array(
            "status" => 200, "body" => "copyrighted file"
        ));

        $this->assertSame("copyrighted file", ShortPixel\Source::fromBuffer("png file")->preserve("copyright", "location")->toBuffer());
        $this->assertSame("{\"preserve\":[\"copyright\",\"location\"]}", CurlMock::last(CURLOPT_POSTFIELDS));
    }

    public function testWithValidApiKeyPreserveShouldReturnSourceWithDataForArray() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));

        CurlMock::register("https://api.shortpixel.com/some/location", array(
            "status" => 200, "body" => "copyrighted file"
        ));

        $this->assertSame("copyrighted file", ShortPixel\Source::fromBuffer("png file")->preserve(array("copyright", "location"))->toBuffer());
        $this->assertSame("{\"preserve\":[\"copyright\",\"location\"]}", CurlMock::last(CURLOPT_POSTFIELDS));
    }

    public function testWithValidApiKeyPreserveShouldIncludeOtherOptionsIfSet() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));

        CurlMock::register("https://api.shortpixel.com/some/location", array(
            "status" => 200, "body" => "copyrighted resized file"
        ));

        $source = ShortPixel\Source::fromBuffer("png file")->resize(array("width" => 400))->preserve(array("copyright", "location"));

        $this->assertSame("copyrighted resized file", $source->toBuffer());
        $this->assertSame("{\"resize\":{\"width\":400},\"preserve\":[\"copyright\",\"location\"]}", CurlMock::last(CURLOPT_POSTFIELDS));
    }

    public function testWithValidApiKeyResizeShouldReturnSource() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));

        CurlMock::register("https://api.shortpixel.com/some/location", array(
            "status" => 200, "body" => "small file"
        ));

        $this->assertInstanceOf("ShortPixel\Source", ShortPixel\Source::fromBuffer("png file")->resize(array("width" => 400)));
        $this->assertSame("png file", CurlMock::last(CURLOPT_POSTFIELDS));
    }

    public function testWithValidApiKeyResizeShouldReturnSourceWithData() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));

        CurlMock::register("https://api.shortpixel.com/some/location", array(
            "status" => 200, "body" => "small file"
        ));

        $this->assertSame("small file", ShortPixel\Source::fromBuffer("png file")->resize(array("width" => 400))->toBuffer());
        $this->assertSame("{\"resize\":{\"width\":400}}", CurlMock::last(CURLOPT_POSTFIELDS));
    }

    public function testWithValidApiKeyStoreShouldReturnResultMeta() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201,
            "headers" => array("Location" => "https://api.shortpixel.com/some/location"),
        ));

        CurlMock::register("https://api.shortpixel.com/some/location", array(
            "body" => '{"store":{"service":"s3","aws_secret_access_key":"abcde"}}'
        ), array("status" => 200));

        $options = array("service" => "s3", "aws_secret_access_key" => "abcde");
        $this->assertInstanceOf("ShortPixel\Result", ShortPixel\Source::fromBuffer("png file")->store($options));
        $this->assertSame("{\"store\":{\"service\":\"s3\",\"aws_secret_access_key\":\"abcde\"}}", CurlMock::last(CURLOPT_POSTFIELDS));
    }

    public function testWithValidApiKeyStoreShouldReturnResultMetaWithLocation() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201,
            "headers" => array("Location" => "https://api.shortpixel.com/some/location"),
        ));

        CurlMock::register("https://api.shortpixel.com/some/location", array(
            "body" => '{"store":{"service":"s3"}}'
        ), array(
            "status" => 201,
            "headers" => array("Location" => "https://bucket.s3.amazonaws.com/example"),
        ));

        $location = ShortPixel\Source::fromBuffer("png file")->store(array("service" => "s3"))->location();
        $this->assertSame("https://bucket.s3.amazonaws.com/example", $location);
        $this->assertSame("{\"store\":{\"service\":\"s3\"}}", CurlMock::last(CURLOPT_POSTFIELDS));
    }

    public function testWithValidApiKeyStoreShouldIncludeOtherOptionsIfSet() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201,
            "headers" => array("Location" => "https://api.shortpixel.com/some/location"),
        ));

        CurlMock::register("https://api.shortpixel.com/some/location", array(
            "body" => '{"resize":{"width":300},"store":{"service":"s3","aws_secret_access_key":"abcde"}}'
        ), array("status" => 200));

        $options = array("service" => "s3", "aws_secret_access_key" => "abcde");
        $this->assertInstanceOf("ShortPixel\Result", ShortPixel\Source::fromBuffer("png file")->resize(array("width" => 300))->store($options));
        $this->assertSame("{\"resize\":{\"width\":300},\"store\":{\"service\":\"s3\",\"aws_secret_access_key\":\"abcde\"}}", CurlMock::last(CURLOPT_POSTFIELDS));
    }

    public function testWithValidApiKeyToBufferShouldReturnImageData() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));
        CurlMock::register("https://api.shortpixel.com/some/location", array(
            "status" => 200, "body" => "compressed file"
        ));

        $this->assertSame("compressed file", ShortPixel\Source::fromBuffer("png file")->toBuffer());
    }

    public function testWithValidApiKeyToFileShouldStoreImageData() {
        ShortPixel\setKey("valid");

        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));

        CurlMock::register("https://api.shortpixel.com/some/location", array(
            "status" => 200, "body" => "compressed file"
        ));

        $path = tempnam(sys_get_temp_dir(), "shortpixel-php");
        ShortPixel\Source::fromBuffer("png file")->toFile($path);
        $this->assertSame("compressed file", file_get_contents($path));
    }
}
