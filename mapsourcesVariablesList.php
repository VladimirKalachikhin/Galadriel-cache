<?php
// Все эти переменные глобальны!
// Также, они передаются клиентскому приложению по запросу cacheControl.php?getMapInfo=map_source_file_name
// All of this variables is global!
// They are also transmitted to the client application at the request  cacheControl.php?getMapInfo=map_source_file_name

// Человеко-читаемые наименования карт, массив вида 'ru'=>'','en'=>''
// Human readable maps names
$humanName = array();	

// время, через которое тайл считается протухшим, сек.
// cache timeout in seconds.  86400 sec = 1 day. After this, tile trying to reload from source.
// If 0 - never trying to reload.
//$ttl = 86400*30*12*3; 
$ttl = 0;

// Время, через которое протухает тайл нулевого размера, обозначающий нерешаемые проблемы скачивания.
// empty tile timeout, good if < $ttl sec. 
$noTileReTry=86400*7; 	

// показывать протухшие тайлы. Сперва тайл будет показан, а потом начнётся скачивание свежего. Если TRUE - старый тайл не будет показан, будет показан свежий, если удастся его скачать.
// Normally, outdated tiles first showing, then - downloaded. If TRUE - first loaded.
$freshOnly = FALSE; 	

// Расширение файла тайла. Указывать обязательно!
// tile image file extension. Required!
$ext = 'png'; 	

// mime type тайла. Указывать очень желательно!
// mime type of the tile. Highly recommended!
$ContentType = "image/$ext"; 	
$content_encoding = '';
// Если карта векторная - и клиенту и серверу могут понадобится ресурсы: стиль, шрифты и спрайты
// If the map is vector, both the client and the server may need resources: style, fonts, and sprites.
$vectorTileStyleFile = '';
$vectorTileStyleURL = '';
$vectorTileFonsDir = '';
$vectorTileFonsURL = '';
$vectorTileSpritesDir = '';
$vectorTileSpritesURL = '';

// Минимальный масштаб карты
// map min zoom
$minZoom = 1; 	
// Максимальный масштаб карты
// map max zoom
$maxZoom = 21; 	
// Проекция карты, Web Mercator. Может быть также 3395. Ничего другого быть не может, потому что ничего больше Leaflet не умеет.
// map projection, for map viewer (such as web client) or get tile algorithm.
// Not used for cache/proxy. Must be 3857 (default) or 3395 due to the Leaflet limitations. 
$EPSG=3857; 	

// что делать, если Not Found или Forbidden: skip, wait, done.
// 'skip' - эквивалентно отсутствию файла, будет сохранён пустой тайл
// 'wait', источник будет забанен на время $noInternetTimeout (params.php).
// 'done' - неудачное скачивание, ничего не сохранено
// Not Found or Forbidden action: skip, wait, done. 
// 'skip' eq 'File not found', will be saved empty tile
// 'wait', source will be banned on $noInternetTimeout (params.php) time.
// 'done' - failed download, nothing saved
$on403 = 'wait'; 	
$on404 = 'skip'; 	

// Границы карты, если не весь мир. array() - карта на весь мир. Иначе - границы неизвестны
// В формате {"leftTop":{"lat":lat,"lng":lng},"rightBottom":{"lat":lat,"lng":lng}}
// Map bounds, if if not the whole world.
// In format: {"leftTop":{"lat":lat,"lng":lng},"rightBottom":{"lat":lat,"lng":lng}}
//$bounds = array();	// no bounds. If false - bonds are unknown

// crc32 хеши тайлов, которые не надо сохранять: логотипы, пустые тайлы,
// тайлы с дурацкими надписями
// array of crc32 of bad, junk or other unwanted tiles or other files.
// This tiles not be stored to cache.
$trash = array(); 	

// Для контроля источника: номер правильного тайла и его CRC32b хеш,
// to source check; tile number and CRC32b hash
// array($zoom,$x,$y,$hash,layer)
// Например Example: [15,20337,10160,'d205b575']
$trueTile=array();	


// Массив для взаимодействия с клиентом, например, GaladrielMap
// Array to influence from map source file to client app, GaladrielMap for example.
$clientata = array();	
// GaladrielMap понимает:
// The GaladrielMap accepted:
// $data['javascriptOpen'] 	- код javascript, который будет сперва eval в глобальном контексте, а потом последняя определённая в нём функция будет выполнена при открытии карты.
//							Чтобы это произошло, код должен содержать функцию, которую вернёт eval,
//							т.е., содержать конструкцию (function(mapLayer){})
//							Функции во время выполнения будет передан мультислой карты.
//							- javascript code that will first be eval'ed in the global context, and then executed when opening the map.
//							For this to happen, the code must contain a function that eval returns,
//							that is, contain the construct (function(mapLayer){})
//							The map multilayer will be passed to the function during execution.
// $data['javascriptClose']	- код javascript, который будет сперва eval в глобальном контексте, а потом последняя определённая в нём функция будет выполнена при закрытии карты.
//							Так же как и предыдущий, должен вернуть функцию.
//							Функции во время выполнения будет передан мультислой карты.
//							- javascript code that will first be eval'ed in the global context, and then executed when closing the map.
//							Just like the previous, it should return a function.
//							The map multilayer will be passed to the function during execution.
// $data['noAutoScaled'] bool - запрещает показ карты масштаба меньше minZoom и масштаба больше maxZoom
//							путём графического уменьшения или увеличения карты масштаба minZoom и maxZoom соответственно.
//							Нормально карта уменьшается до масштаба 3 и увеличивается до масштаба maxZoom + 2.
//							- prohibits displaying a map of a scale smaller than minZoom and a scale larger than maxZoom
//							by graphically zooming out or enlarging the minZoom and maxZoom scale maps, respectively.
//							Normally, the map is reduced to a scale of 3 and increased to a scale of maxZoom + 2.

// Массив для передачи произвольных параметров команде $mapTiles url.
// Массив кодируется как json, и должен клиентской программой присоединятся к url
// заменой шаблона {options} в url.
//
// An array for passing custom parameters to the $mapTiles url command.
// The array is encoded as json, and will be attached to the url by client app by replacing the {options} template in the url.
//
// Умолчальная команда tiles.php?z={z}&x={x}&y={y}&r={map}&options={options}
// понимает следующие опции:
// The default command undestands the following options:
// $requestOptions['prepareTileImg'] = bool - 	применять ли функцию $prepareTileImgBeforeReturn к возвращаемому изображению.
// 												whether to use the function $prepareTileImgBeforeReturn to then output image
// $requestOptions['r'] = mapName - заменять в команде $mapTiles url шаблон {map} на указанную строку или на имя карты.
//									whether to replace the {map} template in the $mapTiles url command to the specified string or to the map name.
// $requestOptions['layer'] = (int) - будет запускаться фоновое скачивание слоя, а не всей карты, как mapName_N.Zoom
//									Will be started the background download of the layer, not the entire map, as mapName_N.Zoom
//
$requestOptions = array();

// Массив для передачи произвольных параметров в функцию getURL()
// getURL() custom parameters array
$getURLoptions=array();	

// Указание клиентскому приложению, к каким url обращаться для получения тайлов.
// Описывает многослойную или составную карту
// Строка url, если карта имеет только один слой
// или массив строк url, если карта многослойная
// или массив вида:
// array(
//		array("mapName"=>(string)$mapName,"mapParm"=>array("key"=>$value))
//	)
// где mapName - имя карты в GaladrielCache, а
// mapParm - (некоторые) переменные из описания карты
// , если карта составная.
//
// Порядок элементов массива соответствует порядку расположения слоёв от нижнего к верхнему.
//
// По умолчанию - строка с url вызова tiles.php в соответствии с текущей конфигурацией веб-сервера.
// Можно указать просто исходный url карты, тем самым отключив всякое кеширование.
// Требуется поддержка со стороны клиента, когда как просто вызов tiles.php в соответствии с
// текущей конфигурацией веб-сервера, не требует от клиента умения читать описание карты,
// но позволяет, однако, получить тайлы.
//
//
// The URL's where the client application should request a single map tile.
// Describes a multi-layered or composite map
// The string with url
// or array of strings with url's for multi-layered map
// or array:
// array(
//		array("mapName"=>(string)$mapName,"mapParm"=>array("key"=>$value))
//	)
// where map - the GaladrielCache map name
// and mapParm - (some) map description variables
// for composite map.
//
// The order of the array elements corresponds to the order of the layers from the bottom to the top.
//
// This is usually a string with a call to one GaladrielCache map in the usual way: by calling tiles.php
// However, you can simply specify the source url of the map, thereby disabling any caching.
// Support from the client application is required. But just a call to tiles.php,
// according to the current configuration of the web server, does not require the client
// to be able to read the map description. However, it allows them to get the tiles.
//
//
$mapTiles = "$tileCacheServerPath/tiles.php?z={z}&x={x}&y={y}&r={map}&options={options}";




// Основные функции Base functions

// Функция формирования запроса на получение тайлов из внешнего источника (из Интернета). 
// Возвращает строку с url и массив с параметрами stream_context.
// It's gets url of tile in internet.
// Must be return url string and stream_context options array.
//$getURL = function ($z,$x,$y,$options=array()){return array($url,$opts)}; 	


// Функция (имя функции) получение тайла из хранилища.
// Должна возвращать array('img'=>$img,'ContentType'=>...), возможно, пустой.
// Можно вернуть пустой массив, или массив с любыми ключами. Массив extract в переменные
// таким образом, можно  изменить любые переменные отсюда.
//
// Стандартная функция getTileFromFile понимает следующие параметры в $options:
// $options['needToRetrieve'] и возвращает в ответ переменную needToRetrieve с логическим значением
// и не возвращает собственно тайл.
// Она указвает, имеется ли в хранилище указанный актуальный тайл, или его надо получить извне.
// Загрузчик получает тайл, исходя из значения этой переменной. Если её нет, он будет получать 
// тайлы извне вне зависимости от их наличия.
//
// $options['checkonly'] и возвращает переменную $checkonly=false || filesize(), указывающую,
// что запрошенный тайл существует.
// Например, построение карты покрытия руководствуется этой переменной.
//
// $options['getURLoptions'] = $getURLoptions Этот параметр будет указан в качестве
// одного из параметров --options при вызове tilefromsource.php
// Таким образом можно передать какой-то параметр от клиента в процедуру $getURL
//
// Если карта многослойная, функция должна понимать параметр $options['layer'], содержащий
// номер слоя, из которого запрашивается тайл.
//
// It's gets tile from storage function. Anonymous function or real function name.
// Must return a array('img'=>$img,'needToRetrieve'=>true,...), possibly empty.
// This array turns into variables by "extract". So, you can change any variables from here.
//
// The standard getTileFromFile function understands the following parameters:
// $options['needToRetrieve'] parameter and returns the needToRetrieve variable with a boolean value in
// response and does not return the tile.
// It indicates whether the specified current tile is available in the storage, 
// or whether it needs to be obtained from the outside.
// The loader gets a tile based on the value of this variable. If it doesn't exist, it will
// receive tiles from the outside regardless of their availability.
//
// $options['checkonly'] parameter and returns a variable with $checkonly=false || true
// indicating that the requested tile exists.
// For example, the build of a coverage map is guided by this variable.	
//
// $options['getURLoptions'] = $getURLoptions This parameter will be specified as
// one of the --options parameters when calling tilefromsource.php
// This way, you can pass some parameter from the client to the $getURL procedure.
//
// If the map is multi-layered, the function must understand the $options['layer'] parameter,
// which contains the layer number from which the tile is requested.
//
//$getTile = function ($r,$z,$x,$y,$options=array()){$img=null;return array('img'=>$img);}; 
$getTile = 'getTileFromFile'; 	


// Функция (имя функции) помещения тайла в хранилище.
// It's pu tile to storage function. Anonymous function or real function name.
//
// $putTile = function ($mapName,$imgArray,$trueTile=array(),$options=array()){return array(bool,$message)}
// Где: Where:
// $mapName		имя карты 
//				the map name
// $imgArray	массив array(array($img,$path)) тайлов с их адресами в виде строки '$z/$x/$y.$ext' 
//				an array of [[tile,path]] tiles with their addresses as a string '$z/$x/$y.$ext'
// $trueTile	массив $trueTile (см.выше) - указание просто проверить правильность тайла, ничего не сохранять.
//				the $trueTile array (see above) is an indication to simply check for the tile and not save anything.
// $options		array(), произвольный массив 
//				an arbitrary array
//
// Стандартная функция putTileToFile понимает следующие параметры в $options:
// The standard putTileToFile function understands the following parameters in $options:
//
// $options['layer'] = (string)$layer сохранять тайл в файловой системе по пути ...$r/$layer/...
//									put tile in file system to path ...$r/$layer/...
//
//
$putTile = 'putTileToFile';




// Хуки Hooks
// Эти функции применяются при наличии $options ключа с именем соответствующей процедуры,
// и значением true, например {'prepareTileImg'=>true}
// These functions are applied if there is a $options key with the name of the corresponding procedure
// and the value true. {'prepareTileImg'=>true} for example.

// Функция обработки изображения тайла перед отправкой клиенту.
// Вызывается в tiles.php непосредственно перед отдачей тайла. 
// Для того, чтобы функция была вызвана, в массиве $requestOptions, передаваемом в tiles.php,
// должен быть параметр $requestOptions['prepareTileImg'] = true
// Возврат аналогичен $getTile: array('img'=>$img,'ContentType'=>...), но "img" должна быть.
// Иначе возврат игнорируется, и клиенту отдаётся исходное изображение.
// Результат работы этой функции только показывается, но не сохраняется!
// It's for transform image before sending.
// Called in tiles.php just before the tile is released.
// In order for the function to be called, the $requestOptions array passed to tiles.php
// must contain the parameter $requestOptions[“prepareTileImg”] = true.
// The return is similar to $getTile: array('img'=>$img,'ContentType'=>...), but "img" should be there.
// Otherwise, the return is ignored and the original image is given to the client.
// The result of this function is only shown, but not saved!
#$prepareTileImgBeforeReturn = function ($img){}; 	

// Функция обработки файла тайла после его получения от источника.
// Может возвращать массив [[tile,path]] тайлов с их адресами от каталога карты, первый тайл -
// должен быть собственно запрошенный, как это требуется для функции $putTile.
// Вызывается в tilefromsource.php сразу после получения картинки.
// Результат работы этой функции сохраняется в локальном хранилище!
// It's for transform image from source. For example, to cut image into tiles.
// It can return an array of [[tile,path]] tiles with their names from the map directory.
// The first tile must be the one actually requested, as required by the $putTile function.
// Called in tilefromsource.php immediately after receiving the image.
// The result of this function is saved in the local storage!
#$prepareTileImgAfterRecieve = function ($img,$z,$x,$y,$ext='png'){};	

?>
