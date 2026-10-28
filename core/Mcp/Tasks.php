<?php

/**
 * Read saved-post checklist results and change only authorized ticked-by-hand tasks.
 *
 * @package PublishPress\Checklists
 * @author PublishPress
 * @copyright Copyright (c) 2026, PublishPress
 * @license GPLv2 or later
 */

declare(strict_types=1);

namespace PublishPress\Checklists\Core\Mcp;

use PublishPress\Checklists\Core\Utils\CustomItemsMeta;

\defined('ABSPATH') || exit;

class Tasks
{
    /** @since 2.31.0 */
    public static function canEdit(array $input): bool
    {
        $post = get_post($input['post_id'] ?? 0);
        return $post && current_user_can('edit_post', $post->ID)
            && in_array($post->post_type, Settings::get()['settings']->post_types, true)
            && (Options::read('checklists')['enabled'] ?? 'off') === 'on';
    }

    /** @since 2.31.0 */
    public static function register(): void
    {
        $output = ['post_id' => ['type' => 'integer'], 'items' => ['type' => 'array', 'items' => ['type' => 'object']],
            'hash' => ['type' => 'string'], 'saved_content_only' => ['type' => 'boolean'], 'dry_run' => ['type' => 'boolean']];
        Registry::add(
            'get-post-checklist',
            __('Read a post checklist', 'publishpress-checklists'),
            __('Read the native checklist for an editable saved post, respecting ignored roles and active Pro status/taxonomy exclusions. Results are native server snapshots, not a guarantee about unsaved editor content or JavaScript-only checks. OpenAI calls are not performed. Includes manual completion permissions and current revision.', 'publishpress-checklists'),
            Registry::object(['post_id' => Registry::id()], ['post_id']),
            $output,
            [self::class, 'get'],
            [self::class, 'canEdit']
            );
        Registry::add(
            'set-post-task-state',
            __('Complete or reopen manual checklist tasks', 'publishpress-checklists'),
            __('Set explicit boolean states for configured, enabled custom checkboxes or approvals on one editable post using its latest revision. Checks each task\'s native role/user restrictions and post-meta permission before any write. Automatic and OpenAI results cannot be forged. dry_run validates without saving.', 'publishpress-checklists'),
            Registry::object(['post_id' => Registry::id(), 'states' => ['type' => 'object', 'minProperties' => 1, 'maxProperties' => 100, 'additionalProperties' => ['type' => 'boolean']],
                'expected_hash' => Registry::revision(), 'dry_run' => Registry::preview()], ['post_id', 'states', 'expected_hash']),
            $output,
            [self::class, 'set'],
            [self::class, 'canEdit'],
            true
            );
    }

    /** @since 2.31.0 */
    public static function get(array $input): array
    {
        $post = get_post($input['post_id']);
        $instances = Catalog::instances($post->post_type);
        $rows = apply_filters('publishpress_checklists_requirement_list', [], $post);
        $raw = Options::read('checklists');
        $items = [];
        foreach ($rows as $id => $row) {
            if (!isset($instances[$id]) || !Catalog::describe($instances[$id])['available']) {
                continue;
            }
            $manual = CustomItemsMeta::isRegisteredItem($post->post_type, $id) && !in_array($id, $raw['openai_items'] ?? [], true);
            $items[] = ['id' => (string) $id, 'label' => wp_strip_all_tags((string) $row['label']), 'rule' => (string) $row['rule'],
                'type' => (string) ($row['type'] ?? ''), 'complete' => is_bool($row['status'] ?? null) ? $row['status'] : null,
                'source' => $manual ? 'saved-manual-state' : 'native-server-snapshot',
                'can_complete' => $manual && CustomItemsMeta::userCanComplete($post->post_type, $id)
                    && current_user_can('edit_post_meta', $post->ID, CustomItemsMeta::getMetaKey($id))];
        }
        return ['post_id' => $post->ID, 'items' => $items, 'hash' => Registry::fingerprint([(array) $post, get_post_meta($post->ID), $raw,
            Options::read('settings'), apply_filters('publishpress_checklists_mcp_targeting_revision', [], $post->post_type)]),
            'saved_content_only' => true, 'dry_run' => false];
    }

    /** @since 2.31.0 */
    public static function set(array $input)
    {
        $before = self::get($input);
        if ($before['hash'] !== $input['expected_hash']) {
            return Registry::error(__('The post or checklist changed. Read it again.', 'publishpress-checklists'), 409);
        }
        $known = array_column($before['items'], null, 'id');
        $snapshot = [];
        foreach ($input['states'] as $id => $state) {
            if (!isset($known[$id]) || !$known[$id]['can_complete']) {
                return Registry::error(__('You cannot mark this task for this post.', 'publishpress-checklists'), 403);
            }
            $key = CustomItemsMeta::getMetaKey($id);
            $snapshot[$key] = ['exists' => metadata_exists('post', $input['post_id'], $key), 'value' => get_post_meta($input['post_id'], $key, true)];
        }
        if (!empty($input['dry_run'])) {
            foreach ($before['items'] as &$item) {
                if (array_key_exists($item['id'], $input['states'])) {
                    $item['complete'] = $input['states'][$item['id']];
                }
            }
            unset($item);
            return array_merge($before, ['dry_run' => true]);
        }
        try {
            foreach ($input['states'] as $id => $state) {
                $key = CustomItemsMeta::getMetaKey($id);
                $value = $state ? 'yes' : 'no';
                update_post_meta($input['post_id'], $key, $value);
                if (get_post_meta($input['post_id'], $key, true) !== $value) {
                    throw new \RuntimeException('Task state was not saved.');
                }
            }
        } catch (\Throwable $error) {
            $restored = true;
            foreach ($snapshot as $key => $entry) {
                try {
                    if ($entry['exists']) {
                        update_post_meta($input['post_id'], $key, $entry['value']);
                    } else {
                        delete_post_meta($input['post_id'], $key);
                    }
                } catch (\Throwable $error) {
                    // A failing hook must not prevent recovery of the remaining tasks.
                }
                $restored = metadata_exists('post', $input['post_id'], $key) === $entry['exists']
                    && get_post_meta($input['post_id'], $key, true) === $entry['value'] && $restored;
            }
            return Registry::error($restored ? __('Task state could not be saved; original states were restored.', 'publishpress-checklists')
                : __('Task state save failed and recovery was incomplete. Read the checklist before retrying.', 'publishpress-checklists'), 500);
        }
        return self::get($input);
    }
}
