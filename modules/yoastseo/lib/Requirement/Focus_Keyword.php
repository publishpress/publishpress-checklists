<?php

/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (C) 2018 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Yoastseo\Requirement;

use PublishPress\Checklists\Core\Requirement\Base_counter;
use stdClass;

\defined('ABSPATH') || exit;

class Focus_Keyword extends Base_counter
{
    /**
     * The name of the requirement, in a slug format
     *
     * @var string
     */
    public $name = 'focus_keyword';

    /**
     * The name of the requirement, in a slug format
     *
     * @var string
     */
    public $group = 'yoastseo';

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language()
    {
        $this->lang['label']                = __('Number of characters in Yoast SEO focus keyword', 'publishpress-checklists');
        $this->lang['label_settings']       = __('Number of characters in Yoast SEO focus keyword', 'publishpress-checklists');
        $this->lang['label_min_singular']   = __('Minimum of %d character in Yoast SEO focus keyword', 'publishpress-checklists');
        $this->lang['label_min_plural']     = __('Minimum of %d characters in Yoast SEO focus keyword', 'publishpress-checklists');
        $this->lang['label_max_singular']   = __('Maximum of %d character in Yoast SEO focus keyword', 'publishpress-checklists');
        $this->lang['label_max_plural']     = __('Maximum of %d characters in Yoast SEO focus keyword', 'publishpress-checklists');
        $this->lang['label_exact_singular'] = __('%d character in Yoast SEO focus keyword', 'publishpress-checklists');
        $this->lang['label_exact_plural']   = __('%d characters in Yoast SEO focus keyword', 'publishpress-checklists');
        $this->lang['label_between']        = __('Between %d and %d characters in Yoast SEO focus keyword', 'publishpress-checklists');
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
        // Get focus keyword from Yoast SEO meta
        $focus_keyword = get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);   
        
        $count = strlen(trim($focus_keyword));

        return ($count >= $option_value[0]) && ($option_value[1] == 0 || $count <= $option_value[1]);
    }
}
