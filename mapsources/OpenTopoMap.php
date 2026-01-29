<?php
// Все эти переменные глобальны!
// All of this variables is global!
$humanName = array('ru'=>'Топокарта OpenTopoMap','en'=>'OpenTopoMap');
//$ttl = 60*60*24*30*12*1; //cache timeout in seconds время, через которое тайл считается протухшим, 1 год
$ttl = 60*60*24*30*12*3; //cache timeout in seconds время, через которое тайл считается протухшим, 3 года, потому что эти суки стали радикально упрощать карты бывших хохляцких территорий
//$ttl = 0; 	// тайлы не протухают никогда
$noTileReTry = 60*60; 	// no tile timeout, sec. Время, через которое переспрашивать тайлы, которые не удалось скачать. OpenTopoMap банит скачивальщиков, поэтому короткое.
$ext = 'png'; 	// tile image type/extension
$minZoom = 0;
$maxZoom = 17;
$data = array('noAutoScaled'=>true);	// не масштабировать графически за пределами максимального и минимального масштабов
// crc32 хеши тайлов, которые не надо сохранять: логотипы, тайлы с дурацкими надписями. '1556c7bd' чистый голубой квадрат 'c7b10d34' чистый голубой квадрат - не мусор! Иначе такие тайлы будут скачиваться снова и снова, а их много.
$trash = array(
	'2afb4cc6'	// кретинская картинка, которую с некоторых пор они отправляют вместо тайла в случае ...? Да в случае русских, казлы.
);
// Для контроля источника: номер правильного тайла и его CRC32b хеш
$trueTile=array(15,19796,10302,'2046a299');	// to source check; tile number and CRC32b hash

$getURLoptions['r'] = pathinfo(__FILE__, PATHINFO_FILENAME);	// $getURLoptions будет передан в $getURL

$prepareTileImgBeforeReturn = function ($img){
/* В OpenTopoMap цвет моря - 163,221,232, а озёр - 151,210,227 
Если заменить эти цвета на прозрачный, можно наложить эту карту на непрозрачные морские
*/
if(!$img) return array('img'=>$img);
$img = setColorsTransparent($img,array(
	array(163,221,232),
	array(151,210,227),
	array(151,209,227),
	array(154,220,232)
));
return array('img'=>$img);
}; // end function prepareTileImg


$getURL = function ($z,$x,$y,$options=array()) {
/* К сожалению, OpenTopoMap очень не приветствует массовое скачивание карты, следит за этим,
и банит по ip. Бан заключается в тридцатисекундной задержке отдачи тайла. Также, возможно,
случайные тайлы не отдаются совсем, с ответом 404.
Это всё не препятствует обычному просмотру карты, но, если надо скачать более-менее обширные
площади - скачивать надо, достаточно часто меняя ip. Проще всего это сделать через tor.
Приведённая сдесь конфигурация предполагает, что на этой же машине имеется узел tor
и proxy polipo или privoxy, сконфигурированный только для приёма http и передаче их tor'у по socs.
У tor должен быть включен управляющий сокет.
По умолчанию всё это отключено. Для включения нужно раскомментировать параметр 'proxy' и 'timeout' в массиве $opts

 http://192.168.10.10/tileproxy/tiles.php?z=12&x=2374&y=1161&r=OpenTopoMap
*/

$server = array('a','b','c');
$url = 'https://'.$server[array_rand($server)].'.tile.opentopomap.org';

$userAgent = randomUserAgent();
$RequestHead='Referer: http://openstreet.com';
//$RequestHead='';

$url .= "/".$z."/".$x."/".$y.".png";
$opts = array(
	'http'=>array(
		'method'=>"GET",
		'header'=>"User-Agent: $userAgent\r\n" . "$RequestHead\r\n",
		//'proxy'=>'tcp://127.0.0.1:8118',
		'timeout' => 60,
		'request_fulluri'=>TRUE
	)
);
//print_r($opts);

// set it if you have Tor as proxy, and want change exit node every $tilesPerNode try. https://stackoverflow.com/questions/1969958/how-to-change-the-tor-exit-node-programmatically-to-get-a-new-ip
// tor MUST have in torrc: ControlPort 9051 without authentication: CookieAuthentication 0 and #HashedControlPassword
// Alternative: set own port, config tor password by tor --hash-password my_password and stay password in `echo authenticate '\"\"'`
changeTORnode($getURLoptions['OpenTopoMap']);
return array($url,$opts);
};
?>
