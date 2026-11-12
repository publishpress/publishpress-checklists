<?php

/**
 * Register private Checklists abilities independently of Hub and admin screens.
 *
 * @package PublishPress\Checklists
 * @author PublishPress
 * @copyright Copyright (c) 2026, PublishPress
 * @license GPLv2 or later
 */

declare(strict_types=1);

namespace PublishPress\Checklists\Core\Mcp;

\defined('ABSPATH') || exit;

class Bootstrap
{
    private static bool $initialized = false;
    private static bool $registered = false;
    /** @since 2.31.0 */
    public static function init(): void
    {
        if (self::$initialized || !function_exists('wp_register_ability') || !function_exists('wp_has_ability_category')) {
            return;
        }
        self::$initialized = true;
        add_action('wp_abilities_api_categories_init', [self::class, 'category']);
        add_action('wp_abilities_api_init', [self::class, 'register']);
    }

    /** @since 2.31.0 */
    public static function category(): void
    {
        if (!wp_has_ability_category('publishpress')) {
            wp_register_ability_category('publishpress', ['label' => __('PublishPress', 'publishpress-checklists'),
                'description' => __('Actions provided by PublishPress plugins.', 'publishpress-checklists')]);
        }
    }

    /** @since 2.31.0 */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        Options::legacy();
        Catalog::register();
        Settings::register();
        Requirements::register();
        Tasks::register();
        do_action('publishpress_checklists_mcp_register');
    }
}
