<?php
/* Загрузчик 
cli only!
Запускается в нескольких экземплярах.
Каждый загрузчик обслуживает все файлы заданий, по очереди. Все загрузчики одинаковы.
Поэтому, скорость скачивания должна линейно зависить от количества загрузчиков.

Каждый оборот основного цикла загрузчик берёт список файлов заданий и выбирает из них
подходящий.
Из выбранного файла задания загрузчик читает одну строку с координатами тайла с конца файл задания, 
сокращает файл на строку и освобождает, после чего файл может читать другой загрузчик.

Это происходит, пока из файлов есть что читать. Если все файлы пустые - загрузчик завершается.
Предполагается, что пустые файлы удалит планировщик.

Загрузчик стремится уделить каждой карте одинаковое время с точностью до $lag
В результате карты из тормозных источников будут скачиваться медленней менее чем вдвое, 
зато из быстрых - быстрее в десятки раз.

Непосредственно для скачивания будет запущена системная команда $execString
В первой строке файла задания, может быть своя $execString. 
Эта строка должна начнаться с #
Специальная $execString может быть использована для копирования части кеша:
# cp -Hpu --parents $tileCacheDir/$r/$z/$x/$y /home/stager/tileCacheCopy/
 - в этом случае создадутся отсутствующие каталоги, и весь исходный путь будет добавлен к целевому
# mkdir -p /home/stager/tileCacheCopy/$r/$z/$x/ && cp -Hpu $tileCacheDir/$r/$z/$x/$y /home/stager/tileCacheCopy/$r/$z/$x/
 - в этом случае сперва создадутся каталоги, потом произойдёт копирование

Кроме того, в начале файла может быть указан php функция вида
<?php
$getTile = function ($r,$z,$x,$y,$options=array()){$img=null;return array('img'=>$img);}; 	
?>
которая будет применена для получения тайла. В функцииогут быть использованы переменные из params.php
и описания карты, так же, как это описано в mapsourcesVariablesList.php
*/
ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
chdir(__DIR__); // задаем директорию выполнение скрипта

$infinitely = false;
if(@$argv[1]=='--infinitely') $infinitely = true;

require('fCommon.php');	// не используется здесь, но в fTilesStorage.php могут применятся любые функции
require('fIRun.php'); 	// 

require('params.php'); 	// пути и параметры
require 'fTilesStorage.php';	// стандартные функции получения/записи тайла из локального хранилища
$bannedSourcesFileName = "$jobsDir/bannedSources";
$maxTry = 5 * $maxTry; 	// увеличим количество попыток получить файл

$pID = getmypid(); 	// process ID
$timer = array(); 	// массив для подсчёта затраченного времени
$lag = 300; 	// сек, на которое может отличатся время, затраченное на карту, от среднего, чтобы карта не подвергалась регулировке затраченного времени. Чем больше - тем ближе скорость скачивания к скорости отдачи для примерно одинаковых по производительности источников, но больше тормозит всё самый медленный.
//error_log("loader.php - The loader $pID has started");
do {
	clearstatcache(TRUE);
	$jobNames = preg_grep('~.[0-9]$~', scandir($jobsInWorkDir)); 	// возьмём только файлы с цифровым расшрением
	shuffle($jobNames); 	// перемешаем массив, чтобы по возможности разные задания брались в обработку
	$jobCNT = count($jobNames);
	if(!$jobCNT) { 	// нет заданий - выход
		//error_log("loader.php $pID - No jobs to executing - break.");
		break;
	};
	// проблемные источники
	$bannedSources = unserialize(@file_get_contents($bannedSourcesFileName));	// заполняется tilefromsource.php
	//echo ":<pre> bannedSources "; print_r($bannedSources); echo "</pre>\n";
	//echo ":<pre> timer "; print_r($timer); echo "</pre>\n";
	
	// Выбор файла задания
	echo "Has ".count($jobNames)." job files.\n";
	foreach($jobNames as $jobName) { 	// возьмём первый файл, которым можно заниматься
		//echo "jobsInWorkDir=$jobsInWorkDir; jobName=$jobName;\n";
		$path_parts = pathinfo($jobName);
		$zoom = $path_parts['extension']; 	//
		$map = $path_parts['filename'];
		list($map,$mapLayer) = explode('__',$map);	// если это слой карты
		$mapLayer = stripslashes($mapLayer);	// может быть номером или строкой. А строка может быть path.
		if(@$bannedSources[$map]) { 	// источник с проблемами
			if((time()-$bannedSources[$map][0]-($noInternetTimeout*1))<0) {	// если многократный таймаут из конфига не истёк
				unset($timer[$jobName]); 	// удалим из планировки загрузки
				//error_log("loader.php $pID - Drop the file $jobName - troubled source {$bannedSources[$map][1]}");
				$jobName = FALSE; 	// других может и не быть
				continue;	// проигнорируем задание для проблемного источника
			}
			else { 				// 	иначе - таки запустим скачивание
				//error_log("loader.php $pID - Trying $jobName - troubled source {$bannedSources[$map][1]}");
				break;
			};
		};
		//echo "jobName=$jobName; is_file($jobsInWorkDir/$jobName)=".is_file("$jobsInWorkDir/$jobName")." filesize($jobsInWorkDir/$jobName)=".filesize("$jobsInWorkDir/$jobName")." \n";
		// Проверим, что файл задания есть и не пуст
		if( $jobName AND is_file("$jobsInWorkDir/$jobName") AND (filesize("$jobsInWorkDir/$jobName") > 4) AND (filesize("$jobsInWorkDir/$jobName")<>4096)) break; 	// выбрали файл для обслуживания
		else $jobName = FALSE;	
	};
	// просмотрели все файлы заданий, не нашли, с чем работать - выход
	if(! $jobName) { 	
		//error_log("loader.php $pID - No jobs to executing - break.");
		break;
	};
	// Выбрали файл задания - всё ли с ним хорошо?
	error_log("loader.php $pID - Take the job file $jobName");
	//echo "размером ".filesize("$jobsInWorkDir/$jobName") . " \n";
	// Планировщик времени
	if($jobCNT > 1) { 	# не надо планировать, если только одно задание
		if($jobCNT<count($timer)) $timer=array(); 	// статистика какого-то завершившегося задания присутствует в $timer, и среднее будет неправильно 
		$ave = ((@max($timer)+@min($timer))/2)+$lag; 	// среднее плюс допустимое
		if($timer[$jobName]>$ave) { 	// пропустим эту карту, если на неё уже затрачено много времени
			//error_log("drop it - it's time consuming");
			//echo ":<pre> timer "; print_r($timer); echo "</pre>\n";
			continue;
		};
	};
	// Существует ли файл до сих пор?
	clearstatcache(TRUE,"$jobsInWorkDir/$jobName");
	$job = fopen("$jobsInWorkDir/$jobName",'r+'); 	// откроем файл /////////////////////////////
	if(!$job) break; 	// файла не оказалось
	$res = flock($job,LOCK_EX);
	if($res === false){
		error_log("loader.php $pID - Unable locking job file Error");
		exit(1);
	};
	$php = false; $customPHP = ''; $execString = '';
	do{
		$s=fgets($job);	// считаем строку
		//echo "s=$s;\n";
		if($s===false) { 	// файл оказался пуст, кроме строк комментариев и пользовательских процедур
			//echo "Файл пуст!\n";
			$res = ftruncate($job,0); 	// укоротим файл на строку
			if($res === false){
				error_log("loader.php $pID - Unable truncated file $jobName");
				exit(1);
			};
			flock($job, LOCK_UN); 	//снимем блокировку
			fclose($job); 	// освободим файл ///////////////////////////////////////////////////////////
			continue 2;	// берём следующий файл. Если этот был последний, то это выяснится на следующем обороте основного цикла, и загрузчик завершится.
			//exit(0);	// прекращаем работу загрузчика. Это неверно, но... Если там есть ещё задания, а планировщика нет, то эти задания останутся невыполненными.
		};
		$s = trim($s);
		if($s[0]=='#'){ 	// там есть указание, что запускать.
			$s = trim(substr($s,1));
			//if($s) $execString .= $s;	// должна ли команда быть многострочной?
			if($s) $execString = $s;	// строго считаем, что только одна строка команды, последняя
			$s = false;
		}
		elseif(substr($s,0,5)=='<?php') {	// собирает как многострочный, так и однострочный код для eval
			//echo "PHP begin\n";
			$php = true;
			$s = substr($s,5);
		};
		if($php){
			if(substr($s,-2)=='?>') {
				//echo "PHP end\n";
				$php = false;
				$s = substr($s,0,-2);
			};
			//echo "PHP\n";
			$customPHP .= "$s\n";
			$s = false;
		};
	}while(!$s);
	//echo "s=$s; execString=$execString; customPHP=$customPHP;\n";
	
	$strSize = strlen($s); 	// размер первой строки в байтах
	// Возьмём последнюю строку с номером тайла
	// Всякая фигня в конце файла, не являющаяся номером тайла, включая пустые строки
	// будет обрезаться. Фигня - построчно, с каждым обращением к файлу, а пустые строки - сразу.
	$seek = fseek($job,-2*$strSize,SEEK_END); 	// сдвинем указатель на 2 строки к началу
	if($seek == -1) $xy = $s;	// сдвинуть не удалось - единственная строка?
	else while(($s=fgets($job)) !== FALSE) $xy = $s;	// считываем строки до конца, в $xy остаётся последняя
	$pos = ftell($job);	//  Returns the current position of the file read/write pointer
	if($seek == -1) $pos -= 1;
	//echo "s=$s; strSize=$strSize; xy=$xy; pos=$pos;\n";
	$res = ftruncate($job,$pos-mb_strlen($xy)); 	// укоротим файл на строку
	if($res === false){
		error_log("loader.php $pID - Unable truncated file $jobName");
		exit(1);
	};
	echo "loader.php $pID - File $jobName is truncated to 1 line.\n";

	// возьмём номер тайла
	list($x,$y) = explode(',',trim($xy));	// наконец, получаем координаты следующего тайла, который надо получить
	$x = trim($x);
	$y = trim($y);
	if(!is_numeric($x) or !is_numeric($y)) continue;	// в строке - не номер тайла

	$now = microtime(TRUE);
	// Запустим скачивание
	require('mapsourcesVariablesList.php');	// потому что в файле источника они могут быть не все, и для новой карты останутся старые
	$res=include("$mapSourcesDir/$map.php"); 	// файл, описывающий источник, используемые ниже переменные - оттуда
	if(!$res){	// нет параметров для этой карты (хотя об этом должен позаботиться планировшик)
		//error_log("loader.php $pID - There is no map specified in this job. Let's kill the job.");
		$res = ftruncate($job,0); 	// укоротим файл на строку
		if($res === false){
			error_log("loader.php $pID - Unable truncated file $jobName");
			exit(1);
		};
		flock($job, LOCK_UN); 	//снимем блокировку
		fclose($job); 	// освободим файл ///////////////////////////////////////////////////////////
		continue;	// берём следующий файл задания.
	};
	flock($job, LOCK_UN); 	//снимем блокировку
	fclose($job); 	// освободим файл ///////////////////////////////////////////////////////////

	//clearstatcache(TRUE,"$jobsInWorkDir/$jobName");
	//echo "Сокращение файла задания filesize after=".filesize("$jobsInWorkDir/$jobName").";\n";
	//echo "Будем скачивать $map, слой $mapLayer, тайл $zoom,$x,$y\n";
	
	// Собственно, содержательная деятельность: получение тайла или выполнение пользовательской процедуры
	if($customPHP){	// имеется пользовательская процедура PHP
		$customPHP = trim($customPHP);
		//error_log("loader.php $pID - Evaled PHP"); 	//
		$result=0; $msg='';
		eval($customPHP);
		// Если $customPHP определяет функцию getTile - то выполним стандартное действие:
		// запустим getTile и с результатом запустим putTile
		// Но вообще так себе и подход и контроль.
		if((substr($customPHP,0,10)=='$getTile =') or (substr($customPHP,0,9)=='$getTile=')){
			list($result,$msg) = putTile($map,$getTile($map,$zoom,$x,$y,array('layer'=>$mapLayer)),$trueTile,array('layer'=>$mapLayer));
			if($result===true) $result = 0;
		};
	}
	elseif($execString){	// имеется пользовательская процедура shell
		if(IRun($execString)) sleep(1); 	// Предотвращает множественную загрузку одного тайла одновременно, если у proxy больше одного клиента. Не сильно тормозит?
		else {
			//error_log("loader.php $pID - Executed custom exec string '$execString'"); 	//
			exec($execString,$output,$result); 	// exec будет ждать завершения
		};
	}
	else{	// пользовательской процедуры нет
		$needToRetrieve = false;
		// Получим сведения из хранилища о том, что файл действительно нужно получить извне
		if($getTile) extract($getTile($map,$zoom,$x,$y,array('needToRetrieve'=>true,'layer'=>$mapLayer)),EXTR_IF_EXISTS);	// оно возвращает array('img'=>,'needToRetrieve'=>...)
		if(!$needToRetrieve) {
			error_log("loader.php $pID - The tile is fresh or out of bounds, skip retrieve");
			continue;	// этот тайл есть и актуален, или не должен быть получен - не будем получать
		};

		$optStr = '';
		if($mapLayer!=null) $optStr = "--options='{\"layer\":$mapLayer}'";
		$phpCLIexec = trim(explode(' ',getCurrentCommand())[0]);	// из PID получаем командную строку и берём первый отделённый пробелом элемент. Считаем, что он - команда запуска php. Должно работать и в busybox.
		$execString = "$phpCLIexec tilefromsource.php  -z$zoom -x$x -y$y -r$map --maxTry=$maxTry $optStr";
		if(IRun($execString)) {
			echo "Такой тайл уже загружается\n";
			$result = 0;
			sleep(1); 	// Предотвращает множественную загрузку одного тайла одновременно, если у proxy больше одного клиента. Не сильно тормозит?
		}
		else {
			//error_log("loader.php $pID - Executed default exec string |$execString|"); 	//
			//file_put_contents('savedTiles',"$execString\n",FILE_APPEND);	
			exec($execString,$output,$result); 	// exec будет ждать завершения
			$result = 0;
		};
	};
	if($result !== 0){
		//error_log("loader.php $pID - Try to retrieve tile x:$x y:$y from $jobName which is failed");
		if($infinitely){
			//clearstatcache(TRUE,"$jobsInWorkDir/$jobName");
			//echo "Удлинение файла задания filesize before=".filesize("$jobsInWorkDir/$jobName").";\n";

			clearstatcache(TRUE,"$jobsInWorkDir/$jobName");	/////////////////////////////////////////
			$job = fopen("$jobsInWorkDir/$jobName",'r+'); 	// откроем файл также, как раньше, иначе flock не сработает
			$res = flock($job,LOCK_EX);
			if($res === false){
				error_log("loader.php $pID - Unable locking job file Error");
				exit(1);
			};
			fseek($job,0,SEEK_END); 	// сдвинем указатель в конец
			fwrite($job, $x.",".$y."\n");
			fflush($job);
			flock($job, LOCK_UN); 	//снимем блокировку		
			fclose($job); 	// освободим файл ///////////////////////////////////////////////////////
			$str = ", but tile $x,$y will be requested again";

			//clearstatcache(TRUE,"$jobsInWorkDir/$jobName");
			//echo "Удлинение файла задания filesize after=".filesize("$jobsInWorkDir/$jobName").";\n";
		};
	};
	
	$now=microtime(TRUE)-$now;
	$timer[$jobName] += $now;
	//echo "Map $map, did the download happen?:".!$result."; consumed ".round($timer[$jobName])."sec. at an average allowable ".round($ave)." sec.\n";
	error_log("loader.php $pID - Tile received $map $mapLayer/$zoom/$x/$y for $now sec. $str");
	//echo "	\n\n";
} while($jobName);
@flock($job, LOCK_UN); 	// на всякий случай - снимем блокировку		
@fclose($job); 	// освободим файл
//error_log("loader.php - The loader $pID has finished");
exit(0);
?>
