#!/usr/bin/php
<?php
/* Планировщик заданий на загрузку тайлов. Видимо, запускается в одном экземпляре
Файлы заданий и файлы заданий в работе убиваются здесь, загрузчик файлы заданий не убивает
*/
$path_parts = pathinfo(__FILE__); // определяем каталог скрипта
chdir($path_parts['dirname']); // задаем директорию выполнение скрипта

require('fcommon.php');
require('params.php'); 	// пути и параметры
$bannedSourcesFileName = "$jobsDir/bannedSources"; 	// служебный файл, куда загрузчик кладёт инфо о проблемах, а скачивальщик смотрит
//@unlink($bannedSourcesFileName);	// удалим файл с информацией о проблемах источников - он мог сохраниться из-за краха

//$loaderMaxZoom = 12; 	// скачивать до этого масштаба или максимального масштаба карты, если он меньше
//print_r($path_parts);
$fullSelfName = realpath(getcwd()).'/'.$path_parts['basename'];
$pID = getmypid(); 	// process ID
file_put_contents("$jobsDir/$pID.slock", "$pID"); 	// положим флаг, что запустились
//echo "pID=$pID;\n";
$inCron = false;
echo "Планировщик запустился с pID $pID\n";
//error_log("Планировщик: Планировщик запустился с pID $pID");
do {
	$jobs = scandir($jobsDir);
	$loaderPIDs = array(); 	// запущенные процессы загрузки
	$schedulerPIDs = array(); 	// запущенные процессы управления загрузками
	//echo ":<pre>"; print_r($jobs); echo "</pre>";
	array_walk($jobs,function (&$name,$ind) {
			global $loaderPIDs, $schedulerPIDs, $pID;
			if(strpos($name,'~')!==FALSE) $name = NULL; 	// скрытые файлы
			if(strpos($name,'.')===FALSE) $name = NULL; 	// служебные файлы - без расширения
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
	//echo "Очередь заданий перед началом обработки:"; print_r($jobs); echo "\n";
	//echo "Вероятно, есть загрузчики: "; print_r($loaderPIDs); echo "\n";
	//echo "Вероятно, есть планировщики: ";print_r($schedulerPIDs); echo "\n";
	//exit;
	// Проверим, запущен ли я
	$runsS = FALSE;
	foreach($schedulerPIDs as $schedulerRunPID) {
		if(file_exists( "/proc/$schedulerRunPID")) $runsS = $schedulerRunPID; 	// процесс с таким PID работает, и это не я
		else unlink("$jobsDir/$schedulerRunPID.slock"); 	// файл-флаг остался от чего-то, но процесс с таким PID не работает - удалим
	}
	if($runsS) {
		echo "Я - $pID, ещё один планировщик с PID $runsS уже работает!\n";
		//error_log("Планировщик: Я - $pID, ещё один планировщик с PID $runsS уже работает!");
		if($pID > $runsS) break; 	// если уже есть работающий планировщик, который был запущен раньше - убъём себя. Планировщики, запускающиеся по крону - должны умереть, если они не единственные.
	}
	// Всё, я работаю
	// Занесём себя в cron
	
	if(! $inCron) { 	// стараемся, чтобы в cron была только одна запись о запуске нас. Хотя это не должно плохо кончаться
		// удалим себя из cron, потому что я мог быть запущен cron'ом, а умерший - не мог удалить
		exec("crontab -l | grep -v '$fullSelfName'  | crontab -");
		exec('(crontab -l ; echo "* * * * * '.$phpCLIexec.' '.$fullSelfName.'") | crontab -'); 	// каждую минуту
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
		//error_log("Планировщик: Имеется задание $job");
		$path_parts = pathinfo($job);
		$mapName = $path_parts['filename'];
		$Zoom = $path_parts['extension'];
		//echo "mapName=$mapName; Zoom=$Zoom;\n";
		include("$mapSourcesDir/$mapName.php"); 	// загрузим параметры карты
		if($loaderMaxZoom > $maxZoom) $loaderMaxZoom = $maxZoom; 	// $maxZoom - из параметров карты
		if($Zoom > $loaderMaxZoom) {
			echo "Это задание имеет масштаб больше разрешённого, убъём задание\n";
			//error_log("Планировщик: Это задание имеет масштаб больше разрешённого, убъём задание");
			unlink("$jobsDir/$job");	// убъём задание
			unset($jobs[$i]);
			continue;
		}
		if(!file_exists("$jobsInWorkDir/$job")) { 	// задание не выполняется
			echo "Новое задание $job\n";
			//error_log("Планировщик: Новое задание $job");
			if(!$functionGetURL) {
				echo "Карту нельзя скачать - нет функции для этого\n";
				//error_log("Планировщик: Карту нельзя скачать - нет функции для этого");
				unlink("$jobsDir/$job");	// карту нельзя скачать, убъём задание
				unset($jobs[$i]);
				continue;
			}
			echo "Поставим его на скачивание\n";
			//error_log("Планировщик: Поставим его на скачивание");
			$umask = umask(0); 	// сменим на 0777 и запомним текущую
			$res = copy("$jobsDir/$job","$jobsInWorkDir/$job"); 	// поставим на скачивание
			chmod("$jobsInWorkDir/$job",0777); 	// чтобы запуск от другого юзера
			umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
			if($res) {
				$loaderPIDs[] = -1; 	// добавим заведомо несуществующий PID к списку запуценных загрузчиков, в знак того, что загрузчик надо запустить
				continue;
			}
			else {
				echo "ERROR: Поставить задание на скачивание не удалось.\nКаталог $jobsInWorkDir/$job отсутствует? На него нет прав?\n";
				//error_log("LoaderShed: ERROR run job file.\nIs $jobsInWorkDir/$job exists?\n");
				break;
			}
		}
		clearstatcache(TRUE,"$jobsInWorkDir/$job");
		$fs = filesize("$jobsInWorkDir/$job"); 	// выполняющееся скачивание
		//echo "Размер $jobsInWorkDir/$job - $fs байт.\n";
		if($fs<=4 OR $fs==4096) { 	// условно - пустой файл, это задание завершилось
			echo "Задание $job завершилось\n";
			//error_log("Планировщик: Задание $job завершилось");
			unlink("$jobsInWorkDir/$job");	// 
			require_once("$mapSourcesDir/$mapName.php"); 	// загрузим параметры карты
			if($Zoom >= $loaderMaxZoom) {
				echo "Всё скачали, убъём задание\n";
				//error_log("Планировщик: Всё скачали, убъём задание");
				unlink("$jobsDir/$job");	// всё скачали, убъём задание
				unset($jobs[$i]);
				continue;
			}
			$nextJob = createNextZoomLevel("$jobsDir/$job",$minZoom); 	// создать файл с номерами тайлов следующего уровня, а текущий убъём ,$minZoom - из парамеров карты. При этом, если закончившееся задание было дополнено во время скачивания, то дополнения в этом задании пропадут. Но в следующий масштаб они попадут.
			echo "Создали новое задание $nextJob; \n";
			//error_log("Планировщик: Создали новое задание $nextJob; ");
			if($nextJob) { 	// его может не быть, если он уже есть
				$newZoom = substr($nextJob, strrpos($nextJob,'.')+1); 	// 
				if($newZoom > $maxZoom) unlink("$nextJob");	// что-то не так с масштабами?
				else {
					echo "Поставим на скачивание масштаб $newZoom\n";
					//error_log("Планировщик: Поставим на скачивание масштаб $newZoom");
					$umask = umask(0); 	// сменим на 0777 и запомним текущую
					copy("$nextJob","$jobsInWorkDir/" . basename($nextJob)); 	// поставим на скачивание следующий уровень
					chmod("$jobsInWorkDir/" . basename($nextJob),0777); 	// чтобы запуск от другого юзера
					umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
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
			//exec("$phpCLIexec loader.php > /dev/null &");
			//exec("$phpCLIexec loader.php &");
			$runs++;
		}
		else break;
	}
	if($runs) { 	// если уже запущено меньше разрешённого количества загрузчиков. Иначе - не было заданий
		for($runs; $runs<$maxLoaderRuns; $runs++) { 	// запустим ещё
			echo "Запускаем ещё загрузчик\n";
			exec("$phpCLIexec loader.php > /dev/null 2>&1 &");
			//exec("$phpCLIexec loader.php > /dev/null &");
			//exec("$phpCLIexec loader.php &");
		}
	}
//LOOP:
	//echo "runs=$runs; nextJob=$nextJob;\n";
	sleep(5);
} while($jobs);

if(! $runsS) { 	// нет других запущенных экземпляров планировщика
	// удалим себя из cron
	exec("crontab -l | grep -v '$fullSelfName'  | crontab -");
	// удалим файл с информацией о проблемах источников
	@unlink($bannedSourcesFileName);	
	// удалим файлы выполняющихся заданий: раз мы здесь, задания на выполнение иссякли, и ничего больше скачивать не нужно
	$jobNames = preg_grep('~.[0-9]$~', scandir($jobsInWorkDir)); 	// возьмём только файлы с цифровым расшрением
	foreach($jobNames as $jobName) { 	// 
		//echo "Delete needless executing job file $jobsInWorkDir/$jobName\n";
		echo "Удаляем ненужный выполняющийся файл задания $jobsInWorkDir/$jobName\n";
		unlink("$jobsInWorkDir/$jobName");	
	}
}
unlink("$jobsDir/$pID.slock");	// 
echo "Планировщик $pID завершился\n";
//error_log("Планировщик: Планировщик $pID завершился");

return;


function createNextZoomLevel($jobName,$minZoom=0) {
/* Получает имя csv файла $jobName, с zoom в качестве раширения,
и для номеров тайлов в этом файле создаёт файл с номерами тайлов следующего, большего уровня
предыдущий убивает
Если требуемый масштаб меньше $minZoom - минимального масштаба из параметров карты,
 то создаёт файлы вплоть до $minZoom
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
	$umask = umask(0); 	// сменим на 0777 и запомним текущую
	$newJob = fopen($nextJobName,'a'); 	// создадим новый - откроем старый файл только для записи, чтобы никто больше не трогал
	while(($xy=fgets($oldJob)) !== FALSE) {
		if(trim($xy[0])=='#') {
			fwrite($newJob,"$xy") or exit("loaderSched.php createNextZoomLevel() write 0 error\n"); 	// запишем файл / допишем в существующий
			continue;
		}
		$xy = str_getcsv(trim($xy));
		if((!is_numeric($xy[0])) OR (!is_numeric($xy[1]))) continue; 	// вдруг в файле фигня
		$xyS = nextZoom($xy);
		//print_r($xyS);
		foreach($xyS as $xy) {
			fwrite($newJob,$xy[0].','.$xy[1]."\n") or exit("loaderSched.php createNextZoomLevel() write 1 error\n"); 	// запишем файл / допишем в существующий
		}
	}
	fclose($newJob);
	chmod($nextJobName,0777); 	// чтобы запуск от другого юзера
	fclose($oldJob);
	umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
	unlink($jobName);	// прибъём старое задание
} while($zoom<$minZoom);
return $nextJobName;
} // end function createNextZoomLevel
?>
