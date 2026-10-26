<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Core\Requirement;

\defined('ABSPATH') || exit;

interface Interface_required
{
    /**
     * Injects the respective default options into the main add-on.
     *
     * @param array $default_options
     *
     * @return array
     */
    public function filter_default_options($default_options);

    /**
     * Validates the option group, making sure the values are sanitized.
     *
     * @param array $new_options
     *
     * @return array
     */
    public function filter_settings_validate($new_options);

    /**
     * Add the requirement to the list to be displayed in the meta box.
     *
     * @param array $requirements
     * @param stdClass $post
     *
     * @return array
     */
    public function filter_requirements_list($requirements, $post);

    /**
     * Returns the current status of the requirement.
     *
     * The child class should
     * evaluate the status and override this method.
     *
     * @param stdClass $post
     * @param mixed $option_value
     *
     * @return mixed
     */
    public function get_current_status($post, $option_value);

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language();
}
