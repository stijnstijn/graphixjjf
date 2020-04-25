<?php
/**
 * Generic JJ2 File handler - JJ2 file formats have some characteristics in common that make it useful to abstract some
 * methods & data structures
 */

namespace J2o\Lib;

use J2o\Exception\JJ2FileException;

/**
 * Class JJ2File
 */
abstract class JJ2File implements JJFile {
    /**
     * Header size, in bytes
     */
    const HEADER_SIZE = 262;
    /**
     * @var array  File settings
     */
    protected array $settings = [];
    /**
     * @var array  File substreams; uncompressed file sections stored for later use
     */
    protected array $substreams = [];
    /**
     * @var array  Substream sizes - stored in header and used to properly decompress them
     */
    protected array $substream_sizes = [];
    /**
     * @var string File data, raw bytes loaded into memory
     */
    protected string $data = '';
    /**
     * @var string File path, i.e. level.j2x
     */
    public string $filename;
    /**
     * @var string  Path to look in for resource files, e.g. animation libraries
     */
    public string $resource_folder;
    /**
     * @var string File name, i.e. level or tileset title
     */
    public string $name;
    /**
     * @var int  File version (i.e. JJ2 version for this file)
     */
    public ?int $version = NULL;
    /**
     * @var array Palette - used by some of the JJ2 File types
     */
    public ?array $palette = [];

    /**
     * JJ2File constructor.
     *
     * Sets up the common object properties and calls the file-specific
     * initialisation method.
     *
     * @param string $filename Filename to work with
     * @param array $palette Palette to render with (unused by some file types)
     * @param string|NULL $resource_folder Folder to look in for resource
     * files (e.g. animation libraries). Defaults to 'resources' in this file's
     * parent folder if left empty
     */
    public function __construct(string $filename, array $palette = NULL, string $resource_folder = NULL) {
        $this->filename = $filename;
        $this->palette = $palette;
        $this->resource_folder = $resource_folder ?? dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'resources';
        $this->initialise();
    }

    /**
     * Get uncompressed file substream
     *
     * @param int $substream Substream to decompress: Data1/2/3/4
     *
     * @return string  Uncompressed data
     * @throws JJ2FileException  If substream number is invalid or uncompressing fails
     */
    protected function get_substream(int $substream): string {
        if (!isset($this->substreams[$substream])) {
            if ($substream < 1 || $substream > 4) {
                throw new JJ2FileException('Cannot read substream Data'.$substream.' (must be between 1 and 4)');
            }

            $offset = static::HEADER_SIZE;
            for ($i = 1; $i < $substream; $i += 1) {
                $offset += $this->substream_sizes['data'.$i.'_c'];
            }

            $uncompressed = gzuncompress(substr($this->data, $offset, $this->substream_sizes['data'.$i.'_c']));
            if ($uncompressed === false) {
                throw new JJ2FileException('Could not uncompress substream Data'.$substream);
            }

            if (strlen($uncompressed) != $this->substream_sizes['data'.$i.'_u']) {
                throw new JJ2FileException('Substream Data'.$substream.' is '.strlen($uncompressed).' bytes uncompressed, should be '.$this->substream_sizes['data'.$i.'_u']);
            }

            $this->substreams[$substream] = $uncompressed;
        }

        return $this->substreams[$substream];
    }


    /**
     * Get JASC-format palette from file
     *
     * @param string|NULL $palette_file File path to load palette from. Uses `Jazz2.pal` if NULL
     *
     * @return array                     Palette file, 256 entries with `[r, g, b]` structure
     * @throws JJ2FileException          If file path given does not work
     */
    public function parse_palette(string $palette_file = NULL): array {
        if (!$this->palette) {
            if ($palette_file === NULL) {
                $palette_file = $this->resource_folder.DIRECTORY_SEPARATOR.'Jazz2.pal';
            }

            if (!is_readable($palette_file)) {
                throw new JJ2FileException('Could not load palette file '.$palette_file.'');
            } else {
                $palette_file = explode("\n", file_get_contents($palette_file));
            }

            if (count($palette_file) < 259) {
                throw new JJ2FileException('Palette file '.$palette_file.' is not a valid JASC-format palette');
            }

            $this->palette = [];
            for ($i = 3; $i < 259; $i += 1) {
                $this->palette[] = array_map(function ($a) {
                    return intval($a);
                }, explode(" ", trim($palette_file[$i])));
            }
        }

        return $this->palette;
    }

    /**
     * Get parsed file settings
     *
     * @return array  File settings
     */
    public function get_settings(): array {
        return $this->settings;
    }


    /**
     * Strip common JJ2 formatting characters from a string
     *
     * @param string $string
     *
     * @return string
     */
    public static function clean(string $string): string {
        return str_replace(['#', '|', '@'], ['', '', ' '], $string);
    }
}