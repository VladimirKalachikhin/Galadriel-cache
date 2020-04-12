<?php
/* Options and paths 
*/
// Common
// paths
$tileCacheDir = 'tiles'; 	// tile storage directory, in filesystem. Link is good idea.
$mapSourcesDir = 'mapsources'; 	// map sources directory, in filesystem.
// options
$ttl = 86400*365*5; //default cache timeout in seconds.  86400 sec == 1 day. After this, tile trying to reload from source. If 0 - never trying to reload.
$ext = 'png'; 	// default tile image type/extension
$minZoom = 0; 	// default min zoom
$maxZoom = 19; 	// default max zoom
$maxTry = 3; 	// number of tryes to download tile from source
$tryTimeout = 3; 	// pause between try to download tile from source, sec
$getTimeout = 10; 	// timeout tile source response, sec
$noInternetTimeout = 20; 	// no try the source this time if no internet connection found, sec

//$globalProxy = 'tcp://127.0.0.1:8123'; 	// Global Proxy. May be tor via Polipo, for example. If not defined - not used.
/*
// crc32 of junk tiles '00000000' zero length file and '0940c426' empty png are not trash!
$globalTrash = array(
);
*/ 
// Tile loader.
// paths - required if lazy download use
$jobsDir = 'loaderJobs'; 	// loader jobs directory, in filesystem. Check the user rights to this directory. Need to read/write for loaderSched.php
$jobsInWorkDir = "$jobsDir/inWork"; 	// current jobs directory.  Check the user rights to this directory. Need to read/write for loaderSched.php and loader.php
// options
$maxLoaderRuns = 12; 	// simultaneously working loader tasks. Set at least 2 to avoid blocking download by bad source.
$loaderMaxZoom = 16; 	// loader download tiles to this zoom only, not to map or default $maxZoom
$aheadLoadStartZoom = 14; // start of the ahead loading from this zoom 
//$phpCLIexec = '/usr/bin/php-cli'; 	// php-cli executed name on your OS
$phpCLIexec = '/usr/bin/php'; 	// php-cli executed name on your OS
?>
