<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Core\Utils;

\defined('ABSPATH') || exit;

class ElementorUtils
{
    /**
     * Check if Elementor is enabled for the current post
     *
     * @return bool
     */
    public static function isElementorEnabled()
    {
        if (!function_exists('get_post_meta') || !function_exists('get_the_ID')) {
            return false;
        }

        return get_post_meta(get_the_ID(), '_elementor_edit_mode', true) === 'builder';
    }
}
