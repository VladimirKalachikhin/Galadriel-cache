<?php
$ttl = 86400*30*12*1; //cache timeout in seconds время, через которое тайл считается протухшим, 1 год
//$ttl = 0; 	// тайлы не протухают никогда
$ttl = 2; 	// тайлы не протухают никогда
$ext = 'png'; 	// tile image type/extension
$minZoom = 0;
$maxZoom = 18;
// crc32 хеши тайлов, которые не надо сохранять: логотипы, тайлы с дурацкими надписями. '1556c7bd' чистый голубой квадрат 'c7b10d34' чистый голубой квадрат - не мусор! Иначе такие тайлы будут скачиваться снова и снова, а их много.

$trash = array(
'00000000' 	// zero length file
);

$functionGetURL = <<<'EOFU'
function getURL($z,$x,$y) {
/* К сожалению, OpenTopoMap очень не приветствует массовое скачивание карты, следит за этим,
и банит по ip. Бан заключается в тридцатисекундной задержке отдачи тайла. Также, возможно,
случайные тайлы не отдаются совсем, с ответом 404.
Это всё не препятствует обычному просмотру карты, но, если надо скачать более-менее обширные
площади - скачивать надо, достаточно часто меняя ip. Проще всего это сделать через tor.
Приведённая сдесь конфигурация предполагает, что на этой же машине имеется узел tor
и proxy lightdm, сконфигурированный только для приёма http и передаче их tor'у по socs.
У tor должен быть включен управляющий сокет.
Если клиент умеет сессии - то команда tor'у сменить выходную ноду передаётся через несколько
тайлов, если нет (загрузчик в cli, например) - выходная нода меняется каждый тайл.
Разумеется, смена выходной ноды не гарантирует смену ip, но в среднем всё работает.

По умолчанию всё это отключено. Для включения нужно раскомментировать параметр 'proxy' в массиве $opts

 http://192.168.10.10/tileproxy/tiles.php?z=12&x=2374&y=1161&r=OpenTopoMap
*/
//error_log("OpenTopoMap $z,$x,$y");
$server = array('a','b','c');
$getTorNewNode = "(echo authenticate '\"\"'; echo signal newnym; echo quit) | nc localhost 9051"; 	// set it if you hawe Tor as proxy, and want change exit node every $tilesPerNode try. https://stackoverflow.com/questions/1969958/how-to-change-the-tor-exit-node-programmatically-to-get-a-new-ip
$tilesPerNode = 10; 	// попытка смены ip предпринимается каждые столько тайлов

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

//$RequestHead='Referer: http://openstreet.com';
$RequestHead='';

$url = 'https://'.$server[array_rand($server)] . '.tile.opentopomap.org';
$url .= "/".$z."/".$x."/".$y.".png";
$opts = array(
	'http'=>array(
		'method'=>"GET",
		'header'=>"User-Agent: $userAgent\r\n" . "$RequestHead\r\n",
		//'proxy'=>'tcp://127.0.0.1:8123',
		'request_fulluri'=>TRUE
	)
);
//print_r($opts);
if($getTorNewNode AND @$opts['http']['proxy']) { 	// можно менять выходную ноду Tor.
	//error_log("Are session support present? _SESSION['tilesPerNode']=".$_SESSION['tilesPerNode']);
	if ((!$_SESSION['tilesPerNode']) OR ($_SESSION['tilesPerNode'] > $tilesPerNode)) { 	// если сессии нет совсем или уже пора
		error_log("getting new Tor exit node");
		exec($getTorNewNode);	// сменим выходную ноду Tor
		$_SESSION['tilesPerNode'] = 1;
	}
	else $_SESSION['tilesPerNode']++;
}
return array($url,$opts);
}
EOFU;
?>
