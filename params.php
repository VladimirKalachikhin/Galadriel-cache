<?php
/* 
Пути и параметры
Options and paths 
*/
// Общие параметры Common
// Пути paths
//	Хранилище тайлов, каталог в файловой системе. Обычно -- ссылка на специальный раздел.
$tileCacheDir = 'tiles'; 	// tile storage directory, in filesystem. Link is good idea.
//	Каталог описаний источников карт, в файловой системе
$mapSourcesDir = 'mapsources'; 	// map sources directory, in filesystem.
// Параметры options
// 	Число попыток скачать тайл, прежде чем он будет считаться отсутствующим
$maxTry = 3; 	// number of tryes to download tile from source
// 	Пауза между попытками скачать тайл, сек.
$tryTimeout = 3; 	// pause between try to download tile from source, sec
// 	Время ожидания ответа источника тайла, сек.
$getTimeout = 10; 	// timeout tile source response, sec
// 	Пауза между запросами, если обнаружено отсутствие связи
$noInternetTimeout = 20; 	// no try the source this time if no internet connection found, sec

// Глобальный прокси -- для всех источников
//$globalProxy = 'tcp://127.0.0.1:8118'; 	// Global Proxy. May be tor via Polipo, for example. If not defined - not used.

// Глобальный список мусорных тайлов. Тайлы с такой суммой crc32 заменяются пустыми, но будет предпринята попытка их получить снова через $noTileReTry

// crc32 of junk tiles '00000000' zero length file and '0940c426' empty png are not trash!
$globalTrash = array(
'f821973a',	// белый тайл. Борьба с русскими?
'a5da4659',	// белый тайл
'af8e80fa'	// прозрачный тайл.
);

// Загрузчик Tile loader.
// Пути paths
// 	Путь к каталогу заданий планировщика, в файловой системе
$jobsDir = 'loaderJobs'; 	// loader jobs directory, in filesystem. Check the user rights to this directory. Need to read/write for loaderSched.php
// 	Путь к каталогу заданий загрузчика, в файловой системе 
$jobsInWorkDir = "$jobsDir/inWork"; 	// current jobs directory.  Check the user rights to this directory. Need to read/write for loaderSched.php and loader.php
// Параметры options
// 	Количество одновременно запускаемых загрузчиков
$maxLoaderRuns = 15; 	// simultaneously working loader tasks. Set at least 2 to avoid blocking download by bad source.
// 	Загрузчик скачивает тайлы до масштаба включительно
$loaderMaxZoom = 16; 	// loader download tiles to this zoom only, not to map or default $maxZoom
// 	Предварительная загрузка тайлов большего масштаба запускается при просмотре тайла указанного масштаба
$aheadLoadStartZoom = 14; // start of the ahead loading from this zoom 

// Определение покрытия Tiles cover detection
$tileCacheServerPath = '/tileproxy'; 	// web path to GaladrielCache

// Ситемные параметры System
// Вызов php из командной строки
//$phpCLIexec = 'php-cli'; 	// php-cli executed name on your OS
$phpCLIexec = 'php'; 	// php-cli executed name on your OS
?>
