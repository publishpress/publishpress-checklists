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

class Internal_links extends Base_counter
{

    /**
     * The name of the requirement, in a slug format
     *
     * @var string
     */
    public $name = 'internal_links';

     /**
     * The name of the group, used for the tabs
     * 
     * @var string
     */
    public $group = 'links';

    /**
     * @var int
     */
    public $position = 100;

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language()
    {
        $this->lang['label_settings']       = __('Number of internal links in content', 'publishpress-checklists');
        $this->lang['label_min_singular']   = __('Minimum of %d internal link in content', 'publishpress-checklists');
        $this->lang['label_min_plural']     = __('Minimum of %d internal links in content', 'publishpress-checklists');
        $this->lang['label_max_singular']   = __('Maximum of %d internal link in content', 'publishpress-checklists');
        $this->lang['label_max_plural']     = __('Maximum of %d internal links in content', 'publishpress-checklists');
        $this->lang['label_exact_singular'] = __('%d internal link in content', 'publishpress-checklists');
        $this->lang['label_exact_plural']   = __('%d internal links in content', 'publishpress-checklists');
        $this->lang['label_between']        = __(
            'Between %d and %d internal links in content',
            'publishpress-checklists'
        );
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
        $count = count($this->extract_internal_links($post_content));

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
     * Turn all URLs to clickable links and extract internal links after.
     *
     * @param string $content
     * @param array $protocols http/https, ftp, mail, twitter
     * @param array $attributes
     * @param array $internal_links
     * @param string $website
     *
     * @return array
     * @since  1.0.1
     */
    public function extract_internal_links(
        $content,
        $protocols = array(
            'http',
            'mail'
        ),
        array $attributes = array(),
        $internal_links = array(),
        $website = ''
    ) {
        //website host(s). A single $website keeps working for backward
        //compatibility with existing callers; otherwise fall back to every
        //host the site is reachable on (front-end and wp-admin can differ).
        if ($website) {
            $websites = [$website];
        } else {
            $websites = apply_filters(
                'publishpress_checklists_internal_link_hosts',
                array_values(array_unique(array_filter([
                    parse_url(home_url(), PHP_URL_HOST),
                    parse_url(site_url(), PHP_URL_HOST),
                ])))
            );
        }

        //remove images from content
        $content = preg_replace("/<img[^>]+\>/i", "", $content);

        // Link attributes
        $attr = '';
        foreach ($attributes as $key => $val) {
            $attr .= ' ' . $key . '="' . htmlentities($val) . '"';
        }

        $links = array();

        // Extract existing links and tags
        $content = preg_replace_callback(
            '~(<a .*?>.*?</a>|<.*?>)~i',
            function ($match) use (&$links) {
                return '<' . array_push($links, $match[1]) . '>';
            },
            $content
        );

        // Extract text links for each protocol
        foreach ((array)$protocols as $protocol) {
            switch ($protocol) {
                case 'http':
                case 'https':
                    $content = preg_replace_callback(
                        '~(?:(https?)://([^\s<]+)|(www\.[^\s<]+?\.[^\s<]+))(?<![\.,:])~i',
                        function ($match) use ($protocol, &$links, $attr) {
                            if ($match[1]) {
                                $protocol = $match[1];
                            }
                            $link = $match[2] ?: $match[3];

                            return '<' . array_push($links, "<a $attr href=\"$protocol://$link\">$link</a>") . '>';
                        },
                        $content
                    );
                    break;
                case 'mail':
                    $content = preg_replace_callback(
                        '~([^\s<]+?@[^\s<]+?\.[^\s<]+)(?<![\.,:])~',
                        function ($match) use (&$links, $attr) {
                            return '<' . array_push(
                                    $links,
                                    "<a $attr href=\"mailto:{$match[1]}\">{$match[1]}</a>"
                                ) . '>';
                        },
                        $content
                    );
                    break;
                default:
                    $content = preg_replace_callback(
                        '~' . preg_quote($protocol, '~') . '://([^\s<]+?)(?<![\.,:])~i',
                        function ($match) use ($protocol, &$links, $attr) {
                            return '<' . array_push(
                                    $links,
                                    "<a $attr href=\"$protocol://{$match[1]}\">{$match[1]}</a>"
                                ) . '>';
                        },
                        $content
                    );
                    break;
            }
        }

        //Add links to content
        $content = preg_replace_callback(
            '/<(\d+)>/',
            function ($match) use (&$links) {
                return $links[$match[1] - 1];
            },
            $content
        );

        //extract links attributes
        $content = preg_match_all("'\<a.*?href=\"(.*?)\".*?\>(.*?)\<\/a\>'si", $content, $match);

        //loop array and return only valid internal links excluding other images url
        if ($match) {
            $image_extension = array('gif', 'jpg', 'jpeg', 'png', 'svg');
            foreach ($match[0] as $k => $e) {
                $current_link      = $match[1][$k];
                $current_extension = strtolower(
                    pathinfo($current_link, PATHINFO_EXTENSION)
                ); // Using strtolower to overcome case issue
                //skip if link is image
                if (in_array($current_extension, $image_extension)) {
                    continue;
                }
                //skip if link doesn't match any of the site's hosts
                $is_internal_link = false;
                foreach ($websites as $host) {
                    if ($host !== '' && strpos($current_link, $host) !== false) {
                        $is_internal_link = true;
                        break;
                    }
                }
                if (!$is_internal_link) {
                    continue;
                }
                //add valid link to array
                $internal_links[] = $current_link;
            }
        }


        // return internal links as array
        return $internal_links;
    }
}
