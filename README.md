# GaladrielCache
This is a simple raster map tiles cache/proxy to use on a weak computers such as RaspberryPi or NAS. Author use it in the wi-fi router/GSM modem under OpenWRT on his sailboat Galadriel.<br>
GaladrielCache can be used with any on-line map viewer. [OruxMaps](http://www.oruxmaps.com/cs/en/) is a good choice. [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map/tree/master) is a good choice too.<br>
Tiles stored on standard OSM z/x/y file structure, so you may use SD with maps directly on your smartphone in the event of a disaster.

## v. 0.1

## Features:
1. User-defined map sources
2. nginx support
3. Dumb tile loader

It's all. No versioning, no reprojection.

## Usage:
_tiles.php_ - cache/proxy<br>
_tilefromsource.php_ - proxy, mostly for a nginx 404 helper usage

OruxMaps source definition:
```
	<onlinemapsource uid="1055">
		<name>Yandex Sat via my proxy (SAT)</name>
		<url><![CDATA[http://192.168.1.1/tileproxy/tiles.php?z={$z}&x={$x}&y={$y}&r=yasat.EPSG3395]]></url>
		<minzoom>5</minzoom>
		<maxzoom>19</maxzoom>
		<projection>MERCATORELIPSOIDAL</projection>
		<cacheable>1</cacheable>
		<downloadable>0</downloadable>
	</onlinemapsource>
```
or, if you use nginx for serve files:
```
	<onlinemapsource uid="1055">
		<name>Yandex Sat via my proxy (SAT)</name>
		<url><![CDATA[http://192.168.1.1/tiles/yasat.EPSG3395/{$z}/{$x}/{$y}.png]]></url>
		<minzoom>5</minzoom>
		<maxzoom>19</maxzoom>
		<projection>MERCATORELIPSOIDAL</projection>
		<cacheable>1</cacheable>
		<downloadable>0</downloadable>
	</onlinemapsource>
```
Where:
		192.168.1.1 - your server 
		/tileproxy/tiles.php - cache/proxy 
		/tiles/ - path to file storage in the sense of a web server
		and yasat.EPSG3395 - your custom map source name.

ATTENTION! You MUST configure your MAP VIEWER for the use specific projection! 
(`<projection>MERCATORELIPSOIDAL</projection>` in the example above)<br> 
GaladrielCache knows nothing about projections, it's store tiles only.

## Install&configure:
You must have a web server with php support. Just copy.<br>
Paths and other set and describe in _params.php_<br>
Custom sources are in _mapsources/_<br>
Help about map sources are in _mapsources/mapsources.txt_

If you use nginx to serve cache, configure 404 helper to proxy:<br>
nginx.conf:
```
http {
	server {
		location /tileproxy {
			default_type  application/octet-stream;
			error_page  404  /tileproxy/tilefromsource.php?uri=$uri;
		}
	}
}
```
## Prepare SD card to cache:
```
# mkfs.ext4 -i 4096 /dev/sdb1
```
it's increase i-nodes to max.
```
# tune2fs -m 1 /dev/sdb1
```
it's reduce system area.
/dev/sdb1 - your SD card


## Direct usage of cache:
If you server dead, but you have a rooted Android phone or tablet, you may:<br>
1. remove SD card with cache from server
2. insert SD card with cache to Android device<br>
on Android device with terminal:
3. Open terminal. Try:
```
# mount -o rw,remount /
# mkdir /data/mySDcard  
# chown 1000:1000 /data/mySDcard
# chmod 774 /data/mySDcard
# mount -o ro,remount /
# mount -rw -t ext4  /dev/block/mmcblk1p1 /data/mySDcard
```
This creates mount point and mounts your SD card there to, so you have all maps on your Android device.<br>
There:<br>
		_/dev/block/mmcblk1p1_ - partition wint cache on you SD card. To find it, try `ls /dev/block`. Last mmcblk - most probably your SD card.<br>
		_/data/mySDcard_ - mount point

To access map via OruxMaps:<br>
Modify _oryxmaps/mapfiles/onlinemapsources.xml_ by add:
```
	<onlinemapsource uid="1052">
		<name>Navionics layer from local dir (NAV)</name>
		<url><![CDATA[file://data/mySDcard/tiles/navionics_layer/{$z}/{$x}/{$y}.png]]></url> 
		<minzoom>12</minzoom>
		<maxzoom>18</maxzoom>
		<projection>MERCATORESFERICA</projection>
		<cacheable>0</cacheable>
		<downloadable>0</downloadable>
	</onlinemapsource>
	<onlinemapsource uid="1054">
		<name>OpenTopoMap from local dir (TOPO)</name>
		<url><![CDATA[file://data/mySDcard/tiles/OpenTopoMap/{$z}/{$x}/{$y}.png]]></url> 
		<minzoom>5</minzoom>
		<maxzoom>18</maxzoom>
		<projection>MERCATORESFERICA</projection>
		<cacheable>0</cacheable>
		<downloadable>0</downloadable>
	</onlinemapsource>
```
and other maps by same way.<br>
There:<br>
		/tiles/ - path to cache from SD card root.<br>

Depending on how you obtain root, the command `mount` may be written as<br>
`su --mount-master -c "busybox mount -rw -t ext4  /dev/block/mmcblk1p1 /data/mySDcard"`

With **3C toolbox** you can automate the mounting when the device boots.<br>
Create file:<br>
01_mountExtSDcard
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
mount -rw -t ext4  /dev/block/mmcblk1p1 $EXT_SD_DIRECTORY
```
and place it in home directory by path _Android/data/ccc71.at.free/scripts/_ <br>
Open **3C toolbox**<br>
Go Tools - Script editor<br>
Mark _01_mountExtSDcard_ as runed in boot


## Loader
GaladrielCache includes dumb tile loader. Create a csv job file with map_source_name.zoom as a name and x,y strings as content and place it in loaderjobs/ directory. Start loaderSched.php in cli.
For example:

navionics_layer.9
```
295,145
296,145
296,144
295,144
295,143
296,143
```

Will be downloaded navionics_layer map within the specified tiles from zoom 9 to max zoom.<br>

Tile loader may use any number of threads to load, and use cron for robust download.<br>
You may use a [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map/tree/master) for control Loader.

### Use Loader to copy part of cache
Add to first line of csv job file some copy command. In this case Loader start this command instead loading tile.<br>
This first line must be started from #. For example:

navionics_layer.9
```
# mkdir -p /mnt/mySDcard/RegionTileCache/$r/$z/$x/ && cp -Hpu $tileCacheDir/$r/$z/$x/$y.png /mnt/mySDcard/RegionTileCache/$r/$z/$x/
295,145
296,145
296,144
295,144
295,143
296,143
```

This will copy the specified tiles of navionics_layer map up to max zoom to destination.<br>

You can use variables from _params.php_, but not from _mapsources/_. Avoid to create job files have custom command and without for one map. <br>
Job files, created by [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map/tree/master), saved in _loaderjobs/oldjobs_

## clearCache
Use in cli _clearCache.php mapname_ to _mapname_ or _clearCache.php_ to all maps to remove from cache unwanted files, listed in $trash. This is may be a blanck tiles or .tne files from SAS.Planet
