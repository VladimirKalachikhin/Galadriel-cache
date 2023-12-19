[Русское описание](https://github.com/VladimirKalachikhin/Galadriel-cache/blob/master/mapsources/mapsources.ru-RU.md)  
# GaladrielCache map sources
In `mapsources/` directory are stored files fully describing map and map source, include uri, projection and hooks for get tile, tile handing and GaladrielMap interaction.

## Map name and map description file
The map description file has a name that is a map title and .php extension. This is a file in the PHP language, and its syntax must be fully observed.  
Map title are directory name in $tileCacheDir or .mbtiles file name in this directory.  
None of the parameters in map describe file are required. If necessary, default parameters will be used.

## Variables in map description file
The list of variables used in the description of the source is in `mapsourcesVariablesList.php`
You CANNOT modify this file.  
These variables are global.

## Hooks in map description file
Functions for various purposes wrapped in a string to prevent their definition where it is not necessary and/or redefinition.  
In all hooks you can use utilites from `fcommon.php`

### Function to get tile url
```
function getURL($z,$x,$y,$getURLparams) {}
```
`$z` - zoom  
`$x` - x number of tile  
`$y` - y number of tile  
`$getURLparams` - key => value array of different things  
One of these things is version of tiles: just additional section of path before Z, such as http://base.url/$layer/$z/$y/$x.png for example.  
If this present, then `$getURLparams['mapAddPath']` value present too. An example of use can be found in `Wether.php` map description file.

Function `getURL()` must return a string of uri for get the tile, or array of two items: string uri and array stream_context parameters.
About stream_context parameters see http://php.net/manual/en/context.php

### Function to get tile from local storage
```
function getTileFile($r,$z,$x,$y) {}
```
`$r` - map name, may be with path to version
`$z` - zoom  
`$x` - x number of tile  
`$y` - file name, y number and extension  

Usually tiles are stored in the file system in `$tileCacheDir/mapName/z/x/y.ext` structure. At same time `mapName` may include an additional path as  `mapName/mapAddPath`.
Function getTileFile() is alternative way to get tile from local storage. If it is specified in map description file, the GaladroelCache will try to get the tile file using this function, not from the file system.

There is only one ready-made such function: getting a tile from a MBTiles format file. For MBTiles map the map description file can only consist of:
```
$functionGetTileFile = <<<'EOFU'
function getTile($r,$z,$x,$y){
	require_once('MBTilesClient.php');
	return serveMBTiles($r,$z,$x,$y);
} // end function getTile
EOFU;
```
but for vector tiles you should specify  
```
$ext = "pbf";  
$ContentType = "application/x-protobuf";
$content_encoding = "gzip";
```
and have a style file.
See `world-coastline.php` for example.

Function must return `array('img'=>$img)`, there `$img` is a image binary string or `null`. The array can also contain any variables, for example  
`array('img'=>$img, 'ext'=>'png')`

### Function to prepare tile image
`function prepareTileImg($img)`  
`$img` - image binary string
This function calls before sent image to user.

Function must return `array('img'=>$img)`, there `$img` is a image binary string or `null`. The array can also contain any variables, for example, function may change image format and return new mime type  
`array('img'=>$img, 'ContentType'=>'image/jpeg')`  
More example see `OpenTopoMap.php`.

### Javascript for GaladrielMap
You can influence from map description file to GaladrielMap by
```
$data = array();
$data['javascriptOpen'] = ...; 	// the javascript string, eval'ed before create map
$data['javascriptClose'] = ...; 	// the javascript string, eval'ed after close map
```
See `Wether.php` for example.

## Vector tiles
-- must die. This is an ugly, resource-intensive format for benefit impudent merchants.  
But a MapBox .pbf vector tiles has limited support. Style .json file must have the same name as the map description file and lie there.
ATTENTION! The stupid boys which developed MapBox format takes full url for resources. So glifs and icons lay in GaladrielMap directory, and every style file must have full path urls to it.

