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

class TemplateLoader
{
    /**
     * Load template for modules.
     *
     * @param       $moduleName
     * @param       $templateName
     * @param bool $requireOnce
     * @param array $context
     *
     * @param bool $return
     *
     * @return false|string
     */
    public function load($moduleName, $templateName, $context = [], $return = false, $requireOnce = true)
    {
        $context = apply_filters('publishpress_checklists_load_template_context', $context, $moduleName, $templateName);

        $templatePath = $this->locate($moduleName, $templateName);

        if (!empty($templatePath)) {
            if ($return) {
                ob_start();
            }

            if ($requireOnce) {
                require_once $templatePath;
            } else {
                require $templatePath;
            }

            if ($return) {
                return ob_get_clean();
            }
        } else {
            Factory::getErrorHandler()->add('PublishPress Checklists template path not found', $templatePath);
        }
    }

    /**
     * Locate template for modules.
     *
     * @param $moduleName
     * @param $templateName
     *
     * @return string
     */
    public function locate($moduleName, $templateName)
    {
        $located = '';

        $paths = [
            get_stylesheet_directory() . '/' . PPCH_RELATIVE_PATH . '/' . $moduleName,
            get_template_directory() . '/' . PPCH_RELATIVE_PATH . '/' . $moduleName,
            ABSPATH . WPINC . '/theme-compat/' . PPCH_RELATIVE_PATH . '/' . $moduleName,
            PPCH_MODULES_PATH . '/' . $moduleName . '/templates',
        ];

        $paths = apply_filters('publishpress_checklists_template_paths', $paths);

        foreach ($paths as $path) {
            if (file_exists($path . '/' . $templateName . '.php')) {
                $located = $path . '/' . $templateName . '.php';
                break;
            }
        }

        return $located;
    }
}
