<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.4.8
 */

namespace PublishPress\Checklists\Core;


\defined('ABSPATH') || exit;

class ErrorHandler
{
    public function add($errorMessage, $errorData = null)
    {
        if (!empty($errorData)) {
            if (!is_string($errorData)) {
                $encoded_data = wp_json_encode($errorData);
                $errorData    = false !== $encoded_data ? $encoded_data : '';
            }

            $errorMessage .= ': ' . $errorData;
        }

        error_log($errorMessage); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- This is the plugin's error handler; logging is its job.
    }
}
