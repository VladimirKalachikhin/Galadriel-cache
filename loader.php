<?php 
/* Загрузчик 
Запускается в нескольких экземплярах
Читает файл задания, сокращает его, освобождает, и крутится, пока из файлов есть что читать
Планировщик стремится уделить каждой карте одинаковое время с точностью до $lag
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
Могут быть использованы переменные из params.php
ВНИМАНИЕ! Загрузчик может загружать только карты с одним вариантом, типа map/z/y/x !!!
Версионные карты (типа Weather) не могут быть загружены загрузчиком !!!!
*/
chdir(__DIR__); // задаем директорию выполнение скрипта

$infinitely = false;
if(@$argv[1]=='--infinitely') $infinitely = true;

require('params.php'); 	// пути и параметры
$bannedSourcesFileName = "$jobsDir/bannedSources";
$maxTry = 5 * $maxTry; 	// увеличим количество попыток получить файл

$pID = getmypid(); 	// process ID
$timer = array(); 	// массив для подсчёта затраченного времени
$lag = 300; 	// сек, на которое может отличатся время, затраченное на карту, от среднего, чтобы карта не подвергалась регулировке затраченного времени. Чем больше - тем ближе скорость скачивания к скорости отдачи для примерно одинаковых по производительности источников, но больше тормозит всё самый медленный.
$customExec = FALSE;
file_put_contents("$jobsDir/$pID.lock", "$pID"); 	// положим флаг, что запустились
echo "The loader $pID has started\n";
do {
	$execString = '$phpCLIexec tilefromsource.php -z$z -x$x -y$y -r$r --maxTry $maxTry'; 	// default exec - то, что будет запущено непосредственно для скачивания тайла. Обязательно в одинарных кавычках - во избежании подстановки прямо здесь
	
	clearstatcache(TRUE);
	$jobNames = preg_grep('~.[0-9]$~', scandir($jobsInWorkDir)); 	// возьмём только файлы с цифровым расшрением
	shuffle($jobNames); 	// перемешаем массив, чтобы по возможности разные задания брались в обработку
	$jobCNT = count($jobNames);
	// проблемные источники
	$bannedSources = unserialize(@file_get_contents($bannedSourcesFileName));	// заполняется tilefromsource.php
	//echo ":<pre> bannedSources "; print_r($bannedSources); echo "</pre>\n";
	//echo ":<pre> timer "; print_r($timer); echo "</pre>\n";
	// Выбор файла задания
	echo "Has ".count($jobNames)." job files.\n";
	foreach($jobNames as $jobName) { 	// возьмём первый файл, которым можно заниматься
		//echo "jobsInWorkDir=$jobsInWorkDir; jobName=$jobName;\n";
		//$jobName = 'C-MAP.3';	// FOR TEST FOR TEST
		$path_parts = pathinfo($jobName);
		$zoom = $path_parts['extension']; 	//
		$map = $path_parts['filename'];
		if(@$bannedSources[$map]) { 	// источник с проблемами
			if((time()-$bannedSources[$map][0]-($noInternetTimeout*1))<0) {	// если многократный таймаут из конфига не истёк
				unset($timer[$jobName]); 	// удалим из планировки загрузки
				echo "Drop the file $jobName - troubled source {$bannedSources[$map][1]}\n\n";
				$jobName = FALSE; 	// других может и не быть
				continue;	// проигнорируем задание для проблемного источника
			}
			else { 				// 	иначе - таки запустим скачивание
				echo "Trying $jobName - troubled source {$bannedSources[$map][1]}\n";
				break;
			}
		}
		echo "jobName=$jobName; is_file($jobsInWorkDir/$jobName)=".is_file("$jobsInWorkDir/$jobName")." filesize($jobsInWorkDir/$jobName)=".filesize("$jobsInWorkDir/$jobName")." \n";
		if( $jobName AND is_file("$jobsInWorkDir/$jobName") AND (filesize("$jobsInWorkDir/$jobName") > 4) AND (filesize("$jobsInWorkDir/$jobName")<>4096)) break; 	// выбрали файл для обслуживания
		else $jobName = FALSE;	
	};
	if(! $jobName) { 	// просмотрели все файлы, не нашли, с чем работать - выход
		echo "No files to executing - break.\n";
		break;
	};
	// Выбрали файл задания - всё ли с ним хорошо?
	echo "Take the file $jobName\n";
	//echo filesize("$jobsInWorkDir/$jobName") . " \n";
	// Планировщик времени
	if($jobCNT > 1) { 	# не надо планировать, если только одно задание
		if($jobCNT<count($timer)) $timer=array(); 	// статистика какого-то завершившегося задания присутствует в $timer, и среднее будет неправильно 
		$ave = ((@max($timer)+@min($timer))/2)+$lag; 	// среднее плюс допустимое
		if($timer[$jobName]>$ave) { 	// пропустим эту карту, если на неё уже затрачено много времени
			echo "drop it - it's time consuming\n\n";
			//echo ":<pre> timer "; print_r($timer); echo "</pre>\n";
			continue;
		};
	};
	// Есть ли ещё файл?
	clearstatcache(TRUE,"$jobsInWorkDir/$jobName");
	$job = fopen("$jobsInWorkDir/$jobName",'r+'); 	// откроем файл /////////////////////////////
	if(!$job) break; 	// файла не оказалось

	//clearstatcache(TRUE,"$jobsInWorkDir/$jobName");
	//echo "Сокращение файла задания filesize before=".filesize("$jobsInWorkDir/$jobName").";\n";

	flock($job,LOCK_EX) or exit("loader.php Unable locking job file Error");
	$s=fgets($job);
	//echo "s=$s;\n";
	if($s===FALSE) break; 	// файл оказался пуст - выход.Хотя это мог быть и не последний файл....
	if($s[0]=='#') { 	// там есть указание, что запускать
		$customExec = TRUE;
		$execString = trim(substr($s,1));
		//echo "execString=$execString;\n";
		if(!$execString) {
			ftruncate($job,0) or exit("loader.php Unable truncated file $jobName"); 	// грохнем файл задания, с которым непонятно что делать
			flock($job, LOCK_UN); 	//снимем блокировку
			fclose($job); 	// освободим файл //////////////////////////////
			continue;	// отправимся брать другой файл
		}
		$s=fgets($job);
	};
	$strSize = strlen($s); 	// размер первой строки в байтах
	//echo "s=$s; strSize=$strSize;\n";
	if(!$strSize) {
		do { 	// берём следующу непустую содержательную строку
			$s=fgets($job);
			//echo "s=$s;\n";
		} while(($s!==FALSE) AND (trim($s)==''));
		if($s===FALSE) { 	// но упс - файл кончился
			ftruncate($job,0) or exit("loader.php Unable truncated file $jobName"); 	// 
			flock($job, LOCK_UN); 	//снимем блокировку
			fclose($job); 	// освободим файл ////////////////
			break;	// уходим, не будем брать новый файл, хотя этот мог быть не последним
		};
	};
	$strSize = strlen($s); 	// размер первой строки в байтах
	// Возьмём последний тайл
	$seek = fseek($job,-2*$strSize,SEEK_END); 	// сдвинем указатель на 2 строки к началу
	if($seek == -1)  $xy = $s;	// сдвинуть не удалось - первая строка?
	else while(($s=fgets($job)) !== FALSE) $xy = $s;	// считываем строки до конца, в $xy остаётся последняя
	//echo "s=$s; strSize=$strSize; xy=$xy;\n";
	$pos = ftell($job);	//  Returns the current position of the file read/write pointer
	ftruncate($job,$pos-strlen($xy)) or exit("loader.php Unable truncated file $jobName"); 	// укоротим файл на строку
	flock($job, LOCK_UN); 	//снимем блокировку
	fclose($job); 	// освободим файл ///////////////////////////////////////////////////////////

	//clearstatcache(TRUE,"$jobsInWorkDir/$jobName");
	//echo "Сокращение файла задания filesize after=".filesize("$jobsInWorkDir/$jobName").";\n";

	$xy = str_getcsv(trim($xy));
	//echo "xy :<pre> "; print_r($xy); echo "</pre>\n";

	$now = microtime(TRUE);
	// Запустим скачивание
	if(!is_numeric($xy[0]) or !is_numeric($xy[1])) continue;
	if($pos=strpos($map,'_COVER')) { 	// нужно показать покрытие, а не саму карту
		require("$mapSourcesDir/common_COVER"); 	// файл, описывающий источник тайлов покрытия, используемые ниже переменные - оттуда.
	}
	else require("$mapSourcesDir/$map.php"); 	// файл, описывающий источник, используемые ниже переменные - оттуда
	// возьмём тайл
	$x=$xy[0];$y=$xy[1];$z=$zoom;$r=$map;
	if($ext) $y .= ".$ext"; 	// в конфиге источника указано расширение
	else $y .= ".png";
	$fileName = "$tileCacheDir/$map/$zoom/$x/$y"; 	// из кэша, однако наличие версионности игнорируется
	echo "file=$fileName; <br>\n";

	$doLoading = FALSE; 	
	$imgFileTime = @filemtime($fileName); 	// файла может не быть
	//echo "imgFileTime=$imgFileTime; ttl=$ttl;\n";
	if($imgFileTime) { 	// файл есть
		if($customExec) $doLoading = TRUE; 	// копирование кеша
		elseif(($imgFileTime+$ttl) < time()) { 	// файл протух. Таким образом, файлы нулевой длины могут протухнуть раньше, но не позже.
			$doLoading = TRUE; 	// 
			//echo "тайл $fileName есть, но протух на ".round((time()-($imgFileTime+$ttl))/(60*60*24))." дней\n";
		}
		else { 	// файл свежий
			$img = file_get_contents($fileName); 	// берём тайл из кеша, возможно, за приделами разрешённых масштабов
			if(!$img) { 	// файл нулевой длины
			 	if($noTileReTry) $ttl= $noTileReTry; 	// если указан специальный срок протухания для файла нулевой длины -- им обозначается перманентная проблема скачивания
				if(($imgFileTime+$ttl) < time()) { 	// файл протух
					$doLoading = TRUE; 	// 
				}
			}
		}
	}
	else { 	// файла нет
		if($customExec) $doLoading = FALSE; 	// копирование кеша
		else $doLoading = TRUE; 	// 
	}
	//echo "doLoading=$doLoading;\n";
	// Решение принято, выполняем
	$res = FALSE;
	if($doLoading){
		eval('$execStringParsed="'.$execString.'";'); 	// распарсим строку,как если бы она была в двойных кавычках.  но переприсвоить почему-то не получается...
		if(thisRun($execStringParsed)) $res=0; 	// Предотвращает множественную загрузку одного тайла одновременно, если у proxy больше одного клиента. Не сильно тормозит?
		else{ 
			echo "Executed $execStringParsed\n"; 	//
			$res = exec($execStringParsed); 	// 
		}
	}
	//echo "res=$res; \n";

	$str = "";
	//
	if($res===1 and $infinitely) { 	// загрузка тайла плохо кончилась, если указано - вернём его в задание. В результате отсутствующий тайл будет скачиваться вечно

		//clearstatcache(TRUE,"$jobsInWorkDir/$jobName");
		//echo "Удлинение файла задания filesize before=".filesize("$jobsInWorkDir/$jobName").";\n";

		clearstatcache(TRUE,"$jobsInWorkDir/$jobName");	/////////////////////////////////////////
		$job = fopen("$jobsInWorkDir/$jobName",'r+'); 	// откроем файл также, как раньше, иначе flock не сработает
		flock($job,LOCK_EX) or exit("loader.php 2 Unable locking job file Error");
		fseek($job,0,SEEK_END); 	// сдвинем указатель в конец
		fwrite($job, $xy[0].",".$xy[1]."\n");
		fflush($job);
		flock($job, LOCK_UN); 	//снимем блокировку		
		fclose($job); 	// освободим файл ///////////////////////////////////////////////////////
		$str = ", but tile ".$xy[0].",".$xy[1]." will be requested again";

		//clearstatcache(TRUE,"$jobsInWorkDir/$jobName");
		//echo "Удлинение файла задания filesize after=".filesize("$jobsInWorkDir/$jobName").";\n";

	};
	//
	$now=microtime(TRUE)-$now;
	$timer[$jobName] += $now;
	echo "Map $map, did the download happen?:".!$res."; consumed ".round($timer[$jobName])."sec. at an average allowable ".round($ave)." sec.\n";
	echo "Tile received x=".$xy[0].", y=".$xy[1].", z=$zoom за $now сек. $str";
	echo "	\n\n";
	//exit;
} while($jobName);
@flock($job, LOCK_UN); 	// на всякий случай - снимем блокировку		
@fclose($job); 	// освободим файл
unlink("$jobsDir/$pID.lock");	// 
echo "The loader $pID has finished\n";

function thisRun($exec) {
/**/
global $pID;
exec("ps -A w | grep '$exec'",$psList);
if(!$psList) exec("ps w | grep '$exec'",$psList); 	// for OpenWRT. For others -- let's hope so all run from one user
//print_r($psList); //
$run = FALSE;
foreach($psList as $str) {
	if(strpos($str,(string)$pID)!==FALSE) continue;
	if(strpos($str,'grep')!==FALSE) continue;
	if(strpos($str,$exec)!==FALSE){
		$run=TRUE;
		break;
	}
}
return $run;
}

?>
