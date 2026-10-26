<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.120.0
 */

use PublishPress\Checklists\Core\Factory;
use PublishPress\Checklists\Core\Legacy\Module;
use PublishPress\Checklists\Core\Legacy\Util;
use PublishPress\WordPressReviews\ReviewsController;

defined('ABSPATH') || exit;

/**
 * Class PPCH_Reviews
 *
 * This class adds a review request system for your plugin or theme to the WP dashboard.
 */
class PPCH_Reviews extends Module
{
    public $module_name = 'reviews';

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
    private $module_url;

    /**
     * @var ReviewsController
     */
    private $reviewController;

    /**
     * Construct the PPCH_Reviews class
     *
     * @todo: Fix to inject the dependencies in the constructor as params.
     */
    public function __construct()
    {
        $container = Factory::getContainer();

        $this->legacyPlugin = Factory::getLegacyPlugin();

        $this->module_url = $this->getModuleUrl(__FILE__);

        // Register the module with PublishPress
        $args = [
            'title' => __('Reviews', 'publishpress-checklists'),
            'module_url' => $this->module_url,
            'icon_class' => 'dashicons dashicons-feedback',
            'slug' => 'reviews',
            'default_options' => [
                'enabled' => 'on',
            ],
            'options_page' => false,
            'autoload' => true,
        ];

        // Apply a filter to the default options
        $args['default_options'] = apply_filters('ppch_reviews_default_options', $args['default_options']);

        $this->module = $this->legacyPlugin->register_module($this->module_name, $args);

        $this->reviewController = new ReviewsController(
            'publishpress-checklists',
            'PublishPress Checklists',
            Util::pluginDirUrl() . 'modules/checklists/assets/img/publishpress-checklists-logo.png'
        );
    }

    /**
     * Initialize the module. Conditionally loads if the module is enabled
     */
    public function init()
    {
        $this->reviewController->init();
    }
}
