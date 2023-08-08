<?php
/**
 * Created by: simon
 * Date: 15.11.2016
 * Time: 14:59
 * Usage: cmdShortpixelOptimize.php --apiKey=<your-api-key-here> --folder=/full/path/to/your/images
 *   - add --compression=x : 1 for lossy, 2 for glossy and 0 for lossless
 *   - add --resize=800x600/[type] where type can be 1 for outer resize (default) and 3 for inner resize
 *   - add --backupBase=/full/path/to/your/backup/basedir
 *   - add --targetFolder to specify a different destination for the optimized files.
 *   - add --webPath=http://yoursites.address/img/folder/ to map the folder to a web URL and have our servers download the images instead of posting them (less heavy on memory for large files)
 *   - add --keepExif to keep the EXIF data
 *   - add --speeed=x x between 1 and 10 - default is 10 but if you have large images it will eat up a lot of memory when creating the post messages so sometimes you might need to lower it. Not needed when using the webPath mapping.
 *   - add --verbose parameter for more info during optimization
 *   - add --clearLock to clear a lock that's already placed on the folder. BE SURE you know what you're doing, files might get corrupted if the previous script is still running. The locks expire in 6 min. anyway.
 *   - add --logLevel for different areas of logging - bitwise flags: 4 for metadata handling, 8 for server comm (add them up to log more areas)
 *   - add --cacheTime=[seconds] to cache the folders which have no new image to process. Useful for large folders for which checking at each pass is slowing down the optimization.
 *   - add --quiet for no output - TBD
 *   - the backup path will be used as parent directory to the backup folder which, if the backup path is outside the optimized folder, will be the basename of the folder, otherwise will be ShortPixelBackup
 * The script will read the .sp-options configuration file and will honour the parameters set there, but the command line parameters take priority
 */

ini_set('memory_limit','256M');
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

require_once("shortpixel-php-req.php");

use ShortPixel\Lock;
use ShortPixel\ShortPixel;
use \ShortPixel\SPLog;
use ShortPixel\SPTools;

$argvIsNull = ($argv === NULL);

$processId = uniqid("CLI");

$options = getopt("", array("apiKey::", "file::", "targetFile::", "webPath::", "compression::", "resize::", "createWebP", "createAVIF", "keepExif", "speed::", "backupBase::", "verbose", "clearLock", "retrySkipped",
                            "exclude::", "recurseDepth::", "logLevel::", "cacheTime::"));

$verbose = isset($options["verbose"]) ? (isset($options["logLevel"]) ? $options["logLevel"] : 0) | SPLog::PRODUCER_CMD_VERBOSE : 0;
$logger = SPLog::Init($processId, $verbose | SPLog::PRODUCER_CMD, SPLog::TARGET_CONSOLE, false, ($verbose ? SPLog::FLAG_MEMORY : SPLog::FLAG_NONE));

$logger->log(SPLog::PRODUCER_CMD_VERBOSE, "ShortPixel CLI version " . ShortPixel::VERSION);
$logger->log(SPLog::PRODUCER_CMD_VERBOSE, "ShortPixel Logging VERBOSE" . ($verbose & SPLog::PRODUCER_PERSISTER ? ", PERSISTER" : "") . ($verbose & SPLog::PRODUCER_CLIENT ? ", CLIENT" : ""));
if($argvIsNull) {
    $logger->bye(SPLog::PRODUCER_CMD, 'THE $argv global is not set, please check the register_argc_argv setting in php.ini');
}

$apiKey = isset($options["apiKey"]) ? $options["apiKey"] : false;

$file = isset($options["file"]) ? $options["file"] : false;
$targetFile = isset($options["targetFile"]) ? verifyFolder($options["targetFile"], true) : $file;
$totalFiles = 1; //this is for future extensions when we will alow more than one file to be processed at once. Just in case.

$compression = isset($options["compression"]) ? intval($options["compression"]) : false;
$resizeRaw =  isset($options["resize"]) ? $options["resize"] : false;
$createWebP = isset($options["createWebP"]);
$createAVIF = isset($options["createAVIF"]);
$keepExif = isset($options["keepExif"]);
$speed = isset($options["speed"]) ? intval($options["speed"]) : false;
$bkBase = isset($options["backupBase"]) ? verifyFolder($options["backupBase"]) : false;
//$clearLock = isset($options["clearLock"]);
$retrySkipped = isset($options["retrySkipped"]);
//$exclude = isset($options["exclude"]) ? explode(",", $options["exclude"]) : array();
//$recurseDepth = isset($options["recurseDepth"]) && is_numeric($options["recurseDepth"]) && $options["recurseDepth"] >= 0 ? $options["recurseDepth"] : PHP_INT_MAX;
$cacheTime = isset($options["cacheTime"]) && is_numeric($options["cacheTime"]) && $options["cacheTime"] >= 0 ? $options["cacheTime"] : 0;

if(!function_exists('curl_version')) {
    $logger->bye(SPLog::PRODUCER_CMD, "cURL is not enabled. ShortPixel needs Curl to send the images to optimization and retrieve the results. Please enable cURL and retry.");
} elseif($verbose) {
    $ver = curl_version();
    $logger->log(SPLog::PRODUCER_CMD_VERBOSE, "cURL version: " . $ver['version']);
}

$bkFolder = $bkFolderRel = false;
if($bkBase) {
    if(is_dir($bkBase)) {
        $bkBase = SPTools::trailingslashit($bkBase);
        $folder = dirname($file);
        $bkFolder = $bkBase . (strpos($bkBase, SPTools::trailingslashit($folder)) === 0 ? 'ShortPixelBackups' : basename($folder) . (strpos($bkBase, SPTools::trailingslashit(dirname($folder))) === 0 ? "_SP_BKP" : "" ));
        $bkFolderRel = \ShortPixel\Settings::pathToRelative($bkFolder, dirname($targetFile));
    } else {
        $logger->bye(SPLog::PRODUCER_CMD, "Backup path does not exist ($bkFolder)");
    }
}

//sanity checks
if(!$apiKey || strlen($apiKey) != 20 || !ctype_alnum($apiKey)) {
    $logger->bye(SPLog::PRODUCER_CMD, "Please provide a valid API Key");
}

if(!$file || strlen($file) == 0) {
    $logger->bye(SPLog::PRODUCER_CMD, "Please specify a file to optimize");
}

if(!file_exists($file)) {
    $logger->bye(SPLog::PRODUCER_CMD, "File not found: $file");
}

try {
    $logger->log(SPLog::PRODUCER_CMD, "ShortPixel CLI " . ShortPixel::VERSION . " starting to optimize file $file using API Key $apiKey ...");

    \ShortPixel\setKey($apiKey);

    //try to get optimization options from the folder .sp-options
    $optionsHandler = new \ShortPixel\Settings();
    $fileOptions = [];
    if((!isset($webPath) || !$webPath) && isset($fileOptions["base_url"]) && strlen($fileOptions["base_url"])) {
        $webPath = $fileOptions["base_url"];
        $logger->log(SPLog::PRODUCER_CMD_VERBOSE, "Using Web Path from settings: $webPath");
    }

    // ********************* OPTIMIZATION OPTIONS FROM COMMAND LINE TAKE PRECEDENCE *********************
    $overrides = array();
    if($compression !== false) {
        $overrides['lossy'] = $compression;
    }
    if($resizeRaw !== false) {
        $tmp = explode("/", $resizeRaw);
        $resizeType = (count($tmp) == 2) && ($tmp[1] == 3) ? 3 : 1;
        $sizes = explode("x", $tmp[0]);
        if(count($sizes) == 2 and is_numeric($sizes[0]) && is_numeric($sizes[1])) {
            $overrides['resize'] = $resizeType;
            $overrides['resize_width'] = $sizes[0];
            $overrides['resize_height'] = $sizes[1];
            $logger->log(SPLog::PRODUCER_CMD_VERBOSE, "Resize type: " . ($resizeType == 3 ? "inner" : "outer") . ", width: {$overrides['resize_width']}, height: {$overrides['resize_height']}");
        } else {
            $logger->bye(SPLog::PRODUCER_CMD, "Malformed parameter --resize, should be --resize=[width]x[height]/[type] type being 1 for outer and 3 for inner");
        }
    }
    if($createWebP !== false) {
        $overrides['convertto'] = '+webp';
    }
    if($createAVIF !== false) {
        $overrides['convertto'] = (strlen($overrides['convertto']) ? $overrides['convertto'] . '|' : '') . '+avif';
    }
    if($keepExif !== false) {
        $overrides['keep_exif'] = 1;
    }

    if($bkFolderRel) {
        $overrides['backup_path'] = $bkFolderRel;
    }
    $optimizationOptions = $overrides;
    $logger->log(SPLog::PRODUCER_CMD_VERBOSE, "Using OPTIONS: ", $optimizationOptions);
    ShortPixel::setOptions($optimizationOptions);

    $imageCount = $failedImageCount = $sameImageCount = 0;
    $tries = 0;
    $consecutiveExceptions = 0;
    $folderOptimized = false;

    while ($tries < 100) {
        $crtImageCount = 0;
        $tempus = time();
        try {
            $logger->log(SPLog::PRODUCER_CMD, "\n\n\nPASS $tries ....");
            $result = \ShortPixel\fromFile($file)->wait(300)->toFiles(dirname($targetFile), basename($targetFile));
        } catch (\ShortPixel\ClientException $ex) {
            if ($ex->getCode() == \ShortPixel\ClientException::NO_FILE_FOUND || $ex->getCode() == 2) {
                break;
            } else {
                $logger->log(SPLog::PRODUCER_CMD, "ClientException: " . $ex->getMessage() . " (CODE: " . $ex->getCode() . ")");
                $tries++;
                if(++$consecutiveExceptions > ShortPixel::MAX_RETRIES) {
                    $logger->log(SPLog::PRODUCER_CMD, "Too many exceptions. Exiting.");
                    break;
                }
                continue;
            }
        }
        catch (\ShortPixel\ServerException $ex) {
            if($ex->getCode() == 502) {
                $logger->log(SPLog::PRODUCER_CMD, "ServerException: " . $ex->getMessage() . " (CODE: " . $ex->getCode() . ")");
                if(++$consecutiveExceptions > ShortPixel::MAX_RETRIES) {
                    $logger->log(SPLog::PRODUCER_CMD, "Too many exceptions. Exiting.");
                    break;
                }
            } else {
                throw $ex;
            }
        }
        $tries++;
        $consecutiveExceptions = 0;

        if (count($result->succeeded) > 0) {
            $crtImageCount += count($result->succeeded);
            $imageCount += $crtImageCount;
            if(count($result->succeeded) == $totalFiles) {
                break;
            }
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
            $msg = "\n" . date("Y-m-d H:i:s") . " PASS $tries : " . count($result->succeeded) . " succeeded, " . count($result->pending) . " pending, " . count($result->same) . " don't need optimization, " . count($result->failed) . " failed\n";
            foreach ($result->succeeded as $item) {
                $msg .= " - " . $item->SavedFile . " " . $item->Status->Message . " ("
                    . ($item->PercentImprovement > 0 ? "Reduced by " . $item->PercentImprovement . "%" : "") . ($item->PercentImprovement < 5 ? " - Bonus processing" : ""). ")\n";
            }
            foreach ($result->pending as $item) {
                $msg .= " - " . $item->SavedFile . " " . $item->Status->Message . "\n";
            }
            foreach ($result->same as $item) {
                $msg .= " - " . $item->SavedFile . " " . $item->Status->Message . " (Bonus processing)\n";
            }
            foreach ($result->failed as $item) {
                $msg .= " - " . $item->SavedFile . " " . $item->Status->Message . "\n";
            }
            $logger->logRaw($msg . "\n");
        } else {
            $logger->logRaw(str_pad("", $crtImageCount, "#"));
        }
    }

    $logger->log(SPLog::PRODUCER_CMD, "This pass: $imageCount images optimized, $sameImageCount don't need optimization, $failedImageCount failed to optimize." . ($folderOptimized ? " Congratulations, the folder is optimized.":""));
    echo("\n");
} catch(\Exception $e) {
    //record progress only if it's not a lock exception.
    if($e->getCode() != -19) {
    }
    $logger->log(SPLog::PRODUCER_CMD, "\n" . $e->getMessage() . "( code: " . $e->getCode() . " type: " . get_class($e) . " )" . "\n");
}
