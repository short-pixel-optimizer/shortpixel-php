<?php

use ShortPixel\CurlMock;
use ShortPixel\Client;

class ShortPixelClientTest extends TestCase {
    public function testRequestWhenValidShouldIssueRequest() {
        CurlMock::register(Client::API_URL(), array("status" => 200));
        $client = new ShortPixel\Client("key");
        $client->request("get", "/");

        $this->assertSame(Client::API_URL(), CurlMock::last(CURLOPT_URL));
        $this->assertSame("api:key", CurlMock::last(CURLOPT_USERPWD));
    }

    public function testRequestWhenValidShouldIssueRequestWithoutBodyWhenOptionsAreEmpty() {
        CurlMock::register(Client::API_URL(), array("status" => 200));
        $client = new ShortPixel\Client("key");
        $client->request("get", "/", array());

        $this->assertFalse(CurlMock::last_has(CURLOPT_POSTFIELDS));
    }

    public function testRequestWhenValidShouldIssueRequestWithoutContentTypeWhenOptionsAreEmpty() {
        CurlMock::register(Client::API_URL(), array("status" => 200));
        $client = new ShortPixel\Client("key");
        $client->request("get", "/", array());

        $this->assertSame(array(), CurlMock::last(CURLOPT_HTTPHEADER));
    }

    public function testRequestWhenValidShouldIssueRequestWithJSONBody() {
        CurlMock::register(Client::API_URL(), array("status" => 200));
        $client = new ShortPixel\Client("key");
        $client->request("get", "/", array("hello" => "world"));

        $this->assertSame(array("Content-Type: application/json"), CurlMock::last(CURLOPT_HTTPHEADER));
        $this->assertSame('{"hello":"world"}', CurlMock::last(CURLOPT_POSTFIELDS));
    }

    public function testRequestWhenValidShouldIssueRequestWithUserAgent() {
        CurlMock::register(Client::API_URL(), array("status" => 200));
        $client = new ShortPixel\Client("key");
        $client->request("get", "/");

        $curl = curl_version();
        $this->assertSame(ShortPixel\Client::userAgent(), CurlMock::last(CURLOPT_USERAGENT));
    }

    public function testRequestWhenValidShouldUpdateCompressionCount() {
        CurlMock::register(Client::API_URL(), array(
            "status" => 200, "headers" => array("Compression-Count" => "12")
        ));
        $client = new ShortPixel\Client("key");
        $client->request("get", "/");

        $this->assertSame(12, ShortPixel\getCompressionCount());
    }

    public function testRequestWhenValidWithAppIdShouldIssueRequestWithUserAgent() {
        CurlMock::register(Client::API_URL(), array("status" => 200));
        $client = new ShortPixel\Client("key", "TestApp/0.1");
        $client->request("get", "/");

        $curl = curl_version();
        $this->assertSame(ShortPixel\Client::userAgent() . " TestApp/0.1", CurlMock::last(CURLOPT_USERAGENT));
    }

    public function testRequestWithUnexpectedErrorShouldThrowConnectionException() {
        CurlMock::register(Client::API_URL(), array(
            "error" => "Failed!", "errno" => 2
        ));
        $this->setExpectedException("ShortPixel\ConnectionException");
        $client = new ShortPixel\Client("key");
        $client->request("get", "/");
    }

    public function testRequestWithUnexpectedErrorShouldThrowExceptionWithMessage() {
        CurlMock::register(Client::API_URL(), array(
            "error" => "Failed!", "errno" => 2
        ));
        $this->setExpectedExceptionRegExp("ShortPixel\ConnectionException",
            "/Error while connecting: Failed! \(#2\)/");
        $client = new ShortPixel\Client("key");
        $client->request("get", "/");
    }

    public function testRequestWithCurlErrorShouldThrowConnectionError() {
        CurlMock::register(Client::API_URL(), array(
            "errno" => 0, "error" => "", "return" => null
        ));
        $this->setExpectedException("ShortPixel\ConnectionException");
        $client = new ShortPixel\Client("key");
        $client->request("get", "/");
    }

    public function testRequestWithServerErrorShouldThrowServerException() {
        CurlMock::register(Client::API_URL(), array(
            "status" => 584, "body" => '{"error":"InternalServerError","message":"Oops!"}'
        ));
        $this->setExpectedException("ShortPixel\ServerException");
        $client = new ShortPixel\Client("key");
        $client->request("get", "/");
    }

    public function testRequestWithServerErrorShouldThrowExceptionWithMessage() {
        CurlMock::register(Client::API_URL(), array(
            "status" => 584, "body" => '{"error":"InternalServerError","message":"Oops!"}'
        ));
        $this->setExpectedExceptionRegExp("ShortPixel\ServerException",
            "/Oops! \(HTTP 584\/InternalServerError\)/");
        $client = new ShortPixel\Client("key");
        $client->request("get", "/");
    }

    public function testRequestWithBadServerResponseShouldThrowServerException() {
        CurlMock::register(Client::API_URL(), array(
            "status" => 543, "body" => '<!-- this is not json -->'
        ));
        $this->setExpectedException("ShortPixel\ServerException");
        $client = new ShortPixel\Client("key");
        $client->request("get", "/");
    }

    public function testRequestWithBadServerResponseShouldThrowExceptionWithMessage() {
        CurlMock::register(Client::API_URL(), array(
            "status" => 543, "body" => '<!-- this is not json -->'
        ));
        if (PHP_VERSION_ID >= 50500) {
            $this->setExpectedExceptionRegExp("ShortPixel\ServerException",
                "/Error while parsing response: Syntax error \(#4\) \(HTTP 543\/ParseError\)/");
        } else {
            $this->setExpectedExceptionRegExp("ShortPixel\ServerException",
                "/Error while parsing response: Error \(#4\) \(HTTP 543\/ParseError\)/");
        }
        $client = new ShortPixel\Client("key");
        $client->request("get", "/");
    }

    public function testRequestWithClientErrorShouldThrowClientException() {
        CurlMock::register(Client::API_URL(), array(
            "status" => 492, "body" => '{"error":"BadRequest","message":"Oops!"}')
        );
        $this->setExpectedException("ShortPixel\ClientException");
        $client = new ShortPixel\Client("key");
        $client->request("get", "/");
    }

    public function testRequestWithClientErrorShouldThrowExceptionWithMessage() {
        CurlMock::register(Client::API_URL(), array(
            "status" => 492, "body" => '{"error":"BadRequest","message":"Oops!"}'
        ));
        $this->setExpectedExceptionRegExp("ShortPixel\ClientException",
            "/Oops! \(HTTP 492\/BadRequest\)/");
        $client = new ShortPixel\Client("key");
        $client->request("get", "/");
    }

    public function testRequestWithBadCredentialsShouldThrowAccountException() {
        CurlMock::register(Client::API_URL(), array(
            "status" => 401, "body" => '{"error":"Unauthorized","message":"Oops!"}'
        ));
        $this->setExpectedException("ShortPixel\AccountException");
        $client = new ShortPixel\Client("key");
        $client->request("get", "/");
    }

    public function testRequestWithBadCredentialsShouldThrowExceptionWithMessage() {
        CurlMock::register(Client::API_URL(), array(
            "status" => 401, "body" => '{"error":"Unauthorized","message":"Oops!"}'
        ));
        $this->setExpectedExceptionRegExp("ShortPixel\AccountException",
            "/Oops! \(HTTP 401\/Unauthorized\)/");
        $client = new ShortPixel\Client("key");
        $client->request("get", "/");
    }
}
