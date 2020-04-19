<?php
/**
 * Generate a preview of Jazz Jackrabbit game files
 */
namespace J2o;

require 'vendor/autoload.php';

ini_set('memory_limit', '4G'); //some of these generators need a lot of RAM
ini_set('max_execution_time', 600);

if(!isset($argv[1])) {
    echo 'Provide a file as a command-line argument.';
    exit(1);
}

if(!file_exists($argv[1])) {
    echo 'File not found';
    exit(1);
}

//determine type...
$filename = explode(DIRECTORY_SEPARATOR, $argv[1]);
$filename = array_pop($filename);
if (preg_match('/BLOCKS[^.]*\.([0-9]{3})/si', $filename, $jj1_tileset)) {
    $type = 'jj1tile';
} elseif (preg_match('/LEVEL[^.]*\.([0-9]{3})/si', $filename, $jj1_level)) {
    $type = 'jj1level';
} elseif (preg_match('/PLANET[^.]*\.([0-9]{3})/si', $filename, $jj1_planet)) {
    $type = 'jj1planet';
} else {
    $type = explode('.', $filename);
    $type = array_pop($type);
}

//make file object
switch($type) {
    case 'j2a':
        $file = new Lib\J2AFile($argv[1]);
        break;

    case 'j2t':
        $file = new Lib\J2TFile($argv[1]);
        break;

    case 'j2e':
        $file = new Lib\J2EFile($argv[1]);
        break;

    case 'jj1tile':
        $file = new Lib\JJ1Blocks($argv[1]);
        break;

    case 'jj1planet':
        $file = new Lib\JJ1Planet($argv[1]);
        break;

    case 'j2l':
        $file = new Lib\J2LFile($argv[1]);

        //load script files, etc
        $path = explode(DIRECTORY_SEPARATOR, $argv[1]);
        array_pop($path);
        $path = implode(DIRECTORY_SEPARATOR, $path);
        $files = glob('*.*');
        foreach($files as $file) {
            $extension = explode('.', $file);
            $extension = array_pop($extension);

            if(in_array($extension, ['.j2t', '.j2l', '.j2as', '.asc'])) {
                $file->load_adjacent([$file]);
            }
        };
        break;

    case 'jj1level':
        $file = new Lib\JJ1Level($argv[1]);
        break;

    default:
        $file = false;
        break;
}

if($file) {
    $output_file = explode(DIRECTORY_SEPARATOR, $argv[1]);
    $output_file = explode('.', array_pop($output_file));
    array_pop($output_file);
    $output_file = implode('.', $output_file).'.png';
    imagepng($file->get_preview(), $output_file);
    echo 'Done!';
} else {
    echo 'Unsupported file type.';
    exit(1);
}