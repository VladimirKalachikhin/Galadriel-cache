<?php
$ttl = 86400*365; // 1 year cache timeout in seconds время, через которое тайл считается протухшим
$ext = 'png'; 	// tile image type/extension
$minZoom = 0;
$maxZoom = 19;
/* функция получения тайла позаимствована из OruxMaps */
$functionGetURL = <<<'EOFU'
function getURL($z,$x,$y) {
$server = array(0,1,2,3);

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

$RequestHead='Referer: http://google.com';

//$headersLang = 'iw'; // hebrew
$headersLang = 'ru'; // russian
//$headersLang = 'en'; // english
//$headersLang = ''; // local
$url = 'http://mt'.$server[array_rand($server)].".google.com/vt/lyrs=s,m&hl=$headersLang";
$url .= "&x=$x&y=$y&z=$z";

$opts = array(
	'http'=>array(
		'method'=>"GET",
		'header'=>"User-Agent: $userAgent\r\n" . "$RequestHead\r\n"
	)
);

return array($url,$opts);
}
EOFU;
?>
