<?php
/*
*/
$ttl = 86400*30*12*1; //cache timeout in seconds время, через которое тайл считается протухшим, один год
// $ttl = 0; 	// тайлы не протухают никогда
$ext = 'png'; 	// tile image type/extension
$ContentType = 'image/png'; 	// if content type differ then file extension
$minZoom = 3;
$maxZoom = 22;
$trash = array( 	// crc32 хеши тайлов, которые не надо сохранять: логотипы, пустые тайлы, тайлы с дурацкими надписями
);
$functionGetURL = <<<'EOFU'
function getURL($z,$x,$y) {
/* 
*/
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

$RequestHead='Referer: https://tileservice.charts.noaa.gov/tileset.html';

$opts = array(
	'http'=>array(
		'method'=>"GET",
		'header'=>"User-Agent: $userAgent\r\n" . "$RequestHead\r\n",
		//'proxy'=>'tcp://127.0.0.1:8118',
		//'timeout' => 60,
		'request_fulluri'=>TRUE
	)
);
$url = 'https://tileservice.charts.noaa.gov/tiles/50000_1';
$url .= "/$z/$x/$y.png";
return array($url,$opts);
}
EOFU;
?>
