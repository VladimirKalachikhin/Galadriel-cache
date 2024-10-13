<?php
/* Для каждого источника, для которого указан $trueTile, скачивает этот тайл и заносит в лог результат
Еженедельный запуск (“At 00:00 on Sunday.”):
0 0 * * 0	/usr/bin/php /home/www-data/tileproxy/checkSources.php
*/
chdir(__DIR__); // задаем директорию выполнение скрипта
require('params.php'); 	// пути и параметры

$logFileName = 'checkSources.log';
file_put_contents($logFileName, date('d.m.Y H:i')." Следующий источники вернули тайл, отличающийся от правильного:\nThe following sources returned a tile different from the correct one:\n\n");

// Получаем список имён карт
$mapsInfo = glob("$mapSourcesDir/*.php");
array_walk($mapsInfo,function (&$name,$ind) {
		$name=basename($name,'.php'); 	//
	}); 	// 
//echo ":<pre>"; print_r($mapsInfo); echo "</pre>";

foreach($mapsInfo as $mapName) {
	$trueTile = FALSE;
	// Инициализируем переменные, которые могут быть в файле источника карты
	require('mapsourcesVariablesList.php');	// потому что в файле источника они могут быть не все, и для новой карты останутся старые
	require("$mapSourcesDir/$mapName.php");
	if(!$trueTile) continue;
	echo "Processing $mapName ... ";
	list($z,$x,$y,$hash)=$trueTile;
	$res = exec("$phpCLIexec tilefromsource.php -z$z -x$x -y$y -r$mapName --maxTry=15 --checkonly");
	if($res) {
		echo "no same tile.\n";
		file_put_contents($logFileName,"$mapName\t\t$phpCLIexec tilefromsource.php -z$z -x$x -y$y -r$mapName --maxTry=15 --checkonly\n",FILE_APPEND);
	}
	else {
		echo "ok.\n";
	};
};

?>
