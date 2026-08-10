<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Core\Requirement;

defined('ABSPATH') or die('No direct script access allowed.');

class External_links extends Base_counter
{

    /**
     * The name of the requirement, in a slug format
     *
     * @var string
     */
    public $name = 'external_links';

     /**
     * The name of the group, used for the tabs
     * 
     * @var string
     */
    public $group = 'links';

    /**
     * @var int
     */
    public $position = 110;

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language()
    {
        $this->lang['label_settings']       = __('Number of external links in content', 'publishpress-checklists');
        $this->lang['label_min_singular']   = __('Minimum of %d external link in content', 'publishpress-checklists');
        $this->lang['label_min_plural']     = __('Minimum of %d external links in content', 'publishpress-checklists');
        $this->lang['label_max_singular']   = __('Maximum of %d external link in content', 'publishpress-checklists');
        $this->lang['label_max_plural']     = __('Maximum of %d external links in content', 'publishpress-checklists');
        $this->lang['label_exact_singular'] = __('%d external link in content', 'publishpress-checklists');
        $this->lang['label_exact_plural']   = __('%d external links in content', 'publishpress-checklists');
        $this->lang['label_between']        = __('Between %d and %d external links in content', 'publishpress-checklists');
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
        $count = count($this->extract_external_links($post_content));

        $min_value = (int)($option_value[0] ?? 0);
        $max_value = (int)($option_value[1] ?? 0);

        // Keep the server-side status in sync with check_valid_quantity() in
        // the editor. A blank maximum is serialized as 0, which means a
        // minimum-only rule when the configured minimum is greater than 0.
        if ($min_value === $max_value) {
            return $count === $min_value;
        }

        if ($min_value > 0 && $max_value < $min_value) {
            return $count >= $min_value;
        }

        if ($min_value > 0 && $max_value > $min_value) {
            return $count >= $min_value && $count <= $max_value;
        }

        if ($min_value === 0 && $max_value > $min_value) {
            return $count <= $max_value;
        }

        return false;
    }

    /**
     * Extract external links from content.
     *
     * @param string $content
     * @param array $external_links
     * @param string $website
     *
     * @return array
     * @since  1.0.1
     */
    public function extract_external_links($content, $external_links = array(), $website = '')
    {
        //website host
        if (!$website) {
            $website = parse_url(home_url())['host'];
        }

        //remove images from content
        $content = preg_replace("/<img[^>]+\>/i", "", $content);

        //extract links
        $content = preg_match_all('/<a.*?href=["\']([^"\']+)["\'].*?\>(.*?)\<\/a\>/i', $content, $match);

        //loop array and return only valid external links excluding other images url
        if ($match) {
            $image_extension = array('gif', 'jpg', 'jpeg', 'png', 'svg');
            foreach ($match[1] as $current_link) {
                $current_extension = strtolower(pathinfo($current_link, PATHINFO_EXTENSION));
                //skip if link is image
                if (in_array($current_extension, $image_extension)) {
                    continue;
                }
                //skip if link point to the current website host
                if (strpos($current_link, $website) !== false) {
                    continue;
                }
                //add valid link to array
                $external_links[] = $current_link;
            }
        }


        // return external links as array
        return $external_links;
    }
}
