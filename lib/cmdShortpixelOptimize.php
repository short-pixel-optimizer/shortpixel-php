<?php
/**
 * Created by: simon
 * Date: 15.11.2016
 * Time: 14:59
 * Usage: cmdShortpixelOptimize.php --apiKey=<your-api-key-here> --folder=/full/path/to/your/images --backupBase=/full/path/to/your/backup/basedir
 *   - add --compression=x : 1 for lossy, 2 for glossy and 0 for lossless
 *   - add --webPath=http://yoursites.address/img/folder/ to map the folder to a web URL and have our servers download the images instead of posting them (less heavy on memory for large files)
 *   - add --speeed=x x between 1 and 10 - default is 10 but if you have large images it will eat up a lot of memory when creating the post messages so sometimes you might need to lower it. Not needed when using the webPath mapping.
 *   - add --verbose parameter for more info during optimization
 *   - add --clearLock to clear a lock that's already placed on the folder. BE SURE you know what you're doing, files might get corrupted if the previous script is still running. The locks expire in 6 min. anyway.
 *   - add --quiet for no output - TBD
 *   - the backup path will be used as parent directory to the backup folder (the folder will be fully copied but only if it's not there already)
 * The script will read the .sp-options configuration file and will honour the parameters set there, with the command line parameters having priority
 */

require_once("shortpixel-php-req.php");

define("FOLDER_INI_NAME", '.sp-options');
define("FOLDER_LOCK_FILE", '.sp-lock');

$processId = uniqid();

$options = getopt("", array("apiKey::", "folder::", "webPath::", "compression::", "speed::", "backupBase::", "verbose", "clearLock"));

$apiKey = isset($options["apiKey"]) ? $options["apiKey"] : false;
$folder = isset($options["folder"]) ? realpath($options["folder"]) : false;
$webPath = isset($options["webPath"]) ? filter_var($options["webPath"], FILTER_VALIDATE_URL) : false;
$compression = isset($options["compression"]) ? intval($options["compression"]) : false;
$speed = isset($options["speed"]) ? intval($options["speed"]) : false;
$bkBase = isset($options["backupBase"]) ? realpath($options["backupBase"]) : false;
$verbose = isset($options["verbose"]);
$clearLock = isset($options["clearLock"]);

if($webPath === false && isset($options["webPath"])) {
    die(splog("The Web Path specified is invalid - " . $options["webPath"])."\n");
}

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
        die(getLockMsg($lock, $folder));
    } else {
        unlink($lockFile);
    }
}
file_put_contents($lockFile, $processId . "=" . time());

echo(splog("Starting to optimize folder $folder using API Key $apiKey ..."));

ShortPixel\setKey($apiKey);

//try to get optimization options from the folder .sp-options
$optionsHandler = new \ShortPixel\Settings();
$folderOptions = $optionsHandler->readOptions($folder);
if(!isset($webPath) && $optionsHandler->get("base_url")) {
    $webPath = $optionsHandler->get("base_url");
}

$overrides = array();
if($compression !== false) {
    $overrides['lossy'] = $compression;
}
\ShortPixel\ShortPixel::setOptions(array_merge($folderOptions, $overrides, array("persist_type" => "text")));

if(file_exists($folder . '/' . FOLDER_INI_NAME)) {
    $folderOptions = parse_ini_file($folder . '/' . FOLDER_INI_NAME);
    \ShortPixel\ShortPixel::setOptions($folderOptions);
}

try {
    $imageCount = $failedImageCount = $sameImageCount = 0;
    $tries = 0;
    $folderOptimized = false;
    $info = \ShortPixel\folderInfo($folder);

    if($info->status == 'error') {
        die(splog("Error: " . $info->message . " (Code: " . $info->code . ")"));
    }

    echo(splog("Folder has " . $info->total . " files, " . $info->succeeded . " optimized, " . $info->pending . " pending, " . $info->same . " don't need optimization, " . $info->failed . " failed."));

    if($info->status == "success") {
        echo(splog("Congratulations, the folder is optimized."));
    }
    else {
        while ($tries < 1000) {
            try {
                if ($webPath) {
                    $result = \ShortPixel\fromWebFolder($folder, $webPath)->wait(300)->toFiles($folder);
                } elseif ($speed) {
                    $result = \ShortPixel\fromFolder($folder, $speed)->wait(300)->toFiles($folder);
                } else {
                    $result = \ShortPixel\fromFolder($folder)->wait(300)->toFiles($folder);
                }
            } catch (\ShortPixel\ClientException $ex) {
                if ($ex->getCode() == \ShortPixel\ClientException::NO_FILE_FOUND) {
                    break;
                } else {
                    echo(splog("ClientException: " . $ex->getMessage() . " (CODE: " . $ex->getCode() . ")"));
                }
            }
            $tries++;

            $crtImageCount = 0;
            if (count($result->succeeded) > 0) {
                $crtImageCount += count($result->succeeded);
                $imageCount += $crtImageCount;
            } elseif (count($result->failed)) {
                $crtImageCount += count($result->failed);
                $failedImageCount += count($result->failed);
            } elseif (count($result->same)) {
                $crtImageCount += count($result->same);
                $sameImageCount += count($result->same);
            } elseif (count($result->pending)) {
                $crtImageCount += count($result->pending);
            }
            if ($verbose) {
                echo("PASS $tries : " . count($result->succeeded) . " succeeded, " . count($result->pending) . " pending, " . count($result->same) . " don't need optimization, " . count($result->failed) . " failed\n");
                foreach ($result->succeeded as $item) {
                    echo(" - " . $item->SavedFile . " " . $item->Status->Message . "\n");
                }
                foreach ($result->pending as $item) {
                    echo(" - " . $item->SavedFile . " " . $item->Status->Message . "\n");
                }
                foreach ($result->same as $item) {
                    echo(" - " . $item->SavedFile . " " . $item->Status->Message . "\n");
                }
                foreach ($result->failed as $item) {
                    echo(" - " . $item->SavedFile . " " . $item->Status->Message . "\n");
                }
                echo("\n");
            } else {
                echo(str_pad("", $crtImageCount, "#"));
            }
            //if no files were processed in this pass, the folder is done
            if ($crtImageCount == 0) {
                $folderOptimized = ($item->Status->Code == 2);
                break;
            }
            //check the lock file
            if (file_exists($lockFile)) {
                $lock = file_get_contents($folder . '/' . FOLDER_LOCK_FILE);
                $lock = explode("=", $lock);
                if ($lock[0] != $processId && $lock[1] > time() - 360) {
                    //some other process aquired the lock, alert and exit.
                    die(getLockMsg($lock, $folder));
                } else {
                    file_put_contents($lockFile, $processId . "=" . time());
                }
            }
        }

        echo(splog("This pass: $imageCount images optimized, $sameImageCount don't need optimization, $failedImageCount failed to optimize." . ($folderOptimized ? " Congratulations, the folder is optimized.":"")));
        if ($crtImageCount > 0) echo(splog("Images still pending, please relaunch the script to continue."));
        echo("\n");
    }
} catch(Exception $e) {
    echo("\n" . splog($e->getMessage() . "( code: " . $e->getCode() . " type: " . get_class($e) . ")") . "\n");
}

//cleanup the lock file
if(file_exists($lockFile)) {
    $lock = file_get_contents($folder . '/' . FOLDER_LOCK_FILE);
    $lock = explode("=", $lock);
    if($lock[0] == $processId) {
        unlink($lockFile);
    }
}

function splog($msg) {
    global $processId;
    return "\n$processId@" . date("Y-m-d H:i:s") . "> $msg\n";
}

function getLockMsg($lock, $folder) {
    return splog("The folder is locked by a different ShortPixel process ({$lock[0]}). Exiting. \n\n\033[31mIf you're SURE no other ShortPixel process is running, you can remove the lock with \n\n >\033[34m rm " . $folder . '/' . FOLDER_LOCK_FILE . " \033[0m \n");
}