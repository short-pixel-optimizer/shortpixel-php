<?php
/**
 * Created by: simon
 * Date: 15.11.2016
 * Time: 14:59
 * Usage: cmdShortpixelOptimize.php --apiKey=<your-api-key-here> --folder=/full/path/to/your/images --backupBase=/full/path/to/your/backup/basedir
 *   - add --verbose parameter for more info during optimization
 *   - add --clearLock to clear a lock that's already placed on the folder. BE SURE you know what you're doing, files might get corrupted if the previous script is still running. The locks expire in 6 min. anyway.
 *   - add --quiet for no output - TBD
 *   - the backup path will be used as parent directory to the backup folder (the folder will be fully copied but only if it's not there already)
 */

require_once("shortpixel-php-req.php");
define("FOLDER_INI_NAME", '.sp-options');
define("FOLDER_LOCK_FILE", '.sp-lock');

$processId = uniqid();

$options = getopt("", array("apiKey::", "folder::", "backupBase::", "verbose", "clearLock"));

$apiKey = isset($options["apiKey"]) ? $options["apiKey"] : false;
$folder = isset($options["folder"]) ? realpath($options["folder"]) : false;
$bkBase = isset($options["backupBase"]) ? realpath($options["backupBase"]) : false;
$verbose = isset($options["verbose"]);
$clearLock = isset($options["clearLock"]);

if($bkBase) {
    if(is_dir($bkBase)) {
        $bkFolder = $bkBase . '/' . basename($folder);
        if(is_dir($bkFolder)) {
            echo(splog("The backup is already present, skipping backup."));
        } else {
            echo(splog("Backing-up the folder..."));
            @mkdir($bkFolder);
            if(!is_dir($bkFolder)) {
                die(splog("Backup folder could not be created")."\n");
            }
            try {
                \ShortPixel\recurseCopy($folder, $bkFolder);
            } catch (\ShortPixel\Exception $e) {
                die(splog($e->getMessage()) . "\n");
            }

        }

    } else {
        die(splog("Backup path does not exist ($bkFolder)")."\n");
    }
}

//sanity checks
if(!$apiKey || strlen($apiKey) != 20 || !ctype_alnum($apiKey)) {
    die(splog("Please provide a valid API Key")."\n");
}

if(!is_dir($folder)) {
    die(splog("The folder does not exist.")."\n");
}

if(substr($folder, 0, 2) == "./") {
    $folder = __DIR__ . "/" . substr($folder, 2);
}
if (substr($folder, 0, 1) !== "/") {
    $folder = __DIR__ . "/" . $folder;
}

if(!is_dir($folder)) {
    die(splog("The folder $folder does not exist.")."\n");
}

//check if the folder is not locked by another ShortPixel process
$lockFile = $folder . '/' . FOLDER_LOCK_FILE;
if(file_exists($lockFile) && !$clearLock) {
    $lock = file_get_contents($folder . '/' . FOLDER_LOCK_FILE);
    $lock = explode("=", $lock);
    if(count($lock) == 2 && $lock[1] > time() - 360) {
        //a lock was placed on the file less than 6 min. ago
        die(splog("The $folder folder is locked by another ShortPixel process ({$lock[0]}@" . date("Y-m-d H:i:s", $lock[1]) . ")") . "\n");
    } else {
        unlink($lockFile);
    }
}
file_put_contents($lockFile, $processId . "=" . time());

echo(splog("Starting to optimize folder $folder using API Key $apiKey ..."));

ShortPixel\setKey($apiKey);

\ShortPixel\ShortPixel::setOptions(array("persist_type" => "text"));

if(file_exists($folder . '/' . FOLDER_INI_NAME)) {
    $folderOptions = parse_ini_file($folder . '/' . FOLDER_INI_NAME);
    \ShortPixel\ShortPixel::setOptions($folderOptions);
}

try {
    $imageCount = $failedImageCount = $sameImageCount = 0;
    $tries = 0;

    while($tries < 1000) {
        try {
            $result = \ShortPixel\fromFolder($folder)->wait(300)->toFiles($folder);
        } catch(\ShortPixel\ClientException $ex) {
            if($ex->getCode() == \ShortPixel\ClientException::NO_FILE_FOUND) {
                break;
            }
        }
        $tries++;

        $crtImageCount = 0;
        if(count($result->succeeded) > 0) {
            $crtImageCount += count($result->succeeded);
            $imageCount += $crtImageCount;
        } elseif(count($result->failed)) {
            $crtImageCount += count($result->failed);
            $failedImageCount += count($result->failed);
        } elseif(count($result->same)) {
            $crtImageCount += count($result->same);
            $sameImageCount += count($result->same);
        } elseif(count($result->pending)) {
            $crtImageCount += count($result->pending);
        }
        if($verbose) {
            echo("PASS $tries : " . count($result->succeeded) . " succeeded, " . count($result->pending) . " pending, " . count($result->same) . " same, " . count($result->failed) . " failed\n");
            foreach($result->succeeded as $item) {echo(" - " . $item->SavedFile . " " . $item->Status->Message . "\n");}
            foreach($result->pending as $item) {echo(" - " . $item->SavedFile . " " . $item->Status->Message . "\n");}
            foreach($result->same as $item) {echo(" - " . $item->SavedFile . " " . $item->Status->Message . "\n");}
            foreach($result->failed as $item) {echo(" - " . $item->SavedFile . " " . $item->Status->Message . "\n");}
            echo("\n");
        } else {
            echo(str_pad("", $crtImageCount, "#"));
        }
        //if no files were processed in this pass, the folder is done
        if($crtImageCount == 0) break;
        //check the lock file
        if(file_exists($lockFile)) {
            $lock = file_get_contents($folder . '/' . FOLDER_LOCK_FILE);
            $lock = explode("=", $lock);
            if($lock[0] != $processId && $lock[1] > time() - 360) {
                //some other process aquired the lock, alert and exit.
                die(splog("A different ShortPixel process is optimizing this folder ({$lock[0]}). Exiting."). "\n");
            } else {
                file_put_contents($lockFile, $processId . "=" . time());
            }
        }
    }

    echo(splog("This pass: $imageCount images optimized, $sameImageCount don't need optimization, $failedImageCount failed to optimize."));
    if($crtImageCount > 0) echo(splog("Images still pending, please relaunch the script to continue."));
    echo("\n");
} catch(Exception $e) {
    echo("\n" . splog($e->getMessage() . "( code: " . $e->getCode() . " type: " . get_class($e) . ")") . "\n");
}

function splog($msg) {
    global $processId;
    return "\n$processId@" . date("Y-m-d H:i:s") . "> $msg\n";
}