<?php

namespace ShortPixel;

const VERSION = "0.5.0";

class ShortPixel {
    private static $key = NULL;
    private static $client = NULL;
    private static $options = array(
        "lossy" => 1, // 1 - lossy, 0 - lossless
        "keep_exif" => 0, // 1 - EXIF is preserved, 0 - EXIF is removed
        "resize_width" => null, // in pixels. null means no resize
        "resize_height" => null, // in pixels. null means no resize
        "cmyk2rgb" => 1, // convert CMYK to RGB: 1 yes, 0 no
        "notify_me" => null, // should contain full URL of of notification script (notify.php)
        "wait" => 30, // seconds
        // **** local options ****
        "total_wait" => 30, //seconds
        "base_url" => null, // base url of the images - used to generate the path for toFile by extracting from original URL and using the remaining path as relative path to base_path
        "base_source_path" => "", // base path of the local files
        "base_path" => "/tmp", // base path to save the files
        // "" => null,
    );

    /**
     * @param $key - the ShortPixel API Key
     */
    public static function setKey($key) {
        self::$key = $key;
        self::$client = NULL;
    }

    /**
     * @param $options - set the ShortPxiel options. Options defaults are the following:
     *  "lossy" => 1, // 1 - lossy, 0 - lossless
        "keep_exif" => 0, // 1 - EXIF is preserved, 0 - EXIF is removed
        "resize_width" => null, // in pixels. null means no resize
        "resize_height" => null,
        "cmyk2rgb" => 1,
        "notify_me" => null, // should contain full URL of of notification script (notify.php)
        "wait" => 30,
        //local options
        "total_wait" => 30,
        "base_url" => null, // base url of the images - used to generate the path for toFile by extracting from original URL and using the remaining path as relative path to base_path
        "base_path" => "/tmp", // base path for the saved files
     */
    public static function setOptions($options) {
        array_merge(self::$options, $options);
    }

    /**
     * @return the API Key in use
     */
    public static function getKey() {
        return self::$key;
    }

    /**
     * @param $name - option name
     * @return the option value or false if not found
     */
    public static function opt($name) {
        return isset(self::$options[$name]) ? self::$options[$name] : false;
    }

    /**
     * @return the current options array
     */
    public static function options() {
        return self::$options;
    }

    /**
     * @return the Client singleton
     * @throws AccountException
     */
    public static function getClient() {
        if (!self::$key) {
            throw new AccountException("Provide an API key with ShortPixel\setKey(...)");
        }

        if (!self::$client) {
            self::$client = new Client();
        }

        return self::$client;
    }
}

/**
 * stub for ShortPixel::setKey()
 * @param $key - the ShortPixel API Key
 */
function setKey($key) {
    return ShortPixel::setKey($key);
}

/**
 * stub for ShortPixel::setOptions()
 * @return the current options array
 */
function setOptions($options) {
    return ShortPixel::setOptions($options);
}

/**
 * Stub for Source::fromFile
 * @param $path - the file path on the local drive
 * @return Commander - the class that handles the optimization commands
 * @throws ClientException
 */
function fromFiles($path) {
    $source = new Source();
    return $source->fromFiles($path);
}

function fromBuffer($string) {
    $source = new Source();
    return $source->fromBuffer($string);
}

/**
 * Stub for Source::fromUrls
 * @param $urls - the array of urls to be optimized
 * @return Commander - the class that handles the optimization commands
 * @throws ClientException
 */
function fromUrls($urls) {
    $source = new Source();
    return $source->fromUrls($urls);
}

function validate() {
    try {
        ShortPixel::getClient()->request("post");
    } catch (ClientException $e) {
        return true;
    }
}
