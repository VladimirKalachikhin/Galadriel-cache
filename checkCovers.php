<?php
/* проверяет наличие тайлов всех масштабов для указанного файла загрузки
В каталог $checkCoversDataDirName/notFound кладутся файлы загрузчика, которые отсутствуют в текущем масштабе, хотя
в предыдущем масштабе тайл есть.
Соответственно, в отсутствующие не включены тайды большего масштаба, если нет предыдущего. Предполагается, 
что отсутствующие тайлы отдадут загрузчику, а он загрузит и нижележащее.

НЕ ПОДДЕРЖИВАЕТ версионные карты
 */

chdir(__DIR__); // задаем директорию выполнение скрипта
require('params.php'); 	// пути и параметры
$checkCoversDataDirName = 'checkCoversData';

$loaderJobName = @$argv[1];
//$loaderJobName = '$/OpenTopoMap.9';
if(!$loaderJobName){
	echo "Usage:\nphp checkCovers.php map_name.zoom [maxzoom]\nwhere the\n map_name.zoom \nis text file with tile loader data.\n";
	return;
};
$path_parts = pathinfo($loaderJobName);
//print_r($path_parts);
$mapName = $path_parts['filename'];	// вообще-то, карта может быть версионной, тогда $mapName должно включать и путь к версии
$zoom =  $path_parts['extension'];
require("$mapSourcesDir/$mapName.php");	// параметры карты
if($argv[2] and is_numeric($argv[2])) $maxZoom = (int)$argv[2];
exec("rm -rf $checkCoversDataDirName");	// удаляем каталог с результатами предыдущего
if($zoom > $maxZoom) return;
mkdir($checkCoversDataDirName);
mkdir("$checkCoversDataDirName/nextZoom");
$first = true;
do{
	$fp = @fopen($loaderJobName, "r");
	if(!$fp) {
		echo "Failed to open loader data file $loaderJobName\n";
		return;
	};
	$nextZoom = $zoom + 1;
	$notFound = 0;
	while (($tile = fgetcsv($fp, 1000, ",")) !== FALSE) {
		//print_r($tile);
		if($tile[0][0] == '#') continue;	// комментарий
		if(file_exists("$tileCacheDir/$mapName/$zoom/{$tile[0]}/{$tile[1]}.$ext")){
			// если тайлы есть - запишем в следующий файл задания 4 тайла следующего масштаба
			$tileS = nextZoom($tile);
			//print_r($xyS);
			foreach($tileS as $xy) {
				file_put_contents("$checkCoversDataDirName/nextZoom/$mapName.$nextZoom",$xy[0].','.$xy[1]."\n",FILE_APPEND) or exit("checkCovers.php create next zoom level loader job file error\n");
			};
		}
		else {
			if(!file_exists("$checkCoversDataDirName/notFound")) mkdir("$checkCoversDataDirName/notFound");	// хочу, чтобы каталог создавался, только если есть отсутствующие тайлы
			file_put_contents("$checkCoversDataDirName/notFound/$mapName.$zoom",$tile[0].','.$tile[1]."\n",FILE_APPEND) or exit("checkCovers.php create next zoom level loader job file error\n");
			$notFound++;
		};
	};
	fclose($fp);
	if(!$first) unlink($loaderJobName);
	$loaderJobName = "$checkCoversDataDirName/nextZoom/$mapName.$nextZoom";
	echo "processed $zoom zoom";
	if($notFound) echo ", not found $notFound tiles";
	echo "\n";
	$zoom = $nextZoom;
	$first = false;
}while($zoom <= $maxZoom);
exec("rm -rf $checkCoversDataDirName/nextZoom");



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

?>
