<?php

/**
 * Native module persistence, live permissions and verified revision snapshots.
 *
 * @package PublishPress\Checklists
 * @author PublishPress
 * @copyright Copyright (c) 2026, PublishPress
 * @license GPLv2 or later
 */

declare(strict_types=1);

namespace PublishPress\Checklists\Core\Mcp;

use PublishPress\Checklists\Core\Factory;

\defined('ABSPATH') || exit;

class Options
{
    /** @since 2.31.0 */
    public static function canManage(): bool
    {
        return current_user_can(apply_filters('publishpress_checklists_manage_checklist_cap', 'manage_checklists'));
    }

    /** @since 2.31.0 */
    public static function canSettings(): bool
    {
        return current_user_can('manage_options');
    }

    /** @since 2.31.0 */
    public static function pro(): bool
    {
        return defined('PPCHPRO_LOADED') && PPCHPRO_LOADED && class_exists('PublishPress\\ChecklistsPro\\PluginInitializer');
    }

    /** @since 2.31.0 */
    public static function legacy()
    {
        if (!function_exists('get_editable_roles')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        $legacy = Factory::getLegacyPlugin();
        if (!isset($legacy->checklists)) {
            $legacy->init();
        }
        return $legacy;
    }

    /** @since 2.31.0 */
    public static function key(string $module): string
    {
        return self::legacy()->modules->$module->options_group_name;
    }

    /** @since 2.31.0 */
    public static function read(string $module): array
    {
        $native = self::legacy()->modules->$module;
        $stored = get_option($native->options_group_name, []);
        return array_merge((array) $native->default_options, (array) $stored);
    }

    /** @since 2.31.0 */
    public static function save(string $module, array $values)
    {
        $key = self::key($module);
        $sentinel = new \stdClass();
        $original = get_option($key, $sentinel);
        $legacy = self::legacy();
        $failed = false;
        try {
            $legacy->update_all_module_options($module, (object) $values);
        } catch (\Throwable $error) {
            $failed = true;
        }
        if ($failed || self::fingerprintStored($key) !== Registry::fingerprint((object) $values)
            || array_keys((array) get_option($key, [])) !== array_keys($values)) {
            try {
                if ($original === $sentinel) {
                    delete_option($key);
                } else {
                    update_option($key, $original);
                }
            } catch (\Throwable $error) {
                // Verify actual persistence even if a downstream update hook failed.
            }
            $legacy->modules->$module->options = (object) self::read($module);
            $legacy->$module->module = $legacy->modules->$module;
            $restored = $original === $sentinel ? get_option($key, $sentinel) === $sentinel
                : Registry::fingerprint(get_option($key)) === Registry::fingerprint($original);
            return Registry::error($restored
                ? __('The settings could not be saved; original values were restored.', 'publishpress-checklists')
                : __('Saving failed and recovery was incomplete. Read settings before retrying.', 'publishpress-checklists'), 500);
        }
        return true;
    }

    /** @since 2.31.0 */
    private static function fingerprintStored(string $key): string
    {
        return Registry::fingerprint((object) get_option($key, []));
    }
}
