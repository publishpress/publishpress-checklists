<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2025 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Core\Requirement;

use PublishPress\Checklists\Core\Plugin;

defined('ABSPATH') or die('No direct script access allowed.');

class Base_time extends Base_requirement implements Interface_required
{
    /**
     * @var string
     */
    protected $type = 'time';

    /**
     * Injects the respective default options into the main add-on.
     *
     * @param array $default_options
     *
     * @return array
     */
    public function filter_default_options($default_options)
    {
        $options = [
            $this->name          => [
                $this->post_type => static::VALUE_NO,
            ],
            "{$this->name}_rule" => [
                $this->post_type => Plugin::RULE_ONLY_DISPLAY,
            ],
            "{$this->name}_value" => [
                $this->post_type => '',
            ],
        ];

        return array_merge($default_options, $options);
    }

    /**
     * Validates the option group, making sure the values are sanitized.
     *
     * @param array $new_options
     *
     * @return array
     */
    public function filter_settings_validate($new_options)
    {
        if (isset($new_options[$this->name][$this->post_type])) {
            if (static::VALUE_YES !== $new_options[$this->name][$this->post_type]) {
                $new_options[$this->name][$this->post_type] = static::VALUE_NO;
            }
        } else {
            $new_options[$this->name][$this->post_type] = static::VALUE_NO;
        }

        // Sanitize the time value
        $index = $this->name . '_value';
        if (isset($new_options[$index][$this->post_type])) {
            // Accepts only valid time in HH:MM format (24-hour)
            $time = $new_options[$index][$this->post_type];
            if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
                $new_options[$index][$this->post_type] = '';
            }
        }

        // Sanitize custom label
        $custom_label_key = $this->name . '_custom_label';
        if (isset($new_options[$custom_label_key][$this->post_type])) {
            $new_options[$custom_label_key][$this->post_type] = sanitize_text_field(
                $new_options[$custom_label_key][$this->post_type]
            );
        }

        return $new_options;
    }

    /**
     * Checks if the requirement is complete for the given post.
     *
     * @param int $post_id
     *
     * @return bool
     */
    public function is_complete($post_id)
    {
        // The meta key to check can be set via $this->get_config('field_key') or similar mechanism
        $field_key = $this->get_config('field_key');
        if (!$field_key) {
            return false;
        }
        $time_value = get_post_meta($post_id, $field_key, true);
        if (empty($time_value)) {
            return false;
        }
        // Validate if it's a parseable time string
        $timestamp = strtotime($time_value);
        if ($timestamp === false) {
            return false;
        }
        return true;
    }

    /**
     * Get the HTML for the setting field for the specific post type (time input).
     *
     * @return string
     */
    public function get_setting_field_html($css_class = '')
    {
        $post_type = esc_attr($this->post_type);
        $css_class = esc_attr($css_class);

        // Option name for the time value
        $option_name_time = $this->name . '_value';
        $id   = "{$post_type}-{$this->module->slug}-{$option_name_time}";
        $name = "{$this->module->options_group_name}[{$option_name_time}][{$post_type}]";

        // Get current value
        $option_value = '';
        if (isset($this->module->options->{$option_name_time}[$post_type])) {
            $option_value = $this->module->options->{$option_name_time}[$post_type];
        }

        // Render time input
        $html = sprintf(
            '<input type="time" id="%s" name="%s" value="%s" class="%s" />',
            $id,
            $name,
            esc_attr($option_value),
            $css_class
        );

        return $html;
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

        // Rule
        $rule = $this->get_option_rule();

        // Enabled
        $enabled = $this->is_enabled();

        // Register in the requirements list
        if ($enabled) {
            $requirements[$this->name] = [
                'status'    => $this->get_current_status($post, $enabled),
                'label'     => $this->lang['label'],
                'value'     => $enabled,
                'rule'      => $rule,
                'type'      => $this->type,
                'is_custom' => false,
            ];
        }

        return $requirements;
    }

    /**
     * Returns the value for the rule option. The default value is "Disabled"
     *
     * @return string
     */
    public function get_option_rule()
    {
        $option_rule_property = $this->name . '_rule';
        $options              = $this->module->options;

        // Rule
        $rule = Plugin::RULE_DISABLED;
        if (isset($options->{$option_rule_property}[$this->post_type])) {
            $rule = $options->{$option_rule_property}[$this->post_type];
        }

        return $rule;
    }

    /**
     * Returns true if the requirement is enabled in the settings.
     *
     * @return boolean
     */
    public function is_enabled()
    {
        $rule = $this->get_option_rule();

        return in_array(
            $rule,
            [
                Plugin::RULE_ONLY_DISPLAY,
                Plugin::RULE_WARNING,
                Plugin::RULE_BLOCK,
            ]
        );
    }

}
