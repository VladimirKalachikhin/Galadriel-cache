#!/usr/bin/php
<?php
/* Планировщик заданий на загрузку тайлов. Видимо, запускается в одном экземпляре
Файлы заданий и файлы заданий в работе убиваются здесь, загрузчик файлы заданий не убивает
*/
chdir(__DIR__); // задаем директорию выполнение скрипта

require('fcommon.php');
require('params.php'); 	// пути и параметры

$pID = IRun(); 	// Запущен ли я? Возвращает process ID
if(!$pID) {
	echo "I'm already ruunning, exiting.\n";
	return;
}
file_put_contents("$jobsDir/$pID.slock", "$pID"); 	// положим флаг, что запустились
//echo "pID=$pID;\n";
// Занесём себя в cron
exec("crontab -l | grep -v '".__FILE__."'  | crontab -"); 	// удалим себя из cron, потому что я мог быть запущен cron'ом, а умерший - не мог удалить
exec('(crontab -l ; echo "* * * * * '.$phpCLIexec.' '.__FILE__.'") | crontab -'); 	// каждую минуту
echo "Планировщик запустился с pID $pID\n";

$bannedSourcesFileName = "$jobsDir/bannedSources"; 	// служебный файл, куда загрузчик кладёт инфо о проблемах, а скачивальщик смотрит
//@unlink($bannedSourcesFileName);	// удалим файл с информацией о проблемах источников - он мог сохраниться из-за краха
do {
	$jobs = scandir($jobsDir);
	$loaderPIDs = array(); 	// запущенные процессы загрузки
	//echo ":<pre>"; print_r($jobs); echo "</pre>";
	array_walk($jobs,function (&$name,$ind) {
			global $loaderPIDs;
			if(strpos($name,'~')!==FALSE) $name = NULL; 	// скрытые файлы
			if(strpos($name,'.')===FALSE) $name = NULL; 	// служебные файлы - без расширения
			if($name[0]=='.') $name = NULL; 	// скрытые файлы и каталоги
			if(substr($name,-5)=='slock') $name = NULL; 	// флаги запущенных планировщиков
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
	//exit;
	// Проверим наличие, установим и снимем задания
	foreach($jobs as $i => $job) { 	// Ддля каждого файла задания
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
		include_once("$mapSourcesDir/$mapName.php"); 	// загрузим параметры карты
		if($loaderMaxZoom > $maxZoom) $loaderMaxZoom = $maxZoom; 	// $maxZoom - из параметров карты
		if($Zoom > $loaderMaxZoom) {
			echo "Это задание имеет масштаб больше разрешённого, убъём задание\n";
			unlink("$jobsDir/$job");	// убъём задание
			unset($jobs[$i]);
			continue;
		}
		if(!file_exists("$jobsInWorkDir/$job")) { 	// задание не выполняется
			echo "Новое задание $job\n";
			if(!$functionGetURL) { 	// карту нельзя скачать
				echo "Карту нельзя скачать - нет функции для этого\n";
				unlink("$jobsDir/$job");	// убъём задание для планировщика
				unset($jobs[$i]);
				continue;
			}
			echo "Поставим его на скачивание\n";
			$umask = umask(0); 	// сменим на 0777 и запомним текущую
			$res = copy("$jobsDir/$job","$jobsInWorkDir/$job"); 	// поставим на скачивание
			chmod("$jobsInWorkDir/$job",0666); 	// чтобы запуск от другого юзера
			umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
			if($res) continue;
			else {
				echo "ERROR: Поставить задание на скачивание не удалось.\nКаталог $jobsInWorkDir/$job отсутствует? На него нет прав?\n";
				//error_log("LoaderShed: ERROR run job file.\nIs $jobsInWorkDir/$job exists?\n");
				break;
			}
		}
		clearstatcache(TRUE,"$jobsInWorkDir/$job");
		$fs = filesize("$jobsInWorkDir/$job"); 	// выполняющееся скачивание
		echo "Размер $jobsInWorkDir/$job - $fs байт.\n";
		if($fs<=4 OR $fs==4096) { 	// условно - пустой файл, это задание завершилось
			echo "Задание $job завершилось\n";
			unlink("$jobsInWorkDir/$job");	// 
			if($Zoom >= $loaderMaxZoom) { 	//
				echo "Всё скачали, убъём задание\n";
				unlink("$jobsDir/$job");	// всё скачали, убъём задание
				unset($jobs[$i]);
				continue;
			}
			$nextJob = createNextZoomLevel("$jobsDir/$job",$minZoom); 	// создать файл с номерами тайлов следующего уровня, а текущий убъём ,$minZoom - из парамеров карты. При этом, если закончившееся задание было дополнено во время скачивания, то дополнения в этом задании пропадут. Но в следующий масштаб они попадут.
			echo "Создали новое задание $nextJob; \n";
			if($nextJob) { 	// его может не быть, если он уже есть
				$newZoom = substr($nextJob, strrpos($nextJob,'.')+1); 	// 
				if($newZoom > $maxZoom) unlink("$nextJob");	// что-то не так с масштабами?
				else {
					echo "Поставим на скачивание масштаб $newZoom\n";
					$umask = umask(0); 	// сменим на 0777 и запомним текущую
					copy("$nextJob","$jobsInWorkDir/" . basename($nextJob)); 	// поставим на скачивание следующий уровень
					chmod("$jobsInWorkDir/" . basename($nextJob),0666); 	// чтобы запуск от другого юзера
					umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
				}
			}
		}
		//echo "Очередь заданий к концу обработки:"; print_r($jobs); echo "\n";
	}
	// Если есть задания для загрузчиков -- созданные выше, или добавленные со стороны
	$loaderJobNames = preg_grep('~.[0-9]$~', scandir($jobsInWorkDir)); 	// возьмём только файлы с цифровым расшрением
	foreach($loaderJobNames as $i => $jobName) { 	// 
		clearstatcache(TRUE,"$jobsInWorkDir/$jobName");
		$fs = filesize("$jobsInWorkDir/$jobName"); 	// выполняющееся скачивание
		if($fs<=4 OR $fs==4096) { 	// условно - пустой файл, это задание завершилось
			echo "Удаляем выполнившийся файл задания $jobsInWorkDir/$jobName\n";
			unlink("$jobsInWorkDir/$jobName");	
			unset($loaderJobNames[$i]);
		}
	}
	if($loaderJobNames) { 	// 
		echo "Есть задания для загрузчиков -- запускаем загрузчики\n";
		// Запустим указанное в конфиге количество загрузчиков
		$runs=0;
		foreach($loaderPIDs as $loaderRunPID) { 	// в $loaderPIDs выше могли быть добавлены левые pid с целью запустить загрузчики
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
		for($runs; $runs<$maxLoaderRuns; $runs++) { 	// если уже запущено меньше разрешённого количества загрузчиков - запустим ещё
			echo "Запускаем ещё загрузчик\n";
			exec("$phpCLIexec loader.php > /dev/null 2>&1 &");
			//exec("$phpCLIexec loader.php > /dev/null &");
			//exec("$phpCLIexec loader.php &");
		}
	}
//LOOP:
	//echo "runs=$runs; nextJob=$nextJob;\n";
	sleep(5);
} while($jobs); 	// пока есть задания для планировщика. Загрузчики останутся работать.

// удалим себя из cron
exec("crontab -l | grep -v '$fullSelfName'  | crontab -");

// удалим файл с информацией о проблемах источников
@unlink($bannedSourcesFileName);	

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
	chmod($nextJobName,0666); 	// чтобы запуск от другого юзера
	fclose($oldJob);
	umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
	unlink($jobName);	// прибъём старое задание
} while($zoom<$minZoom);
return $nextJobName;
} // end function createNextZoomLevel

function IRun() {
/* Возвращает FALSE если такой процесс уже запущен
если нет -- PID этого процесса
*/
global $phpCLIexec;
$pid = getmypid();
$this_phpCLIexec = pathinfo($phpCLIexec,PATHINFO_BASENAME);
//echo "pid=$pid; phpCLIexec=$phpCLIexec; this_phpCLIexec=$this_phpCLIexec;\n";
//echo "ps -A w | grep '".pathinfo(__FILE__,PATHINFO_BASENAME)." -s$netAISserverURI'\n";
exec("ps -A w | grep '".pathinfo(__FILE__,PATHINFO_BASENAME)."'",$psList);
if(!$psList) exec("ps w | grep '".pathinfo(__FILE__,PATHINFO_BASENAME)."'",$psList); 	// for OpenWRT. For others -- let's hope so all run from one user
//print_r($psList); //
$run = $pid;
foreach($psList as $str) {
	if(strpos($str,(string)$pid)!==FALSE) continue;
	$str = explode(' ',trim($str)); 	// массив слов
	foreach($str as $w) {
		if(!$w) continue;
		$w = pathinfo($w,PATHINFO_BASENAME);
		//echo "|$w|\n";
		switch($w){
		case 'watch':
		case 'ps':
		case 'grep':
		case 'sh':
		case 'bash': 	// если встретилось это слово -- это не та строка
			break 2;
		case $this_phpCLIexec:
			$run=FALSE;
			break 3;
		}
	}
}
return $run;
}


?>
