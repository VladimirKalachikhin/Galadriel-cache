[Русское описание](README.md)  
# GaladrielCache [![License: CC BY-NC-SA 4.0](Cc-by-nc-sa_icon.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/deed.en)
This is a simple map tiles cache/proxy to use on weak computers such as RaspberryPi or NAS. The author uses it in the [wi-fi router/GSM modem under OpenWRT](https://github.com/VladimirKalachikhin/MT7620_openwrt_firmware) on his sailboat Galadriel.  
GaladrielCache can be used with any on-line map viewer. [OruxMaps](http://www.oruxmaps.com/cs/en/) is a good choice. [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map) is a good choice too.   
Tiles locally stored on OSM z/x/y file structure, so you may use SD with raster maps without a server -- directly on your smartphone in the event of a disaster.

## v. 2.10

Contains:
* [Features](#features)
* [Requirements](#requirements)
* [Usage](#usage)
* * [OruxMaps configuration](#oruxmaps-configuration)
* * [GaladrielMap configuration](#galadrielgap-configuration)
* * [SignalK](#signalk)
* * [OSM "slippy map" tilenames](#osm-slippy-map-tilenames)
* * [MBTiles](#mbtiles)
* [Install&configure](#install&configure)
* [Prepare SD card to cache](#prepare-sd-card-to-cache)
* [Direct access to the cache](#direct-access-to-the-cache)
* * [Mount SD card](#mount-sd-card)
* * [Configure OruxMaps](#configure-oruxmaps)
* * [Automated mounting in Android with 3C toolbox](#automated-mounting-in-android-with-3c-toolbox)
* [Loader](#loader)
* * [Using Loader to copy part of the cache](#using-loader-to-copy-part-of-the-cache) 
* [Cache control (API)](#cache-control)
* * [Get maps list](#get-maps-list)
* * [Get map info](#get-map-info)
* * [Create loader job](#create-loader-job)
* * [Get loader status](#get-loader-status)
* [Utilites](#utilites)
* * [clearCache](#clearcache)
* * [checkSources](#checksources)
* * [Coverage](#coverage)
* [Support](#support)

## Features:
1. User-defined internet map sources, with versioning, if needed.
2. Flexible and robust tile loading with proxy support.
3. Prior loading of large zoom levels.
4. Asynchronous cache freshing.
5. Separated robust loader.
6. Serves [MBTiles](https://github.com/mapbox/mbtiles-spec/blob/master/1.3/spec.md) local maps.

But no reprojection. Map projection is a client application problem.

## Requirements
Linux, PHP < 8. The cretinous decisions made at PHP 8 do not allow the GaladrielCache to work at PHP 8, and I do not want to follow these decisions.
You must have the PHP modules installed:  

* cli
* php-fpm - if you don't use Apache2
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

These modules are included in the PHP by default in "full" linux, but not in OpenWRT nor Raspberry Pi oses.

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

### SignalK
To use GaladrielCache in SignalK just configure [charts-plugin](https://www.npmjs.com/package/@signalk/charts-plugin) to use every map available in the GaladrielCache.  
For examle, for OpenTopoMap specify URL to `http://you_server/tileproxy/tiles.php?z={z}&x={x}&y={y}&r=OpenTopoMap`

### OSM "slippy map" tilenames
Some applications (AvNav?) cannot use tiles other than in [OSM "slippy map" tilenames](https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames) format. To use the GaladrielCache with these applications, you can configure the Apache2 as follows:  
```
<IfModule rewrite_module>
	RewriteEngine On
	RewriteRule ^tiles/(.+)/([0-9]+)/([0-9]+)/([0-9.a-z]+)$ tiles.php?r=$1&z=$2&x=$3&y=$4
</IfModule>
```
and refer to the cache:  
_tiles/map_Name/Zoom/X_tile/Y_tile_  
with or without extension.

### MBTiles
The file with map on mbtiles format must have `.mbtiles` extension and located in _$tileCacheDir_ directory. The map description file must have same name as map file, but with _.php_ extension of course, and similarly to other such files be locate in _mapsources/_ directory.  
It doesn't matter to the GaladrielCache whether vector or raster tiles are in the map file. The corresponding configuration should be in the map description file.

## Install&configure
You must have a web server with php support. Just copy.  
Paths and other settings are described in `params.php`
Custom sources are in `mapsources/*`
Help about map sources are in mapsources/[mapsources.md](https://github.com/VladimirKalachikhin/Galadriel-cache/blob/master/mapsources/mapsources.md)

## Prepare SD card to cache
```
# mkfs.ext4 -O 64bit,metadata_csum -b 4096 -i 4096 /dev/sdXX
```
`-b 4096 -i 4096` set block to 4096 bytes and increase i-nodes to max.  
`-O 64bit,metadata_csum` needs for compability with old Android devices.  
`/dev/sdXX` - your SD card partition.  
However, default formatting in vfat allows you to store a sufficient amount of tiles on the map for practical purposes.

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
GaladrielCache includes a dumb tile loader. Create a csv job file with map_source_name.zoom as a name and x,y strings as content and place it in `loaderjobs/` directory. Run _startLoaderDaemon_ (or _php loaderSched.php_ for foreground) in cli. [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map) has a GUI for it.  
To stop Loader run _stopLoader_ or delete Loader job files bouth from `loaderjobs/` and `loaderjobs/inWork/` directories.

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

Will be downloaded OpenSeaMap within the specified tiles from zoom 9 to $loaderMaxZoom from _params.php_.  

Tile loader may use any number of threads to load, and use cron for robust download.  
You may use a [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map/tree/master) for control Loader. Job files, created by [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map/tree/master), saved in `loaderjobs/oldjobs` for backup.

### Using Loader to copy part of the cache
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

## Cache control
The `cacheControl.php` web service implements an interface (API) for check cache status and loader control.  
The service returns JSON, inclide _null_ on error.

Usage:
### Get maps list
`http://you_address/cacheControl.php?getMapList`  

This returns:  
```
{
	"map_source_file_name": {
		"en": "Human-readable english map name or map_source_file_name",
		["ru": ...]
	}
}
```
### Get map info
`http://you_address/cacheControl.php?getMapInfo=map_source_file_name`  

This returns:  
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
from _mapsources/map_source_file_name.php_

### Create loader job
`http://you_address/cacheControl.php?loaderJob=map_source_file_name.zoom&xys=csv_of_XY`  

This create & run tile loading job. Returns:  
```
{
	"status": 0,
	"jobName": map_source_file_name.zoom
}
```

### Get loader status
`http://you_address/cacheControl.php?loaderStatus[&restartLoader]  

This optionaly (re)start loader. Returns:  
```
{
	"loaderRun": scheduler_PID,
	"jobsInfo": {
		"map_source_file_name.zoom": %_of_complite
		[...]
	}
}
```

## Utilites
### clearCache
Use in cli _clearCache.php mapname_ to *mapname* or _clearCache.php_ to all maps to remove from cache unwanted files, listed in $trash. This is maybe blank tiles or .tne files from SAS.Planet. Tiles outside the bounds of the map are also deleted.  
Use in cli _clearCache.php mapname fresh_ to *mapname* or _clearCache.php fresh_ to all maps to remove from cache expired tiles.

### checkSources
The checkSources.php is cli utility for check map source viability. If maps definition file include special tile info - checkSources.php reads this tile from source and compare loaded with stored. The result is logged.
Use cron to run it periodically.

### Coverage
Run _php checkCovers.php mapName.zoom max_zoom_ for calculate coverage of given region, described as Loader job file mapName.zoom. The `checkCoversData/notFound/` directory contains a Loader job files with tiles that are missing from the coverage.  
For display in map GaladrielCache supports calculate coverage feature. To get the transparent tile with cover map add '_COVER' to map name. This return current_zoom+8 zoom level coverage. It is clear that every pixel of this tile indicate one tile +8 zoom level. Additionally displayed coverage of loader's max zoom level.  
The [GaladrielMap](https://github.com/VladimirKalachikhin/Galadriel-map/tree/master) supports this feature.

## Support
[Discussions](https://github.com/VladimirKalachikhin/Galadriel-map/discussions)

The forum will be more lively if you make a donation at [ЮMoney](https://sobe.ru/na/galadrielmap)

[Paid personal consulting](https://kwork.ru/it-support/20093939/galadrielmap-installation-configuration-and-usage-consulting)  
