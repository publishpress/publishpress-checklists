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

class Tags_count extends Base_counter
{
    /**
     * The priority for the action to load the requirement
     */
    public const PRIORITY = 10;

    /**
     * The name of the requirement, in a slug format
     *
     * @var string
     */
    public $name = 'tags_count';

    /**
     * The name of the group, used for the tabs
     * 
     * @var string
     */
    public $group = 'tags';

    /**
     * @var int
     */
    public $position = 60;

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language()
    {
        $this->lang['label_settings']       = __('Number of tags', 'publishpress-checklists');
        $this->lang['label_min_singular']   = __('Minimum of %d tag', 'publishpress-checklists');
        $this->lang['label_min_plural']     = __('Minimum of %d tags', 'publishpress-checklists');
        $this->lang['label_max_singular']   = __('Maximum of %d tag', 'publishpress-checklists');
        $this->lang['label_max_plural']     = __('Maximum of %d tags', 'publishpress-checklists');
        $this->lang['label_exact_singular'] = __('%d tag', 'publishpress-checklists');
        $this->lang['label_exact_plural']   = __('%d tags', 'publishpress-checklists');
        $this->lang['label_between']        = __('Between %d and %d tags', 'publishpress-checklists');
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
        $post_id = isset($post->ID) ? $post->ID : 0;
        $tags = wp_get_post_tags($post_id);

        $count = count($tags);

        return ($count >= $option_value[0]) && ($option_value[1] == 0 || $count <= $option_value[1]);
    }
}
