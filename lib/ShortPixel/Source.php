<?php

namespace ShortPixel;

class Source {
    private $key, $urls;

    public static function fromFile($path) {
        return self::fromBuffer(file_get_contents($path));
    }

    public static function fromBuffer($string) {
    }

    public static function fromUrls($urls) {
        if(!is_array($urls)) {
            $images = array($urls);
        }
        if(count($urls) > 100) {
            throw new ClientException("Maximum 100 images allowed per call.");
        }

        $images = array_map ('utf8_encode',  $images);
        $data       = array(
            "plugin_version" => "shortpixel-sdk 1.0.0" ,
            "key" =>  ShortPixel::getKey(),
            "urllist" => $images
        );

        return new Commander($data);
    }

    public function __construct($key, $urls) {
        $this->key = $key;
        $this->urls = $urls;
    }
}
