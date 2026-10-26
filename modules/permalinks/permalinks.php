<?php

use PublishPress\Checklists\Core\Factory;
use PublishPress\Checklists\Core\Legacy\LegacyPlugin;
use PublishPress\Checklists\Core\Legacy\Module;
use PublishPress\Checklists\Permalinks\Requirement\ValidChars;

defined('ABSPATH') || exit;

/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

/**
 * Class PPCH_Permalinks
 *
 * @todo Refactor this module and all the modules system to use DI.
 */
#[\AllowDynamicProperties]
class PPCH_Permalinks extends Module
{
    public const SETTINGS_SLUG = 'pp-permalinks';

    public $module_name = 'permalinks';

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
     * Construct the PPCH_WooCommerce class
     *
     * @todo: Fix to inject the dependencies in the constructor as params.
     */
    public function __construct()
    {
        $this->legacyPlugin  = Factory::getLegacyPlugin();
        $this->pluginFile    = PPCH_FILE;
        $this->pluginVersion = PPCH_VERSION;

        $this->module_url = $this->getModuleUrl(__FILE__);

        // Register the module with PublishPress
        $args = [
            'title'             => __('Permalinks', 'publishpress-checklists'),
            'short_description' => __('Define tasks related to permalinks', 'publishpress-checklists'),
            'module_url'        => $this->module_url,
            'icon_class'        => 'dashicons dashicons-feedback',
            'slug'              => $this->module_name,
            'default_options'   => [
                'enabled' => 'on',
            ],
            'options_page'      => false,
            'autoload'          => true,
        ];

        // Apply a filter to the default options
        $args['default_options'] = apply_filters('ppch_permalinks_default_options', $args['default_options']);

        $this->module = $this->legacyPlugin->register_module($this->module_name, $args);

        add_action('publishpress_checklists_load_addons', [$this, 'actionLoadAddons']);
    }

    /**
     * Initialize the module. Conditionally loads if the module is enabled
     */
    public function init()
    {
    }

    /**
     * Action triggered before load requirements. We use this
     * to load the filters.
     */
    public function actionLoadAddons()
    {
        add_filter('publishpress_checklists_post_type_requirements', [$this, 'filterPostTypeRequirements'], 10, 2);
        add_action('publishpress_checklists_enqueue_scripts', [$this, 'enqueueScripts']);
    }

    /**
     * Load default editorial metadata the first time the module is loaded
     *
     * @since 0.7
     */
    public function install()
    {
    }

    /**
     * Upgrade our data in case we need to
     *
     * @since 0.7
     */
    public function upgrade($previous_version)
    {
    }

    /**
     * Set the requirements list for the given post type
     *
     * @param array $requirements
     * @param string $postType
     *
     * @return array
     */
    public function filterPostTypeRequirements($requirements, $postType)
    {
        $requirements[] = ValidChars::class;

        return $requirements;
    }

    /**
     * Enqueue scripts and stylesheets for the admin pages.
     */
    public function enqueueScripts()
    {
        global $current_screen;

        if ($current_screen->base === 'post') {
            wp_enqueue_script(
                'pp-checklists-permalinks',
                plugins_url('/modules/permalinks/assets/js/meta-box.js', $this->pluginFile),
                ['jquery', 'pp-checklists-requirements'],
                $this->pluginVersion,
                true
            );
        }
    }
}
