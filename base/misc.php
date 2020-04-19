<?php
/**
 * Miscellaneous utility functions
 */


/**
 * Get bytes value of memory_limit ini setting
 *
 * Thanks to https://stackoverflow.com/a/45767760
 *
 * @return int Memory limit in bytes
 */
function get_memory_limit(): int {
    $string = ini_get('memory_limit');

    return intval(preg_replace_callback('/(\-?\d+)(.?)/', function ($m) {
        return $m[1] * pow(1024, strpos('BKMG', $m[2]));
    }, strtoupper($string)));
}


/**
 * Choose random value from given array
 *
 * @param array $array Array to choose from
 *
 * @return mixed  Random value from array
 */
function array_rand_value(array $array) {
    return $array[array_rand($array)];
}


/**
 * Imagecopymerge, but preserve alpha values
 *
 * @param resource $dst_im
 * @param resource $src_im
 * @param int $dst_x
 * @param int $dst_y
 * @param int $src_x
 * @param int $src_y
 * @param int $src_w
 * @param int $src_h
 * @param int $pct
 */
function imagecopymerge_alpha($dst_im, $src_im, int $dst_x, int $dst_y, int $src_x, int $src_y, int $src_w, int $src_h, int $pct): void {
    //http://php.net/manual/en/function.imagecopymerge.php#92787
    $cut = imagecreatetruecolor($src_w, $src_h);
    imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
    imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
    imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);
}


/**
 * Wrapper for `imagesetpixel()` that accepts colour components
 *
 * Eliminates the need for `imagecolorallocate` and is (supposedly) faster as well, though I haven't benchmarked that.
 * Thanks to https://php.net/manual/en/function.imagesetpixel.php#122176 for the suggestion.
 *
 * @param resource $image Image resource
 * @param int $x X coordinate
 * @param int $y Y coordinate
 * @param int $red Red RGB component
 * @param int $green Green RGB component
 * @param int $blue Blue RGB component
 *
 * @return bool  Result of `imagesetpixel()`
 */
function imageputpixel($image, int $x, int $y, int $red, int $green, int $blue): bool {
    return imagesetpixel($image, $x, $y, $red << 16 | $green << 8 | $blue);
}


/**
 * Provide a backtrace, then stop executing
 *
 * @param mixed $arg Variable to dump before tracing
 */
function debug_here($arg = 'here buG???'): void {
    $backtrace = debug_backtrace();
    $trace = '';
    foreach ($backtrace as $item) {
        if (!isset($item['file']) || !isset($item['line'])) {
            if (isset($item['function'])) {
                $trace .= ' -> '.$item['function'].'<br>';
            } else {
                $trace .= ' -> (unknown)<br>';
            }
        } else {
            $trace .= ' -> '.$item['file'].':'.$item['line'].'<br>';
        }
    }

    ob_start();
    var_dump($arg);
    $var = ob_get_clean();

    echo <<<PROVISIONAL_ERROR
<!doctype html><title>Website error</title><div style="width:75%;margin:0 auto;margin-top:2em;font-size:16px;padding:1em;background:#CCC;color:#000;border:1px solid #000;font-family:monospace;"><p style="font-weight:bold;">Trace</p><p><pre>{$var}</pre>at:</p><p>{$trace}</p></div>
PROVISIONAL_ERROR;
//    exit;
}