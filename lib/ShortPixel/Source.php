<?php

namespace ShortPixel;

class Source {
    private $urls;

    /**
     * @param $path - the file path on the local drive
     * @return Commander - the class that handles the optimization commands
     * @throws ClientException
     */
    public function fromFiles($paths) {
        if(!is_array($paths)) {
            $paths = array($paths);
        }
        if(count($paths) > 10) {
            throw new ClientException("Maximum 10 local images allowed per call.");
        }
        $files = array();
        foreach($paths as $path) {
            if (!file_exists($path)) throw new ClientException("File not found: " . $path);
            $files[] = $path;
        }
        $data       = array(
            "plugin_version" => "shortpixel-sdk " . VERSION,
            "key" =>  ShortPixel::getKey(),
            "files" => $files
        );

        return new Commander($data, $this);
    }

    public function fromBuffer($string) {
        throw new ClientException("fromBuffer not implemented");
    }

    /**
     * @param $urls - the array of urls to be optimized
     * @return Commander - the class that handles the optimization commands
     * @throws ClientException
     */
    public function fromUrls($urls) {
        if(!is_array($urls)) {
            $urls = array($urls);
        }
        if(count($urls) > 100) {
            throw new ClientException("Maximum 100 images allowed per call.");
        }

        $this->urls = array_map ('utf8_encode',  $urls);
        $data       = array(
            "plugin_version" => "shortpixel-sdk " . VERSION,
            "key" =>  ShortPixel::getKey(),
            "urllist" => $this->urls
        );

        return new Commander($data, $this);
    }
}
