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

class Taxonomies_count extends Base_counter implements Interface_parametrized
{
    /**
     * The priority for the action to load the requirement
     */
    public const PRIORITY = 10;

    /**
     * The name of the requirement, in a slug format. This is dynamic.
     *
     * @var string
     */
    public $name = 'taxonomies_count';

    /**
     * The name of the group, used for the tabs
     * 
     * @var string
     */
    public $group = 'taxonomies';

    /**
     * @var WP_Taxonomy
     */
    public $taxonomy;

    /**
     * Initialize the language strings for the instancef
     *
     * @return void
     */
    public function init_language()
    {
        $label          = $this->taxonomy->labels->name;
        $singular_label = $this->taxonomy->labels->singular_name;

        $this->lang['label_settings']       = __('Number of ', 'publishpress-checklists') . $label;
        $this->lang['label_min_singular']   = __('Minimum of %d ', 'publishpress-checklists') . $singular_label;
        $this->lang['label_min_plural']     = __('Minimum of %d ', 'publishpress-checklists') . $label;
        $this->lang['label_max_singular']   = __('Maximum of %d ', 'publishpress-checklists') . $singular_label;
        $this->lang['label_max_plural']     = __('Maximum of %d ', 'publishpress-checklists') . $label;
        $this->lang['label_exact_singular'] = __('%d ', 'publishpress-checklists') . $singular_label;
        $this->lang['label_exact_plural']   = __('%d ', 'publishpress-checklists') . $label;
        $this->lang['label_between']        = __('Between %d and %d ', 'publishpress-checklists') . $label;
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

        if (!($post instanceof WP_Post)) {
            $post = get_post($post);
        }

        $terms = wp_get_post_terms($post->ID, $this->taxonomy->name);

        $count = count($terms);

        return ($count >= $option_value[0]) && ($option_value[1] == 0 || $count <= $option_value[1]);
    }

    /**
     * @param array $params
     *
     * @return array|void
     */
    public function set_params($params)
    {
        global $wp_taxonomies;


        $this->taxonomy = $wp_taxonomies[$params['taxonomy']];
        $this->name     = $this->taxonomy->name . '_count';

        $subgroup = $this->taxonomy->hierarchical ? 'hierarchical' : 'non_hierarchical';

        $this->type = 'taxonomy_counter_' . $subgroup . '_' . $this->taxonomy->name;

        $this->extra = $this->taxonomy->rest_base;
    }
}
