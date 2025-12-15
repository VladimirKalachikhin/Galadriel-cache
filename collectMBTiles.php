<?php
/* Собирает в $tileCacheDir файлы .mbtiles, для которых нет описания в $mapSourcesDir, и
создаёт для них в $mapSourcesDir описание на основе таблицы metadata.
Т.е., автоматизирует включение в коллекцию сторонних карт формата mbtiles

cli
*/
ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
chdir(__DIR__); // задаем директорию выполнение скрипта

require('fIRun.php'); 	// 
require 'fCommon.php';	// функции, используемые более чем в одном крипте
require 'fTilesStorage.php';	// стандартные функции получения тайла из локального источника

require('params.php'); 	// пути и параметры 

$mapDescribe = 
"<?php
// This extract data from the mbtiles file to variables, described in mapsourcesVariablesList.php,
// and must be first.
// Это получает значения некоторых переменных, указанных в mapsourcesVariablesList.php, из
// таблицы metainfo файла карты.
extract(SQLiteWrapper(basename(__FILE__,'.php'),'getMetainfo'));
// But you can override any of them, or completely refuse to receive them from the map by commenting
// the expression above.
// После можно указать свои значения, или вовсе отказаться от получения их из карты, закомментировав
// выражение выше.
";
$maps = glob("$mapSourcesDir/*.php");
array_walk($maps,function (&$str){$str=basename($str,'.php');});
$files = glob("$tileCacheDir/*.mbtiles");
array_walk($files,function (&$str){$str=basename($str,'.mbtiles');});
$maps = array_diff($files,$maps);
//echo "maps:"; print_r($maps); echo "\n";
$umask = umask(0); 	// сменим на 0777 и запомним текущую
foreach($maps as $mapName){
	if(($mapName[0]=='_') or ($mapName[0]=='.') or (substr($mapName, -1)=='~')) continue;	// пропускаем
	$describe = '';
	$metainfo = SQLiteWrapper($mapName,'getMetainfo');	//
	if($metainfo['ext'] == 'pbf'){
		$describe .= "\$vectorTileStyleURL = \"\$tileCacheServerPath/\$mapSourcesDir/$mapName.json\";\n";	// Даже если его и нет.
	};
	//echo "metainfo:"; print_r($metainfo); echo "\n";
	//foreach($metainfo as $name => $value){
	//	$describe .= var2code($name,$value)."\n";
	//};
	$describe .= 
"
\$getTile = 'getTileFromSQLite';
?>
";
	file_put_contents("$mapSourcesDir/$mapName.php",$mapDescribe.$describe);
	@chmod("$mapSourcesDir/$mapName.php",0666); 	// чтобы запуск от другого юзера
	echo "Created map description file $mapSourcesDir/$mapName.php\n";
};
umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера



function var2code($name,$value){
if($name) $string = "\$$name=";
else $string = '';
switch(gettype($value)){
case "boolean":
	if($value === true) $string .= 'true';
	else $string .= 'false';
	break;
case "integer":
case "double":
	$string .= "{$value}";
	break;
case "string":
	$string .= '"'.addslashes($value).'"';
	break;
case "array":
	$string .= "array(";
	foreach($value as $key=>$val){
		if(gettype($key)=="string") $string .= "\"$key\"=>";
		$string .= var2code('',$val).',';
	};
	$string = rtrim($string, ',');
	$string .= ")";
	break;
case "NULL":
	$string .= 'null';
	break;
case "object":
case "resource":
case "resource (closed)":
case "unknown type":
default:
};
if($name) $string .= ';';
return $string;
}; // end function var2code
?>

