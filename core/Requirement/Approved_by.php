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

class Approved_by extends Base_multiple implements Interface_required
{
    /**
     * The name of the requirement, in a slug format
     *
     * @var string
     */
    public $name = 'approved_by';

    /**
     * The name of the group, used for the tabs
     * 
     * @var string
     */
    public $group = 'approval';

    protected $field_name = 'roles';

    const POST_META_PREFIX = 'pp_checklist_custom_item';

    /**
     * @var int
     */
    public $position = 170;

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language()
    {
        $this->lang['label']          = __('Approved by %s', 'publishpress-checklists');
        $this->lang['label_settings'] = __('Approved by a user in this role', 'publishpress-checklists');
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
        return self::VALUE_YES === get_post_meta($post_id, static::POST_META_PREFIX . '_' . $this->name, true);
    }

    /**
     * Gets settings drop down labels.
     *
     * @return array.
     */
    public function get_setting_drop_down_labels()
    {
        return PPCH_Checklists::get_editable_roles_labels();
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

        // Get the user role names.
        $user_role_names = [];
        foreach ($option_value as $user_role) {
            $role = get_role($user_role);

            if (!empty($role) && !is_wp_error($role)) {
                $user_role_names[] = ucfirst($role->name);
            }
        }
        $user_role_names = implode(', ', $user_role_names);

        // Register in the requirements list.
        $requirements[$this->name]['label'] = $this->get_approval_label_for_display($user_role_names);
        //set custom to false if user role is not permitted to prevent any validation
        $requirements[$this->name]['is_custom'] = $this->isUserRolePermitted();
        $requirements[$this->name]['id'] = 'approved_by';

        return $requirements;
    }

    /**
     * Returns the rendered label for editing screens, preserving selected roles.
     *
     * @param string $user_role_names
     *
     * @return string
     */
    protected function get_approval_label_for_display($user_role_names)
    {
        $default_label = sprintf($this->lang['label'], $user_role_names);
        $editor_label = trim($this->get_editor_label());

        if ('' === $editor_label) {
            return $default_label;
        }

        if ('' === $user_role_names) {
            return $editor_label;
        }

        if (false !== strpos($editor_label, '%roles%')) {
            return str_replace('%roles%', $user_role_names, $editor_label);
        }

        if (preg_match('/%(?:\d+\$)?s/', $editor_label)) {
            return sprintf($editor_label, $user_role_names);
        }

        return sprintf(
            /* translators: 1: custom editor label, 2: selected role names. */
            __('%1$s - %2$s', 'publishpress-checklists'),
            $editor_label,
            $user_role_names
        );
    }

    /**
     * Check if user role is permitted to validate this task
     */
    private function isUserRolePermitted()
    {
        // Option name
        $option_name_multiple = $this->name . '_' . $this->field_name;

        if (!isset($this->module->options->{$option_name_multiple}[$this->post_type])) {
            return true;
        }

        //Saved value
        $option_value = isset($this->module->options->{$option_name_multiple}[$this->post_type]) ? $this->module->options->{$option_name_multiple}[$this->post_type] : array();

        if (array_intersect($option_value, wp_get_current_user()->roles)) {
            return true;
        }

        return false;
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
