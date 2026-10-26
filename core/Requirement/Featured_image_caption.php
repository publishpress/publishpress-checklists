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

class Featured_image_caption extends Base_simple
{
    /**
     * The name of the requirement, in a slug format
     *
     * @var string
     */
    public $name = 'featured_image_caption';

    /**
     * The name of the group, used for the tabs
     * 
     * @var string
     */
    public $group = 'featured_image';

    /**
     * @var int
     */
    public $position = 106;

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language()
    {
        $this->lang['label']          = __('Featured image has caption', 'publishpress-checklists');
        $this->lang['label_settings'] = __('Featured image has caption', 'publishpress-checklists');
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
        $thumbnail_id = get_post_thumbnail_id($post);
        /**
         * check if new post
         * new post will have no thumbnail in initialize page
         */
        if ($thumbnail_id === 0) return true;

        $thumbnail_caption = get_post_meta($thumbnail_id, '_wp_attachment_image_caption', true);

        return !empty($thumbnail_caption);
    }
}
