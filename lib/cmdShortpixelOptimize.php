<?php
/**
 * Created by: simon
 * Date: 15.11.2016
 * Time: 14:59
 * Usage: cmdShortpixelOptimize.php --apiKey=<your-api-key-here> --folder=/full/path/to/your/images --backupBase=/full/path/to/your/backup/basedir
 *   - add --verbose parameter for more info during optimization
 *   - add --quiet for no output - TBD
 *   - the backup path will be used as parent directory to the backup folder (the folder will be fully copied but only if it's not there already)
 */

require_once("shortpixel-php-req.php");
define("FOLDER_INI_NAME", '.sp-options');

$options = getopt("", array("apiKey::", "folder::", "backupBase::", "verbose"));

$apiKey = isset($options["apiKey"]) ? $options["apiKey"] : false;
$folder = isset($options["folder"]) ? realpath($options["folder"]) : false;
$bkBase = isset($options["backupBase"]) ? realpath($options["backupBase"]) : false;
$verbose = isset($options["verbose"]);

if($bkBase) {
    if(is_dir($bkBase)) {
        $bkFolder = $bkBase . '/' . basename($folder);
        if(is_dir($bkFolder)) {
            echo("\nThe backup is already present, skipping backup.\n");
        } else {
            echo("\nBacking-up the folder...\n");
            @mkdir($bkFolder);
            if(!is_dir($bkFolder)) {
                die("\nBackup folder could not be created.\n\n");
            }
            try {
                \ShortPixel\recurseCopy($folder, $bkFolder);
            } catch (\ShortPixel\Exception $e) {
                die("\n" . $e->getMessage() . "\n\n");
            }

        }

    } else {
        die("\nBackup path does not exist ($bkFolder)\n\n");
    }
}

//sanity checks
if(!$apiKey || strlen($apiKey) != 20 || !ctype_alnum($apiKey)) {
    die("\nPlease provide a valid API Key\n\n");
}

if(!is_dir($folder)) {
    die("\nThe folder does not exist.\n\n");
}

if(substr($folder, 0, 2) == "./") {
    $folder = __DIR__ . "/" . substr($folder, 2);
}
if (substr($folder, 0, 1) !== "/") {
    $folder = __DIR__ . "/" . $folder;
}

if(!is_dir($folder)) {
    die("\nThe folder $folder does not exist\n\n");
}

echo("\nStarting to optimize folder $folder using API Key $apiKey ...\n");

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
        if($crtImageCount == 0) break;
    }
    echo("\nThis pass: $imageCount images optimized, $sameImageCount don't need optimization, $failedImageCount failed to optimize.");
    if($crtImageCount > 0) echo("\nImages still pending, please relaunch the script to continue.");
    echo("\n\n");
} catch(Exception $e) {
    echo("\n\n" . $e->getMessage() . "( code: " . $e->getCode() . " type: " . get_class($e) . ")\n\n");
}

