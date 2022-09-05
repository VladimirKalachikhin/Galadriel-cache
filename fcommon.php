<?php
/*
pixResolution - Размер пикселя указанного масштаба на указанной долготе в метрах
tileNum2degree - Tile numbers to lon./lat. left top corner
tileNum2mercOrd - Tile numbers to linear coordinates left top corner on mercator ellipsoidal
merc_x - Долготу в линейную координату x, Меркатор на эллипсоиде
merc_y - Широту в линейную координату y, Меркатор на эллипсоиде
coord2tileNum - координаты в номер тайла
nextZoom - Возвращает четыре номера тайлов следующего (большего) масштаба

quickFilePutContents - запись файла в tmp, а затем переименование
*/
function pixResolution($lat_deg,$zoom,$tile_size=256,$equator=40075016.686){
/* Размер пикселя указанного масштаба на указанной долготе в метрах
$equator - длина экватора в метрах, по умолчанию -- WGS-84
https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#Resolution_and_Scale
*/
$z0rez = $equator / $tile_size; 	// разрешение тайла масштаба 0 на экваторе
return $z0rez * cos(deg2rad($lat_deg)) / pow(2, $zoom);
} // end function pixResolution

function tileNum2degree($zoom,$xtile,$ytile) {
/* Tile numbers to lon./lat. left top corner
// http://wiki.openstreetmap.org/wiki/Slippy_map_tilenames
*/
$n = pow(2, $zoom);
$lon_deg = $xtile / $n * 360.0 - 180.0;
$lat_deg = rad2deg(atan(sinh(pi() * (1 - 2 * $ytile / $n))));
return array('lon'=>$lon_deg,'lat'=>$lat_deg);
}

function tileNum2mercOrd($zoom,$xtile,$ytile,$r_major=6378137.000,$r_minor=6356752.3142) {
/* Меркатор на эллипсоиде
Tile numbers to linear coordinates left top corner on mercator ellipsoidal
*/
$deg = tileNum2degree($zoom,$xtile,$ytile);
$lon_deg = $deg['lon'];
$lat_deg = $deg['lat'];
//return array('x'=>round(merc_x($lon_deg),10),'y'=>round(merc_y($lat_deg),10));
return array('x'=>merc_x($lon_deg,$r_major),'y'=>merc_y($lat_deg,$r_major,$r_minor));
}

function merc_x($lon,$r_major=6378137.000) {
/* Меркатор на эллипсоиде
Долготу в линейную координату x
// http://wiki.openstreetmap.org/wiki/Mercator#PHP_implementation
*/
return $r_major * deg2rad($lon);
}

function merc_y($lat,$r_major=6378137.000,$r_minor=6356752.3142) {
/* Меркатор на эллипсоиде
Широту в линейную координату y
// http://wiki.openstreetmap.org/wiki/Mercator#PHP_implementation
*/
	if ($lat > 89.5) $lat = 89.5;
	if ($lat < -89.5) $lat = -89.5;
    $temp = $r_minor / $r_major;
	$es = 1.0 - ($temp * $temp);
    $eccent = sqrt($es);
    $phi = deg2rad($lat);
    $sinphi = sin($phi);
    $con = $eccent * $sinphi;
    $com = 0.5 * $eccent;
	$con = pow((1.0-$con)/(1.0+$con), $com);
	$ts = tan(0.5 * ((M_PI*0.5) - $phi))/$con;
    $y = - $r_major * log($ts);
    return $y;
}

function coord2tileNum($lon,$lat,$zoom){
/* координаты в градусах в номер тайла */
$xtile = floor((($lon + 180) / 360) * pow(2, $zoom));
$ytile = floor((1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / pi()) /2 * pow(2, $zoom));
return array($xtile,$ytile);
} // end function coord2tileNum

function nextZoom($xy){
/* Возвращает четыре номера тайлов следующего (большего) масштаба
https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#Resolution_and_Scale
Получает массив номера тайла (x,y)
*/
$nextZoom[0] = array(2*$xy[0],2*$xy[1]);	// левый верхний тайл
$nextZoom[1] = array(2*$xy[0]+1,2*$xy[1]);	// правый верхний тайл
$nextZoom[2] = array(2*$xy[0],2*$xy[1]+1);	// левый нижний тайл
$nextZoom[3] = array(2*$xy[0]+1,2*$xy[1]+1);	// правый нижнй тайл
return $nextZoom;
} // end function nextZoom

function quickFilePutContents($fileName,$content) {
/**/
$tmpFileName = tempnam('','');
//echo "$tmpFileName \n";
file_put_contents($tmpFileName,$content);
rename($tmpFileName,$fileName);
}

?>
