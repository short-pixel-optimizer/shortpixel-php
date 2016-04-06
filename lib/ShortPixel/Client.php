<?php

namespace ShortPixel;

define ("API_URL", "https://api.shortpixel.com");

class Client {
    const API_ENDPOINT = API_URL . "/v2/reducer.php";

    private $options;

    public static function userAgent() {
        $curl = curl_version();
        return "ShortPixel/" . VERSION . " PHP/" . PHP_VERSION . " curl/" . $curl["version"];
    }

    function __construct($app_identifier = NULL) {
        $this->options = array(
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => join(" ", array_filter(array(self::userAgent(), $app_identifier))),
        );
    }

    /**
     * @param $method 'post' or 'get'
     * @param null $body - the POST fields
     * @param array $header - HTTP headers
     * @return array
     * @throws ConnectionException
     */
    function request($method, $body = NULL, $header = array()) {
        if (is_array($body)) {
            if (!empty($body)) {
                foreach($body as $key => $val) {
                    if($val === null) {
                        unset($body[$key]);
                    }
                }
                $body = json_encode($body);
                array_push($header, "Content-Type: application/json");
            } else {
                $body = NULL;
            }
        }
        $request = curl_init();
        curl_setopt_array($request, $this->options);

        curl_setopt($request, CURLOPT_URL, Client::API_ENDPOINT);
        curl_setopt($request, CURLOPT_HTTPHEADER, $header);
        curl_setopt($request, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if ($body) {
            curl_setopt($request, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($request);

        if (is_string($response)) {
            $status = curl_getinfo($request, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($request, CURLINFO_HEADER_SIZE);
            curl_close($request);

            $headers = self::parseHeaders(substr($response, 0, $headerSize));
            $body = substr($response, $headerSize);

            $details = json_decode($body);
            if (!$details) {
                $message = sprintf("Error while parsing response: %s (#%d)",
                    PHP_VERSION_ID >= 50500 ? json_last_error_msg() : "Error",
                    json_last_error());
                $details = (object) array(
                    "message" => $message,
                    "error" => "ParseError"
                );
            }

            if ($status >= 200 && $status <= 299) {
                return (object) array("body" => $details, "headers" => $headers);
            }

            throw Exception::create($details->message, $details->error, $status);
        } else {
            $message = sprintf("%s (#%d)", curl_error($request), curl_errno($request));
            curl_close($request);
            throw new ConnectionException("Error while connecting: " . $message);
        }
    }

    protected static function parseHeaders($headers) {
        if (!is_array($headers)) {
            $headers = explode("\r\n", $headers);
        }

        $res = array();
        foreach ($headers as $header) {
            if (empty($header)) continue;
            $split = explode(":", $header, 2);
            if (count($split) === 2) {
                $res[strtolower($split[0])] = trim($split[1]);
            }
        }
        return $res;
    }

    function download($sourceURL, $target) {
        $fp = fopen ($target, 'w+');              // open file handle

        $ch = curl_init($sourceURL);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // enable if you want
        curl_setopt($ch, CURLOPT_FILE, $fp);          // output to file
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1000);      // some large value to allow curl to run for a long time
        curl_setopt($ch, CURLOPT_USERAGENT, $this->options[CURLOPT_USERAGENT]);
        // curl_setopt($ch, CURLOPT_VERBOSE, true);   // Enable this line to see debug prints
        curl_exec($ch);

        curl_close($ch);                              // closing curl handle
        fclose($fp);                                  // closing file handle


    }
}
