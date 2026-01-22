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

class Base_multiple extends Base_simple implements Interface_required
{
    /**
     * @var string
     */
    protected $type = 'multiple';

    protected $field_name = 'multiple';


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

        // The enabled status
        $enabled = $this->is_enabled();

        // If not enabled, bypass the method
        if (!$enabled) {
            return $requirements;
        }

        // Option names
        $option_name_multiple = $this->name . '_' .$this->field_name;

        // Get the value
        $option_value = array();
        if (isset($this->module->options->{$option_name_multiple}[$this->post_type])) {
            $option_value = $this->module->options->{$option_name_multiple}[$this->post_type];
        }

        // Check value is empty, to skip
        if (empty($option_name_multiple)) {
            return $requirements;
        }

        // The rule
        $rule = $this->get_option_rule();

        // Register in the requirements list
        $requirements[$this->name] = [
            'status'    => $this->get_current_status($post, $option_value),
            'label'     => $this->lang['label_settings'],
            'value'     => $option_value,
            'rule'      => $rule,
            'type'      => $this->type,
            'is_custom' => false,
            'require_button' => false,
        ];

        return $requirements;
    }

    /**
     * Get the HTML for the setting field for the specific post type.
     *
     * @return string
     */
    public function get_setting_field_html($css_class = '')
    {
        $html = parent::get_setting_field_html($css_class);

        $post_type = esc_attr($this->post_type);
        $css_class = esc_attr($css_class);

        // Option name
        $option_name_multiple = $this->name . '_' . $this->field_name;

        // Get the value
        $option_value = array();
        if (isset($this->module->options->{$option_name_multiple}[$post_type])) {
            $option_value = $this->module->options->{$option_name_multiple}[$post_type];
        }

        $id   = "{$post_type}-{$this->module->slug}-{$option_name_multiple}";
        $name = "{$this->module->options_group_name}[{$option_name_multiple}][{$post_type}][]";

        if (isset($this->lang['label_option_title'])) {
            $html .= '<label class="pp-checklists-editable-by-label">' . $this->lang['label_option_title'] . '</label>';
        }

        $html .= sprintf(
            '<select id="%s" name="%s" class="%s" multiple="multiple">',
            $id,
            $name,
            $css_class
        );

        $labels = $this->get_setting_drop_down_labels();

        foreach ($labels as $value => $label) {
            $selected = selected(true, in_array($value, $option_value), false);
            $html     .= $this->generate_option($value, $label, $selected);
        }

        $html .= '</select>';

        if (isset($this->lang['label_option_description'])) {
            $html .= '<p class="pp-checklists-editable-by-description">' . $this->lang['label_option_description'] . '</p>';
        }

        return $html;
    }

    /**
     * Generates an <option> element.
     *
     * @param string $value The option's value.
     * @param string $label The option's label.
     * @param string $selected HTML selected attribute for an option.
     *
     * @return string The generated <option> element.
     */
    protected function generate_option($value, $label, $selected = '')
    {
        return '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
    }
    
    /**
     * Validates the option group for multiple select fields.
     * Ensures empty selections are properly handled by creating an empty array.
     * Also filters out any empty values to prevent PHP notices.
     *
     * @param array $new_options
     *
     * @return array
     */
    public function filter_settings_validate($new_options)
    {
        $new_options = parent::filter_settings_validate($new_options);

        // Option name for the multiple field
        $option_name_multiple = $this->name . '_' . $this->field_name;
        
        // If the key doesn't exist at all (completely empty multiselect), create it as an empty array
        if (!isset($new_options[$option_name_multiple][$this->post_type])) {
            $new_options[$option_name_multiple][$this->post_type] = array();
        } 
        
        // Filter out empty values to prevent PHP notices when parsing id__name strings
        $new_options[$option_name_multiple][$this->post_type] = array_filter(
            $new_options[$option_name_multiple][$this->post_type],
            function($value) {
                return !empty($value);
            }
        );
        
        return $new_options;
    }
}
