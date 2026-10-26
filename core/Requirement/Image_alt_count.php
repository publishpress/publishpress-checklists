<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2023 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Core\Requirement;

\defined('ABSPATH') || exit;

class Image_alt_count extends Base_counter
{
    /**
     * The name of the requirement, in a slug format
     *
     * @var string
     */
    public $name = 'image_alt_count';

    /**
     * The name of the group, used for the tabs
     * 
     * @var string
     */
    public $group = 'images';

    /**
     * @var int
     */
    public $position = 135;

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language()
    {
        $this->lang['label']                = __('Number of characters in Alt text', 'publishpress-checklists');
        $this->lang['label_settings']       = __('Number of characters in Alt text', 'publishpress-checklists');
        $this->lang['label_min_singular']   = __('Minimum of %d character in Alt text', 'publishpress-checklists');
        $this->lang['label_min_plural']     = __('Minimum of %d characters in Alt text', 'publishpress-checklists');
        $this->lang['label_max_singular']   = __('Maximum of %d character in Alt text', 'publishpress-checklists');
        $this->lang['label_max_plural']     = __('Maximum of %d characters in Alt text', 'publishpress-checklists');
        $this->lang['label_exact_singular'] = __('%d character in Alt text', 'publishpress-checklists');
        $this->lang['label_exact_plural']   = __('%d characters in Alt text', 'publishpress-checklists');
        $this->lang['label_between']        = __('Between %d and %d characters in Alt text', 'publishpress-checklists');
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
        $alt_lengths = $this->get_image_alt_lengths($post_content);
        
        foreach ($alt_lengths as $length) {
            if ($length < $option_value[0] || ($option_value[1] > 0 && $length > $option_value[1])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the lengths of alt text for all images in the content
     *
     * @param string $content
     *
     * @return array
     */
    private function get_image_alt_lengths($content)
    {
        $lengths = [];

        if (preg_match_all('/<img[^>]+alt=([\'"])(.*?)\1[^>]*>/i', $content, $matches)) {
            foreach ($matches[2] as $alt_text) {
                $lengths[] = strlen(trim($alt_text));
            }
        }

        return $lengths;
    }
}
