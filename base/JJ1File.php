<?php

namespace J2o\Lib;

use J2o\Exception\JJ1FileException, J2o\Exception\BufferException;

/**
 * Class JJ1File
 */
abstract class JJ1File implements JJFile {
    /**
     * @var string  Data to read
     */
    public string $data = '';
    /**
     * @var string  Path to file being read
     */
    public string $filename = '';
    /**
     * @var array  Substream sizes - stored in header and used to properly decompress them
     */
    protected array $substream_sizes = [];
    /**
     * @var array  File substreams; uncompressed file sections stored for later use
     */
    protected array $substreams = [];
    /**
     * @var array File settings, to be filled later
     */
    protected array $settings = [];


    /**
     * JJ1File constructor.
     *
     * @param string $filename File to read
     *
     * @throws JJ1FileException If file could not be read
     */
    public function __construct(string $filename) {

        if (is_file($filename) && is_readable($filename)) {
            $this->filename = $filename;
            $this->data = file_get_contents($this->filename);
        } else {
            throw new JJ1FileException($filename.' is not a readable file.');
        }

        $this->parse_header();
    }


    /**
     * Dummy header parser to be overriden by child classes
     *
     * @throws JJ1FileException  If called directly
     */
    protected function parse_header() {
        throw new JJ1FileException('JJ1File descendant classes must define their own header parser');
    }


    /**
     * Get file settings
     *
     * Not used by all file types, so an empty array may be returned.
     *
     * @return array
     */
    public function get_settings(): array {
        return $this->settings;
    }


    /**
     * Get level substream
     *
     * Sometimes called 'blocks', but function similarly to JJ2's substreams; (mostly) compressed sub-files with level
     * info.
     *
     * @param int $stream
     *
     * @return string
     */
    function get_substream(int $stream): string {
        if (!isset($this->substreams[$stream])) {
            $file = new BufferReader($this->data);
            for ($i = 0; $i < $stream; $i += 1) {
                $file->skip($this->substream_sizes[$i]);
            }

            if (in_array($stream, [9, 11, 14, 16])) {
                //only level files have more than 4 substreams
                //some of these are *not* RLE-encoded, for whatever reason
                $result = $file->bytes($this->substream_sizes[$stream]);
            } else {
                $bytes = $file->bytes($this->substream_sizes[$stream]);
                $result = $this->load_RLE($bytes);
            }

            $this->substreams[$stream] = $result;
        }

        return $this->substreams[$stream];
    }


    /**
     * Decompress an RLE sequence
     *
     * @param string|BufferReader $data Either a string to read from or an already initialized BufferReader.
     *
     * @return string             Decoded bytes
     * @throws JJ1FileException   If RLE indicates that bytes need to be read, but no more bytes are available. This
     *                            indicates a problem with the caller function. Also throws if argument 1 is of the
     *                            wrong type.
     */
    protected function load_RLE(&$data): string {
        if (is_string($data)) {
            $bytes = new BufferReader($data);
        } elseif ($data instanceof BufferReader) {
            $bytes = &$data;
        } else {
            throw new JJ1FileException('JJ1File::load_RLE() expects argument 1 to be a string or an instance of BufferReader, '.gettype($data).' given');
        }

        $rle_length = $bytes->short();
        $start = $bytes->get_offset();
        $return = '';

        try {
            while ($bytes->get_offset() < $start + $rle_length) {
                $byte = $bytes->char();

                if ($byte & 128) {
                    $repeat = $byte & 127;
                    $byte = $bytes->bytes(1);
                    for ($i = 0; $i < $repeat; $i += 1) {
                        $return .= $byte;
                    }
                } elseif ($byte > 0) {
                    for ($i = 0; $i < $byte; $i += 1) {
                        $return .= $bytes->bytes(1);
                    }
                } elseif ($byte == 0) {
                    $return .= $bytes->bytes(1);
                    break;
                }
            }
        } catch (BufferException $e) {
            throw new JJ1FileException('Could not decompress RLE block; end of data reached earlier than indicated by encoding ('.$e->getMessage().')');
        }

        return $return;
    }
}