<?php

/**
 * Discover native feature scopes, requirement instances and permitted field choices.
 *
 * @package PublishPress\Checklists
 * @author PublishPress
 * @copyright Copyright (c) 2026, PublishPress
 * @license GPLv2 or later
 */

declare(strict_types=1);

namespace PublishPress\Checklists\Core\Mcp;

use PublishPress\Checklists\Core\Requirement\Custom_item;
use PublishPress\Checklists\Core\Requirement\Openai_item;
use PublishPress\Checklists\Core\Requirement\Pro_Requirement;

\defined('ABSPATH') || exit;

class Catalog
{
    private static array $customInstances = [];
    /** @since 2.31.0 */
    public static function register(): void
    {
        Registry::add(
            'list-features',
            __('Discover Checklists features', 'publishpress-checklists'),
            __('Discover native checklist configuration, custom and OpenAI tasks, publishing settings, appearance, per-post task states and active Pro/integration modules. Feature discovery grants no additional permissions.', 'publishpress-checklists'),
            Registry::object(),
            ['features' => ['type' => 'array', 'items' => ['type' => 'object']], 'pro_active' => ['type' => 'boolean'], 'hash' => ['type' => 'string']],
            [self::class, 'features'],
            static fn() => Options::canManage() || Options::canSettings() || current_user_can('edit_posts')
            );
        Registry::add(
            'list-checklist-post-types',
            __('Discover checklist post types', 'publishpress-checklists'),
            __('Read native supported post type names, labels, enabled state and configuration locks. Requires the filtered checklist management capability.', 'publishpress-checklists'),
            Registry::object(),
            ['items' => ['type' => 'array', 'items' => ['type' => 'object']]],
            [self::class, 'postTypes'],
            [Options::class, 'canManage']
            );
        Registry::add(
            'list-requirements',
            __('Find checklist requirements', 'publishpress-checklists'),
            __('Find native built-in, custom, OpenAI, duplicated and integration-specific task IDs for one post type. Includes labels, groups, configured rule and actual edition availability. Read one requirement for its exact fields and latest revision.', 'publishpress-checklists'),
            Registry::object(array_merge(Registry::page(), ['post_type' => ['type' => 'string', 'maxLength' => 100], 'search' => ['type' => 'string', 'maxLength' => 200]]), ['post_type']),
            ['items' => ['type' => 'array', 'items' => ['type' => 'object']], 'page' => ['type' => 'integer'], 'total_pages' => ['type' => 'integer']],
            [self::class, 'listing'],
            [Options::class, 'canManage']
            );
        Registry::add(
            'get-requirement',
            __('Read checklist requirement settings', 'publishpress-checklists'),
            __('Read one native task with exact per-post-type field names, types, choices, current settings and revision. Includes ranges, role/user selectors and labels when supported. Free Pro placeholders are unavailable for writes.', 'publishpress-checklists'),
            self::selector(),
            ['requirement' => ['type' => 'object'], 'settings' => ['type' => 'object'], 'schema' => ['type' => 'object'], 'hash' => ['type' => 'string']],
            [self::class, 'get'],
            [Options::class, 'canManage']
            );
        Registry::add(
            'get-requirement-choices',
            __('Discover checklist requirement choices', 'publishpress-checklists'),
            __('Read a bounded page of native field choices, including role/user choices and live assignable taxonomy term identifiers. Use returned values rather than inventing taxonomy IDs or names.', 'publishpress-checklists'),
            Registry::object(array_merge(self::selector()['properties'], Registry::page(), ['field' => ['type' => 'string', 'maxLength' => 100]]), ['post_type', 'requirement', 'field']),
            ['items' => ['type' => 'array', 'items' => ['type' => 'object']], 'page' => ['type' => 'integer'], 'has_more' => ['type' => 'boolean']],
            [self::class, 'choices'],
            [Options::class, 'canManage']
            );
    }

    /** @since 2.31.0 */
    public static function selector(): array
    {
        return Registry::object(['post_type' => ['type' => 'string', 'maxLength' => 100],
            'requirement' => ['type' => 'string', 'maxLength' => 150]], ['post_type', 'requirement']);
    }

    /** @since 2.31.0 */
    public static function features(): array
    {
        $items = [];
        $manage = apply_filters('publishpress_checklists_manage_checklist_cap', 'manage_checklists');
        foreach (Options::legacy()->modules as $name => $module) {
            $configuration = in_array($name, ['settings', 'prosettings'], true);
            $items[] = ['feature' => $module->slug, 'label' => wp_strip_all_tags($module->title),
                'enabled' => (Options::read($name)['enabled'] ?? 'off') === 'on', 'switchable' => !$module->autoload,
                'read_capabilities' => $configuration ? ['manage_options'] : [$manage],
                'write_capabilities' => $configuration ? ['manage_options'] : [$manage], 'toggle_capabilities' => ['manage_options'],
                'read_actions' => $name === 'reviews' ? [] : ($configuration ? ['get-plugin-settings'] : ['list-requirements', 'get-requirement']),
                'write_actions' => $name === 'reviews' ? [] : ($configuration ? ['update-plugin-settings'] : ['update-requirement', 'bulk-update-requirements'])];
        }
        foreach (['settings' => ['get-plugin-settings', 'update-plugin-settings'], 'tasks' => ['get-post-checklist', 'set-post-task-state'],
            'custom-tasks' => ['get-requirement', 'save-custom-task'], 'ordering' => ['list-requirements', 'reorder-requirements']] as $scope => [$read, $write]) {
            $capabilities = $scope === 'settings' ? ['manage_options'] : ($scope === 'tasks' ? ['edit_post'] : [$manage]);
            $items[] = ['feature' => $scope, 'read_actions' => [$read], 'write_actions' => [$write],
                'read_capabilities' => $capabilities,
                'write_capabilities' => $scope === 'tasks' ? ['edit_post', 'edit_post_meta'] : $capabilities,
                'scope' => $scope === 'tasks' ? __('Editable post; native task role/user restrictions also apply', 'publishpress-checklists') : __('Site configuration', 'publishpress-checklists')];
        }
        if (Options::pro()) {
            $items[] = ['feature' => 'pro-targeting', 'read_capabilities' => [$manage, 'manage_options'], 'write_capabilities' => [$manage, 'manage_options'], 'read_actions' => ['get-requirement-targeting'], 'write_actions' => ['update-requirement-targeting']];
            $items[] = ['feature' => 'duplicates', 'read_capabilities' => [$manage], 'write_capabilities' => [$manage, 'manage_options'], 'read_actions' => ['list-requirements'], 'write_actions' => ['duplicate-requirement', 'delete-duplicate-requirement']];
        }
        return ['features' => $items, 'pro_active' => Options::pro(), 'hash' => Registry::fingerprint($items)];
    }

    /** @since 2.31.0 */
    public static function postTypes(): array
    {
        $items = [];
        $enabled = Settings::get()['settings']->post_types;
        foreach (Options::legacy()->settings->getAllPostTypes() as $name => $label) {
            $items[] = ['name' => $name, 'label' => $label, 'enabled' => in_array($name, $enabled, true),
                'locked' => post_type_supports($name, Options::legacy()->modules->settings->post_type_support)];
        }
        return ['items' => $items];
    }

    /** @since 2.31.0 */
    public static function instances(string $type): array
    {
        $legacy = Options::legacy();
        if (!isset($legacy->settings->getAllPostTypes()[$type])) {
            return [];
        }
        $raw = Options::read('checklists');
        $legacy->modules->checklists->options = (object) $raw;
        $legacy->checklists->module = $legacy->modules->checklists;
        $items = [];
        foreach ($legacy->checklists->get_requirement_instances($type) as $item) {
            if (($item instanceof Custom_item || $item instanceof Openai_item)
                && !isset($raw[$item->name . '_title'][$type])) {
                continue;
            }
            if ($item->name !== '') {
                $items[$item->name] = $item;
            }
        }
        foreach (['custom_items' => Custom_item::class, 'openai_items' => Openai_item::class] as $list => $class) {
            foreach ($raw[$list] ?? [] as $id) {
                if (isset($raw[$id . '_title'][$type]) && !isset($items[$id])) {
                    $key = $type . ':' . $id;
                    if (!isset(self::$customInstances[$key])) {
                        $item = new $class($id, $legacy->checklists->module, $type);
                        $item->init();
                        self::$customInstances[$key] = $item;
                    }
                    $items[$id] = self::$customInstances[$key];
                }
            }
        }
        return apply_filters('publishpress_checklists_mcp_requirement_instances', $items, $type);
    }

    /** @since 2.31.0 */
    public static function describe($item): array
    {
        $placeholder = $item instanceof Pro_Requirement;
        return ['id' => $item->name, 'label' => wp_strip_all_tags($item->lang['label_settings'] ?? ($placeholder ? $item->get_label() : $item->name)),
            'group' => $item->group ?? 'general', 'type' => $item->get_requirement_type(),
            'available' => !$placeholder, 'requires_pro' => $placeholder || str_contains($item->name, '_duplicate_') || str_starts_with(get_class($item), 'PublishPress\\ChecklistsPro\\'),
            'custom' => $item instanceof Custom_item, 'openai' => $item instanceof Openai_item];
    }

    /** @since 2.31.0 */
    public static function listing(array $input)
    {
        if (!isset(Options::legacy()->settings->getAllPostTypes()[$input['post_type']])) {
            return Registry::error(__('Choose a supported post type.', 'publishpress-checklists'));
        }
        $items = [];
        $raw = Options::read('checklists');
        foreach (self::instances($input['post_type']) as $item) {
            $row = self::describe($item);
            $row['rule'] = $raw[$item->name . '_rule'][$input['post_type']] ?? 'off';
            if (empty($input['search']) || stripos($row['label'] . ' ' . $row['id'] . ' ' . $row['group'], $input['search']) !== false) {
                $items[] = $row;
            }
        }
        $page = $input['page'] ?? 1;
        $limit = $input['per_page'] ?? 20;
        return ['items' => array_slice($items, ($page - 1) * $limit, $limit), 'page' => $page, 'total_pages' => (int) ceil(count($items) / $limit)];
    }

    /** @since 2.31.0 */
    public static function get(array $input, ?array $proposed = null)
    {
        $item = self::instances($input['post_type'])[$input['requirement']] ?? null;
        if (!$item) {
            return Registry::error(__('Choose a requirement from this post type\'s discovery.', 'publishpress-checklists'), 404);
        }
        $fields = $item->get_configuration_fields();
        $taxonomy = self::taxonomy($item);
        if ($taxonomy && isset($fields['multiple'])) {
            unset($fields['multiple']['items']['enum']);
            $fields['multiple']['choices'] = [];
            $fields['multiple']['description'] = __('Native taxonomy tokens from get-requirement-choices; do not invent names.', 'publishpress-checklists');
        }
        $raw = $proposed ?? Options::read('checklists');
        $values = [];
        foreach ($fields as $suffix => $field) {
            $value = $raw[$item->name . '_' . $suffix][$input['post_type']] ?? ($field['default'] ?? '');
            if (isset($field['anyOf']) && is_numeric($value)) {
                $value = ($field['anyOf'][0]['type'] ?? '') === 'number' ? (float) $value : (int) $value;
            }
            if (($field['type'] ?? '') === 'array') {
                $value = array_values((array) $value);
                if (($field['items']['type'] ?? '') === 'string') {
                    $value = array_map('strval', $value);
                }
            }
            $values[$suffix] = $value;
        }
        return ['requirement' => (object) self::describe($item), 'settings' => (object) $values,
            'schema' => Registry::object($fields), 'hash' => Registry::fingerprint([$input['post_type'], $input['requirement'], $raw, array_keys($raw), apply_filters('publishpress_checklists_mcp_configuration_revision', [])])];
    }

    /** @since 2.31.0 */
    public static function taxonomy($item): ?string
    {
        foreach (['Required_categories' => 'category', 'Prohibited_categories' => 'category', 'Required_tags' => 'post_tag', 'Prohibited_tags' => 'post_tag'] as $class => $taxonomy) {
            if (is_a($item, 'PublishPress\\Checklists\\Core\\Requirement\\' . $class)) {
                return $taxonomy;
            }
        }
        return null;
    }

    /** @since 2.31.0 */
    public static function choices(array $input)
    {
        $state = self::get($input);
        if (is_wp_error($state)) {
            return $state;
        }
        $field = $state['schema']['properties'][$input['field']] ?? null;
        if (!$field) {
            return Registry::error(__('Choose a discovered field.', 'publishpress-checklists'));
        }
        $items = $field['choices'] ?? [];
        $item = self::instances($input['post_type'])[$input['requirement']];
        if ($input['field'] === 'users') {
            if (!current_user_can('list_users')) {
                return Registry::error(__('You cannot list users.', 'publishpress-checklists'), 403);
            }
            $limit = $input['per_page'] ?? 20;
            $page = $input['page'] ?? 1;
            $users = get_users(['capability' => 'edit_posts', 'number' => $limit + 1, 'offset' => ($page - 1) * $limit, 'orderby' => 'ID']);
            return ['items' => array_map(static fn($user) => ['value' => (string) $user->ID, 'label' => $user->display_name], array_slice($users, 0, $limit)),
                'page' => $page, 'has_more' => count($users) > $limit];
        }
        $taxonomy = self::taxonomy($item);
        if ($taxonomy && $input['field'] === 'multiple') {
            $object = get_taxonomy($taxonomy);
            if (!$object || !current_user_can($object->cap->assign_terms)) {
                return Registry::error(__('You cannot access this taxonomy selector.', 'publishpress-checklists'), 403);
            }
            $limit = $input['per_page'] ?? 20;
            $page = $input['page'] ?? 1;
            $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => $limit + 1, 'offset' => ($page - 1) * $limit]);
            if (is_wp_error($terms)) {
                return $terms;
            }
            return ['items' => array_map(static fn($term) => ['value' => $term->term_id . '__' . $term->name, 'label' => $term->name], array_slice($terms, 0, $limit)),
                'page' => $page, 'has_more' => count($terms) > $limit];
        }
        $page = $input['page'] ?? 1;
        $limit = $input['per_page'] ?? 20;
        return ['items' => array_slice($items, ($page - 1) * $limit, $limit), 'page' => $page, 'has_more' => count($items) > $page * $limit];
    }
}
