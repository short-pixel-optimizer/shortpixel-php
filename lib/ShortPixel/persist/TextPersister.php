<?php
/**
 * User: simon
 * Date: 19.08.2016
 * Time: 18:05
 */
namespace ShortPixel\persist;

use ShortPixel\ClientException;
use \ShortPixel\Persister;
use \ShortPixel\ShortPixel;
use \ShortPixel\Client;

/**
 * Class TextPersister - save the optimization information in .shortpixel files in the current folder of the images
 * @package ShortPixel\persist
 */
class TextPersister implements Persister {

    private $fp;
    private $options;
    private STATIC $ALLOWED_STATUSES = array('pending', 'success', 'skip', 'deleted');
    private STATIC $ALLOWED_TYPES = array('I', 'D');

    function __construct($options)
    {
        $this->options = $options;
        $this->fp = array();
    }

    function isOptimized($path)
    {
        if(!file_exists($path)) {
            return false;
        }
        $fp = $this->openMetaFile(dirname($path), 'read');
        if(!$fp) {
            return false;
        }

        while (($line = fgets($fp)) !== FALSE) {
            $data = $this->parse($line);
            if($data->file === \ShortPixel\MB_basename($path) && $data->status == 'success' ) {
                return true;
            }
        }
        fclose($fp);

        return false;
    }

    function info($path, $recurse = true, $fileList = false, $exclude = array(), $persistPath = false) {
        if($persistPath === false) {
            $persistPath = $path;
        }
        if(is_dir($path)) {
            try {
                $toClose = $this->openMetaFileIfNeeded($persistPath);
                $fp = $this->getMetaFile($persistPath);
                $dataArr = $this->readMetaFile($fp);
            } catch(ClientException $e) {
                if(is_dir($persistPath) && file_exists($persistPath . '/' . ShortPixel::opt("persist_name"))) {
                    return (object)array('status' => 'error', 'message' => $e->getMessage(), 'code' => $e->getCode());
                }
                $dataArr = array(); //there's no problem if the metadata file is missing and cannot be created, for the info call
            }

            $info = (object)array('status' => 'pending', 'total' => 0, 'succeeded' => 0, 'pending' => 0, 'same' => 0, 'failed' => 0, 'todo' => null);
            $files = scandir($path);
            $ignore = array_merge(array('.','..','ShortPixelBackups'), $exclude);

            foreach($files as $file) {
                $filePath = $path . '/' . $file;
                $targetFilePath = $persistPath . '/' . $file;
                if (in_array($file, $ignore)
                    || (!ShortPixel::isProcessable($file) && !is_dir($filePath))
                    || isset($dataArr[$file]) && $dataArr[$file]->status == 'deleted'
                ) {
                    continue;
                }
                if (is_dir($filePath)) {
                    if(!$recurse) continue;
                    $subInfo = $this->info($filePath, $recurse, $fileList, $exclude, $targetFilePath);
                    if($subInfo->status == 'error') {
                        return $subInfo;
                    }
                    $info->total += $subInfo->total;
                    $info->succeeded += $subInfo->succeeded;
                    $info->pending += $subInfo->pending;
                    $info->same += $subInfo->same;
                    $info->failed += $subInfo->failed;
                }
                else {
                    $info->total++;
                    if(!isset($dataArr[$file]) || $dataArr[$file]->status == 'pending') {
                        $info->pending++;
                    }
                    elseif(($dataArr[$file]->status == 'success' && filesize($targetFilePath) != $dataArr[$file]->optimizedSize)
                        || ($dataArr[$file]->status == 'skip' &&  $dataArr[$file]->retries <= ShortPixel::MAX_RETRIES)) {
                        //file changed since last optimized, mark it as pending
                        $dataArr[$file]->status = 'pending';
                        $this->updateMeta($dataArr[$file], $fp);
                        $info->pending++;
                    }
                    elseif($dataArr[$file]->status == 'success') {
                        if($dataArr[$file]->percent > 0) {
                            $info->succeeded++;
                        } else {
                            $info->same++;
                        }
                    }
                    elseif($dataArr[$file]->status == 'skip'){
                        $info->failed++;
                    }
                }
                if($fileList) $info->fileList = $dataArr;
            }

            if($toClose) {
                $this->closeMetaFile($persistPath);
            }

            if($info->pending == 0) {
                $info->status = 'success';
            }
            $info->todo = $this->getTodo($path, 1, $exclude, $persistPath);
        }
        else {
            $persistFolder = dirname($persistPath);
            $meta = $toClose = false;
            try {
                $toClose = $this->openMetaFileIfNeeded($persistFolder);
                $meta = $this->findMeta($persistPath);
            } catch(ClientException $e) {
                if(is_dir($persistFolder) && file_exists($persistFolder . '/' . ShortPixel::opt("persist_name"))) {
                    return (object)array('status' => 'error', 'message' => $e->getMessage(), 'code' => $e->getCode());
                }
            }

            if(!$meta) {
                $info = array('status' => 'pending');
            } else {
                $info = array('status' => $meta->getStatus());
            }

            if($toClose) {
                $this->closeLastOpenMetaFile();
            }
        }
        return (object)$info;
    }

    function getTodo($path, $count, $exclude = array(), $persistPath = false)
    {
        if(!file_exists($path) || !is_dir($path)) {
            return array();
        }
        if(!$persistPath) {$persistPath = $path;}

        $toClose = $this->openMetaFileIfNeeded($persistPath);
        $fp = $this->getMetaFile($persistPath);

        $files = scandir($path);
        $dataArr = $this->readMetaFile($fp);

        $results = array();
        $pendingURLs = array();
        $ignore = array_values(array_merge($exclude, array('.','..','ShortPixelBackups')));
        $remain = $count;
        $filesWaiting = 0;
        foreach($files as $file) {
            $filePath = $path . '/' . $file;
            $targetPath = $persistPath . '/' . $file;
            if(in_array($file, $ignore)
               || (!ShortPixel::isProcessable($file) && !is_dir($filePath))
               || isset($dataArr[$file]) && $dataArr[$file]->status == 'deleted'
               || isset($dataArr[$file])
                  && (   $dataArr[$file]->status == 'success' && filesize($targetPath) == $dataArr[$file]->optimizedSize
                      || $dataArr[$file]->status == 'skip') ) {
                continue;
            }
            //if retried too many times recently {
            if(isset($dataArr[$file]) && $dataArr[$file]->status == 'pending') {
                $retries = $dataArr[$file]->retries;
                //over 3 retries wait a minute for each, over 5 retries 2 min. for each, over 10 retries 5 min for each, over 10 retries, 10 min. for each.
                $delta = max(0, $retries - 2) * 60 + max(0, $retries - 5) * 60 + max(0, $retries - 10) * 180 + max(0, $retries - 20) * 450;
                if($dataArr[$file]->changeDate > time() - $delta) {
                    $filesWaiting++;
                    continue;
                }
            }
            if(is_dir($filePath)) {
                if(!isset($dataArr[$file])) {
                    $dataArr[$file] = $this->newMeta($targetPath);
                    $dataArr[$file]->filePos = $this->appendMeta($dataArr[$file], $fp);
                }
                $resultsSubfolder =  $this->getTodo($filePath, $count, $exclude, $targetPath);
                if(count($resultsSubfolder->files)) {
                    if($toClose) { $this->closeMetaFile($persistPath); }
                    return $resultsSubfolder;
                }  elseif($dataArr[$file]->status != 'success' && !$resultsSubfolder->filesWaiting) {//otherwise ignore the folder but mark it as succeeded;
                    $dataArr[$file]->status = 'success';
                    $this->updateMeta($dataArr[$file], $fp);
                }
            } else {
                if(isset($dataArr[$file])) {
                    if(    ($dataArr[$file]->status == 'success')
                        && (filesize($targetPath) !== $dataArr[$file]->optimizedSize)) {
                        // a file with the wrong size
                        $dataArr[$file]->status = 'pending';
                        $dataArr[$file]->optimizedSize = 0;
                        $dataArr[$file]->changeDate = time();
                        $this->updateMeta($dataArr[$file], $fp);
                        if(time() - strtotime($dataArr[$file]->changeDate) < 1800) { //need to refresh the file processing on the server
                            if($toClose) { $this->closeMetaFile($persistPath); }
                            return (object)array('files' => array($filePath), 'filesPending' => array(), 'refresh' => true);
                        }
                    }
                    elseif($dataArr[$file]->status == 'error') {
                        $dataArr[$file]->retries += 1;
                        if($dataArr[$file]->retries >= ShortPixel::MAX_RETRIES) {
                            $dataArr[$file]->status = 'skip';
                        }
                        $this->updateMeta($dataArr[$file], $fp);
                        if($dataArr[$file]->retries >= ShortPixel::MAX_RETRIES) {
                            continue;
                        }
                    }
                    elseif($dataArr[$file]->status == 'pending' && strpos($dataArr[$file]->message, str_replace("https://", "http://",\ShortPixel\Client::API_URL())) === 0) {
                        //the file is already uploaded and the call should  be made with the existent URL on the optimization server
                        $apiURL = $dataArr[$file]->message;
                        $pendingURLs[$apiURL] = $filePath;
                    }
                }
                elseif(!isset($dataArr[$file])) {
                    $this->appendMeta($this->newMeta($targetPath), $fp);
                }

                $results[] = $filePath;
                $remain--;

                if($remain <= 0) {
                    if($toClose) { $this->closeMetaFile($persistPath); }
                    return (object)array('files' => $results, 'filesPending' => $pendingURLs, 'refresh' => false);
                }
            }
        }

        if($toClose) { $this->closeMetaFile($persistPath); }

/*        if(count($results) == 0) {//folder is empty or completely optimized, if it's a subfolder of another optimized folder, mark it as such in the parent .shortpixel file
            if(file_exists(dirname($persistPath) . '/' . ShortPixel::opt("persist_name"))) {
                $this->setOptimized($persistPath);
            }
        }
*/
        return (object)array('files' => $results, 'filesPending' => $pendingURLs, 'filesWaiting' => $filesWaiting);
    }

    function getNextTodo($path, $count)
    {
        // TODO: Implement getNextTodo() method.
    }

    function doneGet()
    {
        // TODO: Implement doneGet() method.
    }

    function getOptimizationData($path)
    {
        // TODO: Implement getOptimizationData() method.
    }

    function setPending($path, $optData) {
        return $this->setStatus($path, $optData, 'pending');
    }

    function setOptimized($path, $optData = array()) {
        return $this->setStatus($path, $optData, 'success');
    }

    function setFailed($path, $optData) {
        return $this->setStatus($path, $optData, 'error');
    }

    function setSkipped($path, $optData) {
        return $this->setStatus($path, $optData, 'skip');
    }

    protected function setStatus($path, $optData, $status) {
        $toClose = $this->openMetaFileIfNeeded(dirname($path));
        $fp = $this->getMetaFile(dirname($path));

        $meta = $this->findMeta($path);
        if($meta) {
            $meta->retries++;
            $meta->changeDate = time();
        } else {
            $meta = $this->newMeta($path);
        }
        $meta->status = $status == 'error' ? $meta->retries > ShortPixel::MAX_RETRIES ? 'skip' : 'pending' : $status;
        $metaArr = array_merge((array)$meta, $optData);
        if(isset($meta->filePos)) {
            $this->updateMeta((object)$metaArr, $fp, false);
        } else {
            $this->appendMeta((object)$metaArr, $fp, false);
        }

        if($toClose) {
            $this->closeMetaFile(dirname($path));
        }
        return $meta->status;
    }

    protected function openMetaFileIfNeeded($path) {
        if(isset($this->fp[$path])) {
            fseek($this->fp[$path], 0);
            return false;
        }
        $fp = $this->openMetaFile($path);
        if(!$fp) {
            throw new \Exception("Could not open meta file in folder " . $path . ". Please check permissions.", -14);
        }
        $this->fp[$path] = $fp;
        return true;
    }

    protected function getMetaFile($path) {
        return $this->fp[$path];
    }

    protected function closeMetaFile($path) {
        if(isset($this->fp[$path])) {
            $fp = $this->fp[$path];
            unset($this->fp[$path]);
            fclose($fp);
        }
    }

    protected function readMetaFile($fp) {
        $dataArr = array(); $err = false;
        for ($i = 0; ($line = fgets($fp)) !== FALSE; $i++) {
            $data = $this->parse($line);
            if($data) {
                $data->filePos = $i;
                $dataArr[$data->file] = $data;
            } else {
                $err = true;
            }
        }
        if($err) { //at least one error found in the .shortpixel file, rewrite it
            fseek($fp, 0);
            ftruncate($fp, 0);
            foreach($dataArr as $meta) {
                fwrite($fp, $this->assemble($meta));
                fwrite($fp, $line . "\r\n");
            }
        }
        return $dataArr;
    }

    protected function openMetaFile($path, $type = 'update') {
        $metaFile = $path . '/' . ShortPixel::opt("persist_name");
        if(!is_dir($path) && !@mkdir($path, 0777, true)) { //create the folder
            throw new ClientException("The metadata destination path cannot be found. Please check rights", -17);
        }
        $fp = @fopen($metaFile, $type == 'update' ? 'c+' : 'r');
        if(!$fp) {
            throw new ClientException("Could not open persistence file $metaFile. Please check rights.", -16);
        }
        return $fp;
    }

    protected function findMeta($path) {
        $fp = $this->openMetaFile(dirname($path));
        fseek($fp, 0);
        for ($i = 0; ($line = fgets($fp)) !== FALSE; $i++) {
            $data = $this->parse($line);
            if($data->file === \ShortPixel\MB_basename($path)) {
                $data->filePos = $i;
                return $data;
            }
        }
        return false;
    }

    /**
     * @param $meta
     * @param bool|false $returnPointer - set this to true if need to have the file pointer back afterwards, such as when updating while reading the file line by line
     */
    protected function updateMeta($meta, $fp, $returnPointer = false) {
        if($returnPointer) {
            $crt = ftell($fp);
        }
        fseek($fp, self::LINE_LENGTH * $meta->filePos); // +2 for the \r\n
        fwrite($fp, $this->assemble($meta));
        fflush($fp);
        if($returnPointer) {
            fseek($fp, $crt);
        }
    }

    /**
     * @param $meta
     * @param bool|false $returnPointer - set this to true if need to have the file pointer back afterwards, such as when updating while reading the file line by line
     */
    protected function appendMeta($meta, $fp, $returnPointer = false) {
        if($returnPointer) {
            $crt = ftell($fp);
        }
        $fstat = fstat($fp);
        fseek($fp, 0, SEEK_END);
        $line = $this->assemble($meta);
        //$ob = $this->parse($line);
        fwrite($fp, $line . "\r\n");
        fflush($fp);
        if($returnPointer) {
            fseek($fp, $crt);
        }
        return $fstat['size'] / self::LINE_LENGTH;
    }

    protected function newMeta($file) {
        return (object) array(
            "type" => is_dir($file) ? 'D' : 'I',
            "status" => 'pending',
            "retries" => 0,
            "compressionType" => $this->options['lossy'] == 1 ? 'lossy' : ($this->options['lossy'] == 2 ? 'glossy' : 'lossless'),
            "keepExif" => $this->options['keep_exif'],
            "cmyk2rgb" => $this->options['cmyk2rgb'],
            "resize" => $this->options['resize_width'] ? 1 : 0,
            "resizeWidth" => 0 + $this->options['resize_width'],
            "resizeHeight" => 0 + $this->options['resize_height'],
            "convertto" => $this->options['convertto'],
            "percent" => null,
            "optimizedSize" => null,
            "changeDate" => time(),
            "file" => \ShortPixel\MB_basename($file),
            "message" => '');
    }

    const LINE_LENGTH = 465; //including the \r\n at the end

    protected function parse($line) {
        if(strlen(rtrim($line, "\r\n")) != (self::LINE_LENGTH - 2)) return false;
        $ret = (object) array(
            "type" => trim(substr($line, 0, 2)),
            "status" => trim(substr($line, 2, 11)),
            "retries" => trim(substr($line, 13, 2)),
            "compressionType" => trim(substr($line, 15, 9)),
            "keepExif" => trim(substr($line, 24, 2)),
            "cmyk2rgb" => trim(substr($line, 26, 2)),
            "resize" => trim(substr($line, 28, 2)),
            "resizeWidth" => trim(substr($line, 30, 6)),
            "resizeHeight" => trim(substr($line, 36, 6)),
            "convertto" => trim(substr($line, 42, 10)),
            "percent" => 0.0 + trim(substr($line, 52, 6)),
            "optimizedSize" => 0 + trim(substr($line, 58, 9)),
            "changeDate" => strtotime(trim(substr($line, 67, 20))),
            "file" => trim(substr($line, 87, 256)),
            "message" => trim(substr($line, 343, 120)),
        );
        if(!in_array($ret->status, self::$ALLOWED_STATUSES) || !$ret->changeDate) {
            return false;
        }
        return $ret;
    }

    protected function assemble($data) {
        return sprintf("%s%s%s%s%s%s%s%s%s%s%s%s%s%s%s",
            str_pad($data->type, 2),
            str_pad($data->status, 11),
            str_pad($data->retries % 100, 2), // for folders, retries can be > 100 so do a sanity check here - we're not actually interested in folder retries
            str_pad($data->compressionType, 9),
            str_pad($data->keepExif, 2),
            str_pad($data->cmyk2rgb, 2),
            str_pad($data->resize, 2),
            str_pad(substr($data->resizeWidth, 0 , 5), 6),
            str_pad(substr($data->resizeHeight, 0 , 5), 6),
            str_pad($data->convertto, 10),
            str_pad(substr(number_format($data->percent, 2, ".",""),0 , 5), 6),
            str_pad(substr(number_format($data->optimizedSize, 0, ".", ""),0 , 8), 9),
            str_pad(date("Y-m-d H:i:s", $data->changeDate), 20),
            str_pad(substr($data->file, 0, 255), 256),
            str_pad(substr($data->message, 0, 119), 120)
        );
    }
}