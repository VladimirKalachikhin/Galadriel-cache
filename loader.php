<?php 
/* Загрузчик 
Запускается в нескольких экземплярах
Читает файл задания, сокращает его, освобождает, и крутится, пока из файлов есть что читать
Планировщик стремится уделить каждой карте одинаковое время с точностью до $lag
В результате карты из тормозных источников будут скачиваться медленней менее чем вдвое, 
зато из быстрых - быстрее в десятки раз.
*/
$path_parts = pathinfo($_SERVER['SCRIPT_FILENAME']); // определяем каталог скрипта
chdir($path_parts['dirname']); // сменим каталог выполнение скрипта

require('params.php'); 	// пути и параметры
$bannedSourcesFileName = "$jobsDir/bannedSources";

$pID = getmypid(); 	// process ID
$timer = array(); 	// массив для подсчёта затраченного времени
$lag = 300; 	// сек, на которое может отличатся время, затраченное на карту, от среднего, чтобы карта не подвергалась регулировке затраченного времени. Чем больше - тем ближе скорость скачивания к скорости отдачи для примерно одинаковых по производительности источников, но больше тормозит всё самый медленный.
file_put_contents("$jobsDir/$pID.lock", "$pID"); 	// положим флаг, что запустились
echo "Стартовал загрузчик $pID\n";
do {
	$jobNames = preg_grep('~.[0-9]$~', scandir($jobsInWorkDir)); 	// возьмём только файлы с цифровым расшрением
	shuffle($jobNames); 	// перемешаем массив, чтобы по возможности разные задания брались в обработку
	//echo ":<pre> jobNames "; print_r($jobNames); echo "</pre>";
	// проблемные источники
	$bannedSources = unserialize(@file_get_contents($bannedSourcesFileName));
	//echo ":<pre> bannedSources "; print_r($bannedSources); echo "</pre>\n";
	// Выбор файла задания
	foreach($jobNames as $jobName) { 	// возьмём первый файл, которым можно заниматься
		//echo "jobsInWorkDir=$jobsInWorkDir; jobName=$jobName;\n";
		$path_parts = pathinfo($jobName);
		$zoom = $path_parts['extension']; 	//
		$map = $path_parts['filename'];
		if($bannedSources[$map]) { 	// проигнорируем задание для проблемного источника
			echo "Бросаем файл $jobName - источник с проблемами\n\n";
			continue;	
		}
		clearstatcache(TRUE,"$jobsInWorkDir/$jobName");
		if( is_file("$jobsInWorkDir/$jobName") AND (filesize("$jobsInWorkDir/$jobName") > 4) AND (filesize("$jobsInWorkDir/$jobName")<>4096)) break; 	// выбрали файл для обслуживания
		else $jobName = FALSE;	
	}
	if(! $jobName) break; 	// просмотрели все файлы, не нашли, с чем работать - выход
	// Выбрали файл задания - всё ли с ним хорошо?
	echo "Берём файл $jobName\n";
	//echo filesize("$jobsInWorkDir/$jobName") . " \n";
	// Планировщик времени
	if(count($jobNames)<count($timer)) $timer=array(); 	// статистика какого-то завершившегося задания присутствует в $timer, и среднее будет неправильно 
	$ave = ((@max($timer)+@min($timer))/2)+$lag; 	// среднее плюс допустимое
	if($timer[$map]>$ave) continue; 	// пропустим эту карту, если на неё уже затрачено много времени
	// Есть ли ещё файл?
	$job = fopen("$jobsInWorkDir/$jobName",'r+'); 	// откроем файл
	if(!$job) break; 	// файла не оказалось
	flock($job,LOCK_EX) or exit("loader.php Unable locking job file Error");
	$strSize = strlen($s=fgets($job)); 	// размер первой строки в байтах
	if(!$strSize) break; 	// файл оказался пуст - выход.Хотя это мог быть и не последний файл....
	// Возьмём последний тайл
	$res = fseek($job,-2*$strSize,SEEK_END); 	// сдвинем указатель на 2 строки к началу
	//echo ftell($job) . "\n";
	if($res == -1)  $xy = $s;	// сдвинуть не удалось - первая строка?
	else while(($s=fgets($job)) !== FALSE) $xy = $s;
	$pos = ftell($job);
	ftruncate($job,$pos-strlen($xy)) or exit("loader.php Unable truncated file $jobName"); 	// укоротим файл на строку
	fclose($job); 	// освободим файл
	$xy = str_getcsv($xy);
	//exit("res=$res pos=$pos s=$s $xy\n");
	$now = microtime(TRUE);
	// Запустим скачивание
	$res = exec("$phpCLIexec tiles.php -z".$zoom." -x".$xy[0]." -y".$xy[1]." -r".$map); 	// загрузим тайл синхронно
	//echo "res=$res;\n";
	$now=microtime(TRUE)-$now;
	$timer[$map] += $now;
	echo "Карта $map, на неё затрачено ".$timer[$map]."сек. при среднем допустимом $ave сек.\nПолучен тайл x=".$xy[0].", y=".$xy[1].", z=$zoom за $now сек.\n\n";
} while($jobName);
unlink("$jobsDir/$pID.lock");	// 
echo "Загрузчик $pID завершился\n";

?>
