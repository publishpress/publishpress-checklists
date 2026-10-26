<?php

/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (C) 2018 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Yoastseo\Requirement;

use PublishPress\Checklists\Core\Requirement\Base_dropdown;
use stdClass;

\defined('ABSPATH') || exit;

class Seo_Analysis extends Base_dropdown
{

    /**
     * Constant used for determining an OK SEO rating.
     *
     * @var integer
     */
    public const OK = '41';

    /**
     * Constant used for determining a good SEO rating.
     *
     * @var integer
     */
    public const GOOD = '71';

    /**
     * The name of the requirement, in a slug format
     *
     * @var string
     */
    public $name = 'yoast_seo_analysis';

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
        $this->lang['label_settings'] = esc_html(__('Minimum Yoast SEO analysis score', 'publishpress-checklists'));
    }

    /**
     * Add the requirement to the list to be displayed in the metabox.
     *
     * @param array $requirements
     * @param stdClass $post
     *
     * @return array
     */
    public function filter_requirements_list($requirements, $post)
    {
        if ($post->post_type !== $this->post_type) {
            return $requirements;
        }

        if (!$this->is_enabled()) {
            return $requirements;
        }

        // Option names
        $option_name_dropdown = $this->name . '_dropdown';

        // Check value is empty, to skip
        if (empty($option_name_dropdown)) {
            return $requirements;
        }

        // Get the value
        $value = $this->get_option($option_name_dropdown);

        $label = $this->get_requirement_drop_down_labels()[$value];

        // Register in the requirements list
        $requirements[$this->name] = [
            'status'    => $this->get_current_status($post, $value),
            'label'     => $label,
            'value'     => $value,
            'rule'      => $this->get_option_rule(),
            'is_custom' => false,
            'type'      => $this->type,
        ];

        return $requirements;
    }

    /**
     * Returns the value of the given option. The option name should
     * be in the short form, without the name of the requirement as
     * the prefix.
     *
     * @param string $option_name
     *
     * @return mixed
     */
    public function get_option($option_name)
    {
        $options = $this->module->options;

        if (isset($options->{$option_name}) && isset($options->{$option_name}[$this->post_type])) {
            return $options->{$option_name}[$this->post_type];
        }

        return null;
    }

    /**
     * Gets the requirement drop down labels for the seo score.
     *
     * @return array The seo rank label.
     */
    public function get_requirement_drop_down_labels()
    {
        $labels = [
            self::OK   => sprintf(
                /* translators: %s expands to the seo score */
                __('Yoast SEO: %s', 'publishpress-checklists'),
                __('OK', 'publishpress-checklists')
            ),
            self::GOOD => sprintf(
                /* translators: %s expands to the seo score */
                __('Yoast SEO: %s', 'publishpress-checklists'),
                __('Good', 'publishpress-checklists')
            ),
        ];

        return $labels;
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
        $score = (int)get_post_meta($post->ID, '_yoast_wpseo_linkdex', true);

        return $score >= $option_value;
    }

    /**
     * Gets settings drop down labels for the seo score.
     *
     * @return array The seo rank label.
     */
    public function get_setting_drop_down_labels()
    {
        $labels = [
            self::OK   => __('OK', 'publishpress-checklists'),
            self::GOOD => __('Good', 'publishpress-checklists'),
        ];

        return $labels;
    }
}
