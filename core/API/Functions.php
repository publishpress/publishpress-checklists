<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       2.18.0
 */

use PublishPress\Checklists\Core\API\Facade;

defined('ABSPATH') || exit;

if (!function_exists('ppch_set_requirement')) {
    /**
     * Set a requirement configuration
     *
     * @param string $requirement_name The name of the requirement (e.g., 'words_count')
     * @param array $config Configuration array with the following keys:
     *                      - rule: (string) The rule to set ('required', 'recommended', 'disabled')
     *                      - params: (array) Associative array of parameter names and values
     *                      - ignored_by: (array) Array of role slugs that can ignore the task
     * @param string|array $post_types Post type(s) to apply the configuration to. Use 'all' for all post types.
     * @return bool True if successful, false otherwise
     */
    function ppch_set_requirement($requirement_name, $config, $post_types = 'all')
    {
        return Facade::set_requirement($requirement_name, $config, $post_types);
    }
}
