[<img src="https://travis-ci.org/short-pixel-optimizer/shortpixel-php.svg?branch=master" alt="Build Status">](https://travis-ci.org/short-pixel-optimizer/shortpixel-php)

# ShortPixel SDK and API client for PHP

PHP client for the ShortPixel API, used for [ShortPixel](https://shortpixel.com) ShortPixel optimizes your images and improves website performance by reducing images size. Read more at [http://shortpixel.com](http://shortpixel.com).

## Documentation

[Go to the documentation for the PHP client](https://shortpixel.com/api).

## Installation

Install the API client with Composer. Add this to your `composer.json`:

```json
{
  "require": {
    "shortpixel/shortpixel-php": "*"
  }
}
```

Then install with:

```
composer install
```

Use autoloading to make the client available in PHP:

```php
require_once("vendor/autoload.php");
```

Alternatively, if you don't use Composer, add the following require to your PHP code:

```php
require_once("lib/shortpixel-php-req.php");_
```

Get your API Key from https://shortpixel.com/free-sign-up

## Usage

```php
// Set up the API Key. 
ShortPixel\setKey("YOUR_API_KEY");

// Compress with default settings
ShortPixel\fromUrls("https://your.site/img/unoptimized.png")->toFiles("/path/to/save/to");
// Compress with default settings but specifying a different file name
ShortPixel\fromUrls("https://your.site/img/unoptimized.png")->toFiles("/path/to/save/to", "optimized.png");

// Compress with default settings from a local file
ShortPixel\fromFile("/path/to/your/local/unoptimized.png")->toFiles("/path/to/save/to");
// Compress with default settings from several local files
ShortPixel\fromFiles(array("/path/to/your/local/unoptimized1.png", "/path/to/your/local/unoptimized2.png"))->toFiles("/path/to/save/to");

// Compress and resize
ShortPixel\fromUrls("https://your.site/img/unoptimized.png")->resize(100, 100)->toFiles("/path/to/save/to", "optimized.png");
// Keep the exif when compressing
ShortPixel\fromUrls("https://your.site/img/unoptimized.png")->keepExif()->toFiles("/path/to/save/to", "optimized.png");
// Also generate and save a WebP version of the file - the WebP file will be saved next to the optimized file, with  same basename and .webp extension
ShortPixel\fromUrls("https://your.site/img/unoptimized.png")->keepExif()->toFiles("/path/to/save/to", "optimized.png");

//Compress from a folder - the status of the compressed images is saved in a text file named .shortpixel in each image folder
\ShortPixel\ShortPixel::setOptions(array("persist_type" => "text"));
//Each call will optimize up to 10 images from the specified folder and mark in the .shortpixel file. 
//It automatically recurses a subfolder when finds it
//Set wait time to 300 to allow enough time for the images to be processed
// !!! current limitation: When using the text persist type, even if the parameter folder_to_save_to still needs to be set, it needs to be identical with the source path to folder !!! 
$ret = ShortPixel\fromFolder("/path/to/your/local/folder")->wait(300)->toFiles("/path/to/save/to");
//use a URL to map the folder to a WEB path in order for our servers to download themselves the images instead of receiving them via POST - faster and less exposed to connection timeouts
$ret = ShortPixel\fromWebFolder("/path/to/your/local/folder", "http://web.path/to/your/local/folder")->wait(300)->toFiles("/path/to/save/to");
//let ShortPixel back-up all your files, before overwriting them (third parameter of toFiles).
$ret = ShortPixel\fromFolder("/path/to/your/local/folder")->wait(300)->toFiles("/path/to/save/to", null, "/back-up/path");
//A simple loop to optimize all images from a folder
$stop = false;
while(!$stop) {
    $ret = ShortPixel\fromFolder("/path/to/your/local/folder")->wait(300)->toFiles("/path/to/save/to");
    if(count($ret->->succeeded) + count($ret->failed) + count($ret->same) + count($ret->pending) == 0) {
        $stop = true;
    }
}
//Alternatively, you might want to add the call to a cron job
```

## Running tests

```
composer install
vendor/bin/phpunit
```

### Integration tests

```
composer install
SHORTPIXEL_KEY=$YOUR_API_KEY vendor/bin/phpunit --no-configuration test/integration.php
```

## License

This software is licensed under the MIT License. [View the license](LICENSE).
