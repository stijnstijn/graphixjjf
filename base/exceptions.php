<?php
/**
 * Exceptions
 */
namespace J2o\Exception;

/**
 * Throw when BufferReader encounters an issue
 * @package J2o\Exception
 */
class BufferException extends\ErrorException {}

/**
 * Throw when an issue is encountered while reading a Jazz Jackrabbit data file
 * @package J2o\Exception
 */
class JJFileException extends \ErrorException {}

/**
 * Throw when an issue is encountered while reading a Jazz Jackrabbit 1 data file
 * @package J2o\Exception
 */
class JJ1FileException extends JJFileException {}

/**
 * Throw when an issue is encountered while reading a Jazz Jackrabbit 2 data file
 * @package J2o\Exception
 */
class JJ2FileException extends JJFileException {}