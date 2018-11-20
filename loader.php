<?php
/* Загрузчик 
Запускается в нескольких экземплярах
Читает файл задания, сокращает его, освобождает, и крутится, пока из файлов есть что читать
*/
$path_parts = pathinfo($_SERVER['SCRIPT_FILENAME']); // определяем каталог скрипта
chdir($path_parts['dirname']); // сменим каталог выполнение скрипта

require('params.php'); 	// пути и параметры

$pID = getmypid(); 	// process ID
file_put_contents("$jobsDir/$pID.lock", "$pID"); 	// положим флаг, что запустились
do {
	$jobNames = scandir($jobsInWorkDir);
	//echo ":<pre>"; print_r($jobs); echo "</pre>";
	array_walk($jobNames,function (&$name,$ind) {
			if(strpos($name,'~')!==FALSE) $name = NULL; 	// скрытые файлы
			if($name[0]=='.') $name = NULL; 	// скрытые файлы и каталоги
		}); 	// 
	sort($jobNames=array_unique($jobNames),SORT_NATURAL | SORT_FLAG_CASE); 	// 
	if(!$jobNames[0]) unset($jobNames[0]); 	// 
	shuffle($jobNames); 	// перемешаем массив, чтобы по возможности разные задания брались в обработку
	//echo ":<pre>"; print_r($jobNames); echo "</pre>";
	foreach($jobNames as $jobName) { 	// возьмём первый файл, которым можно заниматься
		//echo "jobsInWorkDir=$jobsInWorkDir; jobName=$jobName;\n";
		clearstatcache(TRUE,"$jobsInWorkDir/$jobName");
		if( is_file("$jobsInWorkDir/$jobName") AND (filesize("$jobsInWorkDir/$jobName") > 4) AND (filesize("$jobsInWorkDir/$jobName")<>4096)) break;
		else $jobName = FALSE;	
	}
	if(! $jobName) break; 	// просмотрели все файлы, не нашли, с чем работать - выход
	echo "Берём файл $jobName\n";
	//echo filesize("$jobsInWorkDir/$jobName") . " \n";
	$job = fopen("$jobsInWorkDir/$jobName",'r+'); 	// откроем файл
	if(!$job) break; 	// файла не оказалось
	flock($job,LOCK_EX) or exit("loader.php Unable locking job file Error");
	$strSize = strlen($s=fgets($job)); 	// размер первой строки в байтах
	if(!$strSize) break; 	// файл оказался пуст - выход.Хотя это мог быть и не последний файл....
	$res = fseek($job,-2*$strSize,SEEK_END); 	// сдвинем указатель на 2 строки к началу
	//echo ftell($job) . "\n";
	if($res == -1)  $xy = $s;	// сдвинуть не удалось - первая строка?
	else while(($s=fgets($job)) !== FALSE) $xy = $s;
	$pos = ftell($job);
	ftruncate($job,$pos-strlen($xy)) or exit("loader.php Unable truncated file $jobName"); 	// укоротим файл на строку
	fclose($job); 	// освободим файл
	$xy = str_getcsv($xy);
	$path_parts = pathinfo($jobName);
	$zoom = $path_parts['extension']; 	//
	$map = $path_parts['filename'];
	echo "карта $map;\n Тайл x=".$xy[0].", y=".$xy[1].", z=$zoom\n";
	//exit("res=$res pos=$pos s=$s $xy\n");
	$res = exec("$phpCLIexec tiles.php -z".$zoom." -x".$xy[0]." -y".$xy[1]." -r".$map); 	// загрузим тайл синхронно
	//echo "res=$res;\n";
} while($jobName);
unlink("$jobsDir/$pID.lock");	// 

?>
