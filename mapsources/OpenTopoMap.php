<?php
$ttl = 86400*30*12*1; //cache timeout in seconds время, через которое тайл считается протухшим, 1 год
// $ttl = 0; 	// тайлы не протухают никогда
$ext = 'png'; 	// tile image type/extension
$minZoom = 0;
$maxZoom = 17;
// crc32 хеши тайлов, которые не надо сохранять: логотипы, тайлы с дурацкими надписями. '1556c7bd' чистый голубой квадрат 'c7b10d34' чистый голубой квадрат - не мусор! Иначе такие тайлы будут скачиваться снова и снова, а их много.

$trash = array(
'00000000' 	// zero length file
);

$functionGetURL = <<<'EOFU'
function getURL($z,$x,$y) {
/* Алгоритм получения ссылки на тайл заимствован из SAS.Planet
 http://192.168.10.10/tileproxy/tiles.php?z=12&x=2374&y=1161&r=OpenTopoMap
*/
//error_log("OpenTopoMap $z,$x,$y");
$server = array();
$server[] = 'a';
$server[] = 'b';
$server[] = 'c';

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
return array($url,$opts);
}
EOFU;
?>
