<?php
/**
 * User: simon
 * Date: 21.12.2017
 * Time: 23:41
 */
namespace ShortPixel;

class Lock {
    const FOLDER_LOCK_FILE = '.sp-lock';

    private $processId, $targetFolder, $clearLock;

    function __construct($processId, $targetFolder, $clearLock = false) {
        $this->processId = $processId;
        $this->targetFolder = $targetFolder;
        $this->clearLock = $clearLock;
    }

    function lock() {
    //check if the folder is not locked by another ShortPixel process
        $lockFile = $this->targetFolder . '/' . self::FOLDER_LOCK_FILE;
        if(file_exists($lockFile) && !$this->clearLock) {
            $lock = file_get_contents($this->targetFolder . '/' . self::FOLDER_LOCK_FILE);
            $lock = explode("=", $lock);
            if(count($lock) == 2 && $lock[0] != $this->processId && $lock[1] > time() - 360) {
                //a lock was placed on the file less than 6 min. ago
                throw new \Exception($this->getLockMsg($lock, $this->targetFolder), -19);
            }
            // else {
            //   unlink($lockFile);
            //}
        }
        if(FALSE === @file_put_contents($lockFile, $this->processId . "=" . time())) {
            throw new ClientException("Could not write lock file $lockFile. Please check rights.", -16);
        }
    }

    function unlock() {
        $lockFile = $this->targetFolder . '/' . self::FOLDER_LOCK_FILE;
        if(file_exists($lockFile)) {
            $lock = file_get_contents($this->targetFolder . '/' . self::FOLDER_LOCK_FILE);
            $lock = explode("=", $lock);
            if($lock[0] == $this->processId) {
                unlink($lockFile);
            }
        }
    }

    function getLockMsg($lock, $folder) {
        return splog("The folder is locked by a different ShortPixel process ({$lock[0]}). Exiting. \n\n\033[31mIf you're SURE no other ShortPixel process is running, you can remove the lock with \n\n >\033[34m rm " . $folder . '/' . self::FOLDER_LOCK_FILE . " \033[0m \n");
    }
}