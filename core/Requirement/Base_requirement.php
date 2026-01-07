<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Core\Requirement;

use PublishPress\Checklists\Core\Plugin;

defined('ABSPATH') or die('No direct script access allowed.');

class Base_requirement
{
    /**
     * The Yes value
     */
    const VALUE_YES = 'yes';

    /**
     * The No value
     */
    const VALUE_NO = 'no';

    /**
     * The priority for the action to load the requirement
     */
    const PRIORITY = 10;

    /**
     * A reference for the current module
     *
     * @var PP_Checklists
     */
    public $module;

    /**
     * The name of the requirement, in a slug format
     *
     * @var string
     */
    public $name = '';

    /**
     * Array for language strings
     *
     * @var array
     */
    public $lang = [];

    /**
     * Define if it is a custom requirement or not
     *
     * @var array
     */
    public $is_custom = false;

    /**
     * The post type of this requirement.
     *
     * @var string
     */
    protected $post_type;

    /**
     * @var string
     */
    protected $type = 'base';

    /**
     * The constructor. It adds the action to load the requirement.
     *
     * @param string $module
     * @param string $post_type
     *
     * @return  void
     */
    public function __construct($module, $post_type)
    {
        add_action('publishpress_checklists_load_requirements', [$this, 'init'], static::PRIORITY);

        $this->module    = $module;
        $this->post_type = $post_type;
    }

    /**
     * Method to initialize the Requirement, adding filters and actions to
     * interact with the Add-on.
     *
     * @return void
     */
    public function init()
    {
        add_filter('publishpress_checklists_requirements_default_options', [$this, 'filter_default_options']);
        add_filter('publishpress_checklists_validate_requirement_settings', [$this, 'filter_settings_validate']);
        add_filter('publishpress_checklists_requirement_list', [$this, 'filter_requirements_list'], 10, 3);
        add_filter('publishpress_checklists_requirement_instances', [$this, 'filter_requirement_instances'], 10, 4);

        $this->init_language();
    }

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language()
    {
        // override
    }

    /**
     * Returns the current status of the requirement.
     *
     * The child class should
     * evaluate the status and override this method.
     *
     * @param stdClass $post
     * @param mixed $option_value
     *
     * @return mixed
     */
    public function get_current_status($post, $option_value)
    {
        return false;
    }

    /**
     * Add the instance of the requirement class to the list.
     *
     * @param array $requirements
     *
     * @return array
     */
    public function filter_requirement_instances($requirements)
    {
        if (!empty($this->name)) {
            $requirements[$this->name] = $this;
        }

        return $requirements;
    }

    /**
     * Get the custom label for this requirement if set.
     *
     * @return string
     */
    public function get_custom_label()
    {
        $option_name = $this->name . '_custom_label';
        
        if (isset($this->module->options->{$option_name}[$this->post_type])) {
            return stripslashes($this->module->options->{$option_name}[$this->post_type]);
        }
        
        return '';
    }

    /**
     * Get the HTML for the title setting field.
     *
     * @return string
     */
    public function get_setting_title_html($css_class = '')
    {
        $default_label = $this->lang['label_settings'];
        $custom_label = $this->get_custom_label();
        $has_custom = !empty($custom_label);
        
        $post_type = esc_attr($this->post_type);
        $option_name = esc_attr($this->name . '_custom_label');
        $input_name = esc_attr($this->module->options_group_name) . '[' . $option_name . '][' . $post_type . ']';
        
        $html = '<div class="pp-checklists-title-wrapper">';
        $html .= '<span class="pp-checklists-default-label' . ($has_custom ? ' hidden' : '') . '">' . esc_html($default_label) . '</span>';
        $html .= '<span class="pp-checklists-custom-label' . (!$has_custom ? ' hidden' : '') . '">' . esc_html($custom_label) . '</span>';
        $html .= sprintf(
            '<input type="text" name="%s" value="%s" placeholder="%s" class="pp-checklists-custom-label-input hidden" />',
            $input_name,
            esc_attr($custom_label),
            esc_attr($default_label)
        );
        $html .= '<a href="#" class="pp-checklists-edit-label" title="' . esc_attr__('Edit label', 'publishpress-checklists') . '">';
        $html .= '<span class="dashicons dashicons-edit"></span>';
        $html .= '</a>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get the HTML for the setting field for the specific post type.
     *
     * @return string
     */
    public function get_setting_field_html($css_class = '')
    {
        return '';
    }

    /**
     * Get the HTML for the action list field  for the specific post type.
     * Used for settings fields to specify if the requirement is required or
     * not.
     *
     * @return string
     */
    public function get_setting_action_list_html()
    {
        $post_type = esc_attr($this->post_type);

        $option_name = $this->name . '_rule';

        $id   = "{$post_type}-{$this->module->slug}-{$option_name}";
        $name = "{$this->module->options_group_name}[{$option_name}][{$post_type}]";

        $html = sprintf(
            '<select id="%s" name="%s">',
            $id,
            $name
        );

        $rules = [];
        $rules = apply_filters('publishpress_checklists_rules_list', $rules);

        // Get the value
        $value = Plugin::RULE_DISABLED;
        if (isset($this->module->options->{$option_name}[$post_type])) {
            $value = $this->module->options->{$option_name}[$post_type];
        }

        foreach ($rules as $rule => $label) {
            //Recognize RULE_ONLY_DISPLAY value as RULE_WARNING
            $value = $value === Plugin::RULE_ONLY_DISPLAY ? Plugin::RULE_WARNING : $value;
            $html .= sprintf(
                '<option value="%s" %s>%s</option>',
                $rule,
                selected($rule, $value, false),
                $label
            );
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * Returns the post type of this requirement
     *
     * @return string
     */
    public function get_post_type()
    {
        return $this->post_type;
    }
}
