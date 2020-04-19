<?php

namespace J2o\Lib;

/**
 * JJ1 Level handler. Can generate a level preview image.
 */

use J2o\Exception\JJ1FileException;


/**
 * Class JJ1Level
 */
class JJ1Level extends JJ1File
{
    /**
     * @var JJ1Blocks  Tileset to use for this level
     */
    private ?JJ1Blocks $blocksfile = NULL;


    /**
     * Parse header
     *
     * Unlike in JJ2, JJ1 level files don't sequentially store uncompressed block sizes in the header, so we have to
     * skip through the file to get the size for each block (and there are some random uncompressed blocks sprinkled
     * throughout too)
     *
     * Because JJ1 uses RLE encoding rather than zlib compression, uncompressed block sizes aren't stored either, so
     * we're parsing just the compressed sizes here
     */
    protected function parse_header() : void {
        $header = new BufferReader($this->data);

        //skip signature
        $header->seek(39);
        $this->substream_sizes = [0 => 39];

        for($i = 1; $i < 17; $i += 1) {
            //most are simply RLE-encoded blocks (with a short indicating block size before them), except for three
            //that are just uncompressed blobs of info for some reason - sizes of those from OpenJazz's source
            $skip = 0;
            if($i == 9) {
                $this->substream_sizes[$i] = 598;
            } elseif($i == 11) {
                $this->substream_sizes[$i] = 4; //unknown, according to DD
            } elseif($i == 14) {
                $this->substream_sizes[$i] = 25;
            } elseif($i == 16) {
                $this->substream_sizes[$i] = 3;
            } else {
                $this->substream_sizes[$i] = $header->short() + 2;
                $skip -= 2;
            }
            $skip += $this->substream_sizes[$i];
            $header->skip($skip);
        }

        $this->settings = [
            'num_level'  => $header->char() ^ 210,
            'num_world'  => $header->char() ^ 4,
            'num_blocks' => $header->skip(9)->string(3)
        ];

        if($this->settings['num_blocks'] == '999') {
            $this->settings['num_blocks'] = $this->settings['num_world'];
        }
    }


    /**
     * Load blocks file to use for rendering this level
     *
     * @param string $path Path to blocks file. If not given, it will be guessed.
     *
     * @throws JJ1FileException If blocks file could not be read or parsed
     */
    public function load_blocks(string $path = NULL) : void {
        if(!$path) {
            $settings = $this->get_settings();
            $blocks_file = 'BLOCKS.' . $settings['num_blocks'];
            $path = explode(DIRECTORY_SEPARATOR, $this->filename);
            array_pop($path);
            array_push($path, $blocks_file);
        }

        if(!is_readable($path)) {
            throw new JJ1FileException('Cannot read blocks file '.$path);
        }

        try {
            $blocksfile = new JJ1Blocks($path);
            $this->blocksfile = &$blocksfile;
        } catch(JJ1FileException $e) {
            throw new JJ1FileException('Could not load blocks file '.$path.': '.$e->getMessage());
        }
    }


    /**
     * Check if blocks file for this level is loaded
     *
     * @return bool  Has `load_blocks` been called (succefully)?
     */
    public function has_blocks() : bool {
        return isset($this->blocksfile) && $this->blocksfile instanceof JJ1Blocks;
    }


    /**
     * Draw level background in image
     *
     * It's not entirely clear how JJ1 itself positions background - there's some kind of parallax scrolling going on.
     * But stretching the background to 150% vertically seems to give relatively precise results for levels that rely on
     * the scrolling, so we're going with that. The background gradient is drawn at a -1px offset because of what is
     * presumably a rounding error putting it 1 px too low ordinarily, at least in Bad Seed.
     *
     * @param resource $image GD image resource to draw to
     */
    private function render_background($image) : void {
        $properties = $this->get_substream(14);
        $palette = $this->blocksfile->get_palette(1);
        $palette2 = $this->blocksfile->get_palette(2);

        $type = intval(ord($properties[0]));
        if(in_array($type, [2, 3, 4, 5, 6, 7, 10, 11])) {
            //exclude palette entry 127, because of course we have to randomly skip one to get the right result
            $ignore = function($a) {
                return $a != 127;
            };
            $palette = array_values(array_filter($palette, $ignore, ARRAY_FILTER_USE_KEY));
            $palette2 = array_values(array_filter($palette2, $ignore, ARRAY_FILTER_USE_KEY));
            $gradient = imagecreatetruecolor(1, 2 * (count($palette) + count($palette2) - 2));

            //draw the two gradients on top of each other
            foreach($palette as $i => $color) {
                imageline($gradient, 0, $i, 1, $i, imagecolorallocate($gradient, $color[0], $color[1], $color[2]));
            }
            $inc = count($palette) - 1;
            foreach($palette2 as $i => $color) {
                imageline($gradient, 0, $i + $inc, 1, $i + $inc, imagecolorallocate($gradient, $color[0], $color[1], $color[2]));
            }

            //repeat the image vertically, then copy to the level at 1.5x scale, for reasonably accurate results
            imagecopy($gradient, $gradient, 0, $i + $inc, 0, 0, 1, $i + $inc - 1);
            imagecopyresampled($image, $gradient, 0, -1, 0, 0, imagesx($image), imagesy($gradient) * 3, imagesx($gradient), imagesy($gradient));
            imagedestroy($gradient);
        } else {
            //should probably try to cover some of the other background modes as well...
            $color = $palette[42];
            imagefill($image, 0, 0, imagecolorallocate($image, $color[0], $color[1], $color[2]));
        }
    }


    /**
     * Generate level preview image
     *
     * @return resource  GD Image resource
     */
    function get_image() {
        if(!isset($this->blocksfile)) {
            $path = explode('/', $this->filename);
            array_pop($path);
            $blocks_path = '.'.implode('/', $path).'/BLOCKS.'.$this->settings['num_blocks'];
            $this->load_blocks($blocks_path); //best guess we've got with just info from the level
        }
        //set up image and load tileset
        $level = imagecreatetruecolor(256 * 32, 64 * 32);
        $this->render_background($level);


        //get tileset palette and load the image
        $palette = $this->blocksfile->get_palette(0);
        $tileset = $this->blocksfile->get_image();

        //determine the colour for tiles with a background (event > 128)
        $black_palette = $palette[31];
        $black = imagecolorallocate($level, $black_palette[0], $black_palette[1], $black_palette[2]);

        //copy tiles from tileset to level
        $map = new BufferReader($this->get_substream(1));
        $tiles = $map->get_length() / 2; //two bytes per tile

        for($i = 0; $i < $tiles; $i += 1) {
            $tile = $map->char();

            //determine positions to copy from and to
            $set_x = ($tile % 10) * 32;
            $set_y = floor($tile / 10) * 32;

            $level_x = floor($i / 64) * 32;
            $level_y = ($i % 64) * 32;

            //put background on tile if needed - each byte following a tile ID is the 'event' ID
            $event = $map->char();
            if($event >= 128) { //doesn't apply in some obscure situations, but these aren't used generally
                imagefilledrectangle($level, $level_x, $level_y, $level_x + 32, $level_y + 32, $black);
            }

            imagecopy($level, $tileset, $level_x, $level_y, $set_x, $set_y, 32, 32);
        }

        return $level;
    }

    /**
     * Generate preview image
     *
     * @return resource  GD Image resource
     */
    public function get_preview() {
        return $this->get_image();
    }
}