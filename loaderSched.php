<?php
/* Планировщик заданий на загрузку тайлов. Видимо, запускается в одном экземпляре
cli only!
Просматривает файлы заданий, ставит их на скачивание, удаляет скачаные, создаёт задания следующего
масштаба. Запускает загрузчики.
*/
ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
chdir(__DIR__); // задаем директорию выполнение скрипта

require('fCommon.php'); 	// 
require('fIRun.php'); 	// 

require('params.php'); 	// пути и параметры
// Создадим рабочие каталоги - вдруг их нет?
$umask = umask(0); 	// сменим на 0777 и запомним текущую. Иначе не получится сделать mkdir с нужными прававми
@mkdir($jobsDir, 0777, true);
@mkdir($jobsInWorkDir, 0777, true);
umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера

if(IRun(basename(__FILE__))) {
	error_log("loaderSched.php - I'm already ruunning, exiting.");
	return;
};

// Занесём себя в crontab grep -v - инвертировать результат, т.е., в crontab заносится всё, кроме __FILE__
//exec("crontab -l | grep -v '".(basename(__FILE__))."'  | crontab -"); 	// удалим себя из cron, потому что я мог быть запущен cron'ом, а умерший - не мог удалить
//exec('(crontab -l ; echo "* * * * * '.getCurrentCommand().'  > /dev/null") | crontab -'); 	// каждую минуту  > /dev/null - это если cron настроен так, что шлёт письмо юзеру, если задание что-то вернуло
error_log("loaderSched.php - The Loader's scheduler started with pID ".getmypid());

$infinitely = '';
if(@$argv[1]=='--infinitely') $infinitely = '--infinitely';

$bannedSourcesFileName = "$jobsDir/bannedSources"; 	// служебный файл, куда загрузчик кладёт инфо о проблемах, а скачивальщик смотрит
@unlink($bannedSourcesFileName);	// удалим файл с информацией о проблемах источников - он мог сохраниться из-за краха
while($jobs = preg_grep('~.[0-9]$~', scandir($jobsDir))) {	// возьмём только файлы с цифровым расшрением
	//echo "Очередь заданий перед началом обработки:"; print_r($jobs); echo "\n";
	$loaderPIDs = array(); 	// запущенные процессы загрузки
	exec('ps ax | grep "loader.php"',$loaderPIDs);
	array_walk($loaderPIDs, function (&$str,$i){
		if(strpos($str,'ps ax')!==false) $str=null;
		if(strpos($str,'grep')!==false) $str=null;
		if($str) $str = trim(explode(' ',trim($str))[0]);
	});
	$loaderPIDs = array_filter($loaderPIDs);	// но на самом деле массив с PID загрузчиков нам не нужен. Нужно только их количество.
	//echo "Вероятно, есть загрузчики: "; print_r($loaderPIDs); echo "\n";
	//exit;
	// Проверим наличие, установим и снимем задания
	foreach($jobs as $i => $job) { 	// Ддля каждого файла задания
		//echo "Очередь заданий к началу обработки:"; print_r($jobs); echo "\n";
		if(!is_file("$jobsDir/$job")) { 	// этого задания уже нет
			//echo "$i -е задание $job не является заданием\n";
			unset($jobs[$i]);
			continue; 	// 
		};
		//echo "$jobsDir/$job \n$jobsInWorkDir/$job \n";
		error_log("loaderSched.php - There is a job $job");
		
		$path_parts = pathinfo($job);
		$mapName = $path_parts['filename'];
		list($mapName,$mapLayerNum) = explode('__',$mapName);	// если это слой карты
		$Zoom = $path_parts['extension'];
		//echo "mapName=$mapName; Zoom=$Zoom;\n";
		
		// загрузим параметры карты
		require('mapsourcesVariablesList.php');	// потому что в файле источника они могут быть не все, и для новой карты останутся старые
		$res=include("$mapSourcesDir/$mapName.php"); 	
		if(!$res){	// нет параметров для этой карты
			error_log("loaderSched.php - There is no map specified in this job. Let's kill the job.");
			unlink("$jobsDir/$job");	// убъём задание
			unset($jobs[$i]);
			continue;
		};
		// Проверим соответствие задания параметрам карты
		if($loaderMaxZoom > $maxZoom) $loaderMaxZoom = $maxZoom; 	// $maxZoom - из параметров карты
		if($Zoom > $loaderMaxZoom) {
			error_log("loaderSched.php - This job has a larger zoom than allowed, we will kill the job.");
			unlink("$jobsDir/$job");	// убъём задание
			unset($jobs[$i]);
			continue;
		};
		// Если рассматриваемое задание не выполняется
		if(!file_exists("$jobsInWorkDir/$job")) {
			if($mapLayerNum != null){	// это задание для слоя карты - просто поставим его на выполнение
				error_log("loaderSched.php - A new job $job. Let's put it on download.");
				$umask = umask(0); 	// сменим на 0777 и запомним текущую
				$res = copy("$jobsDir/$job","$jobsInWorkDir/$job"); 	// поставим на скачивание
				chmod("$jobsInWorkDir/$job",0666); 	// чтобы запуск от другого юзера
				umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
				if($res) continue;
				else {
					error_log("loaderSched.php - ERROR: The download task could not be placed.\nThe directory $jobsInWorkDir/$job is missing? No permitions to it?");
					break;
				};
			}
			else {	// это цельная карта
				if(is_array($mapTiles)){	
					// у нас составная или многослойная карта
					// В этом случае исходный файл задания нужно удалить, а вместо него сделать
					// сколько-то файлов заданий с тем же масштабом и содержанием, но на каждый слой.
					// Ставить их на скачивание не обязательно - они поставятся на следующем обороте.
					foreach($mapTiles as $k=>$layerInfo){
						if(is_array($layerInfo)){	// ссылка на составляющую карту
							// Сделаем файл задания для карты.
							// На следующем обороте его возмём в оборот как новое задание для карты
							copy("$jobsDir/$job","$jobsDir/{$layerInfo['mapName']}.$Zoom");
						}
						else {	// слой данной карты
							// Сделаем задание для слоя.
							copy("$jobsDir/$job","$jobsDir/$mapName".'__'."$k.$Zoom");
						};
					};
					echo "Created a lot of job files instead of the complex job file $jobsInWorkDir/$jobName. Complex job file removed.\n";
					unlink("$jobsDir/$job");	// убъём задание
					unset($jobs[$i]);
					continue;
				}
				else {	// карта однослойная, просто ставим задание на выполнение
					error_log("loaderSched.php - A new job $job. Let's put it on download.");
					$umask = umask(0); 	// сменим на 0777 и запомним текущую
					$res = copy("$jobsDir/$job","$jobsInWorkDir/$job"); 	// поставим на скачивание
					chmod("$jobsInWorkDir/$job",0666); 	// чтобы запуск от другого юзера
					umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
					if($res) continue;
					else {
						error_log("loaderSched.php - ERROR: The download task could not be placed.\nThe directory $jobsInWorkDir/$job is missing? No permitions to it?");
						break;
					};
				};
			};
		};
		// Рассматриваемое задание выполняется.
		clearstatcache(TRUE,"$jobsInWorkDir/$job");
		$fs = filesize("$jobsInWorkDir/$job"); 	// выполняющееся скачивание
		//echo "The size of the $jobsInWorkDir/$job is $fs bytes.\n";
		if($fs<=4 OR $fs==4096) { 	// условно - пустой файл, это задание завершилось
			error_log("loaderSched.php - The job $job is completed.");
			unlink("$jobsInWorkDir/$job");	// 
			if($Zoom >= $loaderMaxZoom) { 	//
				error_log("loaderSched.php - We've downloaded everything, we'll kill the job.");
				unlink("$jobsDir/$job");	// всё скачали, убъём задание
				unset($jobs[$i]);
				continue;
			};
			$nextJob = createNextZoomLevel("$jobsDir/$job",$minZoom); 	// создать файл с номерами тайлов следующего уровня, а текущий убъём ,$minZoom - из парамеров карты. При этом, если закончившееся задание было дополнено во время скачивания, то дополнения в этом задании пропадут. Но в следующий масштаб они попадут.
			error_log("loaderSched.php - We have created a new job $nextJob;");
			if($nextJob) { 	// его может не быть, если он уже есть
				$newZoom = substr($nextJob, strrpos($nextJob,'.')+1); 	// 
				if($newZoom > $maxZoom) unlink("$nextJob");	// что-то не так с масштабами?
				else {
					error_log("loaderSched.php - Let's set the zoom for downloading $newZoom");
					$umask = umask(0); 	// сменим на 0777 и запомним текущую
					copy("$nextJob","$jobsInWorkDir/" . basename($nextJob)); 	// поставим на скачивание следующий уровень
					chmod("$jobsInWorkDir/" . basename($nextJob),0666); 	// чтобы запуск от другого юзера
					umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
				};
			};
		};
	};	// конец перебора заданий
	//echo "Очередь заданий к концу обработки:"; print_r($jobs); echo "\n";
	
	// Если есть задания для загрузчиков -- созданные выше, или добавленные со стороны
	$loaderJobs = preg_grep('~.[0-9]$~', scandir($jobsInWorkDir)); 	// возьмём только файлы с цифровым расшрением
	// Удалим завершившиеся загружающиеся задания
	foreach($loaderJobs as $i => $jobName) { 	// 
		clearstatcache(TRUE,"$jobsInWorkDir/$jobName");
		$fs = filesize("$jobsInWorkDir/$jobName"); 	// выполняющееся скачивание
		//echo "Есть задание $jobName размером $fs\n";
		if($fs<=4 OR $fs==4096) { 	// условно - пустой файл, это задание завершилось
			echo "Deleting the completed job file $jobsInWorkDir/$jobName.\n";
			unlink("$jobsInWorkDir/$jobName");	
			unset($loaderJobs[$i]);
		};
	};
	if($loaderJobs) { 	// Если есть задания для загрузки
		error_log("loaderSched.php - There are jobs for loaders -- loaders are needed.");
		// Запустим указанное в конфиге количество загрузчиков
		$phpCLIexec = trim(explode(' ',getCurrentCommand())[0]);	// из PID получаем командную строку и берём первый отделённый пробелом элемент. Считаем, что он - команда запуска php. Должно работать и в busybox.
		//$execString = "$phpCLIexec loader.php $infinitely > /dev/null 2>&1 &";
		$execString = "$phpCLIexec loader.php $infinitely > /dev/null &";	// так в консоли видно и сообщения загрузчиков
		for($runs=count($loaderPIDs); $runs<$maxLoaderRuns; $runs++) { 	// если уже запущено меньше разрешённого количества загрузчиков - запустим ещё
			error_log("loaderSched.php - Launching another loader.");
			exec($execString,$output,$result);
			if($result==1){
				error_log("loaderSched.php - Start loader filed, abort. execString=$execString;");
				exit(1);
			};
		};
	};
	//break;
	sleep(5);
}; 	// пока есть задания для планировщика. Загрузчики останутся работать.

// удалим себя из cron
//exec("crontab -l | grep -v '".__FILE__."'  | crontab -");
// удалим файл с информацией о проблемах источников
@unlink($bannedSourcesFileName);	
error_log("loaderSched.php - The loader scheduler has ended.");
exit(0);




function createNextZoomLevel($jobName,$minZoom=0) {
/* Получает имя csv файла $jobName, с zoom в качестве раширения,
и для номеров тайлов в этом файле создаёт файл с номерами тайлов следующего, большего уровня
предыдущий убивает
Если требуемый масштаб меньше $minZoom - минимального масштаба из параметров карты,
 то создаёт файлы вплоть до $minZoom, т.е., задания для мелких масштабов, которых нет в карте
не останутся и не попадут к загрузчикам.
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
	};
	$res = flock($oldJob,LOCK_EX);
	if($res === false){
		error_log("loaderSched.php [createNextZoomLevel] Unable locking job file $jobName Error");
		exit(1);
	};
	//echo "nextJobName=$nextJobName\n";
	if(file_exists($nextJobName)) { 	//echo " такой файл уже может обрабатываться по какой-то причине\n";
		$runNextJobName = "$jobsInWorkDir/" . $path_parts['filename'] . ".$zoom";
		if(file_exists($runNextJobName)) rename($runNextJobName, $nextJobName); 	// заменим существующий файл задания ещё не выполненной его частью, удалив часть из выполняемых
	};
	$umask = umask(0); 	// сменим на 0777 и запомним текущую
	// создадим новый - откроем старый файл только для записи, чтобы никто больше не трогал
	$newJob = fopen($nextJobName,'a'); 	
	if($newJob === false){
		error_log("loaderSched.php [createNextZoomLevel] open new job file error");
		exit(1);
	};
	while(($str=fgets($oldJob)) !== false) {
		list($x,$y) = explode(',',$str);
		$x = trim($x);
		$y = trim($y);
		if((is_numeric($x)) and (is_numeric($y))) {
			$xyS = nextZoom(array($x,$y));
			foreach($xyS as $xy) {
				fwrite($newJob,$xy[0].','.$xy[1]."\n"); 	// запишем файл / допишем в существующий
			};
		}
		else fwrite($newJob,$str);
	};
	fclose($newJob);
	chmod($nextJobName,0666); 	// чтобы запуск от другого юзера
	fclose($oldJob);
	umask($umask); 	// 	Вернём. Зачем? Но umask глобальна вообще для всех юзеров веб-сервера
	unlink($jobName);	// прибъём старое задание
} while($zoom<$minZoom);
return $nextJobName;
}; // end function createNextZoomLevel

?>
