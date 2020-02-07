<?php 
//ob_start(); 	// попробуем перехватить любой вывод скрипта
session_start(); 	// оно не нужно, но в источниках может использоваться, например, в navionics
require_once('fcache.php'); // getTile($uri)

$path_parts = pathinfo($_SERVER['SCRIPT_FILENAME']); // 
$selfPath = $path_parts['dirname'];
chdir($selfPath); // задаем директорию выполнение скрипта

$params = array();
if(@$argv) { 	// cli
	$options = getopt("z:x:y:r:",array('maxTry::','tryTimeout::'));
	//print_r($options);
	if($options) {
		$x = intval($options['x']);
		$y = intval($options['y']);
		$z = intval($options['z']);
		$r = filter_var($options['r'],FILTER_SANITIZE_URL);
		$uri = "$r/$z/$x/$y";
		if($options['maxTry']) $params['maxTry'] = intval($options['maxTry']);
		if($options['tryTimeout']) $params['tryTimeout'] = intval($options['tryTimeout']);
	}
	else $uri = filter_var($argv[1],FILTER_SANITIZE_URL);
}
else {
	$uri = filter_var($_REQUEST['uri'],FILTER_SANITIZE_URL); 	// запрос, переданный от nginx. Считаем, что это запрос тайла
}

//echo "Исходный uri=$uri; <br>\n";
if($uri) $img=getTile($uri,$params); 	// fcache.php собственно, получение

session_write_close();
if($runCLI) {
	if($img===FALSE) fwrite(STDOUT, '1'); 	// тайла не было и он не был получен
	else fwrite(STDOUT, '0');
}
//ob_flush();
//echo $newimg; 	// всё, вернём то, что удалось получить
return;

?>
