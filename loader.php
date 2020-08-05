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
# cp -Hpu --parents $tileCacheDir/$r/$z/$x/$y.png /home/stager/tileCacheCopy/
 - в этом случае создадутся отсутствующие каталоги, и весь исходный путь будет добавлен к целевому
# mkdir -p /home/stager/tileCacheCopy/$r/$z/$x/ && cp -Hpu $tileCacheDir/$r/$z/$x/$y.png /home/stager/tileCacheCopy/$r/$z/$x/
 - в этом случае сперва создадутся каталоги, потом произойдёт копирование
Могут быть использованы переменные из params.php
ВНИМАНИЕ! Загрузчик может загружать только карты с одним вариантом, типа map/z/y/x !!!
Версионные карты (типа Weather) не могут быть загружены загрузчиком !!!!
*/

$path_parts = pathinfo(__FILE__); // определяем каталог скрипта
chdir($path_parts['dirname']); // сменим каталог выполнение скрипта

require('params.php'); 	// пути и параметры
$bannedSourcesFileName = "$jobsDir/bannedSources";
$maxTry = 5 * $maxTry; 	// увеличим количество попыток получить файл

$pID = getmypid(); 	// process ID
$timer = array(); 	// массив для подсчёта затраченного времени
$lag = 300; 	// сек, на которое может отличатся время, затраченное на карту, от среднего, чтобы карта не подвергалась регулировке затраченного времени. Чем больше - тем ближе скорость скачивания к скорости отдачи для примерно одинаковых по производительности источников, но больше тормозит всё самый медленный.
file_put_contents("$jobsDir/$pID.lock", "$pID"); 	// положим флаг, что запустились
echo "Стартовал загрузчик $pID\n";
do {
	$execString = '$phpCLIexec tilefromsource.php -z$z -x$x -y$y -r$r --maxTry$maxTry'; 	// default exec - то, что будет запущено непосредственно для скачивания тайла. Обязательно в одинарных кавычках - во избежании подстановки прямо здесь
	
	clearstatcache(TRUE);
	$jobNames = preg_grep('~.[0-9]$~', scandir($jobsInWorkDir)); 	// возьмём только файлы с цифровым расшрением
	shuffle($jobNames); 	// перемешаем массив, чтобы по возможности разные задания брались в обработку
	$jobCNT = count($jobNames);
	// проблемные источники
	$bannedSources = unserialize(@file_get_contents($bannedSourcesFileName));
	//echo ":<pre> bannedSources "; print_r($bannedSources); echo "</pre>\n";
	//echo ":<pre> timer "; print_r($timer); echo "</pre>\n";
	// Выбор файла задания
	foreach($jobNames as $jobName) { 	// возьмём первый файл, которым можно заниматься
		//echo "jobsInWorkDir=$jobsInWorkDir; jobName=$jobName;\n";
		$path_parts = pathinfo($jobName);
		$zoom = $path_parts['extension']; 	//
		$map = $path_parts['filename'];
		if($bannedSources[$map]) { 	// источник с проблемами
			if((time()-$bannedSources[$map][0]-($noInternetTimeout*1))<0) {	// если многократный таймаут из конфига не истёк
				unset($timer[$jobName]); 	// удалим из планировки загрузки
				echo "Бросаем файл $jobName - источник с проблемами {$bannedSources[$map][1]}\n\n";
				$jobName = FALSE; 	// других может и не быть
				continue;	// проигнорируем задание для проблемного источника
			}
			else { 				// 	иначе - таки запустим скачивание
				echo "Пытаемся $jobName - источник с проблемами {$bannedSources[$map][1]}\n";
				break;
			}
		}
		clearstatcache(TRUE,"$jobsInWorkDir/$jobName");
		//echo "jobName=$jobName; is_file($jobsInWorkDir/$jobName)=".is_file("$jobsInWorkDir/$jobName")." filesize($jobsInWorkDir/$jobName)=".filesize("$jobsInWorkDir/$jobName")." \n";
		if( $jobName AND is_file("$jobsInWorkDir/$jobName") AND (filesize("$jobsInWorkDir/$jobName") > 4) AND (filesize("$jobsInWorkDir/$jobName")<>4096)) break; 	// выбрали файл для обслуживания
		else $jobName = FALSE;	
	}
	if(! $jobName) break; 	// просмотрели все файлы, не нашли, с чем работать - выход
	// Выбрали файл задания - всё ли с ним хорошо?
	echo "Берём файл $jobName\n";
	//echo filesize("$jobsInWorkDir/$jobName") . " \n";
	// Планировщик времени
	if($jobCNT > 1) { 	# не надо планировать, если только одно задание
		if($jobCNT<count($timer)) $timer=array(); 	// статистика какого-то завершившегося задания присутствует в $timer, и среднее будет неправильно 
		$ave = ((@max($timer)+@min($timer))/2)+$lag; 	// среднее плюс допустимое
		if($timer[$jobName]>$ave) { 	// пропустим эту карту, если на неё уже затрачено много времени
			echo "бросаем - на него затрачено много времени\n\n";
			//echo ":<pre> timer "; print_r($timer); echo "</pre>\n";
			continue;
		}
	}
	// Есть ли ещё файл?
	$job = fopen("$jobsInWorkDir/$jobName",'r+'); 	// откроем файл
	if(!$job) break; 	// файла не оказалось
	flock($job,LOCK_EX) or exit("loader.php Unable locking job file Error");
	$s=trim(fgets($job));
	//echo "s=$s;\n";
	if($s===FALSE) break; 	// файл оказался пуст - выход.Хотя это мог быть и не последний файл....
	$customExec = FALSE;
	if($s[0]=='#') { 	// там есть указание, что запускать
		$execString = trim(substr($s,1));
		//echo "execString=$execString;";
		if(!$execString) {
			ftruncate($job,0) or exit("loader.php Unable truncated file $jobName"); 	// грохнем файл задания, с которым непонятно что делать
			flock($job, LOCK_UN); 	//снимем блокировку
			fclose($job); 	// освободим файл
			continue;
		}
		$customExec = TRUE;
		$s=trim(fgets($job));
	}
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
			fclose($job); 	// освободим файл
			break;
		}
	}
	$strSize = strlen($s); 	// размер первой строки в байтах
	// Возьмём последний тайл
	$seek = fseek($job,-2*$strSize,SEEK_END); 	// сдвинем указатель на 2 строки к началу
	if($seek == -1)  $xy = $s;	// сдвинуть не удалось - первая строка?
	else while(($s=fgets($job)) !== FALSE) $xy = $s;
	//echo "s=$s; strSize=$strSize; xy=$xy;\n";
	$pos = ftell($job);
	ftruncate($job,$pos-strlen($xy)) or exit("loader.php Unable truncated file $jobName"); 	// укоротим файл на строку
	flock($job, LOCK_UN); 	//снимем блокировку
	fclose($job); 	// освободим файл
	$xy = str_getcsv(trim($xy));
	//echo "xy :<pre> "; print_r($xy); echo "</pre>\n";
	$now = microtime(TRUE);
	// Запустим скачивание
	$str = "";
	if(is_numeric($xy[0]) AND is_numeric($xy[1])) {
		require("$mapSourcesDir/$map.php"); 	// файл, описывающий источник, используемые ниже переменные - оттуда
		// возьмём тайл
		$path_parts = pathinfo($xy[1]); // 
		if(!$path_parts['extension']) {
			if($ext) $xy[1] = $xy[1].".$ext"; 	// в конфиге источника указано расширение
			else $xy[1] = $xy[1].".png";
		}
		$fileName = "$tileCacheDir/$map/$zoom/".$xy[0]."/".$xy[1]; 	// из кэша, однако наличие версионности игнорируется
		//echo "file=$fileName; <br>\n";
		$x=$xy[0];$y=$xy[1];$z=$zoom;$r=$map;
		$res = FALSE;
		$img = @file_get_contents($fileName); 	// попробуем взять тайл из кеша, возможно, за приделами разрешённых масштабов
		if($img!==FALSE) { 	//echo "тайл есть\n";
			//echo '$execString="'.$execString.'";'."\n";
			if($customExec) {
				eval('$execStringParsed="'.$execString.'";'); 	// распарсим строку,как если бы она была в двойных кавычках.  но переприсвоить почему-то не получается...
				echo "Выполняется $execStringParsed\n"; 	//
				$res = exec($execStringParsed); 	// 
			}
			else {
				$imgFileTime = time()-filemtime($fileName)-$ttl; 	// прожитое тайлом время сверх положенного
				//error_log("Get      $r/$z/$x/$y.$ext : ".strlen($img)." bytes from cache");		
				if((($z <= $maxZoom) AND ($z >= $minZoom)) AND ($ttl AND ($imgFileTime > 0))) { 	// если масштаб допустим, есть функция получения тайла, и нет в кэше или файл протух
					// тайл надо получать
					eval('$execStringParsed="'.$execString.'";'); 	// распарсим строку,как если бы она была в двойных кавычках.  но переприсвоить почему-то не получается...
					//echo "$execStringParsed\n"; 	//
					$res = exec($execStringParsed); 	// загрузим тайл синхронно
				} 
			}
		}
		else { 	//echo "тайла нет, тайл надо получать\n";
			//echo '$execString1="'.$execString.'";'."\n";
			eval('$execStringParsed="'.$execString.'";'); 	// распарсим строку,как если бы она была в двойных кавычках.  но переприсвоить почему-то не получается...
			//echo "$execString1\n"; 	//
			$res = exec($execStringParsed); 	// загрузим тайл синхронно
		}
		//echo "res=$res; \n";
		if($res==1) { 	// загрузка тайла плохо кончилась
			//file_put_contents("$jobsInWorkDir/$jobName", $xy[0].",".$xy[1]."\n",FILE_APPEND | LOCK_EX); 	// вернём номер тайла в файл задания для загрузчика
			$job = fopen("$jobsInWorkDir/$jobName",'r+'); 	// откроем файл также, как раньше, иначе flock не сработает
			flock($job,LOCK_EX) or exit("loader.php 2 Unable locking job file Error");
			fseek($job,0,SEEK_END); 	// сдвинем указатель в конец
			fwrite($job, $xy[0].",".$xy[1]."\n");
			fflush($job);
			flock($job, LOCK_UN); 	//снимем блокировку		
			fclose($job); 	// освободим файл
			$str = ", но тайл будет запрошен повторно";
		}
	}
	$now=microtime(TRUE)-$now;
	$timer[$jobName] += $now;
	echo "Карта $map, на неё затрачено ".$timer[$jobName]."сек. при среднем допустимом $ave сек.\n";
	echo "Получен тайл x=".$xy[0].", y=".$xy[1].", z=$zoom за $now сек. $str";
	echo "	\n\n";
	//exit;
} while($jobName);
@flock($job, LOCK_UN); 	// на всякий случай - снимем блокировку		
@fclose($job); 	// освободим файл
unlink("$jobsDir/$pID.lock");	// 
echo "Загрузчик $pID завершился\n";

?>
