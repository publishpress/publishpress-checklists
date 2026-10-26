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

class Featured_image extends Base_simple
{
    /**
     * The name of the requirement, in a slug format
     *
     * @var string
     */
    public $name = 'featured_image';

    /**
     * The name of the group, used for the tabs
     * 
     * @var string
     */
    public $group = 'featured_image';

    /**
     * @var int
     */
    public $position = 102;

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language()
    {
        $this->lang['label']          = __('Featured image is added', 'publishpress-checklists');
        $this->lang['label_settings'] = __('Featured image is added', 'publishpress-checklists');
    }

    /**
     * Returns the current status of the requirement.
     *
     * @param stdClass $post
     * @param mixed $option_value
     *
     * @return mixed
     */
    public function get_current_status($post, $option_value)
    {
        $thumbnail = get_the_post_thumbnail($post);

        return !empty($thumbnail);
    }
}
