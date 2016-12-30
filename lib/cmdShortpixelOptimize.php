<?php
/**
 * Created by: simon
 * Date: 15.11.2016
 * Time: 14:59
 * Usage: cmdShortpixelOptimize.php --apiKey <your-api-key-here> --folder /full/path/to/your/images --backupFolder /full/path/to/your/backup
 *   - currently backup is not implemented
 */

require_once("../lib/shortpixel-php-req.php");

$options = getopt("", array("apiKey::", "folder::", "backupFolder::", "verbose"));
$apiKey = isset($options["apiKey"]) ? $options["apiKey"] : false;
$folder = isset($options["folder"]) ? $options["folder"] : false;
$bkFolder = isset($options["backupFolder"]) ? $options["backupFolder"] : false;
$verbose = isset($options["verbose"]);

if($bkFolder) {
    die("\nBackup is not yet implemented. Please make a copy of the folder before proceeding.\n\n");
}

//sanity checks
if(!$apiKey || strlen($apiKey) != 20 || !ctype_alnum($apiKey)) {
    die("\nPlease provide a valid API Key\n\n");
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

echo("\nStarting to optimize folder $folder using API Key $apiKey ...\n\n");

ShortPixel\setKey($apiKey);

\ShortPixel\ShortPixel::setOptions(array("persist_type" => "text"));

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
    echo("\n\n $imageCount images optimized, $sameImageCount checked and were already optimized, $failedImageCount failed to optimize.");
    if($crtImageCount > 0) echo("\nImages still pending, please relaunch the script to continue.");
    echo("\n\n");
} catch(Exception $e) {
    echo("\n\n" . $e->getMessage() . "( code: " . $e->getCode() . " type: " . get_class($e) . ")");
}

