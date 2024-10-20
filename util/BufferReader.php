<?php
/**
 * Buffer Reader class
 *
 * Can be used to parse binary values from a string of bytes.
 */

namespace J2o\Lib;


use J2o\Exception\BufferException;


/**
 * Class BufferReader
 *
 * Provides an API through which streams of data can be manipulated on a per-byte basis
 *
 * @package J2o\Lib
 */
class BufferReader {
    /**
     * @var int  Offset within data being read
     */
    private int $offset = 0;
    /**
     * @var int  Length of buffer
     */
    private int $length = 0;
    /**
     * @var string  Buffer to read from
     */
    private string $buffer = '';
    /**
     * @var mixed  Latest read value
     */
    private $latest = NULL;


    /**
     * BufferReader constructor.
     *
     * @param string $buffer Data to pass to `load()`
     */
    public function __construct(string $buffer) {
        $this->load($buffer);
    }


    /**
     * Load a string of bytes as data buffer to read from, and reset pointer
     *
     * @param string $buffer
     */
    public function load(string $buffer) {
        $this->buffer = $buffer;
        $this->length = strlen($buffer);
        $this->offset = 0;
    }


    /**
     * Generic byte reader
     *
     * @param int $num_bytes Number of bytes to return
     *
     * @return string Bytes, as string
     * @throws BufferException  If not enough bytes are left in buffer
     */
    public function get_bytes(int $num_bytes): string {
        if ($this->offset >= $this->length) {
            throw new BufferException('End of buffer reached while trying to read '.$num_bytes.' bytes from a '.$this->length.'-byte buffer');
        }

        if ($this->offset + $num_bytes > $this->length) {
            throw new BufferException('Need '.$num_bytes.' bytes, but only '.($this->length - $this->offset).' left in buffer');
        }

        $bytes = substr($this->buffer, $this->offset, $num_bytes);
        $this->offset += $num_bytes;

        $this->latest = $bytes;

        return $bytes;
    }


    /**
     * Get current offset
     *
     * @return int  Byte offset
     */
    public function get_offset(): int {
        return $this->offset;
    }


    /**
     * Get buffer length
     *
     * @return int
     */
    public function get_length(): int {
        return $this->length;
    }


    /**
     * Get bytes remaining in buffer after current offset
     *
     * @return int
     */
    public function get_remaining(): int {
        return $this->length - $this->offset;
    }


    /**
     * Move buffer reader to given offset
     *
     * @param int $offset
     *
     * @return BufferReader Own object instance, for chaining (e.g. `$p->seek(128)->char()`)
     *
     * @throws BufferException If offset is out of bounds
     */
    public function seek(int $offset): BufferReader {
        if ($offset > $this->length || $offset < 0) {
            throw new BufferException('Cannot seek to position '.$offset.', out of bounds');
        }

        $this->offset = $offset;

        return $this;
    }


    /**
     * Skip a number of bytes, changing the reader offset
     *
     * Number can be negative, to move back
     *
     * @param int $bytes Bytes to skip
     *
     * @return BufferReader Own object instance, for chaining (e.g. `$p->skip(5)->char()`)
     *
     * @throws BufferException If new offset is out of bounds
     */
    public function skip(int $bytes): BufferReader {
        if ($this->offset + $bytes > $this->length || $this->offset + $bytes < 0) {
            throw new BufferException('Cannot skip to position '.($this->offset + $bytes).', out of bounds');
        }

        $this->offset += $bytes;

        return $this;
    }


    /**
     * Generic value reader
     *
     * @param int $number Number of bytes to read
     * @param callable $parser Function to parse the bytes with
     *
     * @return array|mixed      If `$number` is one, returns the parsed value; if it is greater than one, returns an
     *                          array of parsed values
     * @throws BufferException  If `$number` is less than 1
     */
    private function read(int $number, callable $parser) {
        if ($number < 1) {
            throw new BufferException('Cannot read '.$number.' values: must be 1 or more');
        }
        $results = [];

        for ($i = 0; $i < $number; $i += 1) {
            $results[] = $parser();
        }

        return ($number == 1) ? array_shift($results) : $results;
    }


    /**
     * Copies latest returned value to variable
     *
     * @param mixed $container Variable to copy to
     *
     * @return BufferReader Own object instance, for chaining
     */
    public function store(&$container) {
        $container = $this->latest;

        return $this;
    }


    /**
     * Read text string
     *
     * @param int $length If left NULL, will read until a NULL byte is encountered (or the end of the buffer is
     *                         reached, which throws an error)
     * @param int $number
     * @param bool $trim Trim string? Removes \0 bytes
     * @param bool $parse_7bit If `true`, string will be parsed as 7-bit ASCII
     *
     * @return array|string
     * @throws BufferException  If asked to return less than 1 value
     */
    public function string(int $length = NULL, int $number = 1, bool $trim = true, bool $parse_7bit = false) {
        if ($number < 1) {
            throw new BufferException('Cannot read '.$number.' values: must be 1 or more');
        }
        $result = [];

        if ($length === NULL && $parse_7bit) {
            $length = 0;
            while (true) {
                $byte = $this->uint8();
                $length |= ($byte & 0x7F);
                if ($byte >= 0x80) {
                    $length <<= 7;
                } else {
                    break;
                }
            }
        }

        for ($i = 0; $i < $number; $i += 1) {
            $string = '';
            while ($length === NULL || strlen($string) < $length) {
                $char = $this->char();
                if ($length === NULL && $char === 0x00) {
                    break;
                }
                $string .= chr($char);
            }

            $result[] = $trim ? trim($string) : $string;
        }

        $this->latest = ($number == 1) ? array_shift($result) : $result;

        return $this->latest;
    }


    /**
     * Read 7-bit encoded text string
     *
     * Identical to `string()`, but converts from 7-bit to 8-bit encoding
     *
     * @param int $length If left NULL, will read until a NULL byte is encountered (or the end of the buffer is
     *                         reached, which throws an error)
     * @param int $number
     * @param bool $trim Trim string? Removes \0 bytes
     *
     * @return array|string
     */
    public function string_7bit(int $length = NULL, int $number = 1, bool $trim = true) {
        return $this->string($length, $number, $trim, true);
    }


    /**
     * Read pure bytes
     *
     * Basically `string()`, but doesn't `trim()` the results
     *
     * @param int $length
     * @param int $number
     *
     * @return array|string
     */
    public function bytes(int $length, int $number = 1) {
        $this->latest = $this->string($length, $number, false);

        return $this->latest;
    }


    /**
     * Read boolean
     *
     * @param int $number
     *
     * @return array|boolean  Whether the byte is 0 (`false`) or another value (`true`)
     */
    public function bool(int $number = 1) {
        $this->latest = $this->read($number, function () {
            return ord($this->get_bytes(1)) !== 0;
        });

        return $this->latest;
    }


    /**
     * Read 8-bit integer
     *
     * @param int $number
     *
     * @return array|int
     */
    public function int8(int $number = 1) {
        $this->latest = $this->read($number, function () {
            $byte = ord($this->get_bytes(1));
            return ($byte & 0x80) ? -($byte - 0x80) : $byte;
        });

        return $this->latest;
    }


    /**
     * Read unsigned 8-bit integer
     *
     * @param int $number
     *
     * @return array|int
     */
    public function uint8(int $number = 1) {
        $this->latest = $this->read($number, function () {
            return ord($this->get_bytes(1));
        });

        return $this->latest;
    }


    /**
     * Read 16-bit integer
     *
     * @param int $number
     *
     * @return array|int
     */
    public function int16(int $number = 1) {
        $this->latest = $this->read($number, function () {
            $value = unpack('s', ($this->get_bytes(2)));

            return array_pop($value);
        });

        return $this->latest;
    }


    /**
     * Read unsigned 16-bit integer
     *
     * @param int $number
     *
     * @return array|int
     */
    public function uint16(int $number = 1) {
        $this->latest = $this->read($number, function () {
            $value = unpack('S', ($this->get_bytes(2)));

            return array_pop($value);
        });

        return $this->latest;
    }


    /**
     * Read 32-bit integer
     *
     * @param int $number
     *
     * @return array|int
     */
    public function int32(int $number = 1) {
        $this->latest = $this->read($number, function () {
            $value = unpack('l', ($this->get_bytes(4)));

            return array_pop($value);
        });

        return $this->latest;
    }


    /**
     * Read unsigned 32-bit integer
     *
     * @param int $number
     *
     * @return array|mixed
     */
    public function uint32(int $number = 1) {
        $this->latest = $this->read($number, function () {
            $value = unpack('L', ($this->get_bytes(4)));

            return array_pop($value);
        });

        return $this->latest;
    }


    /**
     * Read char (unsigned 8-bit integer)
     *
     * @param int $number
     *
     * @return array|int
     */
    public function char(int $number = 1) {
        $this->latest = $this->uint8($number);

        return $this->latest;
    }


    /**
     * Read short (unsigned 16-bit integer)
     *
     * @param int $number
     *
     * @return array|int
     */
    public function short(int $number = 1) {
        $this->latest = $this->uint16($number);

        return $this->latest;
    }


    /**
     * Read long (unsigned 32-bit integer)
     *
     * @param int $number
     *
     * @return array|mixed
     */
    public function long(int $number = 1) {
        $this->latest = $this->uint32($number);

        return $this->latest;
    }


    /**
     * Read 32-bit float
     *
     * @param int $number
     *
     * @return array|float
     */
    public function float(int $number = 1) {
        $this->latest = $this->read($number, function () {
            $value = unpack('g', ($this->get_bytes(4)));

            return array_pop($value);
        });

        return $this->latest;
    }
}