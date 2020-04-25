<?php
/**
 * Jazz Jackrabbit file reader interface
 */

namespace J2o\Lib;

/**
 * Jazz Jackrabbit file reader interface
 *
 * Very minimal, but defines two methods that all file readers should implement, and that are actually useful for
 * people.
 *
 * @package J2o\Lib
 */
interface JJFile {
    /**
     * Get file setttings, parsed from (usually) the file header
     *
     * @return array  File settings
     */
    function get_settings(): array;

    /**
     * Render a preview of the file as an image
     *
     * @return resource  Preview image resource
     */
    function get_preview();

    /**
     * Initialise the file object
     *
     * Depends on the file type - parse header, load substreams, etc. To be
     * called by the constructor.
     */
    function initialise(): void;
}