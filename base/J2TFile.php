<?php
/**
 * Tileset reader - gets information from file and provides methods to render it to an image
 */

namespace J2o\Lib;

use J2o\Exception\JJ2FileException;

/**
 * Tileset class
 *
 * @package J2o\Lib
 */
class J2TFile extends JJ2File {
    /**
     * Magic byte value for TSF tilesets
     */
    const VERSION_TSF = 513;
    /**
     * Magic byte value for 1.23 tilesets
     */
    const VERSION_123 = 512;

    /**
     * @var int Max tiles a tileset can have; depends on JJ2 version
     */
    private int $max_tiles = 0;
    /**
     * @var int|NULL GD color index for this tileset's transparent color
     */
    public ?int $transparent = NULL;


    /**
     * J2TFile constructor. Loads tileset file into memory for further processing.
     *
     * @param string $filename Path to tileset (.j2t) file
     *
     * @throws JJ2FileException  If tileset could not be found or is zero bytes
     */
    public function __construct(string $filename) {
        if (!is_readable($filename)) {
            throw new JJ2FileException('Could not read tileset file '.$filename);
        }

        if (filesize($filename) == 0) {
            throw new JJ2FileException('Tileset file '.$filename.' is zero bytes');
        }

        $this->filename = $filename;
        $this->data = file_get_contents($filename);
        $this->parse_header();
    }


    /**
     * Retrieve tileset header
     *
     * The tileset header (first 262 bytes) contains offsets for the actual tileset data
     * which is retrieved here, and stored in the offsets array. The header is saved too
     * for later usage. The palette is then analyzed and also stored. It also determines
     * whether the tileset is a TSF or 1.23 tileset and sets some variables based on that
     * such as the amount of tiles to look for.
     *
     * @access  private
     */
    private function parse_header(): void {
        $raw_header = substr($this->data, 0, self::HEADER_SIZE);
        $header = new BufferReader($raw_header);

        $header->seek(188);
        $this->name = $header->string(32);

        $header->seek(220);
        $this->version = $header->short();
        $this->max_tiles = $this->version == self::VERSION_TSF ? 4096 : 1024;

        $header->seek(230);
        $this->substream_sizes = [
            'data1_c' => $header->long(),
            'data1_u' => $header->long(),
            'data2_c' => $header->long(),
            'data2_u' => $header->long(),
            'data3_c' => $header->long(),
            'data3_u' => $header->long(),
            'data4_c' => $header->long(),
            'data4_u' => $header->long(),
        ];
    }


    /**
     * Parse tileset settings (palette, tile count, offsets) and return as array
     *
     * Result is saved for later re-use
     *
     * @return array  Settings
     */
    public function get_settings(): array {
        if (!isset($this->settings)) {
            $settings = $this->get_substream(1);
            $bytes = new BufferReader($settings);
            $this->settings = [
                'palette' => $bytes->bytes(4, 256),
                'tile_count' => $bytes->long(),
                'tile_opaque' => $bytes->bool($this->max_tiles),
                'tile_unknown1' => $bytes->bool($this->max_tiles),
                'tile_offset' => $bytes->long($this->max_tiles),
                'tile_unknown2' => $bytes->long($this->max_tiles),
                'tile_offset_transparency' => $bytes->long($this->max_tiles),
                'tile_unknown3' => $bytes->long($this->max_tiles),
                'tile_offset_mask' => $bytes->long($this->max_tiles),
                'tile_offset_mask_flipped' => $bytes->long($this->max_tiles)
            ];

            $this->settings['palette'] = array_map(function ($c) {
                return [ord(substr($c, 0, 1)), ord(substr($c, 1, 1)), ord(substr($c, 2, 1))];
            }, $this->settings['palette']);
        }

        return $this->settings;
    }

    /**
     * Get palette
     *
     * @return array  Array of RGB values `[r, g, b]`
     */
    public function get_palette(): array {
        $settings = $this->get_settings();
        return $settings['palette'];
    }


    /**
     * Load array as palette
     *
     * @param array $palette 256 entries with `[r, g, b]` structure
     *
     * @throws JJ2FileException If palette does not consist of 256 3-colour pairs
     */
    public function load_palette(array $palette): void {
        if (count($palette) != 256) {
            throw new JJ2FileException('Palette given for loading does not have 256 colours');
        }

        $this->get_settings();

        foreach ($palette as $index => $colors) {
            if (count($colors) != 3) {
                throw new JJ2FileException('Palette given invalid; index '.$index.' has '.count($colors).' colours');
            }

            $this->settings['palette'][$index] = $colors;
        }
    }


    /**
     * Get preview image
     *
     * `get_images()` renders with a transparent background, which is useful for further processing, but doesn't look
     * ideal when just viewing the tileset. This puts that image on a "JCS Blue" background so it looks just like it
     * would in JCS, which is more useful for previewing the actual tileset (as opposed to a level using it).
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


    /**
     * Generate tileset image
     *
     * Goes through the tile indexes one by one and draws that tile to the tileset image
     * pixel for pixel. If a tile is a duplicate of another tile, that other tile is copied
     * instead. The image is then saved so it does not need to be generated again if the method
     * is called again. If the image data is not uncompressed yet the method to do so is
     * called.
     *
     * @returns resource The image resource (GDLib) containing the full tileset image.
     */
    public function get_image() {
        $settings = $this->get_settings();
        $image = imagecreatetruecolor(320, (($settings['tile_count'] / 10) * 32));

        $allocated = [];
        foreach ($settings['palette'] as $color => $rgb) {
            if ($color == 1) {
                $allocated[$color] = imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], 127);
                $this->transparent = $allocated[$color];
            } else {
                $allocated[$color] = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
            }
        }

        imagefill($image, 0, 0, $this->transparent);

        //loop through the tiles in the map
        $pixels = new BufferReader($this->get_substream(2));
        foreach ($settings['tile_offset'] as $tile => $offset) {
            if ($offset == 0) { //empty tile
                continue;
            }

            $x = (($tile % 10) * 32);
            $y = (floor($tile / 10) * 32);

            $pixels->seek($offset);
            for ($pixel = 0; $pixel < 1024; $pixel++) { //1024 pixels per tile
                //the byte value is the palette index for this tile
                $color = $pixels->char();
                $nx = $x + ($pixel % 32);
                $ny = $y + floor($pixel / 32);
                if ($color > 0) {
                    imagesetpixel($image, $nx, $ny, $allocated[$color]);
                }
            }
        }

        return $image;
    }


    /**
     * Generate mask image
     *
     * Generates a GDLib image resource representing the tileset mask. This is a
     * black-and-white image, except the background is made "JCS Blue".
     *
     * @returns resource  The image resource (GDLib) containing the mask image.
     */
    public function get_image_mask() {
        $settings = $this->get_settings();
        $image = imagecreate(320, (($settings['tile_count'] / 10) * 32));

        //allocate colours: 'JCS Blue' (background) and black (masks)
        imagefill($image, 1, 1, imagecolorallocate($image, 87, 0, 203)); //"JCS blue"
        $black = imagecolorallocate($image, 0, 0, 0);

        //loop through mask data
        $mask = new BufferReader($this->get_substream(4));
        foreach ($settings['tile_offset_mask'] as $tile => $offset) {
            if ($offset == 0) { //empty tile
                continue;
            }

            $x = (($tile % 10) * 32);
            $y = (floor($tile / 10) * 32);

            $mask->seek($offset);
            for ($pixel = 0; $pixel < 128; $pixel++) { //128 bytes per tile
                //bit by bit... if bit = 1 then draw a pixel, if 0 then transparent
                $byte = $mask->char();
                for ($i = 0; $i < 8; $i++) {
                    $is_mask = ($byte & pow(2, $i)); //bit value
                    $bit = (($pixel * 8) + $i); //bit index, for position
                    $nx = $x + ($bit % 32);
                    $ny = $y + floor($bit / 32);
                    if ($is_mask != 0) {
                        imagesetpixel($image, $nx, $ny, $black);
                    }
                }
            }
        };

        return $image;
    }
}