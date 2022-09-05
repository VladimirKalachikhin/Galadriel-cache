[In English](https://github.com/VladimirKalachikhin/Galadriel-cache/blob/master/README.md)  
# GaladrielCache [![License: CC BY-SA 4.0](https://img.shields.io/badge/License-CC%20BY--SA%204.0-lightgrey.svg)](https://creativecommons.org/licenses/by-sa/4.0/)
Простой кеш/прокси сервер для тайловых карт с сохранением тайлов на диск. Предназначен в первую очередь для применения на очень слабых компьютерах типа RaspberryPi или различных NAS. Автор использует его на своей яхте Galadriel на [wi-fi маршрутизаторе под управлением OpenWRT](https://github.com/VladimirKalachikhin/MT7620_openwrt_firmware), который является сервером в бортовой сети. <br>
GaladrielCache может быть источником тайлов для любой программы, умеющей показывать растровые или векторные карты из интернета. Например, это может быть [OruxMaps](http://www.oruxmaps.com/cs/en/) на телефоне или планшете, или [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map/tree/master) на том же сервере для ноутбука или вообще любого устройства с браузером. <br>
Тайлы хранятся в принятой для OSM файловой структуре z/x/y Такая иерархия понимается очень многими (всеми?) программами показа карт, поэтому в случае проблем с сервером флешку с картами можно вставить в планшет и пользоваться растровыми картами напрямую.

## v. 2.5

## Возможности:
1. Конфигурируемые пользователем источники карт
2. Надёжное скачивание тайла в условиях плохой связи с поддержкой proxy для борьбы с блокировками
3. Опережающее скачивание тайлов крупных масштабов
4. Фоновое обновление кеша
5. Надёжный многопоточный загрузчик тайлов

Но никакой трансформации карт нет.

## Использование:
_tiles.php?z=Zoom&x=X_tile_num&y=Y_tile_num&r=map_Name_  
 
### Конфигурирование OruxMaps
Для использования GaladrielCache с OruxMaps нужно добавить в конфигурационный файл OruxMaps  `onlinemapsources.xml` в соответствии с синтаксисом этого файла описания требуемых карт, например -- карты Yandex Sat:
```
<onlinemapsource uid="1055">
<name>Yandex Sat через прокси (SAT)</name>
<url><![CDATA[http://192.168.1.1/tileproxy/tiles.php?z={$z}&x={$x}&y={$y}&r=yasat.EPSG3395]]></url>
<minzoom>5</minzoom>
<maxzoom>19</maxzoom>
<projection>MERCATORELIPSOIDAL</projection>
<cacheable>1</cacheable>
<downloadable>0</downloadable>
</onlinemapsource>
```
Где:
`192.168.1.1` -- сервер с GaladrielCache  
`/tileproxy/tiles.php` -- собственно cache/proxy  
`yasat.EPSG3395` -- имя карты  
То же самое -- для остальных карт.

ВНИМАНИЕ! Для использования конкретной проекции настраивать надо программу показа карт, а не GaladrielCache!  
(В примере выше -- это строка `<projection>MERCATORELIPSOIDAL</projection>` )  
GaladrielCache ничего не знает о проекциях. Он просто кеширует тайлы.

### Конфигурирование GaladrielMap
Для использования [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map/tree/master) с GaladrielCache -- установите параметр `$tileCachePath` в конфигурационном файле GaladrielMap `params.php` как описанов этом файле. 

### Адреса тайлов OSM
Некоторые приложения могут обращаться к тайлом только по адресу в формате [OSM "slippy map" tilenames](https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames). Для использования GaladrielCache с такими приложениями можно сконфигурировать Apache2 следующим образом:  
```
<IfModule rewrite_module>
	RewriteEngine On
	RewriteRule ^tiles/([A-Za-z]+)/([0-9]+)/([0-9]+)/([0-9.a-z]+)$ tiles.php?r=$1&z=$2&x=$3&y=$4
</IfModule>
```
После этого к GaladrielCache можно будет обратиться:
_tiles/map_Name/Zoom/X_tile/Y_tile_   
с указанием в конце расширения файла изображения, или без указания, в зависимости от конфигурации карты.

## Установка и конфигурирование:
Должен быть веб-сервер с поддержкой php. Просто скопируйте содержимое каталога GaladrielCache в нужное место.<br>
Кофигурация путей и другие параметры описаны в `params.php` Настройте.  
Файлы конфигурации источников карт находятся в `mapsources/*`  
Инструкция по созданию собственного источника карты находится в  `mapsources/mapsources_RU.txt`

## Подготовка карты памяти SD card для хранения кеша:
```
# mkfs.ext4 -O 64bit,metadata_csum -b 4096 -i 4096 /dev/sdb1
```
Где  
`-b 4096 -i 4096` установить размер блока данных в 4096 bytes и установить количество i-nodes в максимально возможное. <br>
`-O 64bit,metadata_csum` необязательный параметр, который может понадобиться для совместимости с некоторыми устройствами под управлением Android <br>
`/dev/sdb1` -- путь к карте памяти SD card

## Прямой доступ к кешу
Если ваш сервер умер, но есть рутованное устройство (планшет или телефон) под управлением Android, для доступа к растровым картам можно сделать следующее:
### Монтирование SD card в устройстве
1. извлеките SD card с кешем из сервера
2. вставьте SD card с кешкм в устройство под управлением Android  
на этом устройстве понадобится терминал (отдельная программа, или в составе чего-нибудь)
3. Откройте терминал. Выполните:
```
# mount -o rw,remount /
# mkdir /data/mySDcard 
# chown 1000:1000 /data/mySDcard
# chmod 774 /data/mySDcard
# mount -o ro,remount /
# mount -rw -t ext4 /dev/block/mmcblk1p1 /data/mySDcard
```
Эти команды создают точку монтирования и монтируют туда SD card. Таким образом все карты оказываются на вашем Android-устройстве. <br>
В этих командах:  
`/dev/block/mmcblk1p1` -- раздел на SD card, где находится кеш. Обычно путь именно такой, но на всякий случай следует уточнить:  
```
$ ls /dev/block
```
Последнее устройство mmcblk -- обычно вставленная SD card, а p1 -- обычно единственный раздел на ней.<br>
`/data/mySDcard` - точка монтирования

В зависимости от способа рутования устройства (и организации конкретного варианта Android), команда `mount` может выглядеть иначе:  
```
 # su --mount-master -c 'busybox mount -rw -t ext4 /dev/block/mmcblk1p1 /data/mySDcard'
``` 
Попробуйте такой вариант, если монтирование не получается.

### Конфигурирование OruxMaps на устройстве: 
Добавьте в конфигурационный файл OruxMaps  `onlinemapsources.xml` в соответствии с синтаксисом этого файла такие описания требуемых карт:

```
<onlinemapsource uid="1052">
<name>Navionics layer локально (NAV)</name>
<url><![CDATA[file://data/mySDcard/tiles/navionics_layer/{$z}/{$x}/{$y}.png]]></url> 
<minzoom>12</minzoom>
<maxzoom>18</maxzoom>
<projection>MERCATORESFERICA</projection>
<cacheable>0</cacheable>
<downloadable>0</downloadable>
</onlinemapsource>
<onlinemapsource uid="1054">
<name>OpenTopoMap локально (TOPO)</name>
<url><![CDATA[file://data/mySDcard/tiles/OpenTopoMap/{$z}/{$x}/{$y}.png]]></url> 
<minzoom>5</minzoom>
<maxzoom>18</maxzoom>
<projection>MERCATORESFERICA</projection>
<cacheable>0</cacheable>
<downloadable>0</downloadable>
</onlinemapsource>
```
И всё остальное, что нужно, таким же способом <br>
Здесь `/data/mySDcard/tiles/` -- путь к кешу на примонтированной SD card от корня файловой системы. 

### Автоматизация процесса монтирования SD card на устройстве с помощью 3C toolbox
С помощью приложения **3C toolbox** можно автоматически монтировать SD card при старте устройства. Для этого: 
1. Создайте файл с именем, например, _01_mountExtSDcard_, и содержанием:
```
#!/system/bin/sh

EXT_SD_DIRECTORY=/data/mySDcard;

if [ ! -d $EXT_SD_DIRECTORY ];
then
mount -o rw,remount /
mkdir $EXT_SD_DIRECTORY;
chown 1000:1000 $EXT_SD_DIRECTORY;
chmod 774 $EXT_SD_DIRECTORY;
mount -o ro,remount /
fi
mount -rw -t ext4 /dev/block/mmcblk1p1 $EXT_SD_DIRECTORY
```
2. Поместите этот файл в домашнюю директорию вашего устройства Android по следующему пути:  
`Android/data/ccc71.at.free/scripts/` или  
`Android/data/ccc71.at/scripts/` 
3. Откройте **3C toolbox** 
4. Перейдите Tools - Script editor 
5. Отметьте ваш файл _01_mountExtSDcard_ как запускаемый при старте.

## Загрузчик
GaladrielCache имеет в своём составе простой загрузчик тайлов. Для его использования нужно создать специальный файл задания - текстовый файл в формате csv. Имя файла должно совпадать с именем файла источника карты, а расширение - быть начальным масштабом в смысле z в координатах тайла. Файл задания должен содержать строки x,y координат требуемых тайлов, через запятую. 

Поместите файл задания в каталог `loaderjobs/` (настраивается в `params.php`) и запустите загрузчик:
```
$ php loaderSched.php
```
Указанные тайлы указанной карты начиная с указанного масштаба и вплоть до максимального масштаба, заданного в `params.php`, скачаются и будут сохранены в кеше.<br>
Например, файл задания с именем _OpenSeaMap.9_, содержащий строки:
```
295,145
296,145
296,144
295,144
295,143
296,143
```
вызовет скачивание указанных шести тайлов карты _OpenSeaMap_ масштаба 9, а также всех тайлов большего масштаба, которые укладываются в эти тайлы, вплоть до масштаба 16, который указан в `params.php` как максимальный для загрузчика.

Для управления загрузчиком и создания файлов заданий удобно использовать [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map/tree/master), где есть нужные инструменты. При этом созданные [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map/tree/master) файлы заданий дополнительно сохраняются в `loaderjobs/oldjobs` для возможного повторного использования.

Загрузка тайлов может осуществляться в несколько потоков, число которых указывается в `params.php`. Кроме того, загрузчик использует cron для надёжности, так что загрузка продолжится и после перезагрузки сервера. Для остановки процесса загрузки конкретной карты удалите соответствующий файл задания.

### Использование загрузчика для создания копии части кеша
Если в первой строке файла задания поместить какую-нибудь команду, то вместо загрузки тайлов загрузчик будет выполнять эту команду. Такая первая строка должна обязательно начинаться с символа #.  
В команде можно использовать переменные из конфигурационного файла _params.php_, но, к сожалению, переменные из файлов конфигурации источников  _mapsources/*_ недоступны.

Например, такой файл задания _OpenSeaMap.9_:  
```
# mkdir -p /mnt/mySDcard/RegionTileCache/$r/$z/$x/ && cp -Hpu $tileCacheDir/$r/$z/$x/$y.png /mnt/mySDcard/RegionTileCache/$r/$z/$x/
295,145
296,145
296,144
295,144
295,143
296,143
```
вызовет копирование указанных тайлов, и всех тайлов большего масштаба, которые укладываются в эти тайлы, из каталога, указанного в переменной $tileCacheDir из конфигурационного файла `params.php`, в каталог _/mnt/mySDcard/RegionTileCache_ с созданием всех подкаталогов.

Нельзя для одной карты одновременно запускать файлы задания со специальной командой и без неё. В конце-концов один из файлов задания перепишет другой, и останется какой-нибудь один.

## Очистка кеша с помощью clearCache
Запустите
```
$ php clearCache.php mapname
```
для карты *mapname*, или 
```
$ php clearCache.php
```
для всех карт, чтобы удалить из кеша ненужные файлы.  
Список ненужных файлов указывается в массиве $trash в описаноо каждой карты, и/или в массиве $globalTrash файла `params.php` для всех карт. Ненужными могут быть тайлы - заглушки, или, допустим, файлы .tne, если вы используете кеш от программы SAS.Planet.

## Карта покрытия
Можно получить карту покрытия, указав в запросе имя карты с добавлением '_COVER' в конце. Будет возвращён полупрозрачный тайл, каждый пиксел которого означает наличие тайла масштаба +8 от указанного.  
Дополнительно будут показаны границы имеющихся тайлов максимального для загрузчика масштаба.

## Поддержка
[Форум](https://github.com/VladimirKalachikhin/Galadriel-map/discussions)

Форум будет живее, если вы сделаете пожертвование [через PayPal](https://paypal.me/VladimirKalachikhin) по адресу [galadrielmap@gmail.com](mailto:galadrielmap@gmail.com) или на [ЮМани](https://yasobe.ru/na/galadrielmap).

Вы можете получить [индивидуальную платную консультацию](https://kwork.ru/training-consulting/20093293/konsultatsii-po-ustanovke-i-ispolzovaniyu-galadrielmap) по вопросам установки и использования GaladrielCache.
