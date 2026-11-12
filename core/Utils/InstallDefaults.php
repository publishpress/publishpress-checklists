<?php

/**
 * Keeps the previous defaults on sites that already used Checklists when a
 * default changes.
 *
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2026 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       2.31.0
 */

namespace PublishPress\Checklists\Core\Utils;

\defined('ABSPATH') || exit;

/**
 * Since 2.31.0 the "Exclude User Roles" (Free, who_can_ignore_option) and
 * "Exclude Statuses" (Pro, status_filter_enabled) columns of the Checklists
 * screen are off by default (#1254).
 *
 * Both settings are read with a fallback to their default when they were
 * never saved, so changing the default alone would turn them off on existing
 * sites. This runs once, before any module reads the settings: on a site that
 * already has Checklists data, it saves the previous default for each of
 * these settings that isn't saved yet. Values a site already saved are never
 * changed. A new install saves nothing and gets the new defaults.
 *
 * @since 2.31.0
 */
class InstallDefaults
{
    /**
     * Records that the check ran: 'existing' or 'new'.
     */
    public const FLAG_OPTION = 'publishpress_checklists_defaults_2_31_0';

    public const SETTINGS_OPTION = 'publishpress_checklists_settings_options';

    /**
     * The defaults before 2.31.0 of the settings whose default changed.
     */
    public const PREVIOUS_DEFAULTS = [
        'who_can_ignore_option' => 'yes',
        'status_filter_enabled' => 'on',
    ];

    /**
     * Options that only exist once Checklists has run on the site.
     */
    private const EXISTING_INSTALL_OPTIONS = [
        'publishpress_checklists_version',
        'publishpress_checklists_settings_options',
        'publishpress_checklists_checklists_options',
    ];

    /**
     * Runs the check once per site.
     *
     * @return void
     */
    public static function maybeMigrate()
    {
        if (false !== get_option(self::FLAG_OPTION)) {
            return;
        }

        $existing = false;
        foreach (self::EXISTING_INSTALL_OPTIONS as $optionName) {
            if (false !== get_option($optionName)) {
                $existing = true;
                break;
            }
        }

        if ($existing) {
            self::keepPreviousDefaults();
        }

        update_option(self::FLAG_OPTION, $existing ? 'existing' : 'new', true);
    }

    /**
     * Saves the previous default of each setting that isn't saved yet.
     *
     * @return void
     */
    private static function keepPreviousDefaults()
    {
        $settings = get_option(self::SETTINGS_OPTION, false);
        $isArray  = is_array($settings);

        if (false === $settings || (!$isArray && !is_object($settings))) {
            $settings = new \stdClass();
        }

        $changed = false;
        foreach (self::PREVIOUS_DEFAULTS as $key => $value) {
            if ($isArray && !isset($settings[$key])) {
                $settings[$key] = $value;
                $changed        = true;
            } elseif (!$isArray && !isset($settings->{$key})) {
                $settings->{$key} = $value;
                $changed          = true;
            }
        }

        if ($changed) {
            update_option(self::SETTINGS_OPTION, $settings);
        }
    }
}
