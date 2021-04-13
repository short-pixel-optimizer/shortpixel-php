<?php
/**
 * User: simon
 * Date: 13.04.2021
 */
namespace ShortPixel;


class SPTools {
    public static function trailingslashit($path) {
        return rtrim($path, '/') . '/';
    }
}