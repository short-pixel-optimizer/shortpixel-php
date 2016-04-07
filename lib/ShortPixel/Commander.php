<?php
/**
 * User: simon
 * Date: 04.04.2016
 * Time: 14:01
 */

namespace ShortPixel;

class Commander {
    private $data, $commands;

    public function __construct($data) {
        $this->data = $data;
        $this->commands = array('lossy' => 1);
    }
    public function optimize($type = 1) {
        $this->commands = array_merge($this->commands, array("lossy" => $type));
        return $this;
    }

    public function resize($width, $height) {
        $this->commands = array_merge($this->commands, array("resize" => 1, "resize_width" => $width, "resize_height" => $height));
        return $this;
    }

    public function keepExif($keep = true) {
        $this->commands = array_merge($this->commands, array("keep_exif" => $keep ? 1 : 0));
        return $this;
    }

    public function refresh($refresh = true) {
        $this->commands = array_merge($this->commands, array("refresh" => $refresh ? 1 : 0));
        return $this;
    }

    public function notifyMe($callbackURL) {
        $this->commands = array_merge($this->commands, array("notify_me" => $callbackURL));
        return $this->execute();
    }

    public function __call($method, $args) {
        if (method_exists("ShortPixel\Result", $method)) {
            //execute the commands and forward to Result
            $return = $this->execute(true);
            return call_user_func_array(array(new Result($this->commands, $return), $method), $args);
        }
        else {
            throw new ClientException('Unknown function '.__CLASS__.':'.$method, E_USER_ERROR);
        }
    }

    private function execute($wait = false){
        if($wait) {
            $this->data = array_merge($this->data, array("wait" => ShortPixel::opt("wait")));
        }
        return ShortPixel::getClient()->request("post", array_merge(ShortPixel::options(), $this->commands, $this->data));
    }
}
