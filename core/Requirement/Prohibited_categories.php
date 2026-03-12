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

class Prohibited_categories extends Base_multiple
{
    /**
     * The name of the requirement, in a slug format
     *
     * @var string
     */
    public $name = 'prohibited_categories';

    /**
     * The name of the group, used for the tabs
     * 
     * @var string
     */
    public $group = 'categories';

    /**
     * @var int
     */
    public $position = 50;

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

        // Add the AJAX action to get the list of categories
        add_action('wp_ajax_pp_checklists_prohibited_category', [$this, 'get_list_category_ajax']);

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
        $this->lang['label']          = __('Prohibited categories: %s', 'publishpress-checklists');
        $this->lang['label_settings'] = __('Prohibited categories', 'publishpress-checklists');
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
        $categories = wp_get_post_categories($post_id);
        $option_ids = $this->category_parser($option_value, 0);

        return empty(array_intersect($option_ids, $categories));
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
        $selected_categories = array();
        foreach ($option_value as $category_str) {
            [$category_id, $category_name] = explode($this->DELIMITER, $category_str);
            $selected_categories[] = $category_id;
        }

        return $selected_categories;
    }

    /**
     * Get the list of categories
     *
     * @param array $args
     * @return WP_Term[]
     */
    private function get_list_categories($args = array('page' => 1, 'per_page' => 10, 'q' => ''))
    {
        // Get selected categories from the settings
        $selected_categories = $this->get_selected_values();

        // Retrieve selected categories only on the first page
        $categories_selected = array();
        if ($args['page'] === 1 && !empty($selected_categories)) {
            $args_selected = array(
                'orderby'    => 'name',
                'order'      => 'ASC',
                'hide_empty' => 0,
                'include'    => $selected_categories,
                'search'     => $args['q'],
            );
            $categories_selected = $this->get_categories_hierarchical($args_selected);
        }

        // Retrieve categories with a limit of 10 rows
        $args_limited = array(
            'type'         => 'post',
            'hide_empty'   => 0,
            'hierarchical' => 1,
            'taxonomy'     => 'category',
            'pad_counts'   => false,
            'orderby'      => 'name',
            'order'        => 'ASC',
            'search'       => $args['q'],
            'number'       => $args['per_page'],
            'offset'       => ($args['page'] - 1) * $args['per_page']
        );
        $categories_limited = $this->get_categories_hierarchical($args_limited);

        // Merge the two arrays
        $categories = array_merge($categories_limited, $categories_selected);

        // Remove duplicates based on term_id
        $categories = array_values(array_reduce($categories, function ($carry, $item) {
            if (!isset($carry[$item->term_id])) {
                $carry[$item->term_id] = $item;
            }
            return $carry;
        }, []));

        // Sort the array by name
        usort($categories, function ($a, $b) {
            return strcasecmp($a->name, $b->name);
        });

        return $categories;
    }

    /**
     * Get the total count of categories
     *
     * @param array $args
     * @return int
     */
    private function get_total_count($args = array('search' => '', 'hide_empty' => 0))
    {
        $args_key = base64_encode($args['search']);
        $cache_key = 'total_prohib_category_count_' . $args_key;

        $total_categories = get_transient($cache_key);
        if ($total_categories === false) {
            $total_categories = wp_count_terms('category', $args);
            set_transient($cache_key, $total_categories, $this->cache_expiration);
        }

        return $total_categories;
    }

    /**
     * Get the list of categories via AJAX
     *
     * @return void
     */
    public function get_list_category_ajax()
    {
        // Check if the request is valid
        check_ajax_referer('pp-checklists-rules', 'nonce');

        // Get the search query and page number from the request
        $search = isset($_POST['q']) ? sanitize_text_field($_POST['q']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 10;

        // Get the categories
        $categories = $this->get_list_categories(['page' => $page, 'per_page' => $per_page, 'q' => $search]);
        $results = array();

        foreach ($categories as $category) {
            $results[] = array(
                'id'   => $category->term_id . $this->DELIMITER . $category->name,
                'text' => $category->name,
            );
            if (isset($category->children)) {
                foreach ($category->children as $child) {
                    $results[] = array(
                        'id'   => $child->term_id . $this->DELIMITER . $child->name,
                        'text' => '— ' . $child->name,
                    );
                }
            }
        }

        // Check if there are more categories
        $total_categories = $this->get_total_count(array('search' => $search, 'hide_empty' => 0));
        $has_next = ($page * $per_page) < $total_categories;

        wp_send_json_success(['items' => $results, 'has_next' => $has_next]);
        wp_die();
    }

    /**
     * Get Categories Hierarchical List
     * 
     * @param array $args
     * @return WP_Term[]
     */
    private function get_categories_hierarchical($args = array())
    {
        if (!isset($args['parent'])) $args['parent'] = 0;

        $cache_key = md5('prohib_category' . json_encode($args));
        $categories = get_transient($cache_key);

        // if cache is empty, get value from database
        if ($categories === false) {
            $categories = get_categories($args);
            foreach ($categories as $key => $category) {
                $args['parent'] = $category->term_id;
                $categories[$key]->children = $this->get_categories_hierarchical($args);
            }
            // save result to cache
            set_transient($cache_key, $categories, $this->cache_expiration);
        }

        return $categories;
    }

    /**
     * Transform categories to labels
     * 
     * @param WP_Term[] $categories
     * @return String[] $labels
     */
    private function transform_categories($categories = array())
    {
        $labels = [];

        foreach ($categories as $cat => $category) {
            $labels[$category->term_id . $this->DELIMITER . $category->name] = $category->name;
            if (isset($category->children)) {
                foreach ($category->children as $child) {
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
        $categories = $this->get_list_categories();

        return $this->transform_categories($categories);
    }

    /**
     * Parse categories
     * This method for remapping
     * example: 1__Category 1, 2__Category 2, 3__Category 3
     * result: [1, 2, 3] or ['Category 1', 'Category 2', 'Category 3'] based on $index
     * 
     * @param String[] $categories
     * @param int $index
     * @return String[] $categories
     */
    private function category_parser($categories = array(), $index = 0 | 1)
    {
        return array_map(function ($value) use ($index) {
            return explode($this->DELIMITER, $value)[$index];
        }, $categories);
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

        $selected_categories = array();
        foreach ($option_value as $category_str) {
            [, $category_name] = explode($this->DELIMITER, $category_str);
            $selected_categories[] = $category_name;
        }

        $blocked_category_names = implode(', ', $selected_categories);

        if (empty($blocked_category_names)) {
            return $requirements;
        }

        // Register in the requirements list.
        $requirements[$this->name]['label'] = $this->get_prohibited_categories_label_for_display(
            $blocked_category_names
        );

        return $requirements;
    }

    /**
     * Returns the rendered label for editing screens, preserving prohibited category names.
     *
     * @param string $category_names
     *
     * @return string
     */
    protected function get_prohibited_categories_label_for_display($category_names)
    {
        $default_label = sprintf($this->lang['label'], $category_names);
        $editor_label = trim($this->get_editor_label());

        if ('' === $editor_label) {
            return $default_label;
        }

        if ('' === $category_names) {
            return $editor_label;
        }

        if (false !== strpos($editor_label, '%categories%')) {
            return str_replace('%categories%', $category_names, $editor_label);
        }

        if (preg_match('/%(?:\d+\$)?s/', $editor_label)) {
            return sprintf($editor_label, $category_names);
        }

        return sprintf(
            /* translators: 1: custom editor label, 2: prohibited category names. */
            __('%1$s - %2$s', 'publishpress-checklists'),
            $editor_label,
            $category_names
        );
    }


    public function get_setting_field_html($css_class = '')
    {
        return parent::get_setting_field_html('pp-checklists-full-width');
    }
}
