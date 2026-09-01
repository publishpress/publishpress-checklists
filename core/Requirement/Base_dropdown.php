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

class Base_dropdown extends Base_simple implements Interface_required
{
    /**
     * @var string
     */
    protected $type = 'dropdown';

    protected $field_name = 'dropdown';


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

        $option_name = $this->name;
        $options     = $this->module->options;

        // The enabled status
        $enabled = $this->is_enabled();

        // If not enabled, bypass the method
        if (!$enabled) {
            return $requirements;
        }

        // Option names
        $option_name_dropdown = $this->name . '_' .$this->field_name;

        // Get the value
        $option_value = '';
        if (isset($this->module->options->{$option_name_dropdown}[$this->post_type])) {
            $option_value = $this->module->options->{$option_name_dropdown}[$this->post_type];
        }


        // Check value is empty, to skip
        if (empty($option_name_dropdown)) {
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
        $option_name_dropdown = $this->name  . '_' .$this->field_name;

        // Get the value
        $option_value = '';
        if (isset($this->module->options->{$option_name_dropdown}[$post_type])) {
            $option_value = $this->module->options->{$option_name_dropdown}[$post_type];
        }

        $id   = "{$post_type}-{$this->module->slug}-{$option_name_dropdown}";
        $name = "{$this->module->options_group_name}[{$option_name_dropdown}][{$post_type}]";

        $html = sprintf(
            '<select id="%s" name="%s" aria-label="%s">',
            $id,
            $name,
            esc_attr(sprintf(__('Set option for %s', 'publishpress-checklists'), $this->lang['label_settings']))
        );

        $labels = [];
        $labels = $this->get_setting_drop_down_labels();

        foreach ($labels as $value => $label) {
            $selected = selected($value, $option_value, false);
            $html     .= $this->generate_option($value, $label, $selected);
        }

        $html .= '</select>';

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
}
