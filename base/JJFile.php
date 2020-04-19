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
    public function get_settings(): array;

    /**
     * Render a preview of the file as an image
     *
     * @return resource  Preview image resource
     */
    public function get_preview();
}