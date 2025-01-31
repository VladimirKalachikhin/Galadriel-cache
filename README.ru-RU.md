[In English](https://github.com/VladimirKalachikhin/Galadriel-cache/blob/master/README.md)  
# GaladrielCache [![License: CC BY-NC-SA 4.0](Cc-by-nc-sa_icon.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/deed.en)
Простой кеш/прокси сервер для тайловых карт с сохранением тайлов на диск. Предназначен в первую очередь для применения на очень слабых компьютерах типа RaspberryPi или различных NAS. Автор использует его на своей яхте Galadriel на [wi-fi маршрутизаторе под управлением OpenWRT](https://github.com/VladimirKalachikhin/MT7620_openwrt_firmware), который является сервером в бортовой сети. <br>
GaladrielCache может быть источником тайлов для любой программы, умеющей показывать растровые или векторные карты из интернета. Например, это может быть [OruxMaps](http://www.oruxmaps.com/cs/en/) на телефоне или планшете, или [GaladrielMap](https://hub.mos.ru/v.kalachihin/GaladrielMap) на том же сервере для ноутбука или вообще любого устройства с браузером. <br>
Тайлы хранятся в принятой для OSM файловой структуре z/x/y Такая иерархия понимается очень многими (всеми?) программами показа карт, поэтому в случае проблем с сервером флешку с картами можно вставить в планшет и пользоваться растровыми картами напрямую.

## v. 2.10

Содержание:
* [Возможности](#возможности)
* [Требования](#требования)
* [Использование](#использование)
* * [Конфигурирование OruxMaps](#конфигурирование-oruxmaps)
* * [Конфигурирование GaladrielMap](#конфигурирование-galadrielmap)
* * [SignalK](#signalk)
* * [Адреса тайлов OSM](#адреса-тайлов-osm)
* * [MBTiles](#mbtiles)
* [Установка и конфигурирование](#установка-и-конфигурирование)
* [Подготовка карты памяти SD card для хранения кеша](#подготовка-карты-памяти-sd-card-для-хранения-кеша)
* [Прямой доступ к кешу](#прямой-доступ-к-кешу)
* * [Монтирование SD card в устройстве](#монтирование-cd-card-в-устройстве)
* * [Конфигурирование OruxMaps на устройстве](#конфигурирование-oruxmaps-на-устройстве)
* * [Автоматизация процесса монтирования SD card на устройстве с помощью 3C toolbox](#автоматизация-процесса-монтирования-cd-card-на-устройстве-с-помощью-3c-toolbox)
* [Загрузчик](#загрузчик)
* * [Использование загрузчика для создания копии части кеша](#использование-загрузчика-для-создания-копии-части-кеша)
* [Сведения о кеше (API)](#сведения-о-кеше)
* * [Список карт](#список-карт)
* * [Сведения о карте](#сведения-о-карте)
* * [Запуск загрузки](#запуск-загрузки)
* * [Сведения о загрузках](#сведения-о-загрузках)
* [Вспомогательные приложения](#вспомогательные-приложения)
* * [Очистка кеша с помощью clearCache](#очистка-кеша-с-помощью-clearcache)
* * [Проверка жизнеспособности источника карты](#проверка-жизнеспособности-источника-карты)
* * [Карта покрытия](#карта-покрытия)
* [Поддержка](#поддержка)


## Возможности:
1. Конфигурируемые пользователем источники карт
2. Надёжное скачивание тайла в условиях плохой связи с поддержкой proxy для борьбы с блокировками
3. Опережающее скачивание тайлов крупных масштабов
4. Фоновое обновление кеша
5. Надёжный многопоточный загрузчик тайлов
6. Поддерживаются локальные карты в формате [MBTiles](https://github.com/mapbox/mbtiles-spec/blob/master/1.3/spec.md)

Но никакой трансформации карт нет. Проекции и прочее -- это проблема клиентского приложения.

## Требования
Linux, PHP < 8. Кретинские решения, принятые в PHP 8 не позволяют GaladrielCache работать под PHP 8, а я не хочу следовать этим решениям.
На сервере должен быть установлен PHP7 со следующими модулями:

* cli
* php-fpm - если предполагается использовать не Apache2
* curl
* exif
* fileinfo
* gd
* iconv
* json
* mbstring
* openssl
* pcntl
* session
* shmop
* simplexml
* sockets
* sqlite3
* tokenizer
* zip

Все эти модули обычно входят в состав PHP в "полноценных" вариантах Linux, но в OpenWRT и сборках для Raspberry Pi многие из этих модулей надо ставить отдельно.

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
Для использования [GaladrielMap](https://hub.mos.ru/v.kalachihin/GaladrielMap) с GaladrielCache -- установите параметр `$tileCachePath` в конфигурационном файле GaladrielMap `params.php` как описанов этом файле. 

### SignalK
Для применения GaladrielCache в SignlK настройте в плагине [charts-plugin](https://www.npmjs.com/package/@signalk/charts-plugin) использование требуемых карт, указав в качестве источника GaladrielCache.  
Например, для использования OpenTopoMap укажите в настройках карты в плагине URL `http://you_server/tileproxy/tiles.php?z={z}&x={x}&y={y}&r=OpenTopoMap`

### Адреса тайлов OSM
Некоторые приложения (AvNav?) могут обращаться к тайлом только по адресу в формате [OSM "slippy map" tilenames](https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames). Для использования GaladrielCache с такими приложениями можно сконфигурировать Apache2 следующим образом:  
```
<IfModule rewrite_module>
	RewriteEngine On
	RewriteRule ^tiles/(.+)/([0-9]+)/([0-9]+)/([0-9.a-z]+)$ tiles.php?r=$1&z=$2&x=$3&y=$4
</IfModule>
```
После этого к GaladrielCache можно будет обратиться:
_tiles/map_Name/Zoom/X_tile/Y_tile_   
с указанием в конце расширения файла изображения, или без указания, в зависимости от конфигурации карты.

### MBTiles
Файл карты в формате mbtiles должен иметь расширение `.mbtiles` и находится в каталоге _$tileCacheDir_. Файл описания карты аналогичен другим файлам описания карты, но должен содержать функцию работы с mbtiles. Более подробно см. `mapsources/[mapsources.ru_RU.md](https://github.com/VladimirKalachikhin/Galadriel-cache/blob/master/mapsources/mapsources.ru-RU.md)

## Установка и конфигурирование:
Должен быть веб-сервер с поддержкой php. Просто скопируйте содержимое каталога GaladrielCache в нужное место.<br>
Кофигурация путей и другие параметры описаны в `params.php` Настройте.  
Файлы конфигурации источников карт находятся в `mapsources/*`  
Инструкция по созданию собственного источника карты находится в  mapsources/[mapsources.ru_RU.md](https://github.com/VladimirKalachikhin/Galadriel-cache/blob/master/mapsources/mapsources.ru-RU.md)

## Подготовка карты памяти SD card для хранения кеша
```
# mkfs.ext4 -O 64bit,metadata_csum -b 4096 -i 4096 /dev/sdXX
```
Где  
`-b 4096 -i 4096` установить размер блока данных в 4096 bytes и установить количество i-nodes в максимально возможное. <br>
`-O 64bit,metadata_csum` необязательный параметр, который может понадобиться для совместимости с некоторыми устройствами под управлением Android <br>
`/dev/sdXX` -- путь к карте памяти SD card.  
Однако, умолчальное форматирование в vfat позволяет хранить на карте достаточный для практических целей объём тайлов.

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

### Конфигурирование OruxMaps на устройстве 
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

Поместите файл задания в каталог `loaderjobs/` (настраивается в `params.php`) и запустите загрузчик как фоновый процесс:
```
tileproxy$ startLoaderDaemon
```
или как обычный процесс:
```
tileproxy$ php loaderSched.php
```
Указанные тайлы указанной карты начиная с указанного масштаба и вплоть до максимального масштаба, заданного в `params.php`, скачаются и будут сохранены в кеше.  
Остановить загрузчик можно командой
```
tileproxy$ stopLoader
```
или удалив все файлы заданий в каталогах `loaderjobs/` и `loaderjobs/inWork/`  

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

Для управления загрузчиком и создания файлов заданий удобно использовать [GaladrielMap](https://hub.mos.ru/v.kalachihin/GaladrielMap), где есть нужные инструменты. При этом созданные [GaladrielMap](https://hub.mos.ru/v.kalachihin/GaladrielMap) файлы заданий дополнительно сохраняются в `loaderjobs/oldjobs` для возможного повторного использования.

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

## Сведения о кеше
По адресу `cacheControl.php` находится сервис (API) представляющий некоторую информацию о кеше и позволяющй активизировать загрузчик.  
В ответ на запросы сервер возвращает JSON, в том числе и _null_ в случае проблем.

Использование:
### Список карт
`http://you_address/cacheControl.php?getMapList`  

Возвращается:  
```
{
	"map_source_file_name": {
		"en": "Human-readable english map name or map_source_file_name",
		["ru": ...]
	}
}
```
### Сведения о карте
`http://you_address/cacheControl.php?getMapInfo=map_source_file_name`  

Возвращается:  
```
{
    "ext": "...",
    "ContentType": "...",
    "epsg": "...",
    "minZoom": ?,
    "maxZoom": ?,
    "data": "...",
    "mapboxStyle": "..."
}
```
из файла _mapsources/map_source_file_name.php_

### Запуск загрузки
`http://you_address/cacheControl.php?loaderJob=map_source_file_name.zoom&xys=csv_of_XY`  

Запускается загрузка списка тайлов csv_of_XY карты map_source_file_name.zoom. Возвращается:  
```
{
	"status": 0,
	"jobName": map_source_file_name.zoom
}
```

### Сведения о загрузках
`http://you_address/cacheControl.php?loaderStatus[&restartLoader]  

Также можно потребовать (ре)старт загрузчика. Возвращается:  
```
{
	"loaderRun": scheduler_PID,
	"jobsInfo": {
		"map_source_file_name.zoom": %_of_complite
		[...]
	}
}
```

## Вспомогательные приложения
### Очистка кеша с помощью clearCache
Запустите
```
tileproxy$ php clearCache.php mapname
```
для карты *mapname*, или 
```
tileproxy$ php clearCache.php
```
для всех карт, чтобы удалить из кеша ненужные файлы.  
Список ненужных файлов указывается в массиве $trash в описаноо каждой карты, и/или в массиве $globalTrash файла `params.php` для всех карт. Ненужными могут быть тайлы - заглушки, или, допустим, файлы .tne, если вы используете кеш от программы SAS.Planet.  
Если у карты указаны границы - тайлы вне границ также удаляются.

### Проверка жизнеспособности источника карты
checkSources.php -- утилита командной строки, которая для каждого файла источника карты, в котором указан специальный тайл, пытается скачать этот тайл и сравнивает полученное с сохранённым. Результат записывается в лог.
Рекомендуется запускать периодически cron'ом.

### Карта покрытия
Запустите
```
tileproxy$ php checkCovers.php mapName.zoom max_zoom
```
для подсчёта отсутствующих тайлов в покрытии карты, описанном в файле задания для загрузчика mapName.zoom. Отсутствующие тайлы будут указаны в виде файлов заданий для загрузчика в каталоге `checkCoversData/notFound/`

Также можно получить карту покрытия, указав в запросе имя карты с добавлением '_COVER' в конце. Будет возвращён полупрозрачный тайл, каждый пиксел которого означает наличие тайла масштаба +8 от указанного. Дополнительно будут показаны границы имеющихся тайлов максимального для загрузчика масштаба. [GaladrielMap](https://hub.mos.ru/v.kalachihin/GaladrielMap) использует эту возможность для показа карты покрытия.

## Поддержка
[Форум](https://github.com/VladimirKalachikhin/Galadriel-map/discussions)

Форум будет живее, если вы сделаете пожертвование на [ЮМани](https://sobe.ru/na/galadrielmap).

Вы можете получить [индивидуальную платную консультацию](https://kwork.ru/training-consulting/20093293/konsultatsii-po-ustanovke-i-ispolzovaniyu-galadrielmap) по вопросам установки и использования GaladrielCache.
