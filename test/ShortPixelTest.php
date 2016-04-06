<?php

use ShortPixel\CurlMock;

class ClientTest extends TestCase {
    private $dummyFile;

    public function setUp() {
        parent::setUp();
        $this->dummyFile = __DIR__ . "/data/dummy.png";
    }

    public function testKeyShouldResetClientWithNewKey() {
        CurlMock::register(API_URL, array("status" => 200));
        ShortPixel\setKey("abcde");
        ShortPixel\ShortPixel::getClient();
        ShortPixel\setKey("fghij");
        $client = ShortPixel\ShortPixel::getClient();
        $client->request("get", "/");

        $this->assertSame("api:fghij", CurlMock::last(CURLOPT_USERPWD));
    }

    public function testAppIdentifierShouldResetClientWithNewAppIdentifier() {
        CurlMock::register(API_URL, array("status" => 200));
        ShortPixel\setKey("abcde");
        ShortPixel\setAppIdentifier("MyApp/1.0");
        ShortPixel\ShortPixel::getClient();
        ShortPixel\setAppIdentifier("MyApp/2.0");
        $client = ShortPixel\ShortPixel::getClient();
        $client->request("get", "/");

        $this->assertSame(ShortPixel\Client::userAgent() . " MyApp/2.0", CurlMock::last(CURLOPT_USERAGENT));
    }

    public function testClientWithKeyShouldReturnClient() {
        ShortPixel\setKey("abcde");
        $this->assertInstanceOf("ShortPixel\Client", ShortPixel\ShortPixel::getClient());
    }

    public function testClientWithoutKeyShouldThrowException() {
        $this->setExpectedException("ShortPixel\AccountException");
        $this->assertInstanceOf("ShortPixel\Client", ShortPixel\ShortPixel::getClient());
    }

    public function testValidateWithValidKeyShouldReturnTrue() {
        ShortPixel\setKey("valid");
        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 400, "body" => '{"error":"InputMissing","message":"No input"}'
        ));
        $this->assertTrue(ShortPixel\validate());
    }

    public function testValidateWithErrorShouldThrowException() {
        ShortPixel\setKey("invalid");
        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 401, "body" => '{"error":"Unauthorized","message":"Credentials are invalid"}'
        ));
        $this->setExpectedException("ShortPixel\AccountException");
        ShortPixel\validate();
    }

    public function testFromFileShouldReturnSource() {
        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));
        ShortPixel\setKey("valid");
        $this->assertInstanceOf("ShortPixel\Source", ShortPixel\fromFile($this->dummyFile));
    }

    public function testFromBufferShouldReturnSource() {
        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));
        ShortPixel\setKey("valid");
        $this->assertInstanceOf("ShortPixel\Source", ShortPixel\fromBuffer("png file"));
    }

    public function testFromUrlShouldReturnSource() {
        CurlMock::register(Client::API_ENDPOINT, array(
            "status" => 201, "headers" => array("Location" => "https://api.shortpixel.com/some/location")
        ));
        ShortPixel\setKey("valid");
        $this->assertInstanceOf("ShortPixel\Source", ShortPixel\fromUrl("http://example.com/test.jpg"));
    }
}
