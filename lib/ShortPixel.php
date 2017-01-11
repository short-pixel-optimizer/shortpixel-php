<?php

namespace ShortPixel;

const LIBRARY_CODE = "sp-sdk";
const VERSION = "0.8.0";

class ShortPixel {
    const MAX_ALLOWED_FILES_PER_CALL = 10;
    const MAX_RETRIES = 3;

    const LOSSY_EXIF_TAG = "SPXLY";
    const LOSSLESS_EXIF_TAG = "SPXLL";

    private static $key = NULL;
    private static $client = NULL;
    private static $options = array(
        "lossy" => 1, // 1 - lossy, 0 - lossless
        "keep_exif" => 0, // 1 - EXIF is preserved, 0 - EXIF is removed
        "resize" => 0, // 0 - don't resize, 1 - outer resize, 3 - inner resize
        "resize_width" => null, // in pixels. null means no resize
        "resize_height" => null, // in pixels. null means no resize
        "cmyk2rgb" => 1, // convert CMYK to RGB: 1 yes, 0 no
        "convertto" => "", // if '+webp' then also the WebP version will be generated
        // **** return options ****
        "notify_me" => null, // should contain full URL of of notification script (notify.php) - to be implemented
        "wait" => 30, // seconds
        // **** local options ****
        "total_wait" => 30, //seconds
        "base_url" => null, // base url of the images - used to generate the path for toFile by extracting from original URL and using the remaining path as relative path to base_path
        "base_source_path" => "", // base path of the local files
        "base_path" => "/tmp", // base path to save the files
        // **** persist options ****
        "persist_type" => null, // null - don't persist, otherwise "text" (.shortpixel text file in each folder), "exif" (mark in the EXIF that the image has been optimized) or "mysql" (to be implemented)
        "persist_name" => ".shortpixel",
        //"persist_user" => "user", // only for mysql
        //"persist_pass" => "pass" // only for mysql
        // "" => null,
    );

    public static $PROCESSABLE_EXTENSIONS = array('jpg', 'jpeg', 'jpe', 'jfif', 'jif', 'gif', 'png', 'pdf');

    private static $persistersRegistry = array();

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
        self::$options = array_merge(self::$options, $options);
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
            throw new AccountException("Provide an API key with ShortPixel\setKey(...)", -6);
        }

        if (!self::$client) {
            self::$client = new Client();
        }

        return self::$client;
    }

    public static function getPersister($context = null) {
        if(!self::$options["persist_type"]) {
            return null;
        }
        if($context && isset(self::$persistersRegistry[self::$options["persist_type"] . $context])) {
            return self::$persistersRegistry[self::$options["persist_type"] . $context];
        }

        $persister = null;
        switch(self::$options["persist_type"]) {
            case "exif":
                $persister = new persist\ExifPersister(self::$options);
                break;
            case "mysql":
                return null;
            case "text":
                $persister = new persist\TextPersister(self::$options);
                break;
            default:
                throw new PersistException("Unknown persist type: " . self::$options["persist_type"]);
        }

        if($context) {
            self::$persistersRegistry[self::$options["persist_type"] . $context] = $persister;
        }
        return $persister;
    }

    static public function isProcessable($path) {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), \ShortPixel\ShortPixel::$PROCESSABLE_EXTENSIONS);
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
 * Stub for Source::fromFiles
 * @param $path - the file path on the local drive
 * @return Commander - the class that handles the optimization commands
 * @throws ClientException
 */
function fromFiles($path) {
    $source = new Source();
    return $source->fromFiles($path);
}

function fromFile($path) {
    return fromFiles($path);
}

/**
 * Stub for Source::folderInfo
 * @param $path - the file path on the local drive
* @return (object)array('status', 'total', 'succeeded', 'pending', 'same', 'failed')
 * @throws ClientException
 */
function folderInfo($path) {
    $source = new Source();
    return $source->folderInfo($path);
}

/**
 * Stub for Source::fromFolder
 * @param $path - the file path on the local drive
 * @return Commander - the class that handles the optimization commands
 * @throws ClientException
 */
function fromFolder($path) {
    $source = new Source();
    return $source->fromFolder($path);
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

/**
 */
function isOptimized($path) {
    $persist = ShortPixel::getPersister($path);
    if($persist) {
        return $persist->isOptimized($path);
    } else {
        throw new Exception("No persister available");
    }
}

function validate() {
    try {
        ShortPixel::getClient()->request("post");
    } catch (ClientException $e) {
        return true;
    }
}

function recurseCopy($source, $dest) {
    foreach (
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST) as $item
    ) {
        $target = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        if ($item->isDir()) {
            if(!@mkdir($target)) {
                throw new PersistException("Could not create directory $target. Please check rights.");
            }
        } else {
            if(!@copy($item, $target)) {
                throw new PersistException("Could not copy file $item to $target. Please check rights.");
            }
        }
    }
}

function delTree($dir, $keepBase = true) {
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file", false) : unlink("$dir/$file");
    }
    return $keepBase ? true : rmdir($dir);
}
