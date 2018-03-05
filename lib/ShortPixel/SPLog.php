<?php
/**
 * User: simon
 * Date: 26.02.2018
 */
namespace ShortPixel;

class SPLog {
    public static function format($msg) {
        global $processId;
        return "\n$processId@" . date("Y-m-d H:i:s") . "> $msg\n";
    }
}