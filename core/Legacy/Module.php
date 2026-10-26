<?php

/**
 * @package     PublishPress\Checklistss
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.1.0
 */

namespace PublishPress\Checklists\Core\Legacy;

use PublishPress\Checklists\Core\Factory;

\defined('ABSPATH') || exit;

/**
 * Module
 */
class Module
{
    public    $options;
    protected $debug = false;

    /**
     * Returns whether the module with the given name is enabled.
     *
     * @param string module Slug of the module to check
     *
     * @return <code>true</code> if the module is enabled, <code>false</code> otherwise
     * @since  0.7
     *
     */
    public function moduleEnabled($slug)
    {
        $legacyPlugin = Factory::getLegacyPlugin();

        return isset($legacyPlugin->$slug) && $legacyPlugin->$slug->module->options->enabled == 'on';
    }

    /**
     * Cleans up the 'on' and 'off' for post types on a given module (so we don't get warnings all over)
     * For every post type that doesn't explicitly have the 'on' value, turn it 'off'
     * If add_post_type_support() has been used anywhere (legacy support), inherit the state
     *
     * @param array $modulePostTypes Current state of post type options for the module
     * @param string $postTypeSupport What the feature is called for post_type_support (e.g. 'ppma_calendar')
     *
     * @return array $normalizedPostTypeOptions The setting for each post type, normalized based on rules
     *
     * @since 0.7
     */
    public function clearPostTypesOptions($modulePostTypes = [], $postTypeSupport = null)
    {
        $normalizedPostTypeOptions = [];
        $allPostTypes              = array_keys($this->getAllPostTypes());
        foreach ($allPostTypes as $postType) {
            if ((isset($modulePostTypes[$postType]) && $modulePostTypes[$postType] == 'on') || post_type_supports(
                $postType,
                $postTypeSupport
            )) {
                $normalizedPostTypeOptions[$postType] = 'on';
            } else {
                $normalizedPostTypeOptions[$postType] = 'off';
            }
        }

        return $normalizedPostTypeOptions;
    }

    /**
     * Gets an array of allowed post types for a module
     *
     * @return array post-type-slug => post-type-label
     */
    public function getAllPostTypes()
    {
        $allowedPostTypes = [];
        $customPostTypes  = $this->getSupportedPostTypesForModule();

        if (!empty($customPostTypes)) {
            foreach ($customPostTypes as $customPostType => $args) {
                $allowedPostTypes[$customPostType] = $args->label;
            }
        }

        return $allowedPostTypes;
    }

    /**
     * Get all of the possible post types that can be used with a given module
     *
     * @param object $module The full module
     *
     * @return array $postTypes An array of post type objects
     *
     * @since 0.7.2
     */
    public function getSupportedPostTypesForModule($module = null)
    {
        $postTypeArgs = [
            'show_ui' => true,
        ];
        $postTypeArgs = apply_filters(
            'publishpress_checklists_supported_module_post_types_args',
            $postTypeArgs,
            $module
        );

        $postTypes = get_post_types($postTypeArgs, 'objects');

        // Remove ignored post types
        $validPostTypes = [];
        foreach ($postTypes as $slug => $postType) {
            // Ignore: Media, Block, Custom Field, Custom Layout, Notification Workflow, WooCommerce Orders, WooCommerce Coupons, Navigation Menu, ACF Taxonomy, ACF Post Type, ACF Field Group
            if (!in_array($slug, ['attachment', 'wp_block', 'ppmacf_field', 'ppmacf_layout', 'psppnotif_workflow', 'shop_order', 'shop_coupon', 'wp_navigation', 'acf-taxonomy', 'acf-post-type', 'acf-field-group'])) {
                $validPostTypes[$slug] = $postType;
            }
        }

        return apply_filters('publishpress_checklists_supported_module_post_types', $validPostTypes);
    }

    /**
     * Whether or not the current page is an PublishPress settings view (either main or module)
     * Determination is based on $pagenow, $_GET['page'], and the module's $settings_slug
     * If there's no module name specified, it will return true against all PublishPress settings views
     *
     * @param string $module_name (Optional) Module name to check against
     *
     * @return bool $is_settings_view Return true if it is
     * @since 0.7
     *
     */
    public function isWhitelistedSettingsView($module_name = null)
    {
        global $pagenow;

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only: compares the page and module query args to known slugs to tell which settings screen is open. Nothing is saved.
        // All of the settings views are based on admin.php and a $_GET['page'] parameter
        if ($pagenow != 'admin.php' || !isset($_GET['page'])) {
            return false;
        }

        if (isset($_GET['page']) && $_GET['page'] === 'ppch-settings') {
            if (empty($module_name)) {
                return true;
            }

            if (!isset($_GET['module']) || $_GET['module'] === 'ppch-settings') {
                if (in_array($module_name, ['editorial_comments', 'notifications', 'dashboard'])) {
                    return true;
                }
            }

            $slug = str_replace('_', '-', $module_name);
            if (isset($_GET['module']) && $_GET['module'] === 'ppch-' . $slug . '-settings') {
                return true;
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        return false;
    }

    /**
     * Get the publicly accessible URL for the module based on the filename
     *
     * @param string $filepath File path for the module
     *
     * @return string $module_url Publicly accessible URL for the module
     * @since 0.7
     *
     */
    public function getModuleUrl($file)
    {
        $module_url = plugins_url('/', $file);

        return trailingslashit($module_url);
    }

    /**
     * Add settings help menus to our module screens if the values exist
     * Auto-registered in PublishPress::register_module()
     *
     * @since 0.7
     */
    public function actionSettingsHelpMenu()
    {
        $screen = get_current_screen();

        if (!method_exists($screen, 'add_help_tab')) {
            return;
        }

        if ($screen->id != 'checklists_page_' . $this->module->settings_slug) {
            return;
        }

        // Make sure we have all of the required values for our tab
        if (isset($this->module->settingsHelpTab['id'], $this->module->settingsHelpTab['title'], $this->module->settingsHelpTab['content'])) {
            $screen->add_help_tab($this->module->settingsHelpTab);

            if (isset($this->module->settingsHelpSidebar)) {
                $screen->set_help_sidebar($this->module->settingsHelpSidebar);
            }
        }
    }

    /**
     *
     */
    public function printDefaultHeader($current_module, $custom_text = null)
    {
        $display_text = '';

        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Read-only: the message and error args only pick a notice from the module's fixed list, which is escaped on output. Nothing is saved.
        // If there's been a message, let's display it
        if (isset($_GET['message'])) {
            $message = sanitize_text_field(wp_unslash($_GET['message']));
        } elseif (isset($_REQUEST['message'])) {
            $message = sanitize_text_field(wp_unslash($_REQUEST['message']));
        } elseif (isset($_POST['message'])) {
            $message = sanitize_text_field(wp_unslash($_POST['message']));
        } else {
            $message = false;
        }

        if ($message && isset($current_module->messages[$message])) {
            $display_text .= '<div class="is-dismissible notice notice-info"><p>' . esc_html(
                $current_module->messages[$message]
            ) . '</p></div>';
        }

        // If there's been an error, let's display it
        if (isset($_GET['error'])) {
            $error = sanitize_text_field(wp_unslash($_GET['error']));
        } elseif (isset($_REQUEST['error'])) {
            $error = sanitize_text_field(wp_unslash($_REQUEST['error']));
        } elseif (isset($_POST['error'])) {
            $error = sanitize_text_field(wp_unslash($_POST['error']));
        } else {
            $error = false;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
        if ($error && isset($current_module->messages[$error])) {
            $display_text .= '<div class="is-dismissible notice notice-error"><p>' . esc_html(
                $current_module->messages[$error]
            ) . '</p></div>';
        }
?>

        <div class="publishpress-checklists-admin pressshack-admin-wrapper wrap">
            <header>
                <h1 class="wp-heading-inline"><?php echo esc_html($current_module->title); ?></h1>

                <?php echo !empty($display_text) ? esc_html($display_text) : ''; ?>
                <?php // We keep the H2 tag to keep notices tied to the header
                ?>
                <h2>

                    <?php if ($current_module->short_description && empty($custom_text)): ?>
                        <?php echo esc_html($current_module->short_description); ?>
                    <?php endif; ?>

                    <?php if (!empty($custom_text)) : ?>
                        <?php echo esc_html($custom_text); ?>
                    <?php endif; ?>
                </h2>

            </header>
    <?php
    }

    /**
     * Echo or returns the default footer
     *
     * @param object $current_module
     * @param bool $echo
     *
     * @return string
     */
    public function printDefaultFooter($current_module, $echo = true)
    {
        $templateLoader = Factory::getTemplateLoader();

        $templateLoader->load(
            'checklists',
            'footer',
            [
                'current_module' => $current_module,
                'plugin_name'    => __('PublishPress Checklists', 'publishpress-checklists'),
                'plugin_slug'    => 'publishpress-checklists',
                'plugin_url'     => Util::pluginDirUrl(),
                'rating_message' => __(
                    'If you like %s please leave us a %s rating. Thank you!',
                    'publishpress-checklists'
                ),
            ]
        );
    }

    /**
     * Collect all of the active post types for a given module
     *
     * @param object $module Module's data
     *
     * @return array $post_types All of the post types that are 'on'
     *
     * @since 0.7
     */
    public function getPostTypesForModule($module)
    {
        return Util::getPostTypesForModule($module);
    }

    /**
     * Returns a list of post types the checklist support.
     *
     * @return array
     */
    public function get_post_types()
    {
        if (empty($this->post_types)) {
            // Apply filters to the list of requirements
            $this->post_types = apply_filters('publishpress_checklists_post_types', []);
        }

        return $this->post_types;
    }
}
