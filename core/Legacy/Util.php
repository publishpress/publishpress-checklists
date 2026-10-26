<?php

/**
 * @package     PublishPress\Checklistss
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.1.0
 */

namespace PublishPress\Checklists\Core\Legacy;

\defined('ABSPATH') || exit;

class Util
{
    /**
     * Checks for the current post type
     *
     * @return string|null $post_type The post type we've found, or null if no post type
     */
    public static function getCurrentPostType()
    {
        global $post, $typenow, $pagenow, $current_screen;

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only: the post and post_type query args only tell which post type the current screen is for. Nothing is saved.
        // get_post() needs a variable
        $post_id = isset($_REQUEST['post']) ? (int)$_REQUEST['post'] : false;

        if ($post && $post->post_type) {
            $post_type = $post->post_type;
        } elseif ($typenow) {
            $post_type = $typenow;
        } elseif ($current_screen && !empty($current_screen->post_type)) {
            $post_type = $current_screen->post_type;
        } elseif (isset($_REQUEST['post_type'])) {
            $post_type = sanitize_key($_REQUEST['post_type']);
        } elseif (
            'post.php' == $pagenow
            && $post_id
            && !empty(get_post($post_id)->post_type)
        ) {
            $post_type = get_post($post_id)->post_type;
        } elseif ('edit.php' == $pagenow && empty($_REQUEST['post_type'])) {
            $post_type = 'post';
        } else {
            $post_type = null;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        return $post_type;
    }

    /**
     * Collect all of the active post types for a given module
     *
     * @param object $module Module's data
     *
     * @return array $post_types All of the post types that are 'on'
     */
    public static function getPostTypesForModule($module)
    {
        $selectedPostTypes = [];

        if (isset($module->options->post_types) && is_array($module->options->post_types)) {
            foreach ($module->options->post_types as $post_type => $value) {
                if ('on' == $value) {
                    $selectedPostTypes[] = $post_type;
                }
            }
        }

        return $selectedPostTypes;
    }

    /**
     * Sanitizes the module name, making sure we always have only
     * valid chars, replacing - with _.
     *
     * @param string $name
     *
     * @return string
     */
    public static function sanitizeModuleName($name)
    {
        return str_replace('-', '_', $name);
    }

    /**
     * Adds an array of capabilities to a role.
     *
     * @param string $role A standard WP user role like 'administrator' or 'author'
     * @param array $caps One or more user caps to add
     * @since 1.9.8
     *
     */
    public static function addCapsToRole($role, $caps)
    {
        // In some contexts, we don't want to add caps to roles
        if (apply_filters('publishpress_checklists_kill_add_caps_to_role', false, $role, $caps)) {
            return;
        }

        global $wp_roles;

        if ($wp_roles->is_role($role)) {
            $role = get_role($role);

            foreach ($caps as $cap) {
                $role->add_cap($cap);
            }
        }
    }

    /**
     * @return bool
     */
    public static function isGutenbergEnabled()
    {
        $isEnabled = defined('GUTENBERG_VERSION');

        // Is WordPress 5?
        if (!$isEnabled) {
            $wpVersion = get_bloginfo('version');

            $isEnabled = version_compare($wpVersion, '5.0', '>=');
        }

        return $isEnabled;
    }

    /**
     * This plugin can be added as dependency in the vendor folder, so the URL needs to be adapted, specially for assets.
     */
    public static function pluginDirUrl()
    {
        $directorySeparator = self::getDirectorySeparator();

        if (substr_count(PPCH_PATH_BASE, 'vendor' . $directorySeparator . 'publishpress') > 0) {
            $relativePathIndex = strpos(PPCH_PATH_BASE, $directorySeparator . 'plugins' . $directorySeparator);
            $relativePath      = substr(PPCH_PATH_BASE, $relativePathIndex + 9);
            $relativePath      = str_replace('\\', '/', $relativePath);

            return plugins_url() . '/' . $relativePath;
        }

        return plugin_dir_url(PPCH_FILE);
    }

    private static function getDirectorySeparator()
    {
        if (defined('DIRECTORY_SEPARATOR')) {
            $directorySeparator = DIRECTORY_SEPARATOR;
        } else {
            $isWindows = false;
            if (defined('PHP_OS')) {
                $winOSValues = [
                    'Windows',
                    'WINNT',
                    'WIN32'
                ];

                $isWindows = in_array(PHP_OS, $winOSValues);
            }

            $directorySeparator = $isWindows ? '\\' : '/';
        }

        return $directorySeparator;
    }

    /**
     * Load Pro Banner Right Sidebar
     */
    public static function ppch_pro_sidebar()
    {
        ?>
        <div class="ppch-advertisement-right-sidebar">
            
            <div class="advertisement-box-content postbox ppch-advert">
                <div class="postbox-header ppch-advert">
                    <h3 class="advertisement-box-header hndle is-non-sortable">
                        <span><?php echo esc_html__('Need PublishPress Checklists Support?', 'publishpress-checklists'); ?></span>
                    </h3>
                </div>

                <div class="inside ppch-advert">
                    <p>
                        <?php echo esc_html__('If you need help or have a new feature request, let us know.', 'publishpress-checklists'); ?>
                        <a
                            class="advert-link" href="https://wordpress.org/plugins/publishpress-checklists/" target="_blank">
                            <?php echo esc_html__('Request Support', 'publishpress-checklists'); ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24" width="24" height="24" class="linkIcon">
                                <path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path>
                            </svg>
                        </a>
                    </p>
                    <p>
                        <?php echo esc_html__('Detailed documentation is also available on the plugin website.', 'publishpress-checklists'); ?>
                        <a
                            class="advert-link" href="https://publishpress.com/docs-category/checklists/" target="_blank">
                            <?php echo esc_html__('View Knowledge Base', 'publishpress-checklists'); ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24" width="24" height="24" class="linkIcon">
                                <path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path>
                            </svg>
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Check if Checklists Pro active
     */
    public static function isChecklistsProActive()
    {
        if (defined('PPCHPRO_VERSION')) {
            return true;
        }
        return false;
    }

    /**
     * Check if a plugin is active.
     *
     * WordPress before 6.8 loads is_plugin_active() only for wp-admin, after the
     * init hook, so load it here when it is not available yet.
     *
     * @param string $plugin Path to the plugin file relative to the plugins directory.
     *
     * @return bool
     */
    public static function isPluginActive($plugin)
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active($plugin);
    }

    /**
     * Check if Rank Math plugin is activated
     * 
     * @return bool
     */
    public static function isRankMathActivated()
    {
        return class_exists('RankMath') || self::isPluginActive('seo-by-rank-math/rank-math.php');
    }

    /**
     * Check if All in One SEO plugin is activated
     * 
     * @return bool
     */
    public static function isAllInOneSeoActivated()
    {
        return class_exists('AIOSEO\\Plugin\\AIOSEO') || self::isPluginActive('all-in-one-seo-pack/all-in-one-seo-pack.php');
    }

    /**
     * Check if WooCommerce plugin is activated
     */
    public static function isWooCommerceActivated()
    {
        return class_exists('WooCommerce') || self::isPluginActive('woocommerce/woocommerce.php');
    }

    /**
     * Check if Yoast SEO plugin is activated
     */
    public static function isYoastSeoActivated()
    {
        return class_exists('WPSEO_Options') || self::isPluginActive('wordpress-seo/wp-seo.php') || self::isPluginActive(
                'wordpress-seo-premium/wp-seo-premium.php'
            );
    }

    /**
     * Check if ACF plugin is activated
     */
    public static function isACFActivated()
    {
        return class_exists('ACF') || self::isPluginActive('advanced-custom-fields/advanced-custom-fields.php');
    }


}
