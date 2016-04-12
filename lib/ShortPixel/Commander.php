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

    public function wait($seconds = 30) {
        $seconds = max(0, intval($seconds));
        $this->commands = array_merge($this->commands, array("wait" => min($seconds, 30), "total_wait" => $seconds));
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
            return call_user_func_array(array(new Result($this, $return), $method), $args);
        }
        else {
            throw new ClientException('Unknown function '.__CLASS__.':'.$method, E_USER_ERROR);
        }
    }

    public function execute($wait = false){
        if($wait && !isset($this->commands['wait'])) {
            $this->commands = array_merge($this->commands, array("wait" => ShortPixel::opt("wait"), "total_wait" => ShortPixel::opt("total_wait")));
        }
        return ShortPixel::getClient()->request("post", array_merge(ShortPixel::options(), $this->commands, $this->data));
    }

    public function relaunch($pending) {
        if(!count($pending)) return false; //nothing to do

        //decrease the total wait and exit while if time expired
        $this->commands["total_wait"] = max(0, $this->commands["total_wait"] - min($this->commands["wait"], 30));
        if($this->commands['total_wait'] == 0) return false;

        $urllist = array();
        $type = isset($pending[0]->OriginalURL) ? 'URL' : 'FILE';
        foreach($pending as $pend) {
            if($type == 'URL') {
                $urllist[] = $pend->OriginalURL;
            } else {
                //for now
                throw new ClientException("Not implemented (Commander->execute())");
            }
        }
        $this->commands["refresh"] = 0;
        if($type == 'URL') {
            $this->data["urllist"] = $urllist;
        }
        return $this->execute();

    }

    public function getCommands() {
        return $this->commands;
    }

/*    public function setCommand($key, $value) {
        return $this->commands[$key] = $value;
    }
*/
}
