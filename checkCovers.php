<?php
/* проверяет наличие тайлов всех масштабов для указанного файла загрузки
Если укакзан третий параметр - то копирует тайлы, включая $mapName в 
указанный в третьем параметре каталог, если их там нет.
Если указано четвёртым параметром zip, то вместо копирования в указанном в третьем параметре
каталоге будет создан архив zip с именем карты.
Например:
php checkCovers.php $/Transas.9 # Проверить наличие тайлов, указанных в файле $/Transas.9 до масштаба в соответствии с конфигом
php checkCovers.php OpenTopoMap.9 14 # Проверить наличие тайлов до масштаба 14 включительно
php checkCovers.php $/OpenTopoMap.9 15 $/tiles # Сделать копию кеша, начиная с тайлов, указанных в файле $/OpenTopoMap.9, до масштаба 15 включительно, в каталоге $/tiles
php checkCovers.php $/OpenTopoMap.9 16 $ zip # В каталоге $ создать архив с кешем, начиная с тайлов, указанных в файле $/OpenTopoMap.9, до масштаба 16 включительно.

В каталог $checkCoversDataDirName/notFound кладутся файлы загрузчика, которые отсутствуют в текущем масштабе, хотя
в предыдущем масштабе тайл есть.
Соответственно, в отсутствующие не включены тайлы большего масштаба, если нет предыдущего. Предполагается, 
что отсутствующие тайлы отдадут загрузчику, а он загрузит и нижележащее.

НЕ ПОДДЕРЖИВАЕТ версионные карты
 */

chdir(__DIR__); // задаем директорию выполнение скрипта
require('fcommon.php');
require('params.php'); 	// пути и параметры
$checkCoversDataDirName = 'checkCoversData';

$loaderJobName = @$argv[1];
//$loaderJobName = '$/OpenTopoMap.9';
if(!$loaderJobName){
	echo "Usage:\nphp checkCovers.php map_name.zoom [maxzoom] [dir_to_copy] [zip]\nwhere the\n map_name.zoom \nis text file with tile loader data.\n";
	return;
};
$path_parts = pathinfo($loaderJobName);
//print_r($path_parts);
$mapName = $path_parts['filename'];	// вообще-то, карта может быть версионной, тогда $mapName должно включать и путь к версии
$zoom =  $path_parts['extension'];
require('mapsourcesVariablesList.php');	// потому что в файле источника они могут быть не все, и для новой карты останутся старые
require("$mapSourcesDir/$mapName.php");	// параметры карты
if($argv[2] and is_numeric($argv[2])) $maxZoom = (int)$argv[2];
exec("rm -rf $checkCoversDataDirName");	// удаляем каталог с результатами предыдущего
if($zoom > $maxZoom) return;
mkdir($checkCoversDataDirName);
mkdir("$checkCoversDataDirName/nextZoom");
echo "Карта $mapName\n";
echo "до масштаба включительно: $zoom\n";
if($argv[3]) $argv[3] = rtrim($argv[3],'/');
if($argv[4]){
	if($argv[4]!= 'zip'){
		echo "use 'zip' as parameter to create zip instead copy tiles\n";
		return;
	}
	$zip = new ZipArchive();
	$res = $zip->open("{$argv[3]}/$mapName.zip", ZipArchive::CREATE);
	if(!$res) return;
	echo "будет создан архив {$argv[3]}/$mapName.zip\n";
}
elseif($argv[3]) {
	echo "будет сделана копия в {$argv[3]}/\n";
};
echo "\n";
$first = true;
do{
	$fp = @fopen($loaderJobName, "r");
	if(!$fp) {
		echo "Failed to open loader data file for next zoom: $loaderJobName\n";
		return;
	};
	$nextZoom = $zoom + 1;
	$notFound = 0; $emptyTiles = 0;
	while (($tile = fgetcsv($fp, 1000, ",")) !== FALSE) {
		//print_r($tile);
		if($tile[0][0] == '#') continue;	// комментарий
		if(file_exists("$tileCacheDir/$mapName/$zoom/{$tile[0]}/{$tile[1]}.$ext")){
			if(filesize("$tileCacheDir/$mapName/$zoom/{$tile[0]}/{$tile[1]}.$ext") == 0){
				if(!file_exists("$checkCoversDataDirName/emptyTiles")) mkdir("$checkCoversDataDirName/emptyTiles");	// хочу, чтобы каталог создавался, только если есть пустые тайлы
				$coord = tileNum2degree($zoom,$tile[0],$tile[1]);
				file_put_contents("$checkCoversDataDirName/emptyTiles/coordinates.$mapName.$zoom",$coord['lat'].' '.$coord['lon']."\n",FILE_APPEND) or exit("checkCovers.php create emptyTiles coordinates list error\n");
				file_put_contents("$checkCoversDataDirName/emptyTiles/$mapName.$zoom",$tile[0].','.$tile[1]."\n",FILE_APPEND) or exit("checkCovers.php create emptyTiles loader job file error\n");
				$emptyTiles++;
			};
			if($argv[3]){	// указано копировать тайлы или делать архив.
				if($argv[4]){	// указано делать архив
					if($zip->locateName("$mapName/$zoom/{$tile[0]}/{$tile[1]}.$ext") === false){
						$res=$zip->addFile("$tileCacheDir/$mapName/$zoom/{$tile[0]}/{$tile[1]}.$ext","$mapName/$zoom/{$tile[0]}/{$tile[1]}.$ext");
					};
				}
				else{	// указано копировать тайлы
					if(!file_exists("{$argv[3]}/$mapName/$zoom/{$tile[0]}/{$tile[1]}.$ext")){
						if(!is_dir("{$argv[3]}/$mapName/$zoom/{$tile[0]}")){
							mkdir("{$argv[3]}/$mapName/$zoom/{$tile[0]}",0777,true) or exit("create target dir error\n");
						};
						//echo "from $tileCacheDir/$mapName/$zoom/{$tile[0]}/{$tile[1]}.$ext; to {$argv[3]}/$mapName/$zoom/{$tile[0]}/{$tile[1]}.$ext\n";
						copy("$tileCacheDir/$mapName/$zoom/{$tile[0]}/{$tile[1]}.$ext","{$argv[3]}/$mapName/$zoom/{$tile[0]}/{$tile[1]}.$ext") or exit("checkCovers.php copy tile error\n");
					};
				};
			};
			// если тайлы есть - запишем в следующий файл задания 4 тайла следующего масштаба
			$tileS = nextZoom($tile);
			//print_r($xyS);
			foreach($tileS as $xy) {
				file_put_contents("$checkCoversDataDirName/nextZoom/$mapName.$nextZoom",$xy[0].','.$xy[1]."\n",FILE_APPEND) or exit("checkCovers.php create next zoom level loader job file error\n");
			};
		}
		else {
			if(!file_exists("$checkCoversDataDirName/notFound")) mkdir("$checkCoversDataDirName/notFound");	// хочу, чтобы каталог создавался, только если есть отсутствующие тайлы
			$coord = tileNum2degree($zoom,$tile[0],$tile[1]);
			file_put_contents("$checkCoversDataDirName/notFound/coordinates.$mapName.$zoom",$coord['lat'].' '.$coord['lon']."\n",FILE_APPEND) or exit("checkCovers.php create notFound coordinates list error\n");
			file_put_contents("$checkCoversDataDirName/notFound/$mapName.$zoom",$tile[0].','.$tile[1]."\n",FILE_APPEND) or exit("checkCovers.php create notFound loader job file error\n");
			$notFound++;
		};
	};
	fclose($fp);
	if(!$first) unlink($loaderJobName);
	$loaderJobName = "$checkCoversDataDirName/nextZoom/$mapName.$nextZoom";
	echo "processed $zoom zoom";
	if($notFound) echo ", not found $notFound tiles";
	if($emptyTiles) echo ", empty tiles $emptyTiles";
	echo "\n";
	$zoom = $nextZoom;
	$first = false;
}while($zoom <= $maxZoom);
if($argv[4]) {
	echo "формируется архив...\n";
	$zip->close();
};
exec("rm -rf $checkCoversDataDirName/nextZoom");
echo "Done\n";
?>
