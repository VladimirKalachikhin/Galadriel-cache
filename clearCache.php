<?php
/* Для указанной карты, или для всех, если не указана
при наличии списка мусорных файлов ($trash)
каждый файл проверяется по этому списку и удаляется, если есть в.
*/
$path_parts = pathinfo($_SERVER['SCRIPT_FILENAME']); // 
chdir($path_parts['dirname']); // задаем директорию выполнение скрипта

require('params.php'); 	// пути и параметры

if($argv) { 	// cli
	$mapName = @$argv[1]; 	// второй элемент - первый аргумент
}
else {	// http
	$mapName = $_REQUEST['r'];
}
//echo "mapName=$mapName;\n";
if($mapName) {
	clearMap($mapName);
	return;
}
// Получаем список имён карт
$mapsInfo = glob("$mapSourcesDir/*.php");
array_walk($mapsInfo,function (&$name,$ind) {
		$name=basename($name,'.php'); 	//
	}); 	// 
//echo ":<pre>"; print_r($mapsInfo); echo "</pre>";
foreach($mapsInfo as $mapName) {
	echo "Processing $mapName<br>\n";
	clearMap($mapName);
}

function clearMap($mapName) {
/* Для указанной карты при наличии списка мусорных файлов ($trash)
каждый файл проверяется по этому списку и удаляется, если есть в.
*/
global $mapSourcesDir, $tileCacheDir, $globalTrash;
@include("$mapSourcesDir/$mapName.php"); 	// а может, такой карты нет?
if($globalTrash) { 	// имеется глобальный список ненужных тайлов
	if($trash) $trash = array_merge($trash,$globalTrash);
	else $trash = $globalTrash;
}
//echo "trash:<pre>"; print_r($trash); echo "</pre>\n";
if(! @$trash) return "No trash list found";
//echo "$tileCacheDir/$mapName/*\n";
$zooms = preg_grep('~.[0-9]$~',glob("$tileCacheDir/$mapName/*",GLOB_ONLYDIR));
if($zooms === FALSE) exit("No access to map cache dir\n");
//echo "zooms:<pre>"; print_r($zooms); echo "</pre>\n";
foreach($zooms as $zoom) {
	$Xs = preg_grep('~.[0-9]$~',glob("$zoom/*",GLOB_ONLYDIR));
	if($Xs === FALSE) exit("No access to zoom level $zoom dir\n");
	//echo "Xs:<pre>"; print_r($Xs); echo "</pre>\n";
	foreach($Xs as $X) {
		$files = glob("$X/*"); 	// будем рассматривать любые файлы, не только тайлы
		if($files === FALSE) exit("No access to X level $X dir\n");
		//echo "files:<pre>"; print_r($files); echo "</pre>\n";
		foreach($files as $file) {
			$crc32 = hash_file('crc32b',$file);
			//echo "$crc32\n";
			if(in_array($crc32,$trash)) {
				echo "deleting $file\n";
				unlink($file);
			}
		}
	}
}
} // end function clearMap
?>

