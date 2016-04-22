<?php

namespace ShortPixel;

class Source {
    private $urls;

    public function fromFile($path) {
        if(!file_exists($path)) throw new ClientException("File not found");
        $data       = array(
            "plugin_version" => "shortpixel-sdk 0.1.0" ,
            "key" =>  ShortPixel::getKey(),
            "files" => array(basename($path) => $path)
        );

        return new Commander($data, $this);
    }

    public function fromBuffer($string) {
        return new Result(array(), $string); //dummy
    }

    public function fromUrls($urls) {
        if(!is_array($urls)) {
            $urls = array($urls);
        }
        if(count($urls) > 100) {
            throw new ClientException("Maximum 100 images allowed per call.");
        }

        $this->urls = array_map ('utf8_encode',  $urls);
        $data       = array(
            "plugin_version" => "shortpixel-sdk 0.1.0" ,
            "key" =>  ShortPixel::getKey(),
            "urllist" => $this->urls
        );

        return new Commander($data, $this);
    }
}
