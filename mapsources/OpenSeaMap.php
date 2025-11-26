<?php
/* OpenSeaMap http://www.openseamap.org/
*/
$humanName = array('ru'=>'Морской слой OpenSea','en'=>'OpenSeaMap');
//$ttl = 86400*30*12*1; //cache timeout in seconds время, через которое тайл считается протухшим, 1 год
$ttl = 86400*30*3; //cache timeout in seconds время, через которое тайл считается протухшим, 1 год
// $ttl = 0; 	// тайлы не протухают никогда
$ext = 'png'; 	// tile image type/extension
$minZoom = 0;
$maxZoom = 18;
// crc32 хеши тайлов, которые не надо сохранять: логотипы, тайлы с дурацкими надписями. '1556c7bd' чистый голубой квадрат 'c7b10d34' чистый голубой квадрат - не мусор! Иначе такие тайлы будут скачиваться снова и снова, а их много.
$trash = array(
'00000000' 	// zero length file
);
// Для контроля источника: номер правильного тайла и его CRC32b хеш
$trueTile=array(15,19095,9521,'7fc1e790');	// to source check; tile number and CRC32b hash

$getURL = function ($z,$x,$y) {
/* К сожалению, OpenTopoMap очень не приветствует массовое скачивание карты, следит за этим,
и банит по ip. Бан заключается в тридцатисекундной задержке отдачи тайла. Также, возможно,
случайные тайлы не отдаются совсем, с ответом 404.
Это всё не препятствует обычному просмотру карты, но, если надо скачать более-менее обширные
площади - скачивать надо, достаточно часто меняя ip. Проще всего это сделать через tor.
Приведённая сдесь конфигурация предполагает, что на этой же машине имеется узел tor
и proxy lightdm, сконфигурированный только для приёма http и передаче их tor'у по socs.
У tor должен быть включен управляющий сокет.
По умолчанию всё это отключено. Для включения нужно раскомментировать параметр 'proxy' в массиве $opts

 http://192.168.10.10/tileproxy/tiles.php?z=12&x=2374&y=1161&r=OpenTopoMap
*/
//error_log("OpenSeaMap $z,$x,$y");

$userAgent = randomUserAgent();
//$RequestHead='Referer: http://openstreet.com';
//$RequestHead='';

$url = 'http://tiles.openseamap.org/seamark';
$url .= "/".$z."/".$x."/".$y.".png";
$opts = array(
	'http'=>array(
		'method'=>"GET",
		'header'=>"User-Agent: $userAgent\r\n" . "$RequestHead\r\n",
		//'proxy'=>'tcp://127.0.0.1:8118',
		//'timeout' => 60,
		'request_fulluri'=>TRUE
	)
);
//print_r($opts);
// set it if you hawe Tor as proxy, and want change exit node every $tilesPerNode try. https://stackoverflow.com/questions/1969958/how-to-change-the-tor-exit-node-programmatically-to-get-a-new-ip
// tor MUST have in torrc: ControlPort 9051 without authentication: CookieAuthentication 0 and #HashedControlPassword
// Alternative: set own port, config tor password by tor --hash-password my_password and stay password in `echo authenticate '\"\"'`
changeTORnode($getURLoptions['OpenTopoMap']);
return array($url,$opts);
};
?>
