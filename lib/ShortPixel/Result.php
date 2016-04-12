<?php

namespace ShortPixel;

class Result {
    protected $commander, $data;

    public function __construct($commander, $data) {
        $this->commander = $commander;
        $this->data = $data;
    }

    public function data() {
        return $this->data;
    }

    public function toBuffer() {
        return $this->data;
    }

    public function toFiles($path = null, $fileName = null) {
        if($path) {
            if(substr($path, 0, 1) !== '/') {
                $path = (ShortPixel::opt("base_path") ?: __DIR__) . '/' . $path;
            }
        }
        //$body = $this->data->body;
        $i = 0;
        $succeeded = $pending = $failed = $same = array();

        $cmds = $this->commander->getCommands();

        while(true) {
            $data = $this->data->body;
            //check API key errors
            if(isset($data->Status->Code)) {
                switch ($data->Status->Code) {
                    case -401:
                        throw new AccountException($data->Status->Message);
                }
            }
            // No API level error
            foreach($data as $item) {
                if($item->Status->Code == 1) {
                    $found = $this->findItem($item, $pending, "OriginalURL"); //TODO check if fromURL
                    if(!$found) {
                        $pending[] = $item;
                    }
                    continue;
                }
                elseif ($item->Status->Code != 2) {
                    $failed[] = $item;
                    $this->removeItem($item, $pending, "OriginalURL"); //TODO check if fromURL and if not, use file path
                    continue;
                }
                elseif($item->PercentImprovement == 0) {
                    $same[] = $item;
                    $this->removeItem($item, $pending, "OriginalURL"); //TODO check if fromURL and if not, use file path
                    continue;
                }

                //Now that's an optimized image indeed
                if($item->OriginalURL) { // it was optimized from a URL
                    if(ShortPixel::opt("base_url")) {
                        $origURLParts = explode('/', str_replace(ShortPixel::opt("base_url"), "", $item->OriginalURL));
                        $origFileName = $origURLParts[count($origURLParts) - 1];
                        unset($origURLParts[count($origURLParts) - 1]);
                        $relativePath = implode('/', $origURLParts);
                    } else {
                        $origURLParts = explode('/', $item->OriginalURL);
                        $origFileName = $origURLParts[count($origURLParts) - 1];
                        $relativePath = "";
                    }
                } else {
                    throw(new ClientException("Post image not implemented"));
                }
                if(!$path) {
                    $path = (ShortPixel::opt("base_path") ?: __DIR__) . '/' . $relativePath;
                }

                $target = $path . '/' . ($fileName ? $fileName . ($i > 0 ? "_" . $i : "") : $origFileName);
                ShortPixel::getClient()->download($cmds["lossy"] == 1 ? $item->LossyURL : $item->LosslessURL, $target);

                $item->SavedFile = $target;
                $succeeded[] = $item;
                //remove from pending
                $this->removeItem($item, $pending, "OriginalURL"); //TODO check if fromURL and if not, use file path
                $i++;
            }

            //For the pending items relaunch
            if(count($pending)) {
                $this->data = $this->commander->relaunch($pending);
            } else {
                break;
            }
            if($this->data == false) { //time's up
                break;
            }
        }

        return (object) array(
            'succeeded' => $succeeded,
            'pending' => $pending,
            'failed' => $failed,
            'same' => $same
        );
    }

    private function findItem($item, $arr, $key) {
        for($j = 0; $j < count($arr); $j++) { //TODO check if fromURL
            if($arr[$j]->$key == $item->$key) {
                return $j;
            }
        }
        return false;
    }
    private function removeItem($item, &$arr, $key) {
        $j = $this->findItem($item, $arr, $key);
        if($j !== false) {
            array_splice($arr, $j);
        }
    }
}
