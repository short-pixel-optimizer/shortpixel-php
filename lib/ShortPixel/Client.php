<?php

namespace ShortPixel;


class Client {

    private $options;
    public static function API_URL() {
        return "https://api.shortpixel.com";
    }
    public static function API_ENDPOINT() {
        return self::API_URL() . "/v2/reducer.php";
    }

    public static function API_UPLOAD_ENDPOINT() {
        return self::API_URL() . "/v2/post-reducer.php";
    }

    public static function userAgent() {
        $curl = curl_version();
        return "ShortPixel/" . VERSION . " PHP/" . PHP_VERSION . " curl/" . $curl["version"];
    }

    private static function caBundle() {
        return dirname(__DIR__) . "/data/shortpixel.crt";
    }

    function __construct() {
        $this->options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CAINFO => self::caBundle(),
            CURLOPT_SSL_VERIFYPEER => false, //TODO true
            CURLOPT_SSL_VERIFYHOST => false, //TODO remove
            CURLOPT_USERAGENT => self::userAgent(),
        );
    }

    /**
     * Does the CURL request to the ShortPixel API
     * @param $method 'post' or 'get'
     * @param null $body - the POST fields
     * @param array $header - HTTP headers
     * @return array - metadata from the API
     * @throws ConnectionException
     */
    function request($method, $body = NULL, $header = array()) {

        $request = curl_init();
        curl_setopt_array($request, $this->options);

        foreach($body as $key => $val) {
            if($val === null) {
                unset($body[$key]);
            }
        }

        $files = false;

        if (isset($body["urllist"])) { //images are sent as a list of URLs
            $this->prepareJSONRequest($request, $body, $method, $header);
        }
        elseif (isset($body["files"])) {
            $files = $this->prepareMultiPartRequest($request, $body, $header);
        }
        else {
            $body = NULL;
        }

         $response = curl_exec($request);
        if(curl_errno($request)) {
            throw new ConnectionException("Error while connecting: " . curl_error($request));
        }

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

            $fileMappings = false;
            if($files) {
                $fileMappings = array();
                foreach($details as $detail) {
                    if(isset($files[$detail->Key])) {
                        $fileMappings[$detail->OriginalURL] = $files[$detail->Key];
                    }
                }
            }
            if ($status >= 200 && $status <= 299) {
                return (object) array("body" => $details, "headers" => $headers, "fileMappings" => $fileMappings);
            }

            throw Exception::create($details->message, $details->error, $status);
        } else {
            $message = sprintf("%s (#%d)", curl_error($request), curl_errno($request));
            curl_close($request);
            throw new ConnectionException("Error while connecting: " . $message);
        }
    }

    protected function prepareJSONRequest($request, $body, $method, $header) {
        $body = json_encode($body);
        array_push($header, "Content-Type: application/json");
        curl_setopt($request, CURLOPT_URL, Client::API_ENDPOINT());
        curl_setopt($request, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($request, CURLOPT_HTTPHEADER, $header);
        if ($body) {
            curl_setopt($request, CURLOPT_POSTFIELDS, $body);
        }

    }

    protected function prepareMultiPartRequest($request, $body, $header) {
        $files = array();
        $fileCount = 1;
        foreach($body["files"] as $filePath) {
            $files["file" . $fileCount] = $filePath;
            $fileCount++;
        }
        unset($body["files"]);
        $body["file_paths"] = json_encode($files);
        curl_setopt($request, CURLOPT_URL, Client::API_UPLOAD_ENDPOINT());
        $this->curl_custom_postfields($request, $body, $files, $header);
        return $files;
    }

    protected function facemtest() {
        $url = Client::API_UPLOAD_ENDPOINT(); // e.g. http://localhost/myuploader/upload.php // request URL


        $filename = "shortpixel.png";
        $filedata = "/home/simon/ShortPixel/DEV/WRAPERS/shortpixel-php/test/data/shortpixel.png";
        $filedat2 = "/media/simon/DATA/FOTO&VIDEO_local/2016-03-08_SoundMaze/_00133.jpg";
        $filesize = filesize($filedata);




        if ($filedata != '')
        {
            $headers = array("Content-Type:multipart/form-data"); // cURL headers for file uploading
            $postfields = array("filedata" => "@$filedata", "filename" => $filename);
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, Client::API_UPLOAD_ENDPOINT());

            $this->curl_custom_postfields($ch, array("key" => $filename), array("file1" => $filedata, "file2" => $filedat2));
            $response = curl_exec($ch);
            if(!curl_errno($ch))
            {
                $info = curl_getinfo($ch);
                if ($info['http_code'] == 200)
                    $errmsg = "File uploaded successfully";
            }
            else
            {
                $errmsg = curl_error($ch);
            }
            curl_close($ch);
        }
        else
        {
            $errmsg = "Please select the file";
        }
    }

    function curl_custom_postfields($ch, array $assoc = array(), array $files = array(), $header = array()) {

        // invalid characters for "name" and "filename"
        static $disallow = array("\0", "\"", "\r", "\n");

        // build normal parameters
        foreach ($assoc as $k => $v) {
            $k = str_replace($disallow, "_", $k);
            $body[] = implode("\r\n", array(
                "Content-Disposition: form-data; name=\"{$k}\"",
                "",
                filter_var($v),
            ));
        }

        // build file parameters
        foreach ($files as $k => $v) {
            switch (true) {
                case false === $v = realpath(filter_var($v)):
                case !is_file($v):
                case !is_readable($v):
                    continue; // or return false, throw new InvalidArgumentException
            }
            $data = file_get_contents($v);
            $v = call_user_func("end", explode(DIRECTORY_SEPARATOR, $v));
            $k = str_replace($disallow, "_", $k);
            $v = str_replace($disallow, "_", $v);
            $body[] = implode("\r\n", array(
                "Content-Disposition: form-data; name=\"{$k}\"; filename=\"{$v}\"",
                "Content-Type: application/octet-stream",
                "",
                $data,
            ));
        }

        // generate safe boundary
        do {
            $boundary = "---------------------" . md5(mt_rand() . microtime());
        } while (preg_grep("/{$boundary}/", $body));

        // add boundary for each parameters
        array_walk($body, function (&$part) use ($boundary) {
            $part = "--{$boundary}\r\n{$part}";
        });

        // add final boundary
        $body[] = "--{$boundary}--";
        $body[] = "";

        // set options
        return @curl_setopt_array($ch, array(
            CURLOPT_POST       => true,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => implode("\r\n", $body),
            CURLOPT_HTTPHEADER => array_merge(array(
                "Expect: 100-continue",
                "Content-Type: multipart/form-data; boundary={$boundary}", // change Content-Type
            ), $header),
        ));
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 10000);      // some large value to allow curl to run for a long time
        curl_setopt($ch, CURLOPT_USERAGENT, $this->options[CURLOPT_USERAGENT]);
        // curl_setopt($ch, CURLOPT_VERBOSE, true);   // Enable this line to see debug prints
        curl_exec($ch);

        curl_close($ch);                              // closing curl handle
        fclose($fp);                                  // closing file handle


    }
}
