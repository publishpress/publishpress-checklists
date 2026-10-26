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

class Image_alt extends Base_simple
{

    /**
     * The name of the requirement, in a slug format
     *
     * @var string
     */
    public $name = 'image_alt';

    /**
     * The name of the group, used for the tabs
     * 
     * @var string
     */
    public $group = 'images';

    /**
     * @var int
     */
    public $position = 130;

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language()
    {
        $this->lang['label']          = __('All images have Alt text', 'publishpress-checklists');
        $this->lang['label_settings'] = __('All images have Alt text', 'publishpress-checklists');
    }

    /**
     * Check for images without alt text from content and return result as array
     *
     * @param string $content
     * @param array $missing_alt
     *
     * @return array
     * @since  1.0.1
     */
    private function missing_alt_images($content, $missing_alt = array())
    {
        if ($content) {
            //remove ALT tag if it value is empty or whitespace without real text
            $content = preg_replace('!alt="\p{Z}*"|alt=\'\p{Z}*\'!s', '', $content);

            //look for images without ALT attribute at all
            preg_match_all('@<img(?:(?!alt=).)*?>@', $content, $images);

            //return the array
            $missing_alt = $images[0];
        }

        return $missing_alt;
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
        $post_content = isset($post->post_content) ? $post->post_content : '';
        $count = count($this->missing_alt_images($post_content));

        return $count == 0;
    }
}
