<?php

use PublishPress\Checklists\Core\Factory;
use PublishPress\Checklists\Core\Legacy\LegacyPlugin;
use PublishPress\Checklists\Core\Legacy\Module;
use PublishPress\Checklists\Core\Legacy\Util;
use PublishPress\Checklists\Yoastseo\Requirement\Readability_Analysis;
use PublishPress\Checklists\Yoastseo\Requirement\Seo_Analysis;
use PublishPress\Checklists\Yoastseo\Requirement\Focus_Keyword;
use PublishPress\Checklists\Yoastseo\Requirement\Meta_Description;

defined('ABSPATH') || exit;

/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

/**
 * Class PPCH_Yoastseo
 *
 * @todo Refactor this module and all the modules system to use DI.
 */
#[\AllowDynamicProperties]
class PPCH_Yoastseo extends Module
{
    public const SETTINGS_SLUG = 'pp-yoastseo';

    public $module_name = 'yoastseo';

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
     * Construct the PPCH_Yoastseo class
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
            'title'             => __('Yoast SEO', 'publishpress-checklists'),
            'short_description' => __('Define tasks related to Yoast SEO', 'publishpress-checklists'),
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
        $args['default_options'] = apply_filters('ppch_yoastseo_default_options', $args['default_options']);

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
        if ($this->isYoastSEOActivated()) {
            add_filter(
                'publishpress_checklists_post_type_requirements',
                [
                    $this,
                    'filterPostTypeRequirements'
                ],
                10,
                2
            );
            add_action('publishpress_checklists_enqueue_scripts', [$this, 'enqueueScripts']);
        }
    }

    /**
     * Check if YoastSEO plugin is activated
     */
    private function isYoastSEOActivated()
    {
        return Util::isYoastSeoActivated();
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
        $requirements[] = Readability_Analysis::class;
        $requirements[] = Seo_Analysis::class;
        $requirements[] = Focus_Keyword::class;
        $requirements[] = Meta_Description::class;

        return $requirements;
    }

    /**
     * Enqueue scripts and stylesheets for the admin pages.
     */
    public function enqueueScripts()
    {
        wp_enqueue_script(
            'pp-checklists-yoastseo',
            plugins_url('/modules/yoastseo/assets/js/meta-box.js', $this->pluginFile),
            ['jquery', 'pp-checklists-requirements'],
            $this->pluginVersion,
            true
        );
    }
}
