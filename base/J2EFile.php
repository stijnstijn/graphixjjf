<?php
/**
 * Episode file reader - gets information from file and provides methods to graphixjjf it to an image
 */

namespace J2o\Lib;

use J2o\Exception\JJ2FileException;


/**
 * Class JJ2Episode
 *
 * @package J2o\Lib
 */
class J2EFile extends JJ2File {
    /**
     * Size of the file header - actual size of the header + 4 (?)
     */
    const HEADER_SIZE = 212;


    /**
     * Constructor method
     *
     * Sets up the object, checking whether given file path is valid and giving several
     * variables initial values. Then it reads the header via another method.
     *
     * @throws  JJ2FileException  If there is a problem with the level file.
     */
    public function initialise(): void {
        if (!is_readable($this->filename)) {
            throw new JJ2FileException('Could not read level file '.$this->filename);
        }

        if (filesize($this->filename) == 0) {
            throw new JJ2FileException('Level file '.$this->filename.' is zero bytes');
        }

        if ($this->palette === NULL) {
            if (!is_readable($this->resource_folder.'/Jazz2.pal')) {
                throw new JJ2FileException('No episode palette was given, trying to read Jazz2.pal, but file is not readable');
            }
            $this->parse_palette();
        } elseif (is_string($this->palette)) {
            $this->palette = $this->parse_palette($this->palette);
        } elseif (count($this->palette) != 256) {
            throw new JJ2FileException('Episode palette should have 256 items');
        }

        $this->data = file_get_contents($this->filename);
        $this->parse_header();
    }


    /**
     * Parse Level header
     *
     * The episode header contains offsets for the episode images, as well as some other useful info (names, level)
     */
    public function parse_header(): void {
        $header = new BufferReader($this->data);
        $this->settings = [
            'size_header' => $header->long(),
            'position' => $header->long(),
            'is_registered' => $header->long(),
            'name' => $header->skip(4)->string(128),
            'level' => $header->string(32),
            'width' => $header->long(),
            'height' => $header->long(),
            'width_title' => $header->skip(8)->long(),
            'height_title' => $header->long()
        ];

        $this->substream_sizes = [
            'data1_c' => $header->long(),
            'data2_c' => $header->long(),
            'data3_c' => $header->long(),
        ];

        $this->substream_sizes['data1_u'] = $this->settings['width'] * $this->settings['height'];
        $this->substream_sizes['data2_u'] = $this->substream_sizes['data3_u'] = $this->settings['width'] * $this->settings['height'];

        $this->name = $this->settings['name'];
    }


    /**
     * Render image to GD image resource
     *
     * @param int $stream Which image (i.e. substream) to read data from
     * @param int $width Image width
     * @param int $height Image height
     *
     * @return resource
     */
    private function render_image(int $stream, int $width, int $height) {
        $map = new BufferReader($this->get_substream($stream));
        $image = imagecreatetruecolor($width, $height);
        $background = [87, 0, 203];

        imagefill($image, 0, 0, imagecolorallocate($image, $background[0], $background[1], $background[2]));

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $index = $map->char();
                if ($index > 0) {
                    $color = $this->palette[$index];
                    imagesetpixel($image, $x, $y, imagecolorallocate($image, $color[0], $color[1], $color[2]));
                }
            }
        }

        return $image;
    }


    /**
     * Get illustration image (the one that is displayed on the right in JJ2)
     *
     * @return resource  GD image resource
     */
    public function get_image_illustration() {
        return $this->render_image(1, $this->settings['width'], $this->settings['height']);
    }


    /**
     * Get title image (the one that is displayed on the left in JJ2, while selected)
     *
     * @return resource  GD image resource
     */
    public function get_image_title() {
        return $this->render_image(2, $this->settings['width_title'], $this->settings['height_title']);
    }


    /**
     * Get illustration image (the one that is displayed on the left in JJ2, while not selected)
     *
     * @return resource  GD image resource
     */
    public function get_image_title_dark() {
        return $this->render_image(3, $this->settings['width_title'], $this->settings['height_title']);
    }

    /**
     * Generate preview image
     *
     * @return resource  GD Image resource
     */
    public function get_preview() {
        return $this->get_image_illustration();
    }
}