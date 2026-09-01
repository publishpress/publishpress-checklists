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

class Custom_item extends Base_multiple implements Interface_required
{
    const VALUE_YES = 'yes';

    /**
     * The title.
     *
     * @var string
     */
    protected $title;

    /**
     * The name of the group, used for the tabs
     * 
     * @var string
     */
    public $group = 'custom';

    /**
     * The constructor. It adds the action to load the requirement.
     *
     * @param string $name
     * @param string $module
     * @param string $post_type
     *
     * @return  void
     */
    public function __construct($name, $module, $post_type)
    {
        $this->name      = trim((string)$name);
        $this->is_custom = true;
        $this->field_name = 'editable_by';
        $this->group     = 'custom';

        parent::__construct($module, $post_type);
    }

    /**
     * Initialize the language strings for the instance
     *
     * @return void
     */
    public function init_language()
    {
        $this->lang['label']          = __('Custom', 'publishpress-checklists');
        $this->lang['label_settings'] = __('Custom', 'publishpress-checklists');
        $this->lang['label_option_description'] = __('Which roles can mark this task as complete?', 'publishpress-checklists');
    }

    /**
     * Get the HTML for the title setting field for the specific post type.
     *
     * @return string
     */
    public function get_setting_title_html($css_class = '')
    {
        $var_name = $this->name . '_title';

        $name = 'publishpress_checklists_checklists_options[' . $var_name . '][' . $this->post_type . ']';
        $id = $this->post_type . '-checklists-' . $this->name . '_title';

        $html = sprintf(
            '<label class="screen-reader-text" for="%s">%s</label><input type="text" id="%s" name="%s" value="%s" data-id="%s" placeholder="%s" class="pp-checklists-custom-item-title" />',
            esc_attr($id),
            esc_html__('Custom task title', 'publishpress-checklists'),
            esc_attr($id),
            esc_attr($name),
            esc_attr($this->get_title()),
            esc_attr($this->name),
            esc_html__('Enter name of custom task', 'publishpress-checklists')
        );

        $html .= sprintf(
            '<input type="hidden" name="publishpress_checklists_checklists_options[custom_items][]" value="%s" />',
            esc_attr($this->name)
        );

        return $html;
    }

    /**
     * Returns the title of this custom item.
     *
     * @return string
     */
    public function get_title()
    {
        if (!empty($this->title)) {
            return $this->title;
        }

        $title    = '';
        $var_name = $this->name . '_title';

        if (isset($this->module->options->{$var_name}[$this->post_type])) {
            $title = stripslashes($this->module->options->{$var_name}[$this->post_type]);
        }

        $this->title = $title;

        // echo '<pre>'; echo $var_name; print_r($this->module->options); die;

        return $this->title;
    }

    /**
     * Get the HTML for the setting field for the specific post type.
     *
     * @return string
     */
    public function get_setting_field_html($css_class = '')
    {
        $html = parent::get_setting_field_html(esc_attr($css_class));

        $html .= sprintf(
            '<button type="button" class="pp-checklists-remove-custom-item" data-id="%1$s" data-type="custom" aria-label="%3$s" title="%2$s"><span class="dashicons dashicons-no" aria-hidden="true"></span></button>',
            esc_attr($this->name),
            __('Remove', 'publishpress-checklists'),
            __('Remove custom task', 'publishpress-checklists')
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

        //set custom to false if user role is not permitted to prevent any validation
        $is_custom = $this->isUserRolePermitted();

        // Register in the requirements list
        if ($enabled) {
            $requirements[$this->name] = [
                'status'    => $this->get_current_status($post, $enabled),
                'label'     => $this->get_title(),
                'value'     => $enabled,
                'rule'      => $rule,
                'id'        => $this->name,
                'is_custom' => $is_custom,
                'type'      => $this->type,
            ];
        }

        return $requirements;
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
        
        return self::VALUE_YES === get_post_meta($post->ID, PPCH_Checklists::POST_META_PREFIX . $this->name, true);
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
        // Make sure to remove the options that were cleaned up
        foreach ($new_options as $key => $value) {
            if (preg_match('/_' . $this->field_name . '/', $key)) {
                if (!isset($_POST['publishpress_checklists_checklists_options'][$key])) {
                    unset($new_options[$key]);
                }
            }
        }

        if (isset($new_options[$this->name . '_title'][$this->post_type])
            && empty($new_options[$this->name . '_title'][$this->post_type])) {
            // Look for empty title
            $index = array_search($this->name, $new_options['custom_items']);
            if (false !== $index) {
                unset(
                    $new_options[$this->name . '_editable_by'][$this->post_type],
                    $new_options[$this->name . '_title'][$this->post_type],
                    $new_options[$this->name . '_rule'][$this->post_type],
                    $new_options['custom_items'][$index]
                );
            }
        }

        // Check if we need to remove items
        if (isset($new_options['custom_items_remove'])
            && !empty($new_options['custom_items_remove'])) {
            foreach ($new_options['custom_items_remove'] as $id) {
                $var_name = $id . '_editable_by';
                unset($new_options[$var_name]);

                $var_name = $id . '_title';
                unset($new_options[$var_name]);

                $var_name = $id . '_rule';
                unset($new_options[$var_name]);

                unset($new_options[$id]);

                $index_remove = array_search($id, $new_options['custom_items']);
                if (false !== $index_remove) {
                    unset($new_options['custom_items'][$index_remove]);
                }
            }
        }

        unset($new_options['custom_items_remove']);

        return $new_options;
    }

    /**
     * Check if user role is permitted to validate this task
     */
    private function isUserRolePermitted()
    {
        // Option name
        $option_name_multiple = $this->name . '_editable_by';

        //Saved value
        $option_value = isset($this->module->options->{$option_name_multiple}[$this->post_type]) ? $this->module->options->{$option_name_multiple}[$this->post_type] : array();

        if (!isset($this->module->options->{$option_name_multiple}[$this->post_type])) {
            return true;
        }

        if (array_intersect($option_value, wp_get_current_user()->roles)) {
            return true;
        }

        return false;
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
