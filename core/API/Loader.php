<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       2.18.0
 */

namespace PublishPress\Checklists\Core\API;

\defined('ABSPATH') || exit;

/**
 * Class to load the API functions
 */
class Loader
{
    /**
     * Initialize the API
     */
    public static function init()
    {
        // Load the API functions
        require_once dirname(__FILE__) . '/Functions.php';
        
        // Add a hook to load the API when WordPress is loaded
        add_action('init', [__CLASS__, 'load_api']);
    }
    
    /**
     * Load the API
     */
    public static function load_api()
    {
        // This hook can be used by other plugins to extend the API
        do_action('publishpress_checklists_api_loaded');
    }
}
