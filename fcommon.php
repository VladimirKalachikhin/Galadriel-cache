<?php
/*
pixResolution - Размер пикселя указанного масштаба на указанной широте в метрах
tileNum2degree - Tile numbers to lon./lat. left top corner
tileNum2mercOrd - Tile numbers to linear coordinates left top corner on mercator ellipsoidal
tileNum2ord - Tile numbers to linear coordinates left top corner on mercator spherical
merc_x - Долготу в линейную координату x, Меркатор на эллипсоиде
merc_y - Широту в линейную координату y, Меркатор на эллипсоиде
lon2x - Долготу в линейную координату x, Меркатор на сфере
lat2y - Широту в линейную координату x, Меркатор на сфере
x2lon - Линейную координату x в долготу, Меркатор на сфере
y2lat - Линейную координату y в широту, Меркатор на сфере
coord2tileNum - координаты в номер тайла
nextZoom - Возвращает четыре номера тайлов следующего (большего) масштаба

quickFilePutContents - запись файла в tmp, а затем переименование

setColorsTransparent - Делает прозрачными указанные цвета в данном тайле.
addTransparent($img,$opacity=67) - делает полупрозрачными все цвета
splitToTiles($originalImg,$z,$x,$y,$ext='png') - режет картину (размерами, кратными тайлу) на тайлы

checkInBounds - находится ли указанный тайл в пределах указанных границ
*/
function pixResolution($lat_deg,$zoom,$tile_size=256,$equator=40075016.686){
/* Размер пикселя указанного масштаба на указанной широте в метрах
$equator - длина экватора в метрах, по умолчанию -- WGS-84
https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#Resolution_and_Scale
*/
$z0rez = $equator / $tile_size; 	// разрешение тайла масштаба 0 на экваторе
return $z0rez * cos(deg2rad($lat_deg)) / pow(2, $zoom);
}; // end function pixResolution

function tileNum2degree($zoom,$xtile,$ytile) {
/* Tile numbers to lon./lat. left top corner
// http://wiki.openstreetmap.org/wiki/Slippy_map_tilenames
*/
$n = pow(2, $zoom);
$lon_deg = $xtile / $n * 360.0 - 180.0;
$lat_deg = rad2deg(atan(sinh(pi() * (1 - 2 * $ytile / $n))));
return array('lon'=>$lon_deg,'lat'=>$lat_deg);
};

function tileNum2mercOrd($zoom,$xtile,$ytile,$r_major=6378137.000,$r_minor=6356752.3142) {
/* Меркатор на эллипсоиде
Tile numbers to linear coordinates left top corner on mercator ellipsoidal
*/
$deg = tileNum2degree($zoom,$xtile,$ytile);
$lon_deg = $deg['lon'];
$lat_deg = $deg['lat'];
//return array('x'=>round(merc_x($lon_deg),10),'y'=>round(merc_y($lat_deg),10));
return array('x'=>merc_x($lon_deg,$r_major),'y'=>merc_y($lat_deg,$r_major,$r_minor));
};

function tileNum2ord($zoom,$xtile,$ytile) {
/* Меркатор на сфере
Tile numbers to linear coordinates left top corner on mercator spherical
*/
$deg = tileNum2degree($zoom,$xtile,$ytile);
$lon_deg = $deg['lon'];
$lat_deg = $deg['lat'];
return array('x'=>lon2x($lon_deg),'y'=>lat2y($lat_deg));
};

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
/* Меркатор на сфере 
https://wiki.openstreetmap.org/wiki/Mercator#PHP
*/
function lon2x($lon){
// Долготу в линейную координату x
	return deg2rad($lon) * 6378137.0;
}
function lat2y($lat){
// Широту в линейную координату x
	return log(tan(M_PI_4 + deg2rad($lat) / 2.0)) * 6378137.0; 
}
function x2lon($x){
// Линейную координату x в долготу
	return rad2deg($x / 6378137.0); 
}
function y2lat($y){
// Линейную координату y в широту
	return rad2deg(2.0 * atan(exp($y / 6378137.0)) - M_PI_2);
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

function setColorsTransparent($img,$colors){
/*
Делает прозрачными указанные цвета в данном тайле.

Requirements: php-gd

$colors массив цветов, которые надо сделать прозрачным; цвет - это массив int чисел [r,g,b]

Нельзя в полноцветной картинке иметь прозрачными два цвета, и нельзя изменить прозрачный цвет.
Поэтому imagecolortransparent ваще не работает, если на картинке уже есть прозрачный цвет.
Т.е., нужно хитро заменить прозрачный цвет своим, потом этим же цветом - те, которые
должны быть прозрачными, а потом сделать этот свой цвет прозрачным.
К сожалению, это невозможно. Цвета, одинаковые по цвету, будут всё равно разными цветами,
и сделать прозрачным можно только один из них. А заменить именно один цвет другим нельзя.
Кроме того, перекрасить цвет в true color изображении невозможно: imagecolorset завершается ошибкой
imagecolorset работает только для индексированных изображений.

Зато в индексированном изображении сколько угодно цветов могут быть прозрачными.

Но печаль в том, что при преобразовании полноцветной картинки в индексные цвета
прозрачный цвет становится чёрным. Поэтому хитрая подмена цвета необходима.


*/
$gd_img = imagecreatefromstring($img);	// создаём полноцветную картинку 
$colorsPresent = array();
$transparentColorS = array();

// Выясним, если точно требуемые цвета в исходной полноцветной картинке
foreach($colors as $color){
	list($r,$g,$b) = $color;
	$transparentColor = imagecolorexact($gd_img,$r,$g,$b);
	if($transparentColor != -1) $colorsPresent[] = $color;	// цвет есть в тайле
};
if(count($colorsPresent)){	// нужные цвета есть в исходном полноцветном изображении
	// Однако, в изображении уже может быть прозрачный цвет, определить наличие которого невозможно?
	// Сделаем замену прозрачного цвета на известный
	//$backgroundImg = imagecreatetruecolor(255, 255);	// создаём полноцыетную картинку
	$backgroundImg = imagecreate(255, 255);	// создаём палитровую картинку
	//$color = imagecolorallocate($backgroundImg, 238, 238, 238);	// регистрируем цвет, какого точно нет (ну, скорее всего)
	$color = imagecolorallocate($backgroundImg, 254, 254, 254);	// регистрируем цвет, какого точно нет (ну, скорее всего)
	imagefill($backgroundImg, 0, 0, $color);	// закрашиваем картинку этим цветом
	// копируем нашу картинку в созданную. Тут магия: прозрачный цвет закрашивается нашим.
	// авотхрен. Закрашивается не только прозрачный, но и все полупрозначные, если были.
	// ещё авотхрен закрашиваются все? если обе картинки полноцветные?
	// поэтому нужно выбрать для замены какой-то нейтральный цвет
	imagecopy($backgroundImg, $gd_img, 0, 0, 0, 0, 255, 255);	
	$gd_img = $backgroundImg;
	
	// сделаем наше изображение из полноцветного в изображение 
	// в индексированных цветах (не может быть больще 256 цветов)
	imagetruecolortopalette($gd_img,false,256);	
	$transparentColorS[] = imagecolorresolve($gd_img, 254, 254, 254);	// цвет, ближайший к тому, что был прозрачным
	// Дальше найдём цвета, ближайшие к указанным 
	// каккой-нибудь цвет оно найдёт
	foreach($colorsPresent as $color){
		list($r,$g,$b) = $color;
		$transparentColorS[] = imagecolorresolve($gd_img,$r,$g,$b);	
	}
	// Теперь просто заменим найденные цвета на прозначные
	foreach($transparentColorS as $transparentColor){
		$res = imagecolorset($gd_img,$transparentColor,0,0,0,127);
		//if($res===false) echo "gd_img Перекраска цвета $transparentColor обломалась\n";
	};
	// А тепрь из gd image сделаем обратно нормальную картику
	ob_start();	// оно может быть вложенным
	ob_clean();
	imagepng($gd_img);
	imagedestroy($gd_img);
	$img = ob_get_contents();
	ob_end_clean();
};
return $img;
}; // end function setColorsTransparent


function addTransparent($img,$opacity=67){
/* 
Добавляет прозрачность к изображению, одновременно делая его из полноцветного в палитровое.
Adding opacity to $img and convert it to pallete img.
$opacity = 0 ... 127
*/
//echo "opacity=$opacity;\n";

$gd_img = imagecreatefromstring($img);	// создаём полноцветную картинку 

// После этих мистических действий прозрачность в индексированной картинке сохраняется.
$backgroundImg = imagecreate(255, 255);	// создаём картинку в индексированных цветах
imagecopy($backgroundImg, $gd_img, 0, 0, 0, 0, 255, 255);	// копируем нашу картинку в созданную.
$gd_img = $backgroundImg;
//

// сделаем наше изображение из полноцветного в изображение 
// в индексированных цветах (не может быть больще 256 цветов)
imagetruecolortopalette($gd_img,false,255);	

// Каждый цвет заменяем на такой же, но с указанной прозрачностью
for($i=0;$i<imagecolorstotal($gd_img);$i++){
	list('red'=>$r,'green'=>$g,'blue'=>$b,'alpha'=>$alpha) = imagecolorsforindex($gd_img,$i);
	if($alpha < $opacity) {
		//echo "$r,$g,$b,$alpha\n";
		imagecolorset($gd_img,$i,$r,$g,$b,$opacity);
	}
};

// А тепрь из gd image сделаем обратно нормальную картику
ob_start();	// оно может быть вложенным
ob_clean();
imagepng($gd_img);
imagedestroy($gd_img);
$img = ob_get_contents();
ob_end_clean();
return $img;
}; // end function addTransparent


function splitToTiles($originalImg,$z,$x,$y,$ext='png'){
/* Режет картинку на тайлы по 256 пикселов.
Возвращает массив картинок и путей вида [[tile,"$z/$x/$y.$ext"]].
Предполагается, что $z,$x,$y - это верхний левый (первый) тайл.
Если формат входного файла экзотический, то будет возвращён png.
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


function checkInBounds($z,$x,$y,$bounds){
/*
$bounds - массив массивов координат углов, 
{"leftTop":{"lat":lat,"lng":lng},"rightBottom":{"lat":lat,"lng":lng}}
*/
if(!$bounds) return true;
$anti = false;
if($bounds['leftTop']['lng']>0 and $bounds['rightBottom']['lng']<0) {	// граница переходит антимередиан
	$bounds['rightBottom']['lng'] += 360;
	$anti = true;
};
$lefttopTile = tileNum2degree($z,$x,$y);	// array('lon'=>lon_deg,'lat'=>lat_deg)
if($anti and $lefttopTile['lon']<0) $lefttopTile['lon'] += 360;
// нижняя граница выше верха тайла или правая граница левее лева тайла
if(($bounds['rightBottom']['lat'] > $lefttopTile['lat']) or ($bounds['rightBottom']['lng'] < $lefttopTile['lon'])) return false;	// выше или левее
$rightbottomTile = tileNum2degree($z,$x+1,$y+1);	// array('lon'=>lon_deg,'lat'=>lat_deg)
if($anti and $rightbottomTile['lon']<0) $rightbottomTile['lon'] += 360;
// верхняя граница ниже низа тайла или левая граница правее права тайла
if(($bounds['leftTop']['lat'] < $rightbottomTile['lat']) or ($bounds['leftTop']['lng'] > $rightbottomTile['lon'])) return false;	// правее или ниже
return true;
}; // end function checkInBounds
?>
