<?php

namespace ShortPixel;

/**
 * Class Result - handles the result of the optimization (saves to file or returns a buffer, etc)
 * @package ShortPixel
 */
class Result {
    protected $commander, $data;

    public function __construct($commander, $data) {
        $this->commander = $commander;
        $this->data = $data;
    }

    /**
     * returns the metadata provided by the optimizer
     * @return mixed
     */
    public function data() {
        return $this->data;
    }

    public function toBuffer() {
        return $this->data;
    }

    /**
     * @param null $path - path to save the file to
     * @param null $fileName - filename of the saved file
     * @return object containig lists with succeeded, pending, failed and same items (same means the image did not need optimization)
     * @throws AccountException
     * @throws ClientException
     */
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
            if(isset($data->Status->Code) && $data->Status->Code < 0) {
                throw new AccountException($data->Status->Message, $data->Status->Code);
            }
            // No API level error
            foreach($data as $item) {

                $targetPath = $path;

                if($item->Status->Code == 1) {
                    $found = $this->findItem($item, $pending, "OriginalURL");
                    if(!$found) {
                        $pending[] = $item;
                    }
                    continue;
                }
                elseif ($item->Status->Code != 2) {
                    $failed[] = $item;
                    $this->removeItem($item, $pending, "OriginalURL");
                    continue;
                }
                elseif($item->PercentImprovement == 0) {
                    $same[] = $item;
                    $this->removeItem($item, $pending, "OriginalURL");
                    continue;
                }

                //Now that's an optimized image indeed
                if($this->data->fileMappings) { // it was optimized from a local file, fileMappings contains the mappings from the local files to the internal ShortPixel URLs
                    $originalPath = isset($this->data->fileMappings[$item->OriginalURL]) ? $this->data->fileMappings[$item->OriginalURL] : false;
                    //
                    if(ShortPixel::opt("base_source_path") && $originalPath) {
                        $origPathParts = explode('/', str_replace(ShortPixel::opt("base_source_path"). "/", "", $originalPath));
                        $origFileName = $origPathParts[count($origPathParts) - 1];
                        unset($origPathParts[count($origPathParts) - 1]);
                        $relativePath = implode('/', $origPathParts);
                    } elseif($originalPath) {
                        $origPathParts = explode('/', $originalPath);
                        $origFileName = $origPathParts[count($origPathParts) - 1];
                        $relativePath = "";
                    } elseif(isset($item->OriginalFileName)) {
                        $origFileName = $item->OriginalFileName;
                        $relativePath = "";
                    } else {
                        throw new ClientException("Cannot determine a filename to save to.");
                    }
                } elseif(isset($item->OriginalURL)) {  // it was optimized from a URL
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
                } else { // something is wrong
                    throw(new ClientException("Malformed response. Please contact support."));
                }
                if(!$targetPath) { //se pare ca trebuie oricum
                    $targetPath = (ShortPixel::opt("base_path") ?: __DIR__) . '/' . $relativePath;
                } elseif(ShortPixel::opt("base_source_path") && strlen($relativePath)) {
                    $targetPath .= '/' . $relativePath;
                }

                $target = $targetPath . '/' . ($fileName ? $fileName . ($i > 0 ? "_" . $i : "") : $origFileName);

                ShortPixel::getClient()->download($cmds["lossy"] == 1 ? $item->LossyURL : $item->LosslessURL, $target);
                $item->SavedFile = $target;

                if(isset($item->WebPLossyURL) && $item->WebPLossyURL !== 'NA') { //a WebP image was generated as per the options, download and save it too
                    $webpTarget = $targetWebPFile = dirname($target) . DIRECTORY_SEPARATOR . basename($target, '.' . pathinfo($target, PATHINFO_EXTENSION)) . ".webp";
                    ShortPixel::getClient()->download($cmds["lossy"] == 1 ? $item->WebPLossyURL : $item->WebPLosslessURL, $webpTarget);
                    $item->WebPSavedFile = $webpTarget;
                }

                $succeeded[] = $item;

                $pers = ShortPixel::getPersister();
                if($pers) {
                    $pers->setOptimized($target, array(
                        "compressionType" => $cmds["lossy"] == 1 ? 'lossy' : 'lossless',
                        "keepExif" => isset($cmds['keep_exif']) ? $cmds['keep_exif'] : ShortPixel::opt("keep_exif"),
                        "cmyk2rgb" => isset($cmds['cmyk2rgb']) ? $cmds['cmyk2rgb'] : ShortPixel::opt("cmyk2rgb"),
                        "resize" => isset($cmds['resize_width']) ? $cmds['resize_width'] : ShortPixel::opt("resize_width") ? 1 : 0,
                        "resizeWidth" => isset($cmds['resize_width']) ? $cmds['resize_width'] : ShortPixel::opt("resize_width"),
                        "resizeHeight" => isset($cmds['resize_height']) ? $cmds['resize_height'] : ShortPixel::opt("resize_height"),
                        "percent" => $item->PercentImprovement,
                        "optimizedSize" => $cmds["lossy"] == 1 ? $item->LossySize : $item->LosslessSize,
                        "changeDate" => time(),
                        "message" => null
                    ));
                }


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

    /**
     * finds if an array contains an item, comparing the property given as key
     * @param $item
     * @param $arr
     * @param $key
     * @return the position that was removed, false if not found
     */
    private function findItem($item, $arr, $key) {
        for($j = 0; $j < count($arr); $j++) {
            if($arr[$j]->$key == $item->$key) {
                return $j;
            }
        }
        return false;
    }

    /**
     * removes the item if found in array (with findItem)
     * @param $item
     * @param $arr
     * @param $key
     * @return true if removed, false if not found
     */
    private function removeItem($item, &$arr, $key) {
        $j = $this->findItem($item, $arr, $key);
        if($j !== false) {
            array_splice($arr, $j);
            return true;
        }
        return false;
    }
}
