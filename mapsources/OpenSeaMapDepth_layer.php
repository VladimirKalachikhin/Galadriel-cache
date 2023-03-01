<?php
/*
*/
//require_once('../fcommon.php');
require_once('fcommon.php');

$humanName = array('ru'=>'Народный слой глубин OpenSeaMap','en'=>'OpenSeaMap folk bathymetry');
//$ttl = 86400*30*12*1; //cache timeout in seconds время, через которое тайл считается протухшим, один год
$ttl = 86400*30; //cache timeout in seconds время, через которое тайл считается протухшим, один месяц
// $ttl = 0; 	// тайлы не протухают никогда
$ext = 'png'; 	// tile image type/extension
$ContentType = 'image/png'; 	// if content type differ then file extension
$minZoom = 2;
$maxZoom = 18;
$trash = array( 	// crc32 хеши тайлов, которые не надо сохранять: логотипы, пустые тайлы, тайлы с дурацкими надписями
);
$data = array();
$data['javascriptOpen'] = "info.addAttribution('<img src=\"img/OpenSeaMapDepthScale.png\">')"; 	// the javascript string, eval'ed before create map
$data['javascriptClose'] = "info.removeAttribution('<img src=\"img/OpenSeaMapDepthScale.png\">')"; 	// the javascript string, eval'ed after close map

$functionGetURL = <<<'EOFU'
function getURL($z,$x,$y) {
/* 
Меркатор на сфере
http://osm.franken.de/cgi-bin/mapserv.fcgi?PROJECTION=EPSG%3A900913&TYPE=png&TRANSPARENT=TRUE&LAYERS=trackpoints_cor1_test_dbs,trackpoints_cor1_test,test_zoom_10_cor_1_points,test_zoom_9_cor_1_points,test_zoom_8_cor_1_points,test_zoom_7_cor_1_points,test_zoom_6_cor_1_points,test_zoom_5_cor_1_points,test_zoom_4_cor_1_points,test_zoom_3_cor_1_points,test_zoom_2_cor_1_points&SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap&STYLES=&FORMAT=image%2Fpng&SRS=EPSG%3A900913&BBOX=1721973.3729687,7200979.5596875,1878516.406875,7357522.5935938&WIDTH=1024&HEIGHT=1024
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
$url = 'http://osm.franken.de/cgi-bin/mapserv.fcgi?PROJECTION=EPSG%3A900913&TYPE=png&TRANSPARENT=TRUE&LAYERS=trackpoints_cor1_test_dbs,trackpoints_cor1_test,test_zoom_10_cor_1_points,test_zoom_9_cor_1_points,test_zoom_8_cor_1_points,test_zoom_7_cor_1_points,test_zoom_6_cor_1_points,test_zoom_5_cor_1_points,test_zoom_4_cor_1_points,test_zoom_3_cor_1_points,test_zoom_2_cor_1_points&SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap&STYLES=&FORMAT=image%2Fpng&SRS=EPSG%3A900913';
$leftTop = tileNum2ord($z,$x,$y);	// fcommon.php
$rightBottom = tileNum2ord($z,$x+1,$y+1);
$url .= "&BBOX={$leftTop['x']},{$rightBottom['y']},{$rightBottom['x']},{$leftTop['y']}";	//  Bounding box corners (lower left, upper right) https://mapserver.org/es/ogc/wms_server.html?highlight=BBOX
$url .= '&WIDTH=256&HEIGHT=256';
return array($url,$opts);
}
//echo getURL(10,558,326)[0]."\n";
EOFU;
?>
