<?php

namespace ShortPixel;

/**
 * Class Result - handles the result of the optimization (saves to file or returns a buffer, etc)
 * @package ShortPixel
 */
class Result {
    protected $commander, $ctx;

    public function __construct($commander, $context) {
        $this->commander = $commander;
        $this->ctx = $context;
    }

    /**
     * returns the metadata provided by the optimizer
     * @return mixed
     */
    public function ctx() {
        return $this->ctx;
    }

    public function toBuffer() {
        return $this->ctx;
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
        $i = 0;
        $succeeded = $pending = $failed = $same = array();

        $cmds = $this->commander->getCommands();

        while(true) {
            $items = $this->ctx->body;
            if(!is_array($items) || count($items) == 0) {
//                return (object)array( 'status' => array('code' => 2, 'message' => 'Folder completely optimized'));
            }
            //check API key errors
            if(isset($items->Status->Code) && $items->Status->Code < 0) {
                throw new AccountException($items->Status->Message, $items->Status->Code);
            }
            // No API level error
            $retry = false;
            foreach($items as $item) {

                $targetPath = $path;

                if($this->ctx->fileMappings && count($this->ctx->fileMappings)) { // it was optimized from a local file, fileMappings contains the mappings from the local files to the internal ShortPixel URLs
                    $originalPath = isset($this->ctx->fileMappings[$item->OriginalURL]) ? $this->ctx->fileMappings[$item->OriginalURL] : false;
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

                $item->SavedFile = $target;

                //TODO: that one is a hack until the API waiting bug is fixed. Afterwards, just throw an exception
                if($item->Status->Code == 2 && $item->LossySize == 0  && $item->LoselessSize == 0 ) {
                    $item->Status->Code = 1;
                }

                if($item->Status->Code == 1) {
                    $found = $this->findItem($item, $pending, "OriginalURL");
                    if(!$found) {
                        $pending[] = $item;
                        $this->persist($item, $cmds, 'pending');
                    }
                    continue;
                }
                elseif ($item->Status->Code != 2) {
                    $this->removeItem($item, $pending, "OriginalURL");
                    if($item->Status->Code == -102 || $item->Status->Code == -106) {
                        // -102 is expired, means we need to resend the image through post
                        // -106 is file was not downloaded due to access restrictions - if these are uploaded files it looks like a bug in the API
                        //      TODO find and fix
                        if($this->ctx->fileMappings[$item->OriginalURL]) {
                            unset($this->ctx->fileMappings[$item->OriginalURL]);
                        }
                        $item->OriginalURL = false;
                    }

                    $status = $this->persist($item, $cmds, 'error');
                    if($status == 'pending') {
                        $retry = true;
                    } else {
                        $failed[] = $item;
                    }
                    continue;
                }
                elseif($item->PercentImprovement == 0) {
                    $same[] = $item;
                    $this->removeItem($item, $pending, "OriginalURL");
                    $this->persist($item, $cmds);
                    continue;
                }

                if(!is_dir($targetPath)) {
                    throw new ClientException("The destination path cannot be found.");
                }

                //Now that's an optimized image indeed
                try {
                    ShortPixel::getClient()->download($cmds["lossy"] == 1 ? $item->LossyURL : $item->LosslessURL, $target);
                    $item->SavedFile = $target;
                } catch(ClientException $e) {
                    $this->persist($item, $cmds, 'error');
                    continue;
                }

                if(isset($item->WebPLossyURL) && $item->WebPLossyURL !== 'NA') { //a WebP image was generated as per the options, download and save it too
                    $webpTarget = $targetWebPFile = dirname($target) . DIRECTORY_SEPARATOR . basename($target, '.' . pathinfo($target, PATHINFO_EXTENSION)) . ".webp";
                    try {
                        ShortPixel::getClient()->download($cmds["lossy"] == 1 ? $item->WebPLossyURL : $item->WebPLosslessURL, $webpTarget);
                        $item->WebPSavedFile = $webpTarget;
                    } catch(ClientException $e) {
                        $this->persist($item, $cmds, 'error');
                        continue;
                    }
                }

                $succeeded[] = $item;

                $this->persist($item, $cmds);

                //remove from pending
                $this->removeItem($item, $pending, "OriginalURL"); //TODO check if fromURL and if not, use file path
                //tell the commander that the item is done so it won't be relaunched
                $this->commander->isDone($item);
                $i++;
            }

            //For the pending items relaunch, or if any item that needs to be retried from file (-102 or -106)
            if($retry || count($pending)) {
                $this->ctx = $this->commander->relaunch((object)array("body" => $pending, "headers" => $this->ctx->headers, "fileMappings" => $this->ctx->fileMappings));
            } else {
                break;
            }
            if($this->ctx == false) { //time's up
                break;
            }
        }

        return (object) array(
            'status' => array('code' => 1, 'message' => 'pending'),
            'succeeded' => $succeeded,
            'pending' => $pending,
            'failed' => $failed,
            'same' => $same
        );
    }

    private function persist($item, $cmds, $status = 'success') {
        $pers = ShortPixel::getPersister();
        if($pers) {
            $optParams = $this->optimizationParams($item, $cmds);
            if($status == 'pending') {
                $optParams['message'] = $item->OriginalURL;
                return $pers->setPending($item->SavedFile, $optParams);
            } elseif ($status == 'error') {
                $optParams['message'] = $item->Status->Message;
                return $pers->setFailed($item->SavedFile, $optParams);
            } else {
                return $pers->setOptimized($item->SavedFile, $optParams);
            }
        }
    }

    private function optimizationParams($item, $cmds) {
        return array(
            "compressionType" => $cmds["lossy"] == 1 ? 'lossy' : 'lossless',
            "keepExif" => isset($cmds['keep_exif']) ? $cmds['keep_exif'] : ShortPixel::opt("keep_exif"),
            "cmyk2rgb" => isset($cmds['cmyk2rgb']) ? $cmds['cmyk2rgb'] : ShortPixel::opt("cmyk2rgb"),
            "resize" => isset($cmds['resize_width']) ? $cmds['resize_width'] : ShortPixel::opt("resize_width") ? 1 : 0,
            "resizeWidth" => isset($cmds['resize_width']) ? $cmds['resize_width'] : ShortPixel::opt("resize_width"),
            "resizeHeight" => isset($cmds['resize_height']) ? $cmds['resize_height'] : ShortPixel::opt("resize_height"),
            "percent" => isset($item->PercentImprovement) ? $item->PercentImprovement : 0,
            "optimizedSize" => $item->Status->Code == 2 ? ($cmds["lossy"] == 1 ? $item->LossySize : $item->LosslessSize) : 0,
            "changeDate" => time(),
            "message" => null
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
