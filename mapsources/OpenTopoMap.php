<?php
$humanName = array('ru'=>'Топокарта OpenTopoMap','en'=>'OpenTopoMap');
//$ttl = 60*60*24*30*12*1; //cache timeout in seconds время, через которое тайл считается протухшим, 1 год
$ttl = 60*60*24*30*6; //cache timeout in seconds время, через которое тайл считается протухшим, 1 год
//$ttl = 0; 	// тайлы не протухают никогда
$noTileReTry = 60*60; 	// no tile timeout, sec. Время, через которое переспрашивать тайлы, которые не удалось скачать. OpenTopoMap банит скачивальщиков, поэтому короткое.
$ext = 'png'; 	// tile image type/extension
$minZoom = 0;
$maxZoom = 18;
// crc32 хеши тайлов, которые не надо сохранять: логотипы, тайлы с дурацкими надписями. '1556c7bd' чистый голубой квадрат 'c7b10d34' чистый голубой квадрат - не мусор! Иначе такие тайлы будут скачиваться снова и снова, а их много.
$trash = array(
);
// Для контроля источника: номер правильного тайла и его CRC32b хеш
$trueTile=array(15,19796,10302,'ec555724');	// to source check; tile number and CRC32b hash

$functionGetURL = <<<'EOFU'
function getURL($z,$x,$y) {
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
//error_log("OpenTopoMap $z,$x,$y");

$server = array('a','b','c');
$url = 'https://'.$server[array_rand($server)] . '.tile.opentopomap.org';

$userAgents = array();
$userAgents[] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36';
$userAgents[] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:59.0) Gecko/20100101 Firefox/59.0';
$userAgents[] = 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36';
$userAgents[] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3282.186 Safari/537.36';
$userAgents[] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36';
$userAgents[] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/604.5.6 (KHTML, like Gecko) Version/11.0.3 Safari/604.5.6';
$userAgents[] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36';
$userAgents[] = 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:59.0) Gecko/20100101 Firefox/59.0';
$userAgents[] = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:59.0) Gecko/20100101 Firefox/59.0';
$userAgents[] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.13; rv:59.0) Gecko/20100101 Firefox/59.0';
$userAgent = $userAgents[array_rand($userAgents)];

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

// set it if you hawe Tor as proxy, and want change exit node every $tilesPerNode try. https://stackoverflow.com/questions/1969958/how-to-change-the-tor-exit-node-programmatically-to-get-a-new-ip
// tor MUST have in torrc: ControlPort 9051 without authentication: CookieAuthentication 0 and #HashedControlPassword
// Alternative: set own port, config tor password by tor --hash-password my_password and stay password in `echo authenticate '\"\"'`
$getTorNewNode = "(echo authenticate '\"\"'; echo signal newnym; echo quit) | nc localhost 9051"; 	
$tilesPerNode = 10; 	// change ip after попытка смены ip предпринимается каждые столько тайлов
$map = 'OpenTopoMap';	// нужно только для смены выходной ноды
if($getTorNewNode AND @$opts['http']['proxy']) { 	// можно менять выходную ноду Tor.
	$dirName = sys_get_temp_dir()."/tileproxyCacheInfo"; 	// права собственно на /tmp в системе могут быть замысловатыми
	if(file_exists($dirName) === FALSE) { 	// не будем сбрасывать кеш -- пусть кешируется
		mkdir($dirName, 0777,true); 	// 
		chmod($dirName,0777); 	// права будут только на каталог OpenTopoMapCacheInfo. Если он вложенный, то на предыдущие, созданные по true в mkdir, прав не будет. Тогда надо использовать umask.
	}
	$tilesCntFile = "$dirName/tilesCnt_$map";
	$tilesCnt = @file_get_contents($tilesCntFile);
	if ($tilesCnt > $tilesPerNode) { 	// если уже пора
		echo"getting new Tor exit node\n";
		exec($getTorNewNode);	// сменим выходную ноду Tor
		$tilesCnt = 1;
	}
	else $tilesCnt++;
	file_put_contents($tilesCntFile,$tilesCnt);
	@chmod($tilesCntFile,0666); 	// всем всё, чтобы работало от любого юзера. Но изменить права существующего файла, созданного другим юзером не удастся.
}

return array($url,$opts);
}
EOFU;
?>
