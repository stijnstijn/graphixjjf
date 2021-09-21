# GRAphics-oriented PHp Interface eXtravaganza for Jazz Jackrabbit Files (GRAPHIXJJF)

GRAPHIXJJF is a collection of PHP classes that can be used to read various Jazz 
Jackrabbit files and render them to an image. The following files are 
supported:

* Jazz Jackrabbit 1 levels (`LEVEL.###`)
* Jazz Jackrabbit 1 tilesets (`BLOCKS.###`)
* Jazz Jackrabbit 1 planet (`PLANET.###`)
* Jazz Jackrabbit 2 levels (`*.j2l`)
* Jazz Jackrabbit 2 tilesets (`*.j2t`)
* Jazz Jackrabbit 2 episodes (`*.j2e`)
* Jazz Jackrabbit 2 sprites (`*.j2a`)

Install it with composer:

```
composer install j2o/graphixjjf
```

Then use it, for example, like this:

```php
<?php
require 'vendor/autoload.php';
use J2o\Lib\J2AFile;

$j2afile = new J2AFile('Anims.j2a');
$settings = $j2afile->get_settings();

echo 'Rendering '.$settings['set_count'].' animation sets to a spritesheet...'.PHP_EOL;
imagepng($j2afile->get_preview(), 'spritesheet.png');
```

One way of using these classes is to extract information from these files, e.g.
the name of a tileset, the help strings of a JJ2 level or the size of a JJ1
level. This can be useful for various things (calculating the amount of pickups
in a level, finding the most-used music file for all levels in a folder, et
cetera).

*Note that to render most J2L files* you will need to put the required `.j2a`
files in the `resources` folder. Usually you can simply copy all j2a files in
your game folder, dump them there, and it should work. GRAPHIXJJF assumes that
the Anims.j2a file it reads from is the The Secret Files version.

## API
A command-line script `render-file.php` is provided to generate PNG previews of
a given file with. However, the focus of this library is on the classes 
themselves. These have different APIs depending on the type of file, but
a common API consists of the following methods:

* `JJFile::get_settings() : array` returns file settings, such as the amount
  of layers in a level, tileset name, et cetera. 
* `JJFile::get_preview() : resource` returns a GD image resource to which
  the file has been rendered (see below) 

## Rendering images
A somewhat more exciting feature is that all these classes are able to render
an image of any files loaded with them. So you can dump a tileset as an image,
or automatically generate level screenshots, or even generate a full-size
preview image of a level.

The most sophisticated variety of this is the Jazz Jackrabbit 2 level renderer,
which is able to render not just the level data but also any events in it, and
it is additonally able to interpret a number of scripted settings. Notably it
can accurately read changes made to the level with 
[MLLE](https://github.com/violet-clm/MLLE); palette changes, extra layers,
edited tiles et cetera will be rendered correctly. Furthermore, if the level's
script implements custom weapons using either MLLE's weapon library or 
[SEWeapon](https://www.jazz2online.com/downloads/7759/standard-weapon-interface/)
these will properly replace the vanilla weapons where appropriate.

TL;DR You can generate accurate full-size preview images of Jazz Jackrabbit 2 
levels, up to and including MLLE-based scripting and custom weapons.

## Contents

* `base`: Classes for the various file formats
* `resources`: Animation libraries for rendering Jazz Jackrabbit 2 event 
  sprites
* `util`: Classes that are used by the file classes to render or parse data,
  but that do not themselves deal with Jazz Jackrabbit files
* `render-file.php`: An example script that can be used to generate images of
  Jazz Jackrabbit files from the command line.

## Why?
This library is part of [Jazz2Online](https://jazz2online.com) and used by it
to generate preview images of files users upload to the site. 