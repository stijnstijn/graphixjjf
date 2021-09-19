<?php

namespace J2o\Lib;

/*
 * Gets info from JJ1 planet files - they contain a graphic depicting the planet
 */

/**
 * Class JJ1Planet
 */
class JJ1Planet extends JJ1File {
    /**
     * @var array  Palette, 256-item array of `[r, g, b]` items
     */
    private array $palette = [];

    /**
     * Parse file header, and store settings and palette
     */
    function parse_header(): void {
        $file = new BufferReader($this->data);
        $file->skip(2);

        $name_length = $file->char();
        $this->name = $file->string($name_length);

        for ($i = 0; $i < 256; $i += 1) {
            $this->palette[] = [$file->char() << 2, $file->char() << 2, $file->char() << 2]; //6 -> 8 bit
        }

        $this->substreams[0] = $file->bytes(55 * 64);
    }


    /**
     * Render JJ1 Planet image
     *
     * @return int Rendered JJ1 Planet image
     */
    function get_image() {
        $image = imagecreatetruecolor(64, 55);

        $map = new BufferReader($this->get_substream(0));
        for ($y = 0; $y < 55; $y += 1) {
            for ($x = 0; $x < 64; $x += 1) {
                $color = $this->palette[$map->char()];
                imagesetpixel($image, $x, $y, imagecolorallocate($image, $color[0], $color[1], $color[2]));
            }
        }

        return $image;
    }

    /**
     * Generate preview image
     *
     * @return int  GD Image resource
     */
    function get_preview() {
        return $this->get_image();
    }
}