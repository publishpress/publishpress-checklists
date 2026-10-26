<?php
/**
 * @package     PublishPress\Checklistss
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.1.0
 */

namespace PublishPress\Checklists\Core\Legacy;

use stdClass;

\defined('ABSPATH') || exit;

/**
 * Legacy plugin class, porting the PublishPress dependencies.
 */
#[\AllowDynamicProperties]
class LegacyPlugin
{
    /**
     * @var stdClass
     */
    public  $modules;
    public  $defaultMenuSlug = 'ppch-checklists';
    private $optionsGroup    = 'publishpress_checklists_';
    private $addedMenuPage   = false;

    public function __construct()
    {
        $this->modules = new stdClass();

        $this->setupActions();
    }

    /**
     * Setup the default hooks and actions
     *
     * @since  PublishPress 0.7.4
     * @access private
     * @uses   add_action() To add various actions
     */
    private function setupActions()
    {
        add_action('init', [$this, 'init'], 1000);
        add_action('init', [$this, 'initAfter'], 1100);
        add_action('init', [$this, 'adminInit'], 1010);
        add_action('admin_menu', [$this, 'addAdminMenu'], 9);

        // Fix the order of the submenus
        add_filter('custom_menu_order', [$this, 'setCustomMenuOrder']);

        do_action_ref_array('publishpress_checklists_after_setup_actions', [$this]);

        add_filter('debug_information', [$this, 'filterDebugInformation']);
    }

    /**
     * Initializes the legacy plugin instance!
     * Loads options for each registered module and then initializes it if it's active
     */
    public function init()
    {
        $this->loadModules();

        // Load all of the module options
        $this->loadModulesOptions();

        // Init all of the modules that are enabled.
        // Modules won't have an options value if they aren't enabled
        foreach ($this->modules as $moduleName => $moduleData) {
            if (isset($moduleData->options->enabled) && $moduleData->options->enabled == 'on') {
                $this->$moduleName->init();
            }
        }

        do_action('publishpress_checklists_init');
    }

    /**
     * Include the common resources to PublishPress and dynamically load the modules
     */
    private function loadModules()
    {
        // We use the WP_List_Table API for some of the table gen
        if (!class_exists('WP_List_Table')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
        }

        $module_dirs = $this->getModulesBasePath();

        $class_names = [];

        foreach ($module_dirs as $module_slug => $base_path) {
            if (file_exists("{$base_path}/{$module_slug}/{$module_slug}.php")) {
                include_once "{$base_path}/{$module_slug}/{$module_slug}.php";

                // Prepare the class name because it should be standardized
                $tmp        = explode('-', $module_slug);
                $class_name = '';
                $slug_name  = '';

                foreach ($tmp as $word) {
                    $class_name .= ucfirst($word) . '_';
                    $slug_name  .= $word . '_';
                }

                $slug_name               = rtrim($slug_name, '_');
                $class_names[$slug_name] = 'PPCH_' . rtrim($class_name, '_');
            }
        }

        // Instantiate all of our classes onto the PublishPress object
        // but make sure they exist too
        foreach ($class_names as $slug => $class_name) {
            if (class_exists($class_name)) {
                $slug            = Util::sanitizeModuleName($slug);
                $module_instance = new $class_name();

                $this->$slug = $module_instance;

                // If there's a Help Screen registered for the module, make sure we auto-load it
                $args = null;
                if (isset($this->modules->$slug)) {
                    $args = $this->modules->$slug;
                }

                if (!is_null($args) && !empty($args->settingsHelpTab)) {
                    add_action(
                        'load-checklists_page_' . $args->settings_slug,
                        [$module_instance, 'action_settings_help_menu']
                    );
                }

                $this->loadedModules[] = $slug;
            }
        }

        $this->helpers = new Module();

        $this->class_names = $class_names;

        // Supplementary plugins can hook into this, include their own modules
        // and add them to the plugin instance
        do_action('publishpress_checklists_modules_loaded');
    }

    /**
     * @return array
     */
    private function getModulesBasePath()
    {
        $defaultDirs = [
            'settings'    => PPCH_MODULES_PATH,
            'checklists'  => PPCH_MODULES_PATH,
            'permalinks'  => PPCH_MODULES_PATH,
            'yoastseo'    => PPCH_MODULES_PATH,
            'permissions' => PPCH_MODULES_PATH,
            'reviews'     => PPCH_MODULES_PATH,
        ];

        return apply_filters('publishpress_checklists_module_dirs', $defaultDirs);
    }

    /**
     * Load all of the module options from the database
     * If a given option isn't yet set, then set it to the module's default (upgrades, etc.)
     */
    public function loadModulesOptions()
    {
        foreach ($this->modules as $moduleName => $moduleData) {
            $this->modules->$moduleName->options = get_option(
                $this->optionsGroup . $moduleName . '_options',
                new stdClass()
            );
            foreach ($moduleData->default_options as $default_key => $default_value) {
                if (!isset($this->modules->$moduleName->options->$default_key)) {
                    $this->modules->$moduleName->options->$default_key = $default_value;
                }
            }
            $this->$moduleName->module = $this->modules->$moduleName;
        }

        do_action('publishpress_checklists_module_options_loaded');
    }

    /**
     * Register a new module.
     */
    public function register_module($name, $args = [])
    {
        // A title and name is required for every module
        if (!isset($args['title'], $name)) {
            return false;
        }

        $defaults = [
            'title'                => '',
            'short_description'    => '',
            'extended_description' => '',
            'icon_class'           => 'dashicons dashicons-admin-generic',
            'slug'                 => '',
            'post_type_support'    => '',
            'default_options'      => [],
            'options'              => false,
            'configure_page_cb'    => false,
            // These messages are applied to modules and can be overridden if custom messages are needed
            'messages'             => [
                'form-error'          => __(
                    'Please correct your form errors below and try again.',
                    'publishpress-checklists'
                ),
                'nonce-failed'        => __('Cheatin&#8217; uh?', 'publishpress-checklists'),
                'invalid-permissions' => __(
                    'You do not have necessary permissions to complete this action.',
                    'publishpress-checklists'
                ),
                'missing-post'        => __('Post does not exist', 'publishpress-checklists'),
            ],
            'autoload'             => false, // autoloading a module will remove the ability to enable or disable it
        ];
        if (isset($args['messages'])) {
            $args['messages'] = array_merge((array)$args['messages'], $defaults['messages']);
        }
        $args                       = array_merge($defaults, $args);
        $args['name']               = $name;
        $args['options_group_name'] = $this->optionsGroup . $name . '_options';

        if (!isset($args['settings_slug'])) {
            $args['settings_slug'] = 'ppch-' . $args['slug'] . '-settings';
        }

        if (empty($args['post_type_support'])) {
            $args['post_type_support'] = 'ppch_' . $name;
        }

        $this->modules->$name = (object)$args;
        do_action('publishpress_checklists_module_registered', $name);

        return $this->modules->$name;
    }

    /**
     * Initialize the plugin for the admin
     */
    public function adminInit()
    {
        $versionOption = $this->optionsGroup . 'version';

        // Upgrade if need be but don't run the upgrade if the plugin has never been used
        $previous_version = get_option($versionOption);
        if ($previous_version && version_compare($previous_version, PPCH_VERSION, '<')) {
            foreach ($this->modules as $moduleName => $moduleData) {
                if (method_exists($this->$moduleName, 'upgrade')) {
                    $this->$moduleName->upgrade($previous_version);
                }
            }
        }

        update_option($versionOption, PPCH_VERSION);

        // For each module that's been loaded, auto-load data if it's never been run before
        foreach ($this->modules as $moduleName => $moduleData) {
            // If the module has never been loaded before, run the install method if there is one
            if (!isset($moduleData->options->loaded_once) || !$moduleData->options->loaded_once) {
                if (method_exists($this->$moduleName, 'install')) {
                    $this->$moduleName->install();
                }
                $this->update_module_option($moduleName, 'loaded_once', true);
            }
        }
    }

    /**
     * Update the $legacyPlugin object with new value and save to the database
     */
    public function update_module_option($moduleName, $key, $value)
    {
        if (false === $this->modules->$moduleName->options) {
            $this->modules->$moduleName->options = new stdClass();
        }

        $this->modules->$moduleName->options->$key = $value;
        $this->$moduleName->module                 = $this->modules->$moduleName;

        return update_option($this->optionsGroup . $moduleName . '_options', $this->modules->$moduleName->options);
    }

    public function update_all_module_options($moduleName, $new_options)
    {
        if (is_array($new_options)) {
            $new_options = (object)$new_options;
        }

        $this->modules->$moduleName->options = $new_options;
        $this->$moduleName->module           = $this->modules->$moduleName;

        return update_option($this->optionsGroup . $moduleName . '_options', $this->modules->$moduleName->options);
    }

    /**
     * Add the menu page and call an action for modules add submenus
     */
    public function addAdminMenu()
    {
        /**
         * Filters the menu slug. By default, each filter should only set a menu slug if it is empty.
         * To determine the precedence of menus, use different priorities among the filters.
         *
         * @param string $menuSlug
         */
        $menuSlug = $this->getMenuSlug();

        /**
         * Action for adding menu pages.
         */
        do_action('publishpress_checklists_admin_menu_page', $menuSlug);

        /**
         * Action for adding submenus.
         */
        do_action('publishpress_checklists_admin_submenu', $menuSlug);
    }

    public function getMenuSlug()
    {
        return apply_filters('publishpress_checklists_admin_menu_slug', $this->defaultMenuSlug);
    }

    /**
     * @param        $page_title
     * @param        $menu_title
     * @param        $capability
     * @param        $menuSlug
     * @param string $function
     * @param string $icon_url
     * @param null $position
     */
    public function addMenuPage($page_title, $capability, $menuSlug, $function = '')
    {
        if ($this->addedMenuPage) {
            return;
        }

        add_menu_page(
            $page_title,
            apply_filters(
                'publishpress_checklists_plugin_title',
                esc_html__('Checklists', 'publishpress-checklists')
            ),
            $capability,
            $menuSlug,
            $function,
            'dashicons-yes-alt',
            26
        );

        $this->addedMenuPage = true;
        $this->menu_slug     = $menuSlug;
    }

    /**
     * Based on Edit Flow's \Block_Editor_Compatible::should_apply_compat method.
     *
     * @return bool
     */
    public function isBlockEditorActive()
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . '/wp-admin/includes/plugin.php';
        }

        // Check if Revisionary lower than v1.3 is installed. It disables Gutenberg.
        if (is_plugin_active('revisionary/revisionary.php')
            && defined('RVY_VERSION')
            && version_compare(RVY_VERSION, '1.3', '<')) {
            return false;
        }

        $pluginsState = [
            'classic-editor' => is_plugin_active('classic-editor/classic-editor.php'),
            'gutenberg'      => is_plugin_active('gutenberg/gutenberg.php'),
            'gutenberg-ramp' => is_plugin_active('gutenberg-ramp/gutenberg-ramp.php'),
        ];


        if (function_exists('get_post_type')) {
            $postType = get_post_type();
        }

        if (!isset($postType) || empty($postType)) {
            $postType = 'post';
        }

        /**
         * If show_in_rest is not true for the post type, the block editor is not available.
         */
        $postTypeObject = get_post_type_object($postType);
        if ($postTypeObject) {
            if (empty($postTypeObject->show_in_rest)) {
                return false;
            }
        }

        $conditions = [];

        /**
         * 5.0:
         *
         * Classic editor either disabled or enabled (either via an option or with GET argument).
         * It's a hairy conditional :(
         */
        // phpcs:ignore WordPress.VIP.SuperGlobalInputUsage.AccessDetected, WordPress.Security.NonceVerification.NoNonceVerification
        $conditions[] = $this->isWp5()
            && !$pluginsState['classic-editor']
            && !$pluginsState['gutenberg-ramp']
            && apply_filters('use_block_editor_for_post_type', true, $postType, PHP_INT_MAX);

        $conditions[] = $this->isWp5()
            && $pluginsState['classic-editor']
            && (get_option('classic-editor-replace') === 'block'
                && !isset($_GET['classic-editor__forget'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: Classic Editor's own query arg, used only to tell which editor loads.

        $conditions[] = $this->isWp5()
            && $pluginsState['classic-editor']
            && (get_option('classic-editor-replace') === 'classic'
                && isset($_GET['classic-editor__forget'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: Classic Editor's own query arg, used only to tell which editor loads.

        /**
         * < 5.0 but Gutenberg plugin is active.
         */
        $conditions[] = !$this->isWp5() && ($pluginsState['gutenberg'] || $pluginsState['gutenberg-ramp']);

        // Returns true if at least one condition is true.
        return count(
                array_filter(
                    $conditions,
                    function ($c) {
                        return (bool)$c;
                    }
                )
            ) > 0;
    }

    /**
     * Returns true if is a beta or stable version of WP 5.
     *
     * @return bool
     */
    public function isWp5()
    {
        global $wp_version;

        return version_compare($wp_version, '5.0', '>=') || substr($wp_version, 0, 2) === '5.';
    }

    /**
     * @return mixed
     */
    public function isClassicEditorInstalled()
    {
        return Util::isPluginActive('classic-editor/classic-editor.php');
    }

    public function setCustomMenuOrder($menu_ord)
    {
        global $submenu;

        $menuSlug = $this->getMenuSlug();

        if (isset($submenu[$menuSlug])) {
            $submenu_pp  = $submenu[$menuSlug];
            $new_submenu = [];

            // Get the index for the menus, removing the first submenu which was automatically created by WP.
            $relevantMenus = [
                'edit-tags.php?taxonomy=author',
                'ppch-settings',
            ];

            foreach ($submenu_pp as $index => $item) {
                if (array_key_exists($item[2], $relevantMenus)) {
                    $relevantMenus[$item[2]] = $index;
                }
            }

            // Authors
            if (isset($relevantMenus['edit-tags.php?taxonomy=author'])) {
                $new_submenu[] = $submenu_pp[$relevantMenus['edit-tags.php?taxonomy=author']];

                unset($submenu_pp[$relevantMenus['edit-tags.php?taxonomy=author']]);
            }

            // Check if we have other menu items, except settings and add-ons. They will be added to the end.
            if (count($submenu_pp) > 1) {
                // Add the additional items
                foreach ($submenu_pp as $index => $item) {
                    if (!in_array($index, $relevantMenus)) {
                        $new_submenu[] = $item;
                        unset($submenu_pp[$index]);
                    }
                }
            }

            // Settings
            if (isset($relevantMenus['ppch-settings'])) {
                $new_submenu[] = $submenu_pp[$relevantMenus['ppch-settings']];

                unset($submenu_pp[$relevantMenus['ppch-settings']]);
            }

            $submenu[$menuSlug] = $new_submenu;
        }

        return $menu_ord;
    }

    /**
     * @param array $debugInfo
     *
     * @return array
     */
    public function filterDebugInformation($debugInfo)
    {
        $modules     = [];
        $modulesDirs = $this->getModulesBasePath();

        foreach ($this->loadedModules as $module) {
            $dashCaseModule = str_replace('_', '-', $module);

            $status = isset($this->{$module}) && isset($this->{$module}->module->options->enabled) ? $this->{$module}->module->options->enabled : 'on';

            $modules[$module] = [
                'label' => $module,
                'value' => $status . ' [' . $modulesDirs[$dashCaseModule] . '/modules/' . $module . ']',
            ];
        }

        $debugInfo['publishpress-modules'] = [
            'label'       => 'PublishPress Modules',
            'description' => '',
            'show_count'  => true,
            'fields'      => $modules,
        ];

        return $debugInfo;
    }

    /**
     * Load the post type options again so we give add_post_type_support() a chance to work
     *
     * @see https://publishpress.com/2011/11/17/publishpress-v0-7-alpha2-notes/#comment-232
     */
    public function initAfter()
    {
        foreach ($this->modules as $moduleName => $moduleData) {
            if (isset($this->modules->$moduleName->options->post_types)) {
                $this->modules->$moduleName->options->post_types = $this->helpers->clearPostTypesOptions(
                    $this->modules->$moduleName->options->post_types,
                    $moduleData->post_type_support
                );
            }

            $this->$moduleName->module = $this->modules->$moduleName;
        }
    }

    /**
     * Get a module by one of its descriptive values
     */
    public function getModuleBy($key, $value)
    {
        $module = false;
        foreach ($this->modules as $moduleName => $moduleData) {
            if ($key == 'name' && $value == $moduleName) {
                $module = $this->modules->$moduleName;
            } else {
                foreach ($moduleData as $mod_data_key => $mod_data_value) {
                    if ($mod_data_key == $key && $mod_data_value == $value) {
                        $module = $this->modules->$moduleName;
                    }
                }
            }
        }

        return $module;
    }
}
