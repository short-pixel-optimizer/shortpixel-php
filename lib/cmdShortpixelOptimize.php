<?php
/**
 * Created by: simon
 * Date: 15.11.2016
 * Time: 14:59
 * Usage: cmdShortpixelOptimize.php --apiKey=<your-api-key-here> --folder=/full/path/to/your/images
 *   - add --compression=x : 1 for lossy, 2 for glossy and 0 for lossless
 *   - add --backupBase=/full/path/to/your/backup/basedir
 *   - add --targetFolder to specify a different destination for the optimized files.
 *   - add --webPath=http://yoursites.address/img/folder/ to map the folder to a web URL and have our servers download the images instead of posting them (less heavy on memory for large files)
 *   - add --speeed=x x between 1 and 10 - default is 10 but if you have large images it will eat up a lot of memory when creating the post messages so sometimes you might need to lower it. Not needed when using the webPath mapping.
 *   - add --verbose parameter for more info during optimization
 *   - add --clearLock to clear a lock that's already placed on the folder. BE SURE you know what you're doing, files might get corrupted if the previous script is still running. The locks expire in 6 min. anyway.
 *   - add --quiet for no output - TBD
 *   - the backup path will be used as parent directory to the backup folder which, if the backup path is outside the optimized folder, will be the basename of the folder, otherwise will be ShortPixelBackup
 * The script will read the .sp-options configuration file and will honour the parameters set there, with the command line parameters having priority
 */

require_once("shortpixel-php-req.php");

$processId = uniqid();

$options = getopt("", array("apiKey::", "folder::", "targetFolder::", "webPath::", "compression::", "speed::", "backupBase::", "verbose", "clearLock"));

$apiKey = isset($options["apiKey"]) ? $options["apiKey"] : false;
$folder = isset($options["folder"]) ? verifyFolder($options["folder"]) : false;
$targetFolder = isset($options["targetFolder"]) ? verifyFolder($options["targetFolder"], true) : $folder;
$webPath = isset($options["webPath"]) ? filter_var($options["webPath"], FILTER_VALIDATE_URL) : false;
$compression = isset($options["compression"]) ? intval($options["compression"]) : false;
$speed = isset($options["speed"]) ? intval($options["speed"]) : false;
$bkBase = isset($options["backupBase"]) ? verifyFolder($options["backupBase"]) : false;
$verbose = isset($options["verbose"]);
$clearLock = isset($options["clearLock"]);

if($webPath === false && isset($options["webPath"])) {
    die(splog("The Web Path specified is invalid - " . $options["webPath"])."\n");
}

$bkFolder = $bkFolderRel = false;
if($bkBase) {
    if(is_dir($bkBase)) {
        $bkFolder = $bkBase . '/' . (strpos(trailingslashit($bkBase), trailingslashit($folder)) === 0 ? 'ShortPixelBackups' : basename($folder));
        $bkFolderRel = \ShortPixel\Settings::pathToRelative($bkFolder, $targetFolder);
    } else {
        die(splog("Backup path does not exist ($bkFolder)")."\n");
    }
}

//sanity checks
if(!$apiKey || strlen($apiKey) != 20 || !ctype_alnum($apiKey)) {
    die(splog("Please provide a valid API Key")."\n");
}

if(!$folder || strlen($folder) == 0) {
    die(splog("Please specify a folder to optimize")."\n");
}

if($targetFolder != $folder) {
    if(strpos($targetFolder, trailingslashit($folder)) === 0) {
        die(splog("Target folder cannot be a subfolder of the source folder. ( $targetFolder $folder)"));
    } elseif (strpos($folder, trailingslashit($targetFolder)) === 0) {
        die(splog("Target folder cannot be a parent folder of the source folder."));
    } else {
        @mkdir($targetFolder, 0777, true);
    }
}

try {
    //check if the folder is not locked by another ShortPixel process
    $splock = new \ShortPixel\Lock($processId, $targetFolder, $clearLock);
    $splock->lock();

    echo(splog("Starting to optimize folder $folder using API Key $apiKey ..."));

    ShortPixel\setKey($apiKey);

    //try to get optimization options from the folder .sp-options
    $optionsHandler = new \ShortPixel\Settings();
    $folderOptions = $optionsHandler->readOptions($targetFolder);
    if(!isset($webPath) && $optionsHandler->get("base_url")) {
        $webPath = $optionsHandler->get("base_url");
    }

    $overrides = array();
    if($compression !== false) {
        $overrides['lossy'] = $compression;
    }
    if($bkFolderRel) {
        $overrides['backup_path'] = $bkFolderRel;
    }
    \ShortPixel\ShortPixel::setOptions(array_merge($folderOptions, $overrides, array("persist_type" => "text")));

    $imageCount = $failedImageCount = $sameImageCount = 0;
    $tries = 0;
    $folderOptimized = false;
    $targetFolderParam = ($targetFolder !== $folder ? $targetFolder : false);

    $info = \ShortPixel\folderInfo($folder, true, false, array(), $targetFolderParam);

    if($info->status == 'error') {
        $splock->unlock();
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
                    $result = \ShortPixel\fromWebFolder($folder, $webPath, array(), $targetFolderParam)->wait(300)->toFiles($targetFolder);
                } else {
                    $speed = ($speed ? $speed : \ShortPixel\ShortPixel::MAX_ALLOWED_FILES_PER_CALL);
                    $result = \ShortPixel\fromFolder($folder, $speed, array(), $targetFolderParam)->wait(300)->toFiles($targetFolder);
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
                $folderOptimized = (!isset($item) || $item->Status->Code == 2);
                break;
            }
            //check & refresh the lock file
            $splock->lock();
        }

        echo(splog("This pass: $imageCount images optimized, $sameImageCount don't need optimization, $failedImageCount failed to optimize." . ($folderOptimized ? " Congratulations, the folder is optimized.":"")));
        if ($crtImageCount > 0) echo(splog("Images still pending, please relaunch the script to continue."));
        echo("\n");
    }
} catch(\Exception $e) {
    echo("\n" . splog($e->getMessage() . "( code: " . $e->getCode() . " type: " . get_class($e) . " )") . "\n");
}

//cleanup the lock file
$splock->unlock();

function splog($msg) {
    global $processId;
    return "\n$processId@" . date("Y-m-d H:i:s") . "> $msg\n";
}

function verifyFolder($folder, $create = false)
{
    $folder = rtrim($folder, '/');
    $suffix = '';
    if($create) {
        $suffix = '/' . basename($folder);
        $folder = dirname($folder);
    }
    $folder = (realpath($folder) ? realpath($folder) : $folder);
    if (!is_dir($folder)) {
        if (substr($folder, 0, 2) == "./") {
            $folder = str_replace(DIRECTORY_SEPARATOR, '/', getcwd()) . "/" . substr($folder, 2);
        }
        if (!is_dir($folder)) {
            if ((strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && preg_match('/^[a-zA-Z]:\//', $folder) === 0) //it's Windows and no drive letter X - relative path?
                || (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN' && substr($folder, 0, 1) !== '/')
            ) { //linux and no / - relative path?
                $folder = str_replace(DIRECTORY_SEPARATOR, '/', getcwd()) . "/" . $folder;
            }
        }
        if (!is_dir($folder)) {
            die(splog("The folder $folder does not exist.") . "\n");
        }
    }
    return $folder . $suffix;
}

function trailingslashit($path) {
    return rtrim($path, '/') . '/';
}