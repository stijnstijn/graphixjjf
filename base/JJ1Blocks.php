<?php
/**
 * Read JJ1 Blocks file, i.e. the 'tilesets' of JJ1
 */

namespace J2o\Lib;

use J2o\Exception\JJ1FileException;


/**
 * Class JJ1Blocks
 */
class JJ1Blocks extends JJ1File
{
    /**
     * @var int  Allocated colour ID to use as a transparent colour
     */
    public ?int $transparent = NULL;


    /**
     * Parse blocks file metadata and header info
     *
     * Technically the blocks file has 3 traditional RLE-encoded substreams (being palettes) and then four sections of
     * tiles, which contain 60 RLE-encoded substreams (one per tile) themselves. Because this is inconvenient we're
     * gonna pretend the tile map is a fourth substream, and parse the contents of those four sections into one
     * pre-loaded substream
     */
    function parse_header() : void {
        $header = new BufferReader($this->data);

        //first three blocks are standard RLE blocks, containing the tileset palettes
        for($i = 0; $i < 3; $i += 1) {
            $this->substream_sizes[$i] = $header->short() + 2;
            $header->skip($this->substream_sizes[$i] - 2);
        }

        //then come the tiles
        $this->substreams[$i] = ''; //pre-load tiles
        while(true) {
            if($header->get_remaining() <= 1 || $header->string(2) != 'ok') {
                break; //no more tiles
            }
            for($j = 0; $j < 60; $j += 1) { //60 tiles (6 rows) per block
                $this->substreams[$i] .= $this->load_RLE($header);
            }
        }

        $this->settings['tile_count'] = strlen($this->substreams[$i]) / 1024; //1024 bytes per tile
    }


    /**
     * Get tileset palette
     *
     * @param int $stream Substream to load; should be 0, 1 or 2 (blocks file contain three palettes)
     *
     * @return array  Colours, 128-item array with `[r, g, b]` values
     * @throws JJ1FileException  If an invalid palette index is given
     */
    function get_palette(int $stream = 0) : array {
        if(!in_array($stream, [0, 1, 2])) {
            throw new JJ1FileException('Tried to parse substream '.$stream.' as JJ1 palette, but is not a palette substream');
        }

        $bytes = $this->get_substream($stream);
        $colors = new BufferReader($bytes);
        $palette = [];
        for($i = 0; $i < strlen($bytes); $i += 1) {
            $rgb = $colors->char();
            $rgb = $rgb << 2; //values are in 6-bit, convert to 8-bit
            $index = intval(floor($i / 3));
            $palette[$index][$i % 3] = $rgb;
        }

        //make the transparent color "JCS blue"
        $palette[127] = [87, 0, 203];

        return $palette;
    }


    /**
     * Generate tileset preview image
     *
     * @return resource  GD Image resource
     */
    public function get_image() {
        $image = imagecreatetruecolor(320, 32 * ($this->settings['tile_count'] / 10));
        $palette = $this->get_palette(0);
        $map = new BufferReader($this->get_substream(3));

        $allocated = [127 => imagecolorallocatealpha($image, $palette[127][0], $palette[127][1], $palette[127][2], 127)];
        $this->transparent = $allocated[127];
        imagefill($image, 0, 0, $this->transparent);
        imagesavealpha($image, true);

        for($i = 0; $i < $this->settings['tile_count']; $i += 1) {
            $tile_x = ($i % 10) * 32;
            $tile_y = floor($i / 10) * 32;
            for($pixel = 0; $pixel < 1024; $pixel += 1) {
                $index = $map->char();
                $x = $tile_x + ($pixel % 32);
                $y = $tile_y + floor($pixel / 32);
                if(!isset($allocated[$index])) {
                    $allocated[$index] = imagecolorallocate($image, $palette[$index][0], $palette[$index][1], $palette[$index][2]);
                }
                if($index != 127) {
                    imagesetpixel($image, $x, $y, $allocated[$index]);
                }
            }
        }

        return $image;
    }

    /**
     * Generate preview image
     *
     * @return resource  GD Image resource
     */
    public function get_preview() {
        $tiles = $this->get_image();
        $blue_bg = imagecreatetruecolor(imagesx($tiles), imagesy($tiles));
        imagefill($blue_bg, 0, 0, imagecolorallocate($blue_bg, 87, 0, 203));
        imagecopy($blue_bg, $tiles, 0, 0, 0, 0, imagesx($tiles), imagesy($tiles));
        return $blue_bg;
    }
}