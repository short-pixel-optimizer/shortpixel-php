<?php

namespace ShortPixel;

class Result {
    protected $commands, $data;

    public function __construct($commands, $data) {
        $this->commands = $commands;
        $this->data = $data;
    }

    public function data() {
        return $this->data;
    }

    public function toBuffer() {
        return $this->data;
    }

    public function toFiles($path = null, $fileName = null) {
        if($path) {
            if(substr($path, 0, 1) !== '/') {
                $path = (ShortPixel::opt("base_path") ?: __DIR__) . '/' . $path;
            }
        }
        //$body = $this->data->body;
        $i = 0;
        $succeeded = array();
        $pending = array();
        $failed = array();
        foreach($this->data->body as $optimized) {
            if($optimized->Status->Code != 2) {
                $pending[] = $optimized;
                continue;
            }
            if($optimized->OriginalURL) { // it was optimized from a URL
                if(ShortPixel::opt("base_url")) {
                    $origURLParts = explode('/', str_replace(ShortPixel::opt("base_url"), "", $optimized->OriginalURL));
                    $origFileName = $origURLParts[count($origURLParts) - 1];
                    unset($origURLParts[count($origURLParts) - 1]);
                    $relativePath = implode('/', $origURLParts);
                } else {
                    $origURLParts = explode('/', $optimized->OriginalURL);
                    $origFileName = $origURLParts[count($origURLParts) - 1];
                    $relativePath = "";
                }
            } else {
                throw(new ClientException("Post image not implemented"));
            }
            if(!$path) {
                $path = (ShortPixel::opt("base_path") ?: __DIR__) . '/' . $relativePath;
            }

            $target = $path . '/' . ($fileName ? $fileName . ($i > 0 ? "_" . $i : "") : $origFileName);
            ShortPixel::getClient()->download($this->commands["lossy"] == 1 ? $optimized->LossyURL : $optimized->LosslessURL, $target);

            $optimized->SavedFile = $target;
            $succeeded[] = $optimized;

            $i++;
        }

        return (object) array(
            'succeeded' => $succeeded,
            'pending' => $pending,
            'failed' => $failed
        );
    }
}
