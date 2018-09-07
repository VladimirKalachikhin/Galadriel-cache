#!/usr/bin/php
<?php
/* Планировщик заданий на загрузку тайлов. Видимо, запускается в одном экземпляре
*/
$path_parts = pathinfo($_SERVER['SCRIPT_FILENAME']); // 
chdir($path_parts['dirname']); // задаем директорию выполнение скрипта

require('fcommon.php');
require('params.php'); 	// пути и параметры

//$loaderMaxZoom = 12; 	// скачивать до этого масштаба или максимального масштаба карты, если он меньше
//print_r($path_parts);
$fullSelfName = realpath(getcwd()).'/'.$path_parts['basename'];
$pID = getmypid(); 	// process ID
file_put_contents("$jobsDir/$pID.slock", "$pID"); 	// положим флаг, что запустились
//echo "pID=$pID;\n";
$inCron = false;
echo "Планировщик запустился с pID $pID\n";
do {
	$jobs = scandir($jobsDir);
	$loaderPIDs = array(); 	// запущенные процессы загрузки
	$schedulerPIDs = array(); 	// запущенные процессы управления загрузками
	//echo ":<pre>"; print_r($jobs); echo "</pre>";
	array_walk($jobs,function (&$name,$ind) {
			global $loaderPIDs, $schedulerPIDs, $pID;
			if(strpos($name,'~')!==FALSE) $name = NULL; 	// скрытые файлы
			if($name[0]=='.') $name = NULL; 	// скрытые файлы и каталоги
			if(substr($name,-5)=='slock') { 	// выберем оттуда флаги запущенных планировщиков
				$schPID = substr($name,0,strpos($name,'.')); 	// имена файлов-PID процессов-планировщиков
				if($schPID <> $pID) $schedulerPIDs[] = $schPID; 	// если это не я
				$name = NULL;
			}
			if(substr($name,-4)=='lock') { 	// выберем оттуда флаги запущенных загрузчиков
				$loaderPIDs[] = substr($name,0,strpos($name,'.')); 	// имена файлов-PID процессов-загрузчиков
				$name = NULL;
			}
		}); 	// 
	$jobs=array_unique($jobs);
	sort($jobs,SORT_NATURAL | SORT_FLAG_CASE); 	// 
	if(!$jobs[0]) unset($jobs[0]); 	// 
	//echo "Вероятно, есть загрузчики: "; print_r($loaderPIDs); echo "\n";
	//echo "Вероятно, есть планировщики: ";print_r($schedulerPIDs); echo "\n";
	//exit;
	// Проверим, запущен ли я
	$runsS = FALSE;
	foreach($schedulerPIDs as $schedulerRunPID) {
		if(file_exists( "/proc/$schedulerRunPID")) $runsS = $schedulerRunPID; 	// процесс с таким PID работает, и это не я
		else unlink("$jobsDir/$schedulerRunPID.slock");
	}
	if($runsS) {
		echo "Ещё один планировщик PID $runsS уже работает!\n";
		break; 	// если уже есть работающий планировщик - прекратим
	}
	// Занесём себя в cron
	if(! $inCron) { 	// стараемся, чтобы в cron была только одна запись о запуске нас. Хотя это не должно плохо кончаться
		// удалим себя из cron, потому что я мог быть запущен cron'ом, а умерший - не мог удалить
		exec("crontab -l | grep -v '$fullSelfName'  | crontab -");
		exec('(crontab -l ; echo "* * * * * '.$fullSelfName.'") | crontab -'); 	// каждую минуту
		$inCron = TRUE;
	}
	// Проверим наличие, установим и снимем задания
	foreach($jobs as $i => $job) {
		//echo "Очередь заданий к началу обработки:"; print_r($jobs); echo "\n";
		if(!is_file("$jobsDir/$job")) { 	// это не задание
			//echo "$i -е задание $job не является заданием\n";
			unset($jobs[$i]);
			continue; 	// 
		}
		//echo "$jobsDir/$job \n$jobsInWorkDir/$job \n";
		echo "Имеется задание $job\n";
		$path_parts = pathinfo($job);
		$mapName = $path_parts['filename'];
		$Zoom = $path_parts['extension'];
		//echo "mapName=$mapName; Zoom=$Zoom;\n";
		if(!file_exists("$jobsInWorkDir/$job")) { 	// задание не выполняется
			echo "Новое задание\n";
			include("$mapSourcesDir/$mapName.php"); 	// загрузим параметры карты
			if(!$functionGetURL) {
				echo "Карту нельзя скачать - нет функции для этого\n";
				unlink("$jobsDir/$job");	// карту нельзя скачать, убъём задание
				unset($jobs[$i]);
				continue;
			}
			echo "Поставим его на скачивание\n";
			copy("$jobsDir/$job","$jobsInWorkDir/$job"); 	// поставим на скачивание
			chmod("$jobsInWorkDir/$job",0777); 	// чтобы запуск от другого юзера
			$loaderPIDs[] = -1; 	// добавим заведомо несуществующий PID к списку запуценных загрузчиков, в знак того, что загрузчик надо запустить
			continue;
		}
		clearstatcache(TRUE,"$jobsInWorkDir/$job");
		$fs = filesize("$jobsInWorkDir/$job"); 	// выполняющееся скачивание
		//echo "Размер $jobsInWorkDir/$job - $fs байт.\n";
		if($fs<4 OR $fs==4096) { 	// условно - пустой файл, это задание завершилось
			echo "Задание $job завершилось\n";
			unlink("$jobsInWorkDir/$job");	// 
			require_once("$mapSourcesDir/$mapName.php"); 	// загрузим параметры карты
			if($loaderMaxZoom > $maxZoom) $loaderMaxZoom = $maxZoom;
			if($Zoom >= $loaderMaxZoom) {
				echo "Всё скачали, убъём задание\n";
				unlink("$jobsDir/$job");	// всё скачали, убъём задание
				unset($jobs[$i]);
				continue;
			}
			$nextJob = createNextZoomLevel("$jobsDir/$job",$minZoom); 	// создать файл с номерами тайлов следующего уровня, а текущий убъём
			echo "Создали новое задание $nextJob; \n";
			if($nextJob) { 	// его может не быть, если он уже есть
				$newZoom = substr($nextJob, strrpos($nextJob,'.')+1); 	// 
				if($newZoom > $maxZoom) unlink("$nextJob");	// что-то не так с масштабами?
				else {
					echo "Поставим на скачивание масштаб $newZoom\n";
					copy("$nextJob","$jobsInWorkDir/" . basename($nextJob)); 	// поставим на скачивание следующий уровень
					chmod("$jobsInWorkDir/" . basename($nextJob),0777); 	// чтобы запуск от другого юзера
					$loaderPIDs[] = -1; 	// добавим заведомо несуществующий PID к списку запуценных загрузчиков, в знак того, что загрузчик надо запустить
				}
			}
		}
		elseif(!$loaderPIDs) $loaderPIDs[] = -1; 	// задание поставлено на загрузку, но нет ни одного загрузчика
		//echo "Очередь заданий к концу обработки:"; print_r($jobs); echo "\n";
	}
	// Запустим указанное в конфиге количество загрузчиков
	$runs=0;
	foreach($loaderPIDs as $loaderRunPID) { 	// 
		if(file_exists( "/proc/$loaderRunPID")){ 	// процесс с таким PID работает
			echo "Работает загрузчик $loaderRunPID\n";
			$runs++;
			continue;
		}
		@unlink("$jobsDir/$loaderRunPID.lock"); 	// процесса с таким PID нет, удалим файл с PID. Но и файла к этому моменту может уже не быть
		if($runs<$maxLoaderRuns) {
			echo "Запускаем загрузчик\n";
			exec("$phpCLIexec loader.php > /dev/null 2>&1 &");
			$runs++;
		}
		else break;
	}
	if($runs) { 	// если уже запущено меньше разрешённого количества загрузчиков. Иначе - не было заданий
		for($runs; $runs<$maxLoaderRuns; $runs++) { 	// запустим ещё
			echo "Запускаем ещё загрузчик\n";
			exec("$phpCLIexec loader.php > /dev/null 2>&1 &");
		}
	}
	//echo "runs=$runs; nextJob=$nextJob;\n";
	sleep(5);
} while($jobs);

echo "Планировщик завершился\n";
unlink("$jobsDir/$pID.slock");	// 
if(! $runsS) { 	// нет других запущенных экземпляров планировщика
	// удалим себя из cron
	exec("crontab -l | grep -v '$fullSelfName'  | crontab -");
}




function createNextZoomLevel($jobName,$minZoom) {
/* Получает имя csv файла $jobName, с zoom в качестве раширения,
и для номеров тайлов в этом файле создаёт файл с номерами тайлов следующего, большего уровня
предыдущий убивает
Если требуемый масштаб меньше $minZoom, то создаёт файлы вплоть до $minZoom
Возвращает имя полученного файла или пусто.
*/
$path_parts = pathinfo($jobName);
$zoom = $path_parts['extension']; 	//
$mapName = $path_parts['dirname'].'/'.$path_parts['filename'];
//echo "mapName=$mapName; Zoom=$zoom;\n";
global $jobsInWorkDir;
do {
	$zoom++;
	$nextJobName="$mapName.$zoom";
	//echo "nextJobName=$nextJobName\n";
	$oldJob = fopen($jobName,'r');
	if(!$oldJob) { 	//echo " другой поцесс убил файл?\n";
		$nextJobName = NULL;
		break;
	}
	flock($oldJob,LOCK_EX) or exit("loader.php Unable locking job file $jobName Error\n");
	//echo "nextJobName=$nextJobName\n";
	if(file_exists($nextJobName)) { 	//echo " такой файл уже может обрабатываться по какой-то причине\n";
		$runNextJobName = "$jobsInWorkDir/" . $path_parts['filename'] . ".$zoom";
		if(file_exists($runNextJobName)) 		rename($runNextJobName, $nextJobName); 	// заменим существующий файл задания ещё не выполненной его частью, удалив часть из выполняемых
	}
	$newJob = fopen($nextJobName,'a'); 	// создадим новый - откроем старый файл только для записи, чтобы никто больше не трогал
	while(($xy=fgetcsv($oldJob)) !== FALSE) {
		if((!is_numeric($xy[0])) OR (!is_numeric($xy[1]))) continue; 	// вдруг в файле фигня
		$xyS = nextZoom($xy);
		//print_r($xyS);
		foreach($xyS as $xy) {
			fwrite($newJob,$xy[0].','.$xy[1]."\n") or exit("loaderSched.php createNextZoomLevel() write error\n"); 	// запишем файл / допишем в существующий
		}
	}
	fclose($newJob);
	chmod($nextJobName,0777); 	// чтобы запуск от другого юзера
	fclose($oldJob);
	unlink($jobName);	// прибъём старое задание
} while($zoom<$minZoom);
return $nextJobName;
} // end function createNextZoomLevel
?>
