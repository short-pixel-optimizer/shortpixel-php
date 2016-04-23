<?php
/**
 * User: simon
 * Date: 04.04.2016
 * Time: 14:01
 */

namespace ShortPixel;

/**
 * Class Commander - handles optimization commands such as lossless/lossy, resize, wait, notify_me etc.
 * @package ShortPixel
 */
class Commander {
    private $data, $source, $commands;

    public function __construct($data, Source $source) {
        $this->source = $source;
        $this->data = $data;
        $this->commands = array('lossy' => 1);
    }

    /**
     * @param int $type 1 - lossy (default), 0 - lossless
     * @return $this
     */
    public function optimize($type = 1) {
        $this->commands = array_merge($this->commands, array("lossy" => $type));
        return $this;
    }

    /**
     * resize the image - performs an outer resize (meaning the image will preserve aspect ratio and have the smallest sizes that allow a rectangle with given width and height to fit inside the resized image)
     * @param $width
     * @param $height
     * @return $this
     */
    public function resize($width, $height) {
        $this->commands = array_merge($this->commands, array("resize" => 1, "resize_width" => $width, "resize_height" => $height));
        return $this;
    }

    /**
     * @param bool|true $keep
     * @return $this
     */
    public function keepExif($keep = true) {
        $this->commands = array_merge($this->commands, array("keep_exif" => $keep ? 1 : 0));
        return $this;
    }

    /**
     * @param bool|true $refresh - if true, tells the server to discard the already optimized image and redo the optimization with the new settings.
     * @return $this
     */
    public function refresh($refresh = true) {
        $this->commands = array_merge($this->commands, array("refresh" => $refresh ? 1 : 0));
        return $this;
    }

    /**
     * will wait for the optimization to finish but not more than $seconds. The wait on the ShortPixel Server side can be a maximum of 30 seconds, for longer waits subsequent server requests will be sent.
     * @param int $seconds
     * @return $this
     */
    public function wait($seconds = 30) {
        $seconds = max(0, intval($seconds));
        $this->commands = array_merge($this->commands, array("wait" => min($seconds, 30), "total_wait" => $seconds));
        return $this;
    }

    /**
     * Not yet implemented
     * @param $callbackURL the full url of the notify.php script that handles the notification postback
     * @return mixed
     */
    public function notifyMe($callbackURL) {
        throw new ClientException("NotifyMe not yet implemented");
        $this->commands = array_merge($this->commands, array("notify_me" => $callbackURL));
        return $this->execute();
    }

    /**
     * call forwarder to Result - when a command is not understood by the Commander it could be a Result method like toFiles or toBuffer
     * @param $method
     * @param $args
     * @return mixed
     * @throws ClientException
     */
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

    /**
     * @internal
     * @param bool|false $wait
     * @return mixed
     * @throws AccountException
     */
    public function execute($wait = false){
        if($wait && !isset($this->commands['wait'])) {
            $this->commands = array_merge($this->commands, array("wait" => ShortPixel::opt("wait"), "total_wait" => ShortPixel::opt("total_wait")));
        }
        return ShortPixel::getClient()->request("post", array_merge(ShortPixel::options(), $this->commands, $this->data));
    }

    /**
     * @internal
     * @param $pending
     * @return bool|mixed
     * @throws ClientException
     */
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

    public function getData() {
        return $this->data;
    }

    /*    public function setCommand($key, $value) {
            return $this->commands[$key] = $value;
        }
    */
}
