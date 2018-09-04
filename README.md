# GaladrielCache
This is a simple raster map tiles cache/proxy to use on a weak computers such as RaspberryPi or NAS. Author use it in the wi-fi router/GSM modem under OpenWRT on his sailboat Galadriel.<br>
GaladrielCache can be used with any on-line map viewer. [OruxMaps](http://www.oruxmaps.com/cs/en/) is a good choice.<br>
Tiles stored on standard OSM z/x/y file structure, so you may use SD with maps directly on your smartphone in the event of a disaster.

## Features:
1. User-defined map sources
2. nginx support
3. Dumb tile loader

It's all. No versioning, no reprojection.

## Usage:
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
where 192.168.1.1 - your server, /tileproxy/ - path to project, /tiles/ - path to file storage in the sense of a web server, and yasat.EPSG3395 - your custom map source name.

ATTENTION! You MUST configure your MAP VIEWER for the use specific projection! 
(`<projection>MERCATORELIPSOIDAL</projection>` in the example above)<br> 
GaladrielCache knows nothing about projections, it's store tiles only.

## Install&configure:
You must have a web server with php support. Just copy.<br>
Paths and other set and describe in _params.php_<br>
Custom sources are in _mapsources/_<br>
Help about map sources are in _mapsources/mapsources.txt_

If you use nginx to serve cache, configure 404 helper to proxy:
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
# une2fs -m 1 /dev/sdb1
```
it's reduce system area.
/dev/sdb1 - your SD card

## Loader.
GaladrielCache includes dumb tile loader. Create a job file with map_source_name.zoom name and x,y strings as content and place it in loaderjobs/ directory. Start loaderSched.php in cli.
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

Will be downloaded navionics_layer map within the specified tiles from zoom 9 to max zoom.
