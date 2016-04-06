[<img src="https://travis-ci.org/shortpixel/shortpixel-php.svg?branch=master" alt="Build Status">](https://travis-ci.org/shortpixel/shortpixel-php)

# ShortPixel SDK and API client for PHP

PHP client for the ShortPixel API, used for [ShortPixel](https://shortpixel.com) ShortPixel optimizes your images and improves website performance by reducing images size. Read more at [http://shortpixel.com](http://shortpixel.com).

## Documentation

[Go to the documentation for the PHP client](https://shortpixel.com/api).

## Installation

Install the API client with Composer. Add this to your `composer.json`:

```json
{
  "require": {
    "shortpixel/shortpixel-sdk": "*"
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

## Usage

```php
ShortPixel\setKey("YOUR_API_KEY");
ShortPixel\fromFile("unoptimized.png")->toFile("optimized.png");
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
