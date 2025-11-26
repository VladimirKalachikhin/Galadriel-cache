<?php
/* проверяет наличие тайлов всех масштабов для указанного файла загрузки в файловом хранилище.
В целом повторяет функциональность loader с его возможностью указать код php для выполнения
всяких действий, однако не предполагает обязательного наличия файла описания карты и, соответственно,
функций getTile и putTile.
Т.е., не годится для хранилищ, отличных от файлового, но зато может работать с тайловым
кешем хоть MOBAC, хоть SAS.Planet

Использование:

php checkCovers.php TileList.Zoom [[[MaxZoom] targetDir] zip] | [-maxZoom int] [-sourceDir str] [-targetDir str] [-zip] [-ext]

т.е., используется до четырёх позиционных параметров и сколько-то именованных в произвольном
сочетании.
Первый параметр - имя файла со списком тайлов, в формате файла задания для загрузчика, обязателен.
Остальные параметры не обязательны.
Никакие команды и процедуры в файле со списком тайлов не поддерживаются, в отличии от загрузчика.

Если укакзан третий параметр - то копирует тайлы, включая $mapName в 
указанный в третьем параметре каталог, если их там нет.
Если указано четвёртым параметром zip, то вместо копирования в указанном в третьем параметре
каталоге будет создан архив zip с именем карты.
Например:
php checkCovers.php $/Transas.9 # Проверить наличие тайлов, указанных в файле $/Transas.9 до масштаба в соответствии с конфигом
php checkCovers.php OpenTopoMap.9 14 # Проверить наличие тайлов до масштаба 14 включительно
php checkCovers.php $/OpenTopoMap.9 15 $/tiles # Сделать копию кеша, начиная с тайлов, указанных в файле $/OpenTopoMap.9, до масштаба 15 включительно, в каталоге $/tiles
php checkCovers.php $/OpenTopoMap.9 16 $ zip # В каталоге $ создать архив с кешем, начиная с тайлов, указанных в файле $/OpenTopoMap.9, до масштаба 16 включительно.

Если имеется параметр -sourceDir, то он заменяет $tileCacheDir из params.php, если нет ни того,
ни другого - тайлы ищутся в каталоге ./TileList
Если строка TileList имеет форму TileList_some\/str\/ing то тайлы ищутся по пути TileList/some/str/ing/

В каталог $checkCoversDataDirName/notFound кладутся файлы загрузчика, которые отсутствуют в текущем масштабе, хотя
в предыдущем масштабе тайл есть.
Соответственно, в отсутствующие не включены тайлы большего масштаба, если нет предыдущего. Предполагается, 
что отсутствующие тайлы отдадут загрузчику, а он загрузит и нижележащее.

*/

chdir(__DIR__); // задаем директорию выполнение скрипта
ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

//$clioptions = getopt("",array('from:','tozoom::','todir::','zip'));
//echo "clioptions: "; var_dump($clioptions); echo "\n";
// Нафига этот баян? Тут собираются просто параметры - типа, позиционные, и параметры с -
// Штатно нельзя смешать.
$n=0;
$clioptions = array();
foreach ($argv as $index => $arg) {
	echo "index=$index; n=$n; opt=$opt; arg=$arg;\n";
	if($arg[0]=='-'){
		$arg = ltrim($arg,'-');
		switch($arg){	// аргументы, которые могут не иметь значения
		case 'zip':
			$toZIP = true;
			continue 2;
		};
		// аргументы, значение которых - следующий элемент массива
		$opt = $arg;
		$n++;
	}
	elseif($opt){	// следующий аргумент после аргумента с - это его значение
		switch($opt){
		case 'maxZoom':
			$maxZoom = (int)$arg;
			break;
		case 'targetDir':
			$targetDir = $arg;
			break;
		case 'sourceDir':
			$sourceDir = $arg;
			break;
		case 'ext':
			$ext = $arg;
			break;
		default:
			$clioptions[$opt] = $arg;
		};
		$opt = false;
		$n++;
	}
	else{
		switch($index-$n){
		case 1:
			$loaderJobName = $arg;
			break;
		case 2:
			$maxZoom = (int)$arg;
			break;
		case 3:
			$targetDir = $arg;
			break;
		case 4:
			if($arg == 'zip') $toZIP = true;
			break;
		};
	};
};
if(($argc == 1) or !$loaderJobName) {
	echo "
Check if tile files exist and copy them to another dir or zip.
Usage:
php checkCovers.php TileList.Zoom [[[MaxZoom] targetDir] zip] | [-maxZoom int] [-sourceDir str] [-targetDir str] [-zip] [-ext]
where the
TileList.Zoom is text file with x,y in new line content.
TileList.Zoom may be a Loader job file. Then TileList is a MapName_MapLayer.

";
	exit(1);
};
echo "loaderJobName=$loaderJobName; maxZoom=$maxZoom; targetDir=$targetDir; toZIP=$toZIP; opt=$opt;\n";
//echo "clioptions:"; print_r($clioptions); echo "\n";

require('fCommon.php');
require('params.php'); 	// пути и параметры

$checkCoversDataDirName = 'checkCoversData';

$path_parts = pathinfo($loaderJobName);
//print_r($path_parts);
$originMapName = $path_parts['filename'];
list($mapName,$mapLayer) = explode('_',$originMapName);	// если это слой карты
if($mapLayer) $mapLayer = '/'.trim(trim(stripslashes($mapLayer)),'/');	// имеет / в начале и не имеет в конце. может быть номером или строкой. А строка может быть path.
else $mapLayer = '';
$zoom =  $path_parts['extension'];

require('mapsourcesVariablesList.php');	// потому что в файле источника они могут быть не все, и для новой карты останутся старые
require("$mapSourcesDir/$mapName.php");	// параметры карты

if($zoom > $maxZoom){
	error_log("checkCovers.php - The required zoom $zoom is greater than the max zoom $maxZoom, abort.");
	exit(1);
};
if(!isset($ext)){
	error_log("checkCovers.php - The tile file extension is not specified, it is impossible to get the file, abort.");
	exit(1);
};

if($sourceDir) $tileCacheDir = $sourceDir;
if(!isset($tileCacheDir)){
	error_log("checkCovers.php - The tile source directory is not specified, it is impossible to get the file, abort.");
	exit(1);
};
$tileCacheDir = trim($tileCacheDir,'/');	// запретим от корня
if(!$tileCacheDir) $tileCacheDir = '.';

if(isset($targetDir)){
	$targetDir = trim($arg,'/');	// запретим от корня
	if(!$targetDir) $targetDir = '.';
};

echo "Выполняется с loaderJobName=$loaderJobName; maxZoom=$maxZoom; targetDir=$targetDir; toZIP=$toZIP;\n";
exec("rm -rf $checkCoversDataDirName");	// удаляем каталог с результатами предыдущего
mkdir($checkCoversDataDirName);
mkdir("$checkCoversDataDirName/nextZoom");
echo "Карта $mapName";
if($mapLayer) echo ', слой '.$mapLayer;
echo "\n";
echo "до масштаба включительно: $maxZoom\n";

if($toZIP){
	$zip = new ZipArchive();
	$res = $zip->open("$targetDir/$originMapName.$zoom.zip", ZipArchive::CREATE);
	if(!$res) {
		error_log("checkCovers.php - Couldn't create a ZIP file $targetDir/$originMapName.$zoom.zip");
		exit(1);
	};
	echo "будет создан архив $targetDir/$originMapName.$zoom.zip\n";
}
elseif($targetDir) {
	echo "будет сделана копия в $targetDir/\n";
};
echo "\n";
$first = true;
do{
	echo "Обрабатываем $loaderJobName\n";
	$fp = @fopen($loaderJobName, "r");
	if(!$fp) {
		echo "\tнечего обрабатывать - такого файла нет, всё, приехали.\n";
		break;
	};
	$nextZoom = $zoom + 1;
	$notFound = 0; $emptyTiles = 0;
	$php = false;
	while (($tile = fgets($fp)) !== FALSE) {
		//echo"tile=$tile;\n";
		$tile = trim($tile);
		// Пропускаем ненужное
		if(!$tile) continue;	// пустая строка
		if($tile[0] == '#') continue;	// комментарий
		if(substr($tile,0,5)=='<?php') {	// php
			//echo "PHP begin\n";
			$php = true;
			continue;
		};
		if($php){
			if(substr($tile,-2)=='?>') {
				//echo "PHP end\n";
				$php = false;
			};
			continue;
		};
		$tile = explode(',',trim($tile));
		//echo "Тайл:"; print_r($tile); echo "zoom=$zoom;\n";
		$tileFileName = "$tileCacheDir/$mapName$mapLayer/$zoom/{$tile[0]}/{$tile[1]}.$ext";
		//echo "Путь к тайлу=$tileFileName;\n";
		clearstatcache(true,$tileFileName);
		$filesize=@filesize($tileFileName);
		if($filesize === false){	// echo "файла нет\n";
			if(!file_exists("$checkCoversDataDirName/notFound")) mkdir("$checkCoversDataDirName/notFound");	// хочу, чтобы каталог создавался, только если есть отсутствующие тайлы
			/*/ А зачем мы делали координаты? Возможно, тогда позиционирования карты по номеру тайла не было?
			$coord = tileNum2degree($zoom,$tile[0],$tile[1]);	// fcommon.php
			$res = file_put_contents("$checkCoversDataDirName/notFound/coordinates.$originMapName.$zoom",$coord['lat'].' '.$coord['lon']."\n",FILE_APPEND);
			if(!$res){
				error_log("checkCovers.php - Error of create notFound coordinates list $checkCoversDataDirName/notFound/coordinates.$originMapName.$zoom");
				exit(1);
			};
			/*/
			$res = file_put_contents("$checkCoversDataDirName/notFound/$originMapName.$zoom",$tile[0].','.$tile[1]."\n",FILE_APPEND);
			if(!$res){
				error_log("checkCovers.php - Error of create notFound loader job file $checkCoversDataDirName/notFound/$originMapName.$zoom");
				exit(1);
			};
			$notFound++;
			continue;
		}
		elseif($filesize == 0){	// echo "файл пустой\n";
			if(!file_exists("$checkCoversDataDirName/emptyTiles")) mkdir("$checkCoversDataDirName/emptyTiles");	// хочу, чтобы каталог создавался, только если есть пустые тайлы
			$coord = tileNum2degree($zoom,$tile[0],$tile[1]);
			$res = file_put_contents("$checkCoversDataDirName/emptyTiles/coordinates.$originMapName.$zoom",$coord['lat'].' '.$coord['lon']."\n",FILE_APPEND);
			if(!$res){
				error_log("checkCovers.php - Error of create emptyTiles coordinates list $checkCoversDataDirName/emptyTiles/coordinates.$originMapName.$zoom");
				exit(1);
			};
			$res = file_put_contents("$checkCoversDataDirName/emptyTiles/$originMapName.$zoom",$tile[0].','.$tile[1]."\n",FILE_APPEND);
			if(!$res){
				error_log("checkCovers.php - Error of create emptyTiles loader job file $checkCoversDataDirName/emptyTiles/$originMapName.$zoom");
				exit(1);
			};
			$emptyTiles++;
			continue;
		};
		//echo "файл есть\n";
		if($toZIP){	// указано делать архив
			if($zip->locateName("$mapName$mapLayer/$zoom/{$tile[0]}/{$tile[1]}.$ext") === false){
				$res=$zip->addFile("$tileCacheDir/$mapName$mapLayer/$zoom/{$tile[0]}/{$tile[1]}.$ext","$mapName/$zoom/{$tile[0]}/{$tile[1]}.$ext");
			};
		}
		elseif($targetDir){	// указано копировать тайлы
			if(!file_exists("$targetDir/$mapName$mapLayer/$zoom/{$tile[0]}/{$tile[1]}.$ext")){
				if(!is_dir("$targetDir/$mapName$mapLayer/$zoom/{$tile[0]}")){
					$res = mkdir("$targetDir/$mapName$mapLayer/$zoom/{$tile[0]}",0777,true);
					if(!$res){
						error_log("checkCovers.php - Error of create target dir $targetDir/$mapName$mapLayer/$zoom/{$tile[0]}");
						exit(1);
					};
				};
				//echo "from $tileCacheDir/$mapName$mapLayer/$zoom/{$tile[0]}/{$tile[1]}.$ext; to $targetDir/$mapName$mapLayer/$zoom/{$tile[0]}/{$tile[1]}.$ext\n";
				$res = copy("$tileCacheDir/$mapName$mapLayer/$zoom/{$tile[0]}/{$tile[1]}.$ext","$targetDir/$mapName$mapLayer/$zoom/{$tile[0]}/{$tile[1]}.$ext");
				if(!$res){
					error_log("checkCovers.php - Error of copy tile from $tileCacheDir/$mapName$mapLayer/$zoom/{$tile[0]}/{$tile[1]}.$ext; to $targetDir/$mapName$mapLayer/$zoom/{$tile[0]}/{$tile[1]}.$ext");
					exit(1);
				};
			};
		};
		
		// если тайлы есть - запишем в следующий файл задания 4 тайла следующего масштаба
		$tileS = nextZoom($tile);	// fcommon.php
		//print_r($xyS);
		foreach($tileS as $xy) {
			$res = file_put_contents("$checkCoversDataDirName/nextZoom/$originMapName.$nextZoom",$xy[0].','.$xy[1]."\n",FILE_APPEND);
			if(!$res){
				error_log("checkCovers.php - Error of create next zoom level loader job file $checkCoversDataDirName/nextZoom/$originMapName.$nextZoom");
				exit(1);
			};
		};
	};
	fclose($fp);
	if(!$first) unlink($loaderJobName);
	$loaderJobName = "$checkCoversDataDirName/nextZoom/$originMapName.$nextZoom";
	echo "processed $zoom zoom";
	if($notFound) echo ", not found $notFound tiles";
	if($emptyTiles) echo ", empty tiles $emptyTiles";
	echo "\n";
	$zoom = $nextZoom;
	$first = false;
}while($zoom <= $maxZoom);
if($toZIP) {
	echo "формируется архив...\n";
	$zip->close();
};
exec("rm -rf $checkCoversDataDirName/nextZoom");
echo "Done\n";
?>
