<?php

/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Core\Requirement;

use PPCH_Checklists;

defined('ABSPATH') or die('No direct script access allowed.');

class Required_tags extends Base_multiple
{
    /**
     * The name of the requirement, in a slug format
     *
     * @var string
     */
    public $name = 'required_tags';

    /**
     * The name of the group, used for the tabs
     * 
     * @var string
     */
    public $group = 'tags';

    /**
     * @var int
     */
    public $position = 70;

    /**
     * @var string
     */
    private $DELIMITER = '__';

    /**
     * The cache expiry time in 10 minutes
     * 
     * @var int
     */
    private $cache_expiration = 1 * MINUTE_IN_SECONDS;

    /**
     * Flag to check if hooks have been initialized
     *
     * @var bool
     */
    private $hooks_initialized = false;

    public function __construct($module, $post_type)
    {
        parent::__construct($module, $post_type);
        $this->init_hooks();
    }

    /**
     * Initialize the hooks for the requirement
     *
     * @return void
     */
    public function init_hooks()
    {
        // Check if the hooks were already initialized
        if ($this->hooks_initialized) return;

        // Add the AJAX action to get the list of tags
        add_action('wp_ajax_pp_checklists_required_tag', [$this, 'get_list_tag_ajax']);

        // Set the initialization flag to true
        $this->hooks_initialized = true;
    }

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language()
    {
        $this->lang['label']          = __('Required tags: %s', 'publishpress-checklists');
        $this->lang['label_settings'] = __('Required tags', 'publishpress-checklists');
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
        $tags = wp_get_post_tags($post_id, array('fields' => 'ids'));
        $option_ids = $this->tag_parser($option_value, 0);

        return !empty(array_intersect($option_ids, $tags));
    }

    /**
     * Get selected values from the settings
     *
     * @return array
     */
    private function get_selected_values()
    {
        // Option names
        $option_name_multiple = $this->name . '_' . $this->field_name;
        $option_value = array();
        if (isset($this->module->options->{$option_name_multiple}[$this->post_type])) {
            $option_value = $this->module->options->{$option_name_multiple}[$this->post_type];
        }
        $selected_tags = array();
        foreach ($option_value as $tag_str) {
            [$tag_id, $tag_name] = explode($this->DELIMITER, $tag_str);
            $selected_tags[] = $tag_id;
        }

        return $selected_tags;
    }

    /**
     * Get the list of tags
     *
     * @param array $args
     * @return WP_Term[]
     */
    private function get_list_tags($args = array('page' => 1, 'per_page' => 10, 'q' => ''))
    {
        // Get selected tags from the settings
        $selected_tags = $this->get_selected_values();

        // Retrieve selected tags only on the first page
        $tags_selected = array();

        if ($args['page'] === 1 && !empty($selected_tags)) {
            $args_selected = array(
                'taxonomy'   => 'post_tag',
                'hide_empty' => 0,
                'include'    => $selected_tags,
                'search'     => $args['q'],
            );
            $cache_key_selected = md5('required_tag_selected' . json_encode($args_selected));
            $tags_selected = get_transient($cache_key_selected);
            if ($tags_selected === false) {
                $tags_selected = get_tags($args_selected);
                set_transient($cache_key_selected, $tags_selected, $this->cache_expiration);
            }
        }

        // Retrieve tags with a limit of 10 rows
        $args_limited = array(
            'taxonomy'   => 'post_tag',
            'hide_empty' => 0,
            'exclude'    => $selected_tags,
            'search'     => $args['q'],
            'number'     => $args['per_page'],
            'offset'     => ($args['page'] - 1) * $args['per_page'],
        );
        $cache_key = md5('required_tag' . json_encode($args_limited));
        $tags_limited = get_transient($cache_key);
        if ($tags_limited === false) {
            $tags_limited = get_tags($args_limited);
            set_transient($cache_key, $tags_limited, $this->cache_expiration);
        }

        // Merge the two arrays
        $tags = array_merge($tags_limited, $tags_selected);

        // Remove duplicates based on term_id
        $tags = array_values(array_reduce($tags, function ($carry, $item) {
            if (!isset($carry[$item->term_id])) {
                $carry[$item->term_id] = $item;
            }
            return $carry;
        }, []));

        // Sort the array by name
        usort($tags, function ($a, $b) {
            return strcasecmp($a->name, $b->name);
        });

        return $tags;
    }

    /**
     * Get the total count of tags
     *
     * @param array $args
     * @return int
     */
    private function get_total_count($args = array('search' => '', 'hide_empty' => 0))
    {
        $args_key = base64_encode($args['search']);
        $cache_key = 'total_required_tag_count_' . $args_key;

        $total_tags = get_transient($cache_key);
        if ($total_tags === false) {
            $total_tags = wp_count_terms('post_tag', $args);
            set_transient($cache_key, $total_tags, $this->cache_expiration);
        }

        return $total_tags;
    }

    /**
     * Get the list of tags via AJAX
     *
     * @return void
     */
    public function get_list_tag_ajax()
    {
        // Check if the request is valid
        check_ajax_referer('pp-checklists-rules', 'nonce');

        // Get the search query and page number from the request
        $search = isset($_POST['q']) ? sanitize_text_field($_POST['q']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 10;

        // Get the tags
        $tags = $this->get_list_tags(['page' => $page, 'per_page' => $per_page, 'q' => $search]);
        $results = array();

        foreach ($tags as $tag) {
            $results[] = array(
                'id'   => $tag->term_id . $this->DELIMITER . $tag->name,
                'text' => $tag->name,
            );
        }

        // Check if there are more tags
        $total_tags = $this->get_total_count(array('search' => $search, 'hide_empty' => 0));
        $has_next = ($page * $per_page) < $total_tags;

        wp_send_json_success(['items' => $results, 'has_next' => $has_next]);
        wp_die();
    }

    /**
     * Transform tags to labels
     * 
     * @param WP_Term[] $tags
     * @return String[] $labels
     */
    private function transform_tags($tags = array())
    {
        $labels = [];

        foreach ($tags as $tag) {
            $labels[$tag->term_id . $this->DELIMITER . $tag->name] = $tag->name;
            if (isset($tag->children)) {
                foreach ($tag->children as $child) {
                    $labels[$child->term_id . $this->DELIMITER . $child->name] = "— {$child->name}";
                }
            }
        }

        return $labels;
    }

    /**
     * Gets settings drop down labels.
     *
     * @return array.
     */
    public function get_setting_drop_down_labels()
    {
        $tags = $this->get_list_tags();

        return $this->transform_tags($tags);
    }

    /**
     * Parse tags
     * This method for remapping
     * example: 1__Tag 1, 2__Tag 2, 3__Tag 3
     * result: [1, 2, 3] or ['Tag 1', 'Tag 2', 'Tag 3'] based on $index
     * 
     * @param String[] $tags
     * @param int $index
     * @return String[] $tags
     */
    private function tag_parser($tags = array(), $index = 0 | 1)
    {
        return array_map(function ($value) use ($index) {
            return explode($this->DELIMITER, $value)[$index];
        }, $tags);
    }


    /**
     * Add the requirement to the list to be displayed in the meta box.
     *
     * @param array $requirements
     * @param stdClass $post
     *
     * @return array
     */
    public function filter_requirements_list($requirements, $post)
    {
        // Check if it is a compatible post type. If not, ignore this requirement.
        if ($post->post_type !== $this->post_type) {
            return $requirements;
        }

        $requirements = parent::filter_requirements_list($requirements, $post);

        // If not enabled, bypass the method
        if (!$this->is_enabled()) {
            return $requirements;
        }

        // Option names
        $option_name_multiple = $this->name . '_' . $this->field_name;

        // Get the value
        $option_value = array();
        if (isset($this->module->options->{$option_name_multiple}[$this->post_type])) {
            $option_value = $this->module->options->{$option_name_multiple}[$this->post_type];
        }

        if (empty($option_value)) {
            return $requirements;
        }

        $selected_tags = array();
        foreach ($option_value as $tag_str) {
            [, $tag_name] = explode($this->DELIMITER, $tag_str);
            $selected_tags[] = $tag_name;
        }

        $required_tag_names = implode(', ', $selected_tags);

        if (empty($required_tag_names)) {
            return $requirements;
        }

        // Register in the requirements list.
        $requirements[$this->name]['label'] = $this->get_required_tags_label_for_display($required_tag_names);

        return $requirements;
    }

    /**
     * Returns the rendered label for editing screens, preserving required tag names.
     *
     * @param string $tag_names
     *
     * @return string
     */
    protected function get_required_tags_label_for_display($tag_names)
    {
        $default_label = sprintf($this->lang['label'], $tag_names);
        $editor_label = trim($this->get_editor_label());

        if ('' === $editor_label) {
            return $default_label;
        }

        if ('' === $tag_names) {
            return $editor_label;
        }

        if (false !== strpos($editor_label, '%tags%')) {
            return str_replace('%tags%', $tag_names, $editor_label);
        }

        if (preg_match('/%(?:\d+\$)?s/', $editor_label)) {
            return sprintf($editor_label, $tag_names);
        }

        return sprintf(
            /* translators: 1: custom editor label, 2: required tag names. */
            __('%1$s - %2$s', 'publishpress-checklists'),
            $editor_label,
            $tag_names
        );
    }


    public function get_setting_field_html($css_class = '')
    {
        return parent::get_setting_field_html('pp-checklists-full-width');
    }
}
