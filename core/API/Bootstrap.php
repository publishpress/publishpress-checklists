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
 * Bootstrap class for the API
 */
class Bootstrap
{
    /**
     * Initialize the API
     */
    public static function init()
    {
        // Load the API functions
        Loader::init();
        
        // Initialize the label mapper
        LabelMapper::init();
    }
}
