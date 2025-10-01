<?php

use PublishPress\Checklists\Core\Factory;
use PublishPress\Checklists\Core\Legacy\LegacyPlugin;
use PublishPress\Checklists\Core\Legacy\Module;

/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

/**
 * Class class PPCH_Permissions extends Module
 *
 * @todo Refactor this module and all the modules system to use DI.
 */
class PPCH_Permissions extends Module
{
    public $module_name = 'permissions';

    /**
     * Instance for the module
     *
     * @var stdClass
     */
    public $module;

    /**
     * @var LegacyPlugin
     */
    private $legacyPlugin;

    /**
     * @var string
     */
    private $pluginFile;

    /**
     * @var string
     */
    private $pluginVersion;

    /**
     * @var string
     */
    private $module_url;

    /**
     * Construct the PPCH_WooCommerce class
     *
     * @todo: Fix to inject the dependencies in the constructor as params.
     */
    public function __construct()
    {
        $container = Factory::getContainer();

        $this->legacyPlugin  = Factory::getLegacyPlugin();
        $this->pluginFile    = PPCH_FILE;
        $this->pluginVersion = PPCH_VERSION;

        $this->module_url = $this->getModuleUrl(__FILE__);

        // Register the module with PublishPress
        $args = [
            'title'           => __('Permissions', 'publishpress-checklists'),
            'module_url'      => $this->module_url,
            'icon_class'      => 'dashicons dashicons-feedback',
            'slug'            => 'permissions',
            'default_options' => [
                'enabled' => 'on',
            ],
            'options_page'    => false,
            'autoload'        => true,
        ];

        // Apply a filter to the default options
        $args['default_options'] = apply_filters('ppch_permissions_default_options', $args['default_options']);

        $this->module = $this->legacyPlugin->register_module($this->module_name, $args);
    }

    /**
     * Initialize the module. Conditionally loads if the module is enabled
     */
    public function init()
    {
        if (isset($this->legacyPlugin->modules->settings->options->who_can_ignore_option)
        && $this->legacyPlugin->modules->settings->options->who_can_ignore_option === 'yes') {
            $this->setHooks();
        }
    }

    private function setHooks()
    {
        add_action('publishpress_checklists_tasks_list_th', [$this, 'actionTasksListTh'], 10);
        add_action('publishpress_checklists_tasks_list_td', [$this, 'actionTasksListTd'], 10, 2);
        add_filter('publishpress_checklists_ignore_item_capability', [$this, 'filterIgnoreItemCapability'], 10, 3);
        add_filter('publishpress_checklists_requirement_list', [$this, 'filterRequirementsList'], 30, 3);
        add_filter('publishpress_checklists_validate_requirement_settings', [$this, 'validateRequirementSettings']);
    }

    public function actionTasksListTh($postType)
    {
        echo '<th>' . esc_html__('Who can ignore the task?', 'publishpress-checklists') . '</th>';
    }

    public function actionTasksListTd($requirement, $postType)
    {
        $propName = $requirement->name . '_can_ignore';
        $selected = [];

        if (isset($this->legacyPlugin->modules->checklists->options->{$propName}) && !empty($this->legacyPlugin->modules->checklists->options->{$propName})) {
            $option = $this->legacyPlugin->modules->checklists->options->{$propName};

            if (isset($option[$postType])) {
                $selected = $option[$postType];
            }
        }

        $userRoles = get_editable_roles();

        $rolesSelect = '<select class="pp-checklists-can-ignore" name="'. esc_attr('publishpress_checklists_checklists_options[' . $requirement->name . '_can_ignore][' . $postType . '][]') .'" multiple="multiple" class="user-roles-list">';
        $rolesSelect .= '<option value=""></option>';
        foreach ($userRoles as $slug => $role) {
            $rolesSelect .= '<option value="' . esc_attr($slug) . '" ' . selected(
                    true,
                    in_array($slug, $selected),
                    false
                ) . '>' . esc_html_x($role['name'], 'User role') . '</option>';
        }
        $rolesSelect .= '</select>';
        
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<td>' . $rolesSelect . '</td>';
    }

    public function filterIgnoreItemCapability($capability, $requirementName, $postType)
    {
        return sprintf('ppch_ignore_item_%s_%s', $postType, $requirementName);
    }

    public function validateRequirementSettings($options)
    {
        // Make sure to remove the options that were cleaned up
        foreach ($options as $key => $value) {
            if (preg_match('/_can_ignore$/', $key)) {
                if (!isset($_POST['publishpress_checklists_checklists_options'][$key])) {
                    unset($options[$key]);
                }
            }
        }

        return $options;
    }

    public function filterRequirementsList($requirements, $post)
    {
        $options = get_option('publishpress_checklists_checklists_options');
        $user    = wp_get_current_user();

        foreach ($requirements as $requirement => $requirementData) {
            $canIgnoreOptionName = $requirement . '_can_ignore';
            if (isset($options->{$canIgnoreOptionName})) {
                $option = $options->{$canIgnoreOptionName};

                if (isset($option[$post->post_type])) {
                    $roles = $option[$post->post_type];

                    $mergedRoles = array_intersect($user->roles, $roles);

                    if (!empty($mergedRoles)) {
                        unset($requirements[$requirement]);
                    }
                }
            }
        }

        return $requirements;
    }
}
