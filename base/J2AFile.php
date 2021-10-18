<?php
/**
 * J2A (anims library) handler
 */

namespace J2o\Lib;

use J2o\Exception\JJ2FileException;

/**
 * Class J2AFile
 *
 * @package J2o\Lib
 */
class J2AFile extends JJ2File {
    /**
     * @var array Palette for rendering
     */
    public ?array $palette = [];
    /**
     * @var string Filename of animation library
     */
    public string $filename = '';
    /**
     * @var string  Raw data to read from
     */
    protected string $data = '';
    /**
     * @var array  File settings, parsed from header
     */
    protected array $settings;
    /**
     * @var array  Palette remappings for specific sprites, with sprite setIDs as keys and
     * each setID being an array with animIDs as key and a 256-colour palette as value
     */
    private array $palette_remapping = [];

    /**
     * J2AFile constructor.
     *
     * @throws JJ2FileException  If file does not exist or is not readable
     */
    public function initialise(): void {
        if (!is_file($this->filename) || !is_readable($this->filename)) {
            throw new JJ2FileException('Not a readable file: '.$this->filename);
        }

        if ($this->palette === NULL) {
            $this->parse_palette();
        }

        $this->data = file_get_contents($this->filename);
        $this->settings = [
            'set_current' => -1,
            'set_count' => -1,
            'set_settings' => [],
            'set_offsets' => []
        ];

        $this->parse_header();
    }

    /**
     * Load palette remap for specific set and anim IDs
     *
     * @param array $remapping Palette remappings for specific sprites, with sprite setIDs as keys and
     * each setID being an array with animIDs as key and a 256-colour palette as value
     */
    public function load_remapping($remapping) {
        $this->palette_remapping = $remapping;
    }


    /**
     * Generate sprite sheet
     *
     * Only shows the first frame of each animation, cause else it's going to be huge in most cases. But should still
     * give a decent impression of the file contents
     *
     * @return resource GD Lib image resource
     */
    public function get_preview() {
        $w = 480; //arbitrary, really
        $row_h = 0;
        $row_w = 0;
        $frames = [];
        $row_heights = [];
        foreach ($this->settings['set_settings'] as $s => $set) {
            $this->load_set($s);
            for ($a = 0; $a < $set['anim_count']; $a += 1) {
                $frame = $this->get_frame($s, $a, 0);
                if($frame[0] === NULL) {
                    continue;
                }
                $row_w += $frame[0]['width'];
                if ($row_w > $w) {
                    $row_w = $frame[0]['width'];
                    $row_heights[] = $row_h;
                    $row_h = 0;
                }
                $row_h = max($row_h, $frame[0]['height']);
                $frames[] = $frame;
            }
        }

        $h = array_sum($row_heights) + $row_h;
        $preview = imagecreatetruecolor((count($row_heights) > 1 ? $w : $row_w), $h);
        imagefill($preview, 0, 0, imagecolorallocate($preview, 87, 0, 203));

        $x = $y = 0;
        foreach ($frames as $frame) {
            if ($x + $frame[0]['width'] > $w) {
                $x = 0;
                $y += array_shift($row_heights);
            }
            imagecopy($preview, $frame[1], $x, $y, 0, 0, imagesx($frame[1]), imagesy($frame[1]));
            $x += $frame[0]['width'];
        }

        return $preview;
    }


    /**
     * Extract file header
     */
    public function parse_header(): void {
        $header = new BufferReader($this->data);

        $header->seek(12);
        $this->version = $header->short();

        $header->seek(24);
        $this->settings['set_count'] = $header->long();
        $this->settings['set_offsets'] = $header->long($this->settings['set_count']);
        if (!is_array($this->settings['set_offsets'])) {
            $this->settings['set_offsets'] = [$this->settings['set_offsets']];
        }

        for ($i = 0; $i < $this->settings['set_count']; $i += 1) {
            $header->seek($this->settings['set_offsets'][$i]);
            $header->skip(4); //skip header signature
            $this->settings['set_settings'][$i] = [
                'anim_count' => $header->char(),
                'sample_count' => $header->char(),
                'frame_count' => $header->short(),
                'sample_count_prior' => $header->long(),
                'data1_c' => $header->long(),
                'data1_u' => $header->long(),
                'data2_c' => $header->long(),
                'data2_u' => $header->long(),
                'data3_c' => $header->long(),
                'data3_u' => $header->long(),
                'data4_c' => $header->long(),
                'data4_u' => $header->long()
            ];
        }
    }


    /**
     * @param int $stream The number of the substream to uncompress
     *
     * @return string            Uncompressed stream
     * @throws JJ2FileException  If substream cold not be uncompressed
     */
    public function get_substream(int $stream): string {
        $set = $this->settings['set_current'];

        $offset = $this->settings['set_offsets'][$set] + 44; //skip header
        $data = $this->settings['set_settings'][$set];

        if (!isset($data['data'.$stream.'_c'])) {
            throw new JJ2FileException('Trying to load non-existent substream Data'.$stream.' from set '.$set.' in '.$this->filename);
        }

        for ($i = 1; $i < $stream; $i += 1) {
            $offset += $data['data'.$i.'_c'];
        }

        $uncompressed = gzuncompress(substr($this->data, $offset, $data['data'.$stream.'_c']), $data['data'.$stream.'_u']);
        if ($uncompressed === false) {
            throw new JJ2FileException('Could not uncompress substream '.$stream.' of set '.$set.' in file '.$this->filename);
        }

        return $uncompressed;
    }


    /**
     * 'Load' current set, i.e. make sure further operations read from this set and that the set exists
     *
     * @param int $set Set index to load
     *
     * @throws JJ2FileException  If set does not exist in file
     */
    public function load_set(int $set): void {
        if ($set > -1 && $set < $this->settings['set_count']) {
            $this->settings['set_current'] = $set;
        } else {
            throw new JJ2FileException('Set '.$set.' does not exist in file '.$this->filename);
        }
    }


    /**
     * Retrieve frame info and image
     *
     * @param int $set Animation set index
     * @param int $anim Animation index within set
     * @param int $frame Animation frame to render
     * @param array|NULL $palette Image palette; if left `NULL`, uses `get_palette()` with default values
     * @param array|NULL $lut Colour look-up table to translate colours; used for events such as gems
     *
     * @return array              Array with frame data: `[array $frame_info, resource $rendered_image, array
     *                            $animation_info]`
     * @throws JJ2FileException   If not enough data is available at given offsets; usually indicates using the wrong
     *                            index
     */
    public function get_frame(int $set, int $anim, int $frame = 0, array $palette = NULL, array $lut = NULL): array {
        $this->load_set($set);
        $frameoffset = $frame;

        if ($palette === NULL) {
            $palette = $this->palette;
        }

        if(array_key_exists($set, $this->palette_remapping) && array_key_exists($anim, $this->palette_remapping[$set])) {
            $new_palette = [];
            foreach($this->palette_remapping[$set][$anim] as $index) {
                $new_palette[] = $palette[$index];
            }
            $palette = $new_palette;
        }

        $bytes = new BufferReader($this->get_substream(1));
        for ($i = 0; $i < $anim; $i += 1) {
            $bytes->seek($i * 8);
            $frameoffset += $bytes->short();
        }

        if ($bytes->get_length() - $bytes->get_offset() < 8) {
            throw new JJ2FileException('Not enough data to parse animation info for set '.$set.', anim '.$anim.', frame '.$frame.'. Check if offsets are correct.');
        }

        $bytes->seek($anim * 8);
        $anim_settings = [
            'framecount' => $bytes->short(),
            'fps' => $bytes->short(),
            'reserved?' => $bytes->long()
        ];

        if($anim_settings['framecount'] == 0) {
            return [NULL, NULL, NULL];
        }

        $bytes->load($this->get_substream(2));
        $bytes->seek($frameoffset * 24);
        $frame_settings['width']= $bytes->short();
        $frame_settings['height']= $bytes->short();
        $frame_settings['coldspotx']= $bytes->int16();
        $frame_settings['coldspoty']= $bytes->int16();
        $frame_settings['hotspotx']= $bytes->int16();
        $frame_settings['hotspoty']= $bytes->int16();
        $frame_settings['gunspotx']= $bytes->int16();
        $frame_settings['gunspoty']= $bytes->int16();
        $frame_settings['offset_image']= $bytes->long();
        $frame_settings['offset_mask']= $bytes->long();

        $pixelmap = $this->make_pixelmap(substr($this->get_substream(3), $frame_settings['offset_image']));

        return [$frame_settings, $this->render_pixelmap($pixelmap, $palette, $lut), $anim_settings];
    }


    /**
     * Decode JJ2's RLE-based anim frame image format
     *
     * @param string $raw Raw image data
     *
     * @return array       Color mappings; two-dimensional array with palette indices `$map[$x][$y]`
     */
    public function make_pixelmap(string $raw): array {
        //first two bytes are frame dimensions
        $dimensions = unpack("S2", substr($raw, 0, 4));
        $width = array_shift($dimensions);
        $height = array_shift($dimensions);
        $raw = substr($raw, 4);

        //unset most significant bit, if set
        if ($width >= 32768) {
            $width -= 32768;
        }

        //prefill pixel map as not all pixels are explicitly stored in the
        //data
        $map = [];
        for ($x = 0; $x < $width; $x += 1) {
            $map[$x] = [];
            for ($y = 0; $y < $height; $y += 1) {
                $map[$x][$y] = 0;
            }
        }

        $x = $y = 0;

        //loop through image data until all lines are filled
        while ($y < $height) {
            //get the codebyte that tells us what to do
            $value = unpack("C", substr($raw, 0, 1));
            $raw = substr($raw, 1);
            $byte = array_shift($value);
            //byte > 128; the next (byte - 128) bytes are pixels indices
            if ($byte > 128) {
                $amount = $byte - 128;
                $sub = substr($raw, 0, $amount);
                $raw = substr($raw, $amount);
                for ($j = 0; $j < $amount; $j += 1) {
                    $value = unpack("C", $sub[$j]);
                    $map[$x][$y] = array_shift($value);
                    $x += 1;
                }
                //byte < 128; skip as many bytes
            } elseif ($byte < 128) {
                $x += $byte;
                //byte == 128: next line of pixels
            } else {
                $x = 0;
                $y += 1;
            }
        }

        return $map;
    }


    /**
     * Render pixelmap (via `get_pixelmap()`) to an image resource
     *
     * @param array $map Colour indices; expects a return value of `make_pixelmap()`
     * @param array|NULL $palette Image palette; if left `NULL`, uses `get_palette()` with default values
     * @param array|NULL $lut Colour look-up table to translate colours; used for events such as gems
     *
     * @return resource            GD Image resource
     */
    public function render_pixelmap(array $map, array $palette = NULL, array $lut = NULL) {
        $width = count($map);
        $height = count($map[0]);

        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 1, 1, imagecolorallocatealpha($img, 0, 0, 0, 127));

        $palette = ($palette === NULL) ? $this->palette : $palette;
        //imagecolortransparent($img, imagecolorallocate($img, $palette[0][0], $palette[0][1], $palette[0][2]));
        foreach ($map as $x => $coords) {
            foreach ($coords as $y => $index) {
                $alpha = 0;
                if ($lut !== NULL && $index > 0) {
                    $index = ($index >> 3) & 15;
                    $index = $lut[$index];
                    $alpha = 32;
                }

                if ($index > 0) {
                    $color = imagecolorallocatealpha($img, $palette[$index][0], $palette[$index][1], $palette[$index][2], $alpha);
                    imagesetpixel($img, $x, $y, $color);
                }
            }
        }

        return $img;
    }

    /**
     * Get a frame, but resize it to fit on an empty 15-ammo crate
     *
     * This is a specialised method that can be used to generate crate sprites
     * for weapons that don't have their own 15-ammo crate sprites, which is the
     * case for a lot of custom weapons.
     *
     * Thanks to Violet for the idea!
     *
     * @param int $set Set ID
     * @param int $anim Animation ID
     * @param int $frame Frame index
     * @param array|NULL $palette Palette to render with
     * @param array|NULL $lut Lookup table, if relevant
     * @return array    [frame info, frame image,animation info]
     */
    public function get_frame_as_crate(int $set, int $anim, int $frame = 0, array $palette = NULL, array $lut = NULL): array {
        $crate_j2a = new J2AFile($this->resource_folder.DIRECTORY_SEPARATOR.'crate.j2a', $palette);
        $crate = $crate_j2a->get_frame(0, 0, 0, $palette, $lut);
        $emblem_source = $this->get_frame($set, $anim, $frame, $palette, $lut);

        $emblem = imagecreatetruecolor(13, 13);

        $transparent = imagecolorallocatealpha($emblem, 0, 0, 0, 127);
        imagefill($emblem, 1, 1, $transparent);
        imagecopyresampled($emblem, $emblem_source[1], 0, 0, 0, 0, imagesx($emblem), imagesy($emblem), imagesx($emblem_source[1]), imagesy($emblem_source[1]));

        //here we recolour the emblem in shades of black (except for the whites which become transparent)
        for ($y = 0; $y < imagesy($emblem); $y += 1) {
            for ($x = 0; $x < imagesx($emblem); $x += 1) {
                $color = imagecolorsforindex($emblem, imagecolorat($emblem, $x, $y));
                if ($color['alpha'] > 0) {
                    continue;
                }

                unset($color['alpha']);
                if (array_sum($color) > 750) {
                    imagesetpixel($emblem, $x, $y, $transparent);
                } else {
                    $r = floor(($color['red'] / 255) * 16);
                    $g = floor(($color['green'] / 255) * 16);
                    $b = floor(($color['blue'] / 255) * 16);
                    imageputpixel($emblem, $x, $y, $r, $g, $b);
                }

            }
        }

        imagecopy($crate[1], $emblem, 6, 7, 0, 0, imagesx($emblem), imagesy($emblem));
        return $crate;
    }

    /**
     * Get a frame, but resize it to fit on an empty monitor
     *
     * This is a specialised method that can be used to generate monitor sprites
     * for weapons that don't have their own powerup monitor sprites, which is the
     * case for a lot of custom weapons.
     *
     * Thanks to Violet for the idea!
     *
     * @param int $set Set ID
     * @param int $anim Animation ID
     * @param int $frame Frame index
     * @param array|NULL $palette Palette to render with
     * @param array|NULL $lut Lookup table, if relevant
     * @return array    [frame info, frame image,animation info]
     */
    public function get_frame_as_monitor(int $set, int $anim, int $frame = 0, array $palette = NULL, array $lut = NULL): array {
        $monitor_j2a = new J2AFile($this->resource_folder.DIRECTORY_SEPARATOR.'Plus.j2a', $palette);
        $monitor = $monitor_j2a->get_frame(2, 4, 0, $palette, $lut);
        $emblem_source = $this->get_frame($set, $anim, $frame, $palette, $lut);

        $emblem = imagecreatetruecolor(12, 14);

        $transparent = imagecolorallocatealpha($emblem, 0, 0, 0, 127);
        imagefill($emblem, 1, 1, $transparent);
        imagecopyresampled($emblem, $emblem_source[1], 0, 0, 0, 0, imagesx($emblem), imagesy($emblem), imagesx($emblem_source[1]), imagesy($emblem_source[1]));

        imagecopy($monitor[1], $emblem, 3, 4, 0, 0, imagesx($emblem), imagesy($emblem));
        return $monitor;
    }
}