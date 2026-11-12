<?php

/**
 * Native typed settings, appearance, publishing controls and feature switches.
 *
 * @package PublishPress\Checklists
 * @author PublishPress
 * @copyright Copyright (c) 2026, PublishPress
 * @license GPLv2 or later
 */

declare(strict_types=1);

namespace PublishPress\Checklists\Core\Mcp;

use PublishPress\Checklists\Core\Requirement\Base_requirement;

\defined('ABSPATH') || exit;

class Settings
{
    /** @since 2.31.0 */
    public static function fields(): array
    {
        $defaults = (array) Options::legacy()->modules->settings->default_options;
        $fields = [];
        foreach (['disable_publish_button', 'show_warning_icon_submit', 'enable_block_highlighting', 'who_can_ignore_option',
            'disable_quick_edit_publish', 'disable_quick_edit_completely', 'enable_rename_label_editor_panel'] as $name) {
            $fields[$name] = ['type' => 'boolean', 'storage' => 'yes-no', 'default' => ($defaults[$name] ?? 'no') === 'yes',
                'title' => ucwords(str_replace('_', ' ', $name))];
        }
        $fields['delete_data_on_uninstall'] = ['type' => 'boolean', 'storage' => 'on-off', 'default' => false];
        $locked = array_values(array_filter(
            array_keys(Options::legacy()->settings->getAllPostTypes()),
            static fn($type) => post_type_supports($type, Options::legacy()->modules->settings->post_type_support)
            ));
        $fields['post_types'] = ['locked' => $locked, 'type' => 'array', 'uniqueItems' => true, 'maxItems' => 100,
            'items' => ['type' => 'string', 'enum' => array_keys(Options::legacy()->settings->getAllPostTypes())], 'default' => ['post']];
        $fields['checklist_items_sort_order'] = ['type' => 'string', 'enum' => ['default', 'required_recommended', 'alphabetical'], 'default' => 'default'];
        foreach (['complete_icon', 'incomplete_icon'] as $name) {
            $fields[$name] = ['type' => 'string', 'maxLength' => 100, 'default' => $defaults[$name]];
        }
        foreach (['required_complete_color', 'required_incomplete_color', 'required_asterisk_color', 'recommended_complete_color', 'recommended_incomplete_color'] as $name) {
            $fields[$name] = ['type' => 'string', 'pattern' => '^$|^#[a-fA-F0-9]{6}$', 'default' => $defaults[$name]];
        }
        $labels = [
            'who_can_ignore_option' => __('Enable User Roles Filter (Who can ignore)', 'publishpress-checklists'),
            'show_warning_icon_submit' => __('Show warning icon', 'publishpress-checklists'),
            'enable_block_highlighting' => __('Highlight failing blocks in the editor', 'publishpress-checklists'),
            'disable_quick_edit_publish' => __('Disable the Status option when using Quick Edit', 'publishpress-checklists'),
            'disable_quick_edit_completely' => __('Disable Quick Edit completely', 'publishpress-checklists'),
            'disable_publish_button' => __('Disable Publish button', 'publishpress-checklists'),
            'enable_rename_label_editor_panel' => __('Rename task labels', 'publishpress-checklists'),
            'checklist_items_sort_order' => __('Checklist task sort order', 'publishpress-checklists'),
        ];
        foreach ($labels as $name => $label) {
            $fields[$name]['title'] = $label;
        }
        return apply_filters('publishpress_checklists_mcp_setting_fields', $fields);
    }

    /** @since 2.31.0 */
    public static function register(): void
    {
        $output = ['settings' => ['type' => 'object'], 'schema' => ['type' => 'object'], 'hash' => ['type' => 'string'],
            'checklist_hash' => ['type' => 'string'], 'openai_configured' => ['type' => 'boolean'], 'pro_active' => ['type' => 'boolean'], 'dry_run' => ['type' => 'boolean']];
        Registry::add(
            'get-plugin-settings',
            __('Read Checklists settings', 'publishpress-checklists'),
            __('Read Post Types, General, Publishing Options and Appearance settings with exact native fields, choices and revisions. Actual Pro adds available non-secret settings. OpenAI/license credentials are excluded. Requires manage_options.', 'publishpress-checklists'),
            Registry::object(),
            $output,
            [self::class, 'get'],
            [Options::class, 'canSettings']
            );
        Registry::add(
            'update-plugin-settings',
            __('Update Checklists settings', 'publishpress-checklists'),
            __('Patch supplied discovered settings using the latest hash. Boolean inputs map to native yes/no or on/off storage; post_types is a list of enabled names. Preserves omitted settings and credentials. dry_run previews native validation without saving. Feature-specific settings retain dependency and edition gates.', 'publishpress-checklists'),
            Registry::object(['settings' => ['type' => 'object', 'minProperties' => 1, 'maxProperties' => 50],
                'expected_hash' => Registry::revision(), 'dry_run' => Registry::preview()], ['settings', 'expected_hash']),
            $output,
            [self::class, 'update'],
            [Options::class, 'canSettings'],
            true
            );
        Registry::add(
            'set-feature-enabled',
            __('Enable an optional Checklists feature', 'publishpress-checklists'),
            __('Enable or disable one discovered optional module. Core/autoloaded modules cannot be disabled. Requires manage_options and actual module availability. Uses the latest feature inventory hash. Changes take effect in a fresh request.', 'publishpress-checklists'),
            Registry::object(['feature' => ['type' => 'string', 'maxLength' => 100], 'enabled' => ['type' => 'boolean'],
                'expected_hash' => Registry::revision(), 'dry_run' => Registry::preview()], ['feature', 'enabled', 'expected_hash']),
            ['feature' => ['type' => 'string'], 'enabled' => ['type' => 'boolean'], 'dry_run' => ['type' => 'boolean']],
            [self::class, 'feature'],
            [Options::class, 'canSettings'],
            true
            );
    }

    /** @since 2.31.0 */
    public static function get(): array
    {
        $raw = Options::read('settings');
        $values = [];
        foreach (self::fields() as $name => $field) {
            $value = $raw[$name] ?? $field['default'];
            if (isset($field['storage'])) {
                $value = $value === ($field['storage'] === 'yes-no' ? 'yes' : 'on');
            } elseif ($name === 'post_types') {
                $value = Options::legacy()->settings->clearPostTypesOptions((array) $value, Options::legacy()->modules->settings->post_type_support);
                $value = array_keys(array_filter($value, static fn($status) => $status === 'on'));
                $value = array_values(array_intersect($value, $field['items']['enum']));
            }
            if (($field['type'] ?? '') === 'array') {
                $value = array_values((array) $value);
            }
            $values[$name] = $value;
        }
        return ['settings' => (object) $values, 'schema' => Registry::object(self::fields()),
            'hash' => Registry::fingerprint([$raw, self::fields(), Options::pro()]), 'checklist_hash' => Registry::fingerprint(Options::read('checklists')), 'openai_configured' => !empty($raw['openai_api_key']),
            'pro_active' => Options::pro(), 'dry_run' => false];
    }

    /** @since 2.31.0 */
    public static function update(array $input)
    {
        $before = self::get();
        if ($input['expected_hash'] !== $before['hash']) {
            return Registry::error(__('Settings changed. Read them again before saving.', 'publishpress-checklists'), 409);
        }
        $valid = Registry::validate($input['settings'], $before['schema']);
        if (is_wp_error($valid)) {
            return $valid;
        }
        $raw = Options::read('settings');
        $fields = self::fields();
        foreach ($input['settings'] as $name => $value) {
            if (isset($fields[$name]['available']) && !$fields[$name]['available']) {
                return Registry::error(__('This setting requires its active integration.', 'publishpress-checklists'), 403);
            }
            if (isset($fields[$name]['storage'])) {
                $value = $value ? ($fields[$name]['storage'] === 'yes-no' ? 'yes' : 'on') : ($fields[$name]['storage'] === 'yes-no' ? 'no' : 'off');
            } elseif ($name === 'post_types') {
                $map = array_fill_keys($fields[$name]['items']['enum'], 'off');
                foreach ($value as $type) {
                    $map[$type] = 'on';
                }
                $native = Options::legacy()->settings;
                $map = $native->clearPostTypesOptions($map, $native->module->post_type_support);
                $value = array_merge((array) ($raw[$name] ?? []), $map);
            } elseif (is_string($value)) {
                $value = sanitize_text_field($value);
            }
            $raw[$name] = $value;
        }
        // Reuse native module filters with checkbox presence semantics, excluding credentials entirely.
        $payload = array_intersect_key($raw, $fields + ['enabled' => []]);
        foreach ($fields as $name => $field) {
            if (($field['storage'] ?? '') === 'on-off' && ($payload[$name] ?? 'off') !== 'on') {
                unset($payload[$name]);
            }
        }
        $validated = apply_filters('publishpress_checklists_validate_module_settings', $payload, 'settings');
        if (is_wp_error($validated)) {
            return $validated;
        }
        $next = $raw;
        foreach (array_keys($input['settings']) as $name) {
            if (!array_key_exists($name, $validated)) {
                return Registry::error(__('Native validation did not accept this setting.', 'publishpress-checklists'));
            }
            $next[$name] = $validated[$name];
        }
        if (!empty($input['dry_run'])) {
            $values = (array) $before['settings'];
            foreach (array_keys($input['settings']) as $name) {
                $value = $next[$name];
                $values[$name] = isset($fields[$name]['storage']) ? $value === ($fields[$name]['storage'] === 'yes-no' ? 'yes' : 'on')
                    : ($name === 'post_types' ? array_keys(array_filter($value, static fn($status) => $status === 'on')) : $value);
            }
            return array_merge($before, ['settings' => (object) $values, 'dry_run' => true]);
        }
        $saved = Options::save('settings', $next);
        return is_wp_error($saved) ? $saved : self::get();
    }

    /** @since 2.31.0 */
    public static function feature(array $input)
    {
        $inventory = Catalog::features();
        if ($inventory['hash'] !== $input['expected_hash']) {
            return Registry::error(__('Feature settings changed. Discover them again.', 'publishpress-checklists'), 409);
        }
        $legacy = Options::legacy();
        foreach ($legacy->modules as $name => $module) {
            if ($module->slug !== $input['feature']) {
                continue;
            }
            if ($module->autoload || $name === 'settings') {
                return Registry::error(__('This core module cannot be switched through MCP.', 'publishpress-checklists'));
            }
            if (empty($input['dry_run'])) {
                $saved = Options::save($name, array_merge(Options::read($name), ['enabled' => $input['enabled'] ? 'on' : 'off']));
                if (is_wp_error($saved)) {
                    return $saved;
                }
            }
            return ['feature' => $input['feature'], 'enabled' => $input['enabled'], 'dry_run' => !empty($input['dry_run'])];
        }
        return Registry::error(__('Choose an available feature from discovery.', 'publishpress-checklists'));
    }
}
