<?php
/*
getCurrentCommand() 	Возвращает команду запуска скрипта 
function IRun($ownCmdline='')	Return true if the process with same command line is run.

*/

function getCurrentCommand() {
/* Возвращает команду запуска скрипта. 
Разумеется, из под сервера оно вернёт что-нибудь типа /usr/sbin/apache2 tilefromsource.php -z12 -x2371 -y1160 -rOpenTopoMap
поэтому узнать $phpCLIexec иначе, чем в cli не получится.
работает и в busybox
*/
return str_replace("\0",' ',shell_exec('cat /proc/'.posix_getpid().'/cmdline'));
};


function IRun($ownCmdline=''){
/* Return true if the process with same command line is run.
Must be work in busybox.

Выглядит более надёжным в смысле совместимости с вариантами linux
такое решение:
// Проверяем, не запущен ли уже такой процесс
// Открываем/создаём файл, потом пытаемся захватить его в исключительное пользование
// Если это уже сделал другой экземпляр - не получится.
// Должно работать везде, и в busybox тоже.
$fp = fopen("$jobsDir/loaderShed.lock", 'c');
if (!flock($fp, LOCK_EX | LOCK_NB)) {	// Попытка захватить эксклюзивный блок навсегда (LOCK_NB, зачем?)
	echo "I'm already ruunning, exiting.\n";
	exit(1);
};
ftruncate($fp, 0);
fwrite($fp, getmypid());
//file_put_contents("$jobsDir/loaderShed.lock", getmypid()); 	// однако, так тоже работает. file_put_contents не открывает файл, если он уже открыт?
....
flock($fp, LOCK_UN);
fclose($fp);

Однако, никакой flock не мешает просто удалить флаг-файл. И тогда можно запустить второй экземпляр.
*/
if(!$ownCmdline) $ownCmdline = trim(getCurrentCommand());
$ownPid = posix_getpid();
//echo "ownPid=$ownPid; ownCmdline=$ownCmdline;\n";
exec('ps ax | grep "'.$ownCmdline.'"',$psList);
//echo "psList:";print_r($psList);echo"\n";
$found = false;
foreach($psList as $str) {
	if(strpos($str,(string)$ownPid)!==false) continue;
	if(strpos($str,'ps ax')!==false) continue;
	if(strpos($str,'grep')!==false) continue;
	//echo "[IRun] str=$str;\n";
	$found = true;
};
return $found;
}; // end function IRun

?>
