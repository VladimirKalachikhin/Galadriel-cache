<?php
// Человеко-читаемые наименования карт, массив вида 'ru'=>'','en'=>''
$humanName = array();	// Human readable maps names
// время, через которое тайл считается протухшим, сек.
//$ttl = 86400*30*12*3; // cache timeout in seconds.  86400 sec = 1 day. After this, tile trying to reload from source. If 0 - never trying to reload.
$ttl = 0;
// Время, через которое протухает тайл нулевого размера, обозначающий нерешаемые проблемы скачивания.
$noTileReTry=86400*7; 	// empty tile timeout, good if < $ttl sec. 
// показывать протухшие тайлы. Сперва тайл будет показан, а потом начнётся скачивание свежего. Если TRUE - старый тайл не будет показан, будет показан свежий, если удастся его скачать.
$freshOnly = FALSE; 	// Normally, outdated tiles first showing, then - downloaded. If TRUE - first loaded.
// Расширение файла изображения
$ext = 'png'; 	// tile image type/extension
// Если тайл содержит изображение не того типа, на который указывает расширение - будет использован этот тип.
$ContentType = 'image/jpeg'; 	// if content type differ then file extension
$content_encoding = '';
// Минимальный масштаб карты
$minZoom = 1; 	// map min zoom
// Максимальный масштаб карты
$maxZoom = 19; 	// map max zoom
// Проекция карты, Web Mercator. Может быть также 3395. Ничего другого быть не может, потому что ничего больше Leaflet не умеет.
$EPSG=3857; 	// map projection, for map viewer (such as web client) or get tile algorithm. Not used for cache/proxy. Must be 3857 (default) or 3395 due to the Leaflet limitations. 
// что делать, если Forbidden: skip, wait. По умолчанию - wait, источник будет забанен на время $noInternetTimeout (params.php). 'skip' - эквивалентно отсутствию файла, будет сохранён и показан пустой тайл, скачивание продолжится.
$on403 = 'wait'; 	// Forbidden action: skip, wait. By default - wait, source will be banned on $noInternetTimeout (params.php) time. 'Skip' eq 'File not found'.
//  /НЕТ РЕАЛИЗОВАНО!!! $on404 = 'skip'; 	// что делать, если Not Found: skip, wait, done. По умолчанию - 'skip' - эквивалентно отсутствию файла, будет сохранён и показан пустой тайл, скачивание продолжится. 'wait', источник будет забанен на время $noInternetTimeout (params.php). 'done' - неудачное скачивание, будет показано 404, ничего не сохранено, тайл снова поставлен в очередь на скачивание.
// Границы карты, если не весь мир. В формате {"leftTop":{"lat":lat,"lng":lng},"rightBottom":{"lat":lat,"lng":lng}}
$bounds = null;	// Map bounds, if if not the whole world. In format: {"leftTop":{"lat":lat,"lng":lng},"rightBottom":{"lat":lat,"lng":lng}}
// crc32 хеши тайлов, которые не надо сохранять: логотипы, пустые тайлы, тайлы с дурацкими надписями
$trash = array(); 	// array of crc32 of bad, junk or other unwanted tiles or other files. This tiles not be stored to cache.
// Для контроля источника: номер правильного тайла и его CRC32b хеш, например [15,20337,10160,'d205b575']
$trueTile=array();	// to source check; tile number and CRC32b hash
// Массив для передачи произвольных параметров в функцию getURL()
$getURLparams=array();	// getURL() custom parameters array
// Хуки Hooks
// строка с функцией формирования запроса на получение тайлов из источника.
$functionGetURL = ''; 	// string to wrap getURL() function. It's gets url of tile in internet. This is necessery to avoid function redefinitions error.
// строка с функцией формирования запроса на получение тайлов из хранилища.
$functionGetTileFile = ''; 	// string to wrap getTile() function. It's gets tile from storage. This is necessery to avoid function redefinitions error.
// строка с функцией обработки изображения тайла перед отправкой клиенту.
$functionPrepareTileImg = ''; 	// string to wrap prepareTile() function. It's transform image before sending. This is necessery to avoid function redefinitions error.
// Для взаимодействия с GaladrielMap
$data = array();	// to influence from map source file to GaladrielMap
?>
