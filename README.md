[Русское описание](https://github.com/VladimirKalachikhin/Galadriel-cache/blob/master/README.ru-RU.md)  
# GaladrielCache [![License: CC BY-SA 4.0](https://img.shields.io/badge/License-CC%20BY--SA%204.0-lightgrey.svg)](https://creativecommons.org/licenses/by-sa/4.0/)
This is a simple map tiles cache/proxy to use on weak computers such as RaspberryPi or NAS. The author uses it in the [wi-fi router/GSM modem under OpenWRT](https://github.com/VladimirKalachikhin/MT7620_openwrt_firmware) on his sailboat Galadriel.  
GaladrielCache can be used with any on-line map viewer. [OruxMaps](http://www.oruxmaps.com/cs/en/) is a good choice. [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map) is a good choice too.   
Tiles stored on OSM z/x/y file structure, so you may use SD with raster maps without a server -- directly on your smartphone in the event of a disaster.

## v. 2.5.3

## Features:
1. User-defined map sources, with versioning, if need
2. Flexible and robust tile loading with proxy support
3. Prior loading of large zoom levels
4. Asynchronous cache freshing
5. Separated robust loader

But no reprojection.

## Usage:
_tiles.php?z=Zoom&x=X_tile_num&y=Y_tile_num&r=map_Name_

### OruxMaps configuration
To use GaladrielCache with OruxMaps, add map definitions to OruxMaps `onlinemapsources.xml`, for example -- Yandex Sat map:
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
Where:
`192.168.1.1` - your server  
`/tileproxy/tiles.php` - cache/proxy  
and `yasat.EPSG3395` - your custom map source name.  
Try it to other maps.

ATTENTION! You MUST configure your MAP VIEWER for the use of specific projection!  
(`<projection>MERCATORELIPSOIDAL</projection>` in the example above)  
The GaladrielCache knows nothing about projections, it's store tiles only.

### GaladrielMap configuration
To use [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map/tree/master) with GaladrielCache -- set `$tileCachePath` in GaladrielMap's `params.php` file. 

### OSM "slippy map" tilenames
Some applications (AvNav?) cannot use tiles other than in [OSM "slippy map" tilenames](https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames) format. To use the GaladrielCache with these applications, you can configure the Apache2 as follows:  
```
<IfModule rewrite_module>
	RewriteEngine On
	RewriteRule ^tiles/([A-Za-z]+)/([0-9]+)/([0-9]+)/([0-9.a-z]+)$ tiles.php?r=$1&z=$2&x=$3&y=$4
</IfModule>
```
and refer to the cache:  
_tiles/map_Name/Zoom/X_tile/Y_tile_  
with or without extension.

## Install&configure:
You must have a web server with php support. Just copy.  
Paths and other settings are described in `params.php`
Custom sources are in `mapsources/*`
Help about map sources are in `mapsources/mapsources.txt`

## Prepare SD card to cache
```
# mkfs.ext4 -O 64bit,metadata_csum -b 4096 -i 4096 /dev/sdb1
```
`-b 4096 -i 4096` set block to 4096 bytes and increase i-nodes to max.  
`-O 64bit,metadata_csum` needs for compability with old Android devices.  
`/dev/sdb1` - your SD card

## Direct access to the cache
If you server dead, but you have a rooted Android phone or tablet, you may use raster tiles directly:

### Mount SD card
1. remove SD card with the cache from the server
2. insert the SD card with cache to Android device
on Android device with terminal:
3. Open terminal. Try:
```
# mount -o rw,remount /
# mkdir /data/mySDcard 
# chown 1000:1000 /data/mySDcard
# chmod 774 /data/mySDcard
# mount -o ro,remount /
# mount -rw -t ext4 /dev/block/mmcblk1p1 /data/mySDcard
```
This creates mount point and mounts your SD card. There to, so you have all maps on your Android device.  
There:  
`/dev/block/mmcblk1p1` - partition wint cache on you SD card. To find it, try 
```
$ ls /dev/block
```
Last mmcblk - most probably your SD card.  
`/data/mySDcard` - mount point

Depending on how you obtain root, the command `mount` may be written as
```
# su --mount-master -c 'busybox mount -rw -t ext4 /dev/block/mmcblk1p1 /data/mySDcard' 
``` 

### Configure OruxMaps: 
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
and other maps by same way.  
There `/tiles/` - path to cache from the SD card root. 

### Automated mounting in Android with 3C toolbox
With **3C toolbox** you can automate the mounting when the device boots. 
1. Create file:   
_01_mountExtSDcard_
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
2. Place it in home directory by path <br>
`Android/data/ccc71.at.free/scripts/` or <br>
`Android/data/ccc71.at/scripts/` 
3. Open **3C toolbox** 
4. Go Tools - Script editor 
5. Mark _01_mountExtSDcard_ as runed in boot

## Loader
GaladrielCache includes a dumb tile loader. Create a csv job file with map_source_name.zoom as a name and x,y strings as content and place it in `loaderjobs/` directory. Start _loaderSched.php_ in cli. [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map) has a GUI for it.  
For example:  
_OpenSeaMap.9_
```
295,145
296,145
296,144
295,144
295,143
296,143
```

Will be downloaded OpenSeaMap within the specified tiles from zoom 9 to max zoom.  
Tile loader may use any number of threads to load, and use cron for robust download.  
You may use a [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map/tree/master) for control Loader. Job files, created by [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map/tree/master), saved in `loaderjobs/oldjobs` for backup.

### Use Loader to copy part of the cache
Add to the first line of csv job file some copy command. In this case, Loader starts this command instead of loading tile. 
This first line must be started from #.  
For example:  
_OpenSeaMap.9_
```
# mkdir -p /mnt/mySDcard/RegionTileCache/$r/$z/$x/ && cp -Hpu $tileCacheDir/$r/$z/$x/$y /mnt/mySDcard/RegionTileCache/$r/$z/$x/
295,145
296,145
296,144
295,144
295,143
296,143
```

This will copy the specified tiles of OpenSeaMap up to max zoom to destination.

You can use variables from _params.php_, but not from _mapsources/*_.  
Avoid creating job files that have a custom command and do not have one for one map.

## clearCache
Use in cli _clearCache.php mapname_ to *mapname* or _clearCache.php_ to all maps to remove from cache unwanted files, listed in $trash. This is maybe blank tiles or .tne files from SAS.Planet.  
Use in cli _clearCache.php mapname fresh_ to *mapname* or _clearCache.php fresh_ to all maps to remove from cache expired tiles.

## Coverage
GaladrielCache supports calculate coverage feature. To get the transparent tile with cover map add '_COVER' to map name. This return current_zoom+8 zoom level coverage. It is clear that every pixel of this tile indicate one tile +8 zoom level.  
Additionally displayed coverage of loader's max zoom level.

## Support
[Discussions](https://github.com/VladimirKalachikhin/Galadriel-map/discussions)

The forum will be more lively if you make a donation [via PayPal](https://paypal.me/VladimirKalachikhin)  at [galadrielmap@gmail.com](mailto:galadrielmap@gmail.com) or at [ЮMoney](https://yasobe.ru/na/galadrielmap)

[Paid personal consulting](https://kwork.ru/it-support/20093939/galadrielmap-installation-configuration-and-usage-consulting)  
