<?php
/* Для каждого источника, для которого указан $trueTile, скачивает этот тайл и заносит в лог результат
Еженедельный запуск (“At 00:00 on Sunday.”):
0 0 * * 0	/usr/bin/php /home/www-data/tileproxy/checkSources.php
*/
ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
chdir(__DIR__); // задаем директорию выполнение скрипта
require('params.php'); 	// пути и параметры
require 'fTilesStorage.php';	// стандартные функции получения тайла из локального источника

$logFileName = 'checkSources.log';
file_put_contents($logFileName, date('d.m.Y H:i')." Следующий источники вернули тайл, отличающийся от правильного:\nThe following sources returned a tile different from the correct one:\n\n");

// Получаем список имён карт
$mapsInfo = glob("$mapSourcesDir/*.php");
array_walk($mapsInfo,function (&$name,$ind) {
		$name=basename($name,'.php'); 	//
	}); 	// 
natcasesort($mapsInfo);
$mapsInfo = array_values($mapsInfo);
//echo ":<pre>"; print_r($mapsInfo); echo "</pre>";

foreach($mapsInfo as $mapName) {
	$trueTile = FALSE;
	// Инициализируем переменные, которые могут быть в файле источника карты
	require('mapsourcesVariablesList.php');	// потому что в файле источника они могут быть не все, и для новой карты останутся старые
	require("$mapSourcesDir/$mapName.php");
	if(!$trueTile) continue;
	echo "Processing $mapName ... ";
	list($z,$x,$y,$hash)=$trueTile;
	$res = exec("$phpCLIexec tilefromsource.php -z$z -x$x -y$y -r$mapName --maxTry=15 --checkonly",$output,$exitcode);
	$res = trim($res);
	//echo "res=$res; exitcode=$exitcode;\n";
	if($exitcode) {
		file_put_contents($logFileName,"$mapName $res\n$phpCLIexec tilefromsource.php -z$z -x$x -y$y -r$mapName --maxTry=15 --checkonly\n\n",FILE_APPEND);
		echo "no same tile or no tile";
		if($res) echo " with res: $res";
		echo "\n";
	}
	else {
		echo "ok.";
		if($res){
			if(stripos($res,'is not true')!==false) echo ", but $res";
		};
		echo "\n";
	};
};

?>
