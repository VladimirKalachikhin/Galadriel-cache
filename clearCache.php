<?php
/* Для указанной карты, или для всех, если не указана
при наличии списка мусорных файлов ($trash)
каждый файл проверяется по этому списку и удаляется, если есть в.
Кроме того, если указан аргумент fresh - удаляются и протухшие тайлы
clearCache.php MapName fresh
*/
chdir(__DIR__); // задаем директорию выполнение скрипта

require('params.php'); 	// пути и параметры

$fresh = FALSE;
if($argv) { 	// cli
	if(@$argv[1] == 'fresh') {
		$mapName = '';
		$fresh = TRUE;
	}
	else {
		$mapName = @$argv[1]; 	// второй элемент - первый аргумент
		if(@$argv[2] == 'fresh') $fresh = TRUE;
	}
}
else {	// http
	$mapName = $_REQUEST['r'];
	$fresh = $_REQUEST['fresh'];
}
//echo "mapName=$mapName; fresh=$fresh;\n";
if($mapName) {
	clearMap($mapName,$fresh);
	return;
}
// Получаем список имён карт
$mapsInfo = glob("$mapSourcesDir/*.php");
array_walk($mapsInfo,function (&$name,$ind) {
		$name=basename($name,'.php'); 	//
	}); 	// 
//echo ":<pre>"; print_r($mapsInfo); echo "</pre>";
foreach($mapsInfo as $mapName) {
	echo "Processing $mapName\n";
	clearMap($mapName,$fresh);
}

function clearMap($mapName,$fresh=FALSE) {
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
//echo "$tileCacheDir/$mapName\n";
clearMapLayer("$tileCacheDir/$mapName",$trash,$fresh,$ttl,$noTileReTry,$ext); 	// рекурсивно обойдём дерево, потому что кеш может быть версионным
} // end function clearMap

function clearMapLayer($indir,$trash=array(),$fresh=FALSE,$ttl=0,$noTileReTry=0,$ext='png') {
/*
//$zooms = preg_grep('~.[0-9]$~',glob("$indir/*",GLOB_ONLYDIR)); 	// клёво же!
*/
//echo "Iteration: fresh=$fresh; ttl=$ttl; $indir\n";
$files = glob("$indir/*");
//echo "dirs:<pre>"; print_r($files); echo "</pre>\n";
foreach($files as $file) {
	if(is_dir($file))	clearMapLayer($file,$trash,$fresh,$ttl);
	else {
		//echo $file.' '.preg_match('~/*[0-9]\.'.$ext.'$~',$file)."\n";
		if($fresh){
			if(filesize($file)){	// 
				if($ttl AND (preg_match('~/*[0-9]\.'.$ext.'$~',$file)==1) AND ((time()-@filemtime($file)-$ttl)>0)) { 	// если это тайл и он протух и сказано освежить
					echo "deleting stinking tile $file\n";
					unlink($file);
				}
			}
			else {	// файл нулевого размера
				if($noTileReTry AND (preg_match('~/*[0-9]\.'.$ext.'$~',$file)==1) AND ((time()-@filemtime($file)-$noTileReTry)>0)) { 
					echo "deleting empty stinking tile $file\n";
					unlink($file);
				}
			}
		}
		elseif($trash){
			$crc32 = hash_file('crc32b',$file);
			//echo "$crc32\n";
			if(in_array($crc32,$trash,TRUE)) {
				echo "deleting trash tile $file\n";
				unlink($file);
			}
		}
	}
}
} // end function clearMapLayer
?>

