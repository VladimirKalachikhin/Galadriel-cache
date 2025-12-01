<?php
/* Стандартные функции получения/записи тайла из/в локальное хранилище - кеша, mbtiles, etc.
Standard functions for getting/writing tiles from/to local storage - cache, mbtiles, etc.

Файловое хранилище
File storage
getTileFromFile($r,$z,$x,$y,$options=array())
putTileToFile($r,$z,$x,$y,$options=array())

*/

// Файловое хранилище
// File storage

function getTileFromFile($r,$z,$x,$y,$options=array()){
/*
Options:
$options['checkonly']		checks is the tile exist. No tile return.
$options['needToRetrieve']	checks whether the tile needs to be  retrieve from source. May or may not return a tile.
$options['layer'] = (string)$layer	get tile from path $r/$layer/

Return:
$img = null - no tile present
*/
global $ext,$ttl,$freshOnly,$bounds,$noTileReTry,$getURL;	// из описания карты
global $tileCacheDir,$phpCLIexec;	// из файла конфигурации

$addPath = '';
if(isset($options['layer'])) {
	if($options['layer'][0]=='/') $addPath = $options['layer'];
	else $addPath ='/'.$options['layer'];
	if(!$options['getURLoptions']['layer']) $options['getURLoptions']['layer'] = $options['layer'];
};
$fileName = "$tileCacheDir/$r$addPath/$z/$x/$y.$ext"; 	// из кэша
//echo "[getTileFromFile] fileName=$fileName; <br>\n"; 
/*
Нужно понять -- тайл сначала показывать, потом скачивать, или сначала скачивать, а потом показывать
если тайла нет -- если есть, чем скачивать - сперва скачивать, потом показывать, иначе 404
если тайл есть, он не нулевой длины, и не протух -- просто показать
если тайл есть, он не нулевой длины, и протух, и указано протухшие не показывать -- скачать, если есть, чем скачивать, потом показать, иначе 404
если тайл есть, он не нулевой длины, и протух, и не указано протухшие не показывать -- показать, потом скачать, если есть чем, иначе - просто показать
если файл есть, но нулевой длины -- использовать специальное время протухания 
*/
/* $showTHENloading
0; 	// ничего не показывать 
1; 	// сперва показывать, потом скачивать 
2; 	//сперва скачивать, потом показывать
3; 	// только показывать 
4; 	// только скачивать 

Должны ли мы получить тайл от источника синхронно?
	получить
Загрузить тайл из файловой системы
Если надо - запустить получение тайла из источника в фоне.

*/
$checkonly = $options['checkonly'];
unset($options['checkonly']);
$needToRetrieve = $options['needToRetrieve'];
unset($options['needToRetrieve']);
$showTHENloading = 0;	// ничего не показывать 

// Узнаем время последнего изменения тайла, а заодно - вообще наличие тайла
clearstatcache();
$imgFileTime = @filemtime($fileName); 	// файла может не быть
//echo "tiles.php: $r/$z/$x/$y tile exist:$imgFileTime, and expired to ".(time()-(filemtime($fileName)+$ttl))."sec. и имеет дату модификации ".date('d.m.Y H:i',$imgFileTime)."<br>\n";
if($imgFileTime) { 	// файл есть
	if($checkonly) return array('checkonly'=>filesize($fileName));
	if(($imgFileTime+$ttl) < time()) { 	// файл протух. Таким образом, файлы нулевой длины могут протухнуть раньше, но не позже.
		if($freshOnly) { 	// протухшие не показывать
			if($getURL)	{
				if($needToRetrieve) $showTHENloading = 4; 	//только скачивать
				else $showTHENloading = 2; 	//сперва скачивать, потом показывать
			};
			// else ;	// ничего не показывать
		}
		else { 	// протухшие показывать
			if($getURL) {
				if($needToRetrieve) $showTHENloading = 4; 	//только скачивать
				else $showTHENloading = 1; 	// сперва показывать, потом скачивать
			}
			else $showTHENloading = 3;	// только показывать
		};
	}
	else $showTHENloading = 3;	// файл есть и не протух - только показывать
}
else{	// файла нет
	if($checkonly) return array('checkonly'=>false);
	if($getURL) {	// файла нет, но в описании карты указано, где взять
		// Границы проверяются здесь, потому что это затратно, и делается когда уже всё.
		// А так - границы должны бы проверяться в tiles.php, до вызова getTile
		if(checkInBounds($z,$x,$y,$bounds)){	// тайл вообще должен быть?
				if($needToRetrieve) $showTHENloading = 4; 	//только скачивать
				else $showTHENloading = 2; 	//сперва скачивать, потом показывать
		};	// иначе - файла и не должно быть, ничего не показывать
	};	// иначе - файла и не должно быть, ничего не показывать
};

// возьмём тайл
$img = null;	// тайла нет
if($showTHENloading === 0) return array('img'=>$img);	//ничего не показывать 
//echo "[getTileFromFile] showTHENloading=$showTHENloading;\n";

if(!$needToRetrieve and $showTHENloading == 2){	//сперва скачивать, т.е., нужно получить тайл от источника синхронно
	$execStr = "$phpCLIexec tilefromsource.php  -z$z -x$x -y$y -r$r  --options='".(json_encode($options,JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))."'";	// строку json - в кавычках обязательно!
	//echo "[getTileFromFile] execStr=$execStr;<br>\n";
	if(IRun($execStr)) sleep(1); 	// Предотвращает множественную загрузку одного тайла одновременно, если у proxy больше одного клиента. Просто подождём, пока другой экземпляр не загрузит этот тайл.
	else exec($execStr,$output,$result); 	// exec будет ждать завершения. Неважно, какой там $output,$result - нас дальше интересует, что в результате произошло
	//echo "[getTileFromFile] result=$result; output:<pre>";print_r($output);echo "</pre><br>\n";
};
// Возможно, тайл получен от источника и положен в кеш
$img = @file_get_contents($fileName); 	// берём тайл из кеша
if($img === false){	// файла нет
	$img = null;	// 
}
elseif(!$img) { 	// файл нулевой длины
 	if($noTileReTry) $ttl= $noTileReTry; 	// если указан специальный срок протухания для файла нулевой длины -- им обозначается перманентная проблема скачивания
	if(($imgFileTime+$ttl) < time()) { 	
		// файл протух. Однако, пустым мог оказаться только что скачанный файл,
		// а $imgFileTime у нас от старого. Ну и ладно.
		// if($freshOnly) { 	// не будем также в этом случае применять требование
		// "протухшие не показывать"
		if($getURL) {
			if($needToRetrieve) $showTHENloading = 4; 	//только скачивать
			else $showTHENloading = 1; 	// сперва показывать, потом скачивать
		};
	};
};
// Концептуально, тут надо запускать фоновое скачивание тайла из источника,
// если оно требуется. Но запуск - это длительный процесс, а нам надо срочно вернуть тайл
// для показа.
// Поэтому сделаем костыль, который не был нужен до идеи, что функция getTile - пользовательская функция.
// Однако, оказалось, что это не совсем костыль - loader'у требуется узнать, есть ли уже актуальный
// тайл, или надо скачивать. Вот тут-то оно и.
switch($showTHENloading){
case 1:
case 4:
	return array('img'=>$img,'needToRetrieve'=>true);
	break;
default:
	return array('img'=>$img,'needToRetrieve'=>false);	// на всякий случай собщим, что скачивать не надо
};
}; // end function getTileFromFile


function putTileToFile($mapName,$imgArray,$trueTile=array(),$options=array()){
/*
$options['layer'] = (string)$layer	put tile to path $r/$layer/
*/
global $tileCacheDir;
//echo "[putTileToFile] mapName=$mapName; options:<pre>"; print_r($options); echo "</pre><br>\n";
if(!$imgArray) return array(false,"No getted any images");
$addPath = '';
if(isset($options['layer'])) {
	if($options['layer'][0]=='/') $addPath = $options['layer'];
	else $addPath ='/'.$options['layer'];
};
// нужно только проверить совпадение полученного и правильного тайла,
// если $trueTile - массив со свойствами правильного тайла
if($trueTile){
	list($z,$x,$y,$ext,$img) = requestedTileInfo($imgArray);
	//echo "Check tile $z $x $y "; print_r($trueTile); echo "\n";
	if($z==$trueTile[0] and $x==$trueTile[1] and $y==$trueTile[2]){	// в описании карты указано, какой тайл правильный
		$hash = hash('crc32b',$img);
		if($hash==$trueTile[3]){	// тайл такой, какой нужно
			return array(true,"The tile is true");	// ничего не сохраняем
		}
		else{
			return array(false,"The tile is not true, must be {$trueTile[3]}, recieved $hash");	// ничего не сохраняем
		};
	};
	return array(true,'');	// ничего не сохраняем
};
if($imgArray[0][0]===null){	// если первый тайл неправильный
	$fileName = "$tileCacheDir/$mapName$addPath/".$imgArray[0][1];
	clearstatcache(true,$fileName);
	if(@filesize($fileName)){	// однако, в хранилище есть такой файл, и он не пустой
		return array(true,"Got null, but have any images");	// тогда не будем перезаписывать
	};
};
$umask = umask(0); 	// сменим на 0777 и запомним текущую
//file_put_contents('savedTiles',"[putTileToFile] imgArray содержит ".(count($imgArray))." элементов\n",FILE_APPEND);	
foreach($imgArray as $imgInfo){
	$fileName = "$tileCacheDir/$mapName$addPath/".$imgInfo[1];
	//@mkdir(dirname($fileName), 0755, true);
	@mkdir(dirname($fileName), 0777, true); 	// если кеш используется в другой системе, юзер будет другим и облом. Поэтому - всем всё. но реально используется umask, поэтому mkdir 777 не получится
	//chmod(dirname($fileName),0777); 	// идейно правильней, но тогда права будут только на этот каталог, а не на предыдущие, созданные по true в mkdir
	$res = file_put_contents($fileName,$imgInfo[0],LOCK_EX);
	if($res===false){
		return array(false,"ERROR save file $fileName");
	};
	chmod($fileName,0666); 	// чтобы при запуске от другого юзера была возможность заменить тайл, когда он протухнет
	error_log("[putTileToFile] Saved ".strlen($imgInfo[0])." bytes to $fileName");	
	//file_put_contents('savedTiles',"[putTileToFile] ".strlen($imgInfo[0])." bytes to $fileName\n",FILE_APPEND);	
};
umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
return array(true,'');
}; // end function putTileToFile




// Хранилище SQLite: mbtiles, oruxmaps(?)
// SQLite storage:  mbtiles, oruxmaps
function getTileFromSQLite($r,$z,$x,$y,$options=array()){
/* Получает тайл из файла $r.mbtiles, от демона, который этот файл пасёт.
*/
$result = SQLiteWrapper($r,'getTile',array('z'=>$z,'x'=>$x,'y'=>$y));
if(!$result) return array('img'=>null);
return $result;
};

function SQLiteWrapper($mapName,$request,$data=array()){
/* Получает запрошенное в $request из файла $mapName.mbtiles, от демона, который этот файл пасёт.
При отсутствии демона -- запускает его.
Предполагается, что переменные из params.php глобальны 

$request: getTile, putTile, getMetainfo
$data: getTile, putTile: array('z'=>$z,'x'=>$x,'y'=>$y)
*/
global $phpCLIexec;

// Просто в /tmp/ сокет делать нельзя, ибо оно индивидуально для каждого процесса из-за systemd PrivateTmp
// Если каталога нет, то он будет создан при запуске сервера, ниже.
$sockName = __DIR__."/sockets/tileproxy_$mapName";	
//echo "[SQLiteWrapper] r=$r, z=$z, x=$x, y=$y, sockName=$sockName;\n";
$socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
$res = @socket_connect($socket,$sockName);
if(!$res){	// нет живого сокета
	exec("$phpCLIexec SQLiteTilesServer.php $mapName > /dev/null 2>&1 &"); 	// запустим срвер exec не будет ждать завершения
	sleep(1);	// а надо ли ждать, пока стартует сервер? socket_connect сколько-то ждёт и так. Надо! иначе оно реально не успевает, и открытие карты вообще ни к чему не приводит.
	$res = @socket_connect($socket,$sockName);
};
if(!$res) return false;

$data['request']=$request;
$msg = serialize($data)."\n";

$msgLen = mb_strlen($msg,'8bit');
//echo "[SQLiteWrapper] Посылаем серверу $msgLen байт:|$msg|\n";
// Посылаем запрос, считаем, что он короткий и гарантированно отдастся за один socket_write
$res = socket_write($socket, $msg, $msgLen);	
if(!$res) return false;

// По причине нехватки буферов и неудачного расположения светил данные могут поступать в сокет
// неполностью. Поэтому нужно читать, пока не кончится.
// Однако, поскольку мы читаем в режиме PHP_BINARY_READ, мы не знаем, когда данные кончились:
// даже если прочли уже всё, никакого сигнала об окончании данных не поступает.
// Даже если указать большой буфер, и считать, что если приняли меньше буфера - то приняли всё,
// данные могут поступать маленькими кусками, меньше буфера.
// Поэтому сигналом о том, что данные кончились является закрытие сокета сервером.
$result = ''; $bufSize = 1048576;
do{
	//error_log("[SQLiteWrapper] Wait from server");
	$buf = socket_read($socket, $bufSize, PHP_BINARY_READ);
	//echo "[SQLiteWrapper] Получили от сервера: ".(mb_strlen($buf,'8bit'))." байт\n";// "|$result|\n";
	//error_log("[SQLiteWrapper] Recieved from server: ".(mb_strlen($buf,'8bit'))." bytes");
	if($buf!==false){
		$result .= $buf;
		//if(mb_strlen($buf,'8bit')<=$bufSize) break;	// прочли всё - не обязательно, данные могли идти маленькими кусками
	};
	// else - на той стороне закрыли сокет, по облому или передали всё
}while($buf);
@socket_close($socket); 	// он может быть уже закрыт

$result = unserialize($result);
//error_log("[SQLiteWrapper] Request: r=$r, z=$z, x=$x, y=$y; Respoce: ".(mb_strlen($result['img'],'8bit'))." bytes of {$result['ext']}");
//echo "[SQLiteWrapper] Декодировали: ".(mb_strlen($result['img'],'8bit'))." байт\n";// "|$result|\n";
return $result;
};



// Функции обработки картинки для сохранения
// Common image prepare function for save to storage

function splitToTiles($originalImg,$z,$x,$y,$ext='png'){
/* Режет картинку на тайлы по 256 пикселов.
Возвращает массив картинок и путей вида [[tile,"$z/$x/$y.$ext"]].
Предполагается, что $z,$x,$y - это верхний левый (первый) тайл.
Если формат входного файла экзотический, то будет возвращён png.
Cuts the image into 256 pixel tiles.
Returns an array of images and paths like [[tile,"$z/$x/$y.$ext"]].
It is assumed that $z,$x,$y are the upper-left (first) tile.
If the input file format is exotic, png will be returned.
0 3 6
1 4 7
2 5 8
*/
$imgs = array();
$imgSize = getimagesizefromstring($originalImg);
//print_r($imgSize);
$cols = intdiv($imgSize[0],256);	// ширина
$rows = intdiv($imgSize[1],256);	// высота
//echo "По горизонтали:$cols, по вертикали:$rows;\n";

$gd_img = imagecreatefromstring($originalImg);	// создаём полноцветную картинку 
for($c=0; $c<$cols; $c++){
	// imagecrop и imagecopy не сохраняют прозрачность,
	// поэтому порезка картинки делается с помощью imagecopy в картинку,
	// предварительно покрашеную в прозрачность.
	// Видимо, прозрачные пиксели вообще не переносятся, т.е. в целевой картинке красятся первым цветом.
	// А первый цвет мы сделали прозрачным.
	$imgC   = imagecreatetruecolor(256, $imgSize[1]);
	$transparentColor = imagecolorallocatealpha($imgC, 0, 0, 0, 127);
	imagefill($imgC, 0, 0, $transparentColor);
	imagesavealpha($imgC,true);
	imagecopy($imgC,$gd_img, 0, 0, $c*256, 0, 256, $imgSize[1]);	// копируем нашу картинку в созданную.
	//imagepng($imgC,"./$/img/imgC$c.png");
	for($r=0; $r<$rows; $r++){
		$img   = imagecreatetruecolor(256, 256);
		$transparentColor = imagecolorallocatealpha($img, 0, 0, 0, 127);
		imagefill($img, 0, 0, $transparentColor);
		imagesavealpha($img,true);
		imagecopy($img, $imgC, 0, 0, 0, $r*256, 256, 256);	// копируем нашу картинку в созданную.
		//imagepng($img,"./$/img/img$r.png");

		// А тепрь из gd image сделаем обратно нормальную картику
		ob_start();	// оно может быть вложенным
		ob_clean();
		switch($imgSize[2]){	// IMAGETYPE_XXX constant
		case IMG_BMP:
			imagebmp($img);
			break;
		case IMG_GIF:
			imagegif($img);
			break;
		case IMG_JPG:
			imagejpeg($img);
			break;
		case IMG_PNG:
			imagepng($img);
			break;
		case IMG_WBMP:
			image2wbmp($img);
			break;
		case IMG_WEBP:
			imagewebp($img);
			break;
		default:
			imagepng($img);
			$ext = 'png';
		};
		imagedestroy($img);
		$img = ob_get_contents();
		ob_end_clean();
		$imgs[] = array($img,"$z/".($x+$c)."/".($y+$r).".$ext");
	};
	imagedestroy($imgC);
};
imagedestroy($gd_img);
return $imgs;
}; // end function splitToTiles

function requestedTileInfo($imgArray){
/* Получает массив массивов [$img,"$z/$x/$y.$ext"]
возвращает массив [$z,$x,$y,$ext,$img] первого элемента.
Считается, что первый элемент - запрошенный тайл.
*/
list($z,$x,$y) = explode('/',$imgArray[0][1]);
$y = explode('.',$y);
$ext = $y[1];
$y = $y[0];
//echo "[requestedTileInfo] {$imgArray[0][1]} z=$z; x=$x; y=$y; ext=$ext;\n";
$img = $imgArray[0][0];
return array($z,$x,$y,$ext,$img);
}; // end function requestedTileInfo

function requestedTileFirst($z,$x,$y,$ext,$imgArray){
$requestedTileInfo = "$z/$x/$y.$ext";
//echo "[requestedTileFirst] Ищем $requestedTileInfo\n";
foreach($imgArray as $key=>$imgInfo){
	if($requestedTileInfo == $imgInfo[1]){
		//echo "[requestedTileFirst] key=$key; imgInfo[1]={$imgInfo[1]};\n";
		unset($imgArray[$key]);
		array_unshift($imgArray,$imgInfo);
		break;
	};
};
return $imgArray;
};	// end function requestedTileFirst
?>
