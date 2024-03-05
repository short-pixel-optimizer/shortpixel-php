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

    public static function convertToUtf8($string)
    {
        if (!self::isUtf8($string)) {
            if (function_exists('mb_convert_encoding')) {
                $string = mb_convert_encoding($string, 'UTF-8');
            } else {
                $string = utf8_encode($string);
            }
        }
        return $string;
    }

    /**
     * Checks a string for UTF-8 encoding.
     *
     * @param string $string
     *
     * @return bool
     */
    protected static function isUtf8($string)
    {
        if(function_exists('mb_detect_encoding')) return mb_detect_encoding($string, 'UTF-8', true);

        $length = strlen($string);

        for ($i = 0; $i < $length; $i++) {
            if (ord($string[$i]) < 0x80) {
                $n = 0;
            } elseif ((ord($string[$i]) & 0xE0) == 0xC0) {
                $n = 1;
            } elseif ((ord($string[$i]) & 0xF0) == 0xE0) {
                $n = 2;
            } elseif ((ord($string[$i]) & 0xF0) == 0xF0) {
                $n = 3;
            } else {
                return false;
            }

            for ($j = 0; $j < $n; $j++) {
                if ((++$i == $length) || ((ord($string[$i]) & 0xC0) != 0x80)) {
                    return false;
                }
            }
        }
        return true;
    }
}