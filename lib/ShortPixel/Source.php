<?php

namespace ShortPixel;

class Source {
    private $urls;

    /**
     * @param $path - the file path on the local drive
     * @param $basePath - common base path used to determine the subfolders that will be created in the destination
     * @return Commander - the class that handles the optimization commands
     * @throws ClientException
     */
    public function fromFiles($paths, $basePath = null, $pending = null, $refresh = false) {
        if(!is_array($paths)) {
            $paths = array($paths);
        }
        if(count($paths) > ShortPixel::MAX_ALLOWED_FILES_PER_CALL) {
            throw new ClientException("Maximum 10 local images allowed per call.");
        }
        $files = array();
        foreach($paths as $path) {
            if (!file_exists($path)) throw new ClientException("File not found: " . $path);
            $files[] = $path;
        }
        $data       = array(
            "plugin_version" => ShortPixel::LIBRARY_CODE . " " . ShortPixel::VERSION,
            "key" =>  ShortPixel::getKey(),
            "files" => $files
        );
        if($refresh) { //don't put it in the array above because false will overwrite the commands refresh. If only set when true, will just force a refresh when needed.
            $data["refresh"] = 1;
        }
        if($pending && count($pending)) {
            $data["pendingURLs"] = $pending;
        }

        return new Commander($data, $this);
    }

    /**
     * returns the optimization counters of the folder and subfolders
     * @param $path
     * @return (object)array('status', 'total', 'succeeded', 'pending', 'same', 'failed')
     * @throws PersistException
     */
    public function folderInfo($path, $recurse = true, $fileList = false, $exclude = array(), $persistPath = false){
        $persister = ShortPixel::getPersister($path);
        if(!$persister) {
            throw new PersistException("Persist is not enabled in options, needed for fetching folder info");
        }
        return $persister->info($path, $recurse, $fileList, $exclude, $persistPath);
    }

    /**
     * processes a chunk of MAX_ALLOWED files from the folder, based on the persisted information about which images are processed and which not. This information is offered by the Persister object.
     * @param $path - the folder path on the local drive
     * @return Commander - the class that handles the optimization commands
     * @throws ClientException
     */
    public function fromFolder($path, $maxFiles = 0, $exclude = array(), $persistFolder = false) {
        if($maxFiles == 0) {
            $maxFiles = ShortPixel::MAX_ALLOWED_FILES_PER_CALL;
        }
        //sanitize
        $maxFiles = max(1, min(ShortPixel::MAX_ALLOWED_FILES_PER_CALL, intval($maxFiles)));

        $persister = ShortPixel::getPersister($path);
        if(!$persister) {
            throw new PersistException("Persist is not enabled in options, needed for folder optimization");
        }
        $paths = $persister->getTodo($path, $maxFiles, $exclude, $persistFolder);
        if($paths) {
            ShortPixel::setOptions(array("base_source_path" => $path));
            return $this->fromFiles($paths->files, null, $paths->filesPending);
        }
        throw new ClientException("Couldn't find any processable file at given path.", 2);
    }

    /**
     * processes a chunk of MAX_ALLOWED URLs from a folder that is accessible via web at the $webPath location,
     * based on the persisted information about which images are processed and which not. This information is offered by the Persister object.
     * @param $path - the folder path on the local drive
     * @param $webPath - the web URL of the folder
     * @return Commander - the class that handles the optimization commands
     * @throws ClientException
     */
    public function fromWebFolder($path, $webPath, $exclude = array(), $persistFolder = false) {

        $path = rtrim($path, '/');
        $webPath = rtrim($webPath, '/');
        $paths = ShortPixel::getPersister()->getTodo($path, ShortPixel::MAX_ALLOWED_FILES_PER_CALL, $exclude, $persistFolder);
        $repl = (object)array("path" => $path, "web" => $webPath);
        if(count($paths->files)) {
            $items = array_merge($paths->files, array_values($paths->filesPending)); //not impossible to have filesPending - for example optimized partially without webPath then added it
            array_walk(
                $items,
                function(&$item, $key, $repl){
                    $item = implode('/', array_map('rawurlencode', explode('/', str_replace($repl->path, '', $item))));
                    $item = $repl->web . $item;
                }, $repl);
            ShortPixel::setOptions(array("base_url" => $webPath, "base_source_path" => $path));

            return $this->fromUrls($items);
        }
        //folder is either empty, either fully optimized, in both cases it's optimized :)
        throw new ClientException("Couldn't find any processable file at given path.", 2);
    }

    public function fromBuffer($string) {
        throw new ClientException("fromBuffer not implemented");
    }

    /**
     * @param $urls - the array of urls to be optimized
     * @return Commander - the class that handles the optimization commands
     * @throws ClientException
     */
    public function fromUrls($urls) {
        if(!is_array($urls)) {
            $urls = array($urls);
        }
        if(count($urls) > 100) {
            throw new ClientException("Maximum 100 images allowed per call.");
        }

        $this->urls = array_map ('utf8_encode',  $urls);
        $data       = array(
            "plugin_version" => ShortPixel::LIBRARY_CODE . " " . ShortPixel::VERSION,
            "key" =>  ShortPixel::getKey(),
            "urllist" => $this->urls,
            // don't add it if false, otherwise will overwrite the refresh command //"refresh" => false
        );

        return new Commander($data, $this);
    }
}
