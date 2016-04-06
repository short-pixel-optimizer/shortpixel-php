<?php

namespace ShortPixel;

const VERSION = "0.1.0";

class ShortPixel {
    private static $key = NULL;
    private static $appIdentifier = NULL;
    private static $client = NULL;
    private static $options = array(
        "lossy" => 1, // 1 - lossy, 0 - lossless
        "keep_exif" => 0, // 1 - EXIF is preserved, 0 - EXIF is removed
        "resize_width" => null, // in pixels. null means no resize
        "resize_height" => null,
        "cmyk2rgb" => 1,
        "notify_me" => null, // should contain full URL of of notification script (notify.php)
        "wait" => 10000,
        //local options
        "base_url" => null, // base url of the images - used to generate the path for toFile by extracting from original URL and using the remaining path as relative path to base_path
        "base_path" => "/tmp", // base path for the saved files
        // "" => null,
    );

    public static function setKey($key) {
        self::$key = $key;
        self::$client = NULL;
    }

    public static function setAppIdentifier($appIdentifier) {
        self::$appIdentifier = $appIdentifier;
        self::$client = NULL;
    }

    public static function setOptions($options) {
        array_merge(self::$options, $options);
    }

    public static function getKey() {
        return self::$key;
    }

    public static function opt($name) {
        return self::$options[$name];
    }

    public static function options() {
        return self::$options;
    }

    public static function getClient() {
        if (!self::$key) {
            throw new AccountException("Provide an API key with ShortPixel\setKey(...)");
        }

        if (!self::$client) {
            self::$client = new Client(self::$appIdentifier);
        }

        return self::$client;
    }
}

function setKey($key) {
    return ShortPixel::setKey($key);
}

function setAppIdentifier($appIdentifier) {
    return ShortPixel::setAppIdentifier($appIdentifier);
}

function setOptions($options) {
    return ShortPixel::setOptions($options);
}

function fromFile($path) {
    return Source::fromFile($path);
}

function fromBuffer($string) {
    return Source::fromBuffer($string);
}

function fromUrls($urls) {
    return Source::fromUrls($urls);
}

function validate() {
    try {
        ShortPixel::getClient()->request("post");
    } catch (ClientException $e) {
        return true;
    }
}
