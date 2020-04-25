<?php
/**
 * Jazz Jackrabbit 2 text renderer
 */

namespace J2o\Lib;

/**
 * Jazz Jackrabbit 2 text renderer
 *
 * Renders arbitrary texts with the Jazz Jackrabbit 2 font
 *
 * @package J2o\Lib
 */
class JJ2Text {
    /**
     * @var array ASCII characters can be mapped through their byte value, but
     * these can't (perhaps they do use some sort of standardised coding table,
     * but I couldn't be bothered to figure that out)
     */
    private array $special_characters = [
        'Š' => 106,
        'Œ' => 108,
        'š' => 122,
        'œ' => 124,
        'Ÿ' => 127,
        '¡' => 130,
        '¿' => 159,
        'À' => 160,
        'Á' => 161,
        'Â' => 162,
        'Ã' => 163,
        'Ä' => 164,
        'Ȧ' => 165,
        'Æ' => 166,
        'Ç' => 167,
        'È' => 168,
        'É' => 169,
        'Ê' => 170,
        'Ë' => 171,
        'Ì' => 172,
        'Í' => 173,
        'Î' => 174,
        'Ï' => 175,
        'Ð' => 176,
        'Ñ' => 177,
        'Ò' => 178,
        'Ó' => 179,
        'Ô' => 180,
        'Õ' => 181,
        'Ö' => 183,
        'Ø' => 184,
        'Ù' => 185,
        'Ú' => 186,
        'Û' => 187,
        'Ü' => 188,
        'Ý' => 189,
        'Þ' => 190,
        'ß' => 191,
        'à' => 192,
        'á' => 193,
        'â' => 194,
        'ã' => 195,
        'ä' => 196,
        'ȧ' => 197,
        'æ' => 198,
        'ç' => 199,
        'è' => 200,
        'é' => 201,
        'ê' => 202,
        'ë' => 203,
        'ì' => 204,
        'í' => 205,
        'î' => 206,
        'ï' => 207,
        'ð' => 208,
        'ñ' => 209,
        'ò' => 210,
        'ó' => 211,
        'ô' => 212,
        'õ' => 213,
        'ö' => 214,
        'ø' => 216,
        'ù' => 217,
        'ú' => 218,
        'û' => 219,
        'ü' => 220,
        'ý' => 221,
        'þ' => 222,
        'ÿ' => 223
    ];

    /**
     * "normal" size
     */
    const SIZE_NORMAL = 1;
    /**
     * "large" size
     */
    const SIZE_LARGE = 0;

    /**
     * JJ2Text constructor.
     *
     * @param string $string  String to be rendered
     * @param array|NULL $palette  Palette to use (nullable, will load Diamondus 1 palettte)
     * @param string $resource_folder  Folder to find Anims.j2a in
     */
    public function __construct(string $string, ?array $palette, string $resource_folder) {
        $this->j2a = new J2AFile($resource_folder.DIRECTORY_SEPARATOR.'Anims.j2a', $palette, $resource_folder);
        $this->text = $string;
    }

    /**
     * Render text to image
     *
     * @todo colours, line breaks, spacing, etc (but do we need them?)
     *
     * @param int $size  Size; either JJ2Text::SIZE_NORMAL or JJ2Text::SIZE_LARGE
     * @return resource  Text as image
     */
    public function get_image($size = self::SIZE_NORMAL) {
        $characters = static::split($this->text);
        $frame_numbers = [];

        //collect which frames to render and in which order - this filters out
        //characters we don't know
        foreach ($characters as $character) {
            if (array_key_exists($character, $this->special_characters)) {
                $frame_numbers[] = $this->special_characters[$character];
            } elseif ($character == ' ') {
                $frame_numbers[] = -1;
            } else {
                $byte = ord($character);
                if (($byte >= 33 && $byte < 122)) {
                    //ascii characters
                    $frame_numbers[] = $byte - 32;
                }
            }
        }

        //render frames, and a black 'space' frame
        $frames = [];
        $space = imagecreatetruecolor(8, 1);
        imagefill($space, 0, 0, imagecolorallocatealpha($space, 0, 0, 0, 127));
        foreach ($frame_numbers as $number) {
            if ($number > 0) {
                $frames[] = $this->j2a->get_frame(46, $size, $number);
            } else {
                //space
                $frames[] = [
                    [
                        'width' => 8,
                        'height' => 1,
                        'hotspoty' => 0
                    ],
                    &$space
                ];
            }
        }

        //get dimensions of label image
        $width = 0;
        $height = 0;
        foreach ($frames as $frame) {
            $width += $frame[0]['width'];
            $height = max($frame[0]['height'] + $frame[0]['hotspoty'], $height);
        }

        //composite the character frames
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagesavealpha($canvas, true);
        $x = 0;
        foreach ($frames as $frame) {
            imagecopy($canvas, $frame[1], $x, $frame[0]['hotspoty'], 0, 0, imagesx($frame[1]), imagesy($frame[1]));
            $x += $frame[0]['width'];
        }

        return $canvas;
    }

    /**
     * Split text into separate characters
     *
     * str_split doesn't work well with non-ascii characters, hence
     *
     * @param string $string  Input
     * @return array  Output
     */
    public static function split(string $string): array {
        return preg_split("//u", $string, -1, PREG_SPLIT_NO_EMPTY);
    }
}