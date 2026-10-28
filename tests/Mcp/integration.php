<?php

/** Executable native Free/Pro contract and permission checks. */
declare(strict_types=1);

use PublishPress\Checklists\Core\Mcp\Catalog;
use PublishPress\Checklists\Core\Mcp\Options;
use PublishPress\Checklists\Core\Mcp\Registry;
use PublishPress\Checklists\Core\Mcp\Settings;

$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$call = static function (string $name, array $input = []) {
    $ability = wp_get_ability('publishpress-checklists/' . $name);
    if (!$ability) {
        throw new RuntimeException('Missing ability ' . $name);
    }
    return $ability->execute($input);
};
$ok = static function ($value, string $message) use ($assert) {
    $assert(!is_wp_error($value), $message . (is_wp_error($value) ? ': ' . $value->get_error_message() : ''));
    return $value;
};
$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
wp_set_current_user($admin->ID);
get_role('administrator')->add_cap('manage_checklists');
wp_set_current_user(0);
wp_set_current_user($admin->ID);
$snapshot = [];
foreach ([Options::key('settings'), Options::key('checklists'), 'ppc_statuses_per_requirement', 'ppc_taxonomies_per_requirement', 'ppc_duplicate_class_mappings'] as $key) {
    $snapshot[$key] = get_option($key, null);
}
$postId = 0;
try {
    $settings = $ok($call('get-plugin-settings'), 'Settings discovery');
    $assert(!isset($settings['settings']->openai_api_key), 'Secrets excluded');
    $features = $ok($call('list-features'), 'Features');
    $inventory = $ok($call('list-requirements', ['post_type' => 'post', 'per_page' => 100]), 'Requirements');
    $assert(count($inventory['items']) > 10, 'Native inventory');
    foreach (wp_get_abilities() as $ability) {
        if (str_starts_with($ability->get_name(), 'publishpress-checklists/')) {
            $assert(($ability->get_meta()['public'] ?? null) === false, 'Private abilities');
        }
    }
    $raw = Options::read('settings');
    $raw['openai_api_key'] = 'fixture-secret';
    $ok(Options::save('settings', $raw), 'Seed secret');
    $settings = $call('get-plugin-settings');
    $assert(is_wp_error($call('update-plugin-settings', ['settings' => ['disable_publish_button' => 'yes'], 'expected_hash' => $settings['hash']])), 'Reject string boolean');
    $assert(is_wp_error($call('update-plugin-settings', ['settings' => ['openai_api_key' => 'no'], 'expected_hash' => $settings['hash']])), 'Reject secret writes');
    $before = Options::read('settings');
    $preview = $ok($call('update-plugin-settings', ['settings' => ['post_types' => ['post', 'page'], 'disable_publish_button' => true], 'expected_hash' => $settings['hash'], 'dry_run' => true]), 'Settings preview');
    $assert(Options::read('settings') === $before, 'Preview no writes');
    $settings = $ok($call('update-plugin-settings', ['settings' => ['post_types' => ['post', 'page'], 'disable_publish_button' => true], 'expected_hash' => $settings['hash']]), 'Settings save');
    $assert(Options::read('settings')['disable_publish_button'] === 'yes', 'Native yes/no');
    $assert(Options::read('settings')['openai_api_key'] === 'fixture-secret', 'Preserve secret');
    $assert(is_wp_error($call('update-plugin-settings', ['settings' => ['disable_publish_button' => false], 'expected_hash' => str_repeat('0', 64)])), 'Stale settings');
    $selector = ['post_type' => 'post', 'requirement' => 'words_count'];
    $task = $ok($call('get-requirement', $selector), 'Read counter');
    $assert(isset($task['schema']['properties']['min']), 'Native counter schema');
    $assert(is_wp_error($call('update-requirement', $selector + ['settings' => ['min' => '12'], 'expected_hash' => $task['hash']])), 'Reject numeric string');
    $task = $ok($call('update-requirement', $selector + ['settings' => ['rule' => 'block', 'min' => 12, 'max' => 200], 'expected_hash' => $task['hash']]), 'Save counter');
    $assert($task['settings']->min === 12 && $task['settings']->rule === 'block', 'Counter round trip');
    $assert(is_wp_error($call('update-requirement', $selector + ['settings' => ['min' => 300], 'expected_hash' => $task['hash']])), 'Invalid range');
    $before = Options::read('checklists');
    $assert(is_wp_error($call('bulk-update-requirements', ['changes' => [$selector + ['settings' => ['min' => 15], 'expected_hash' => $task['hash']],
        ['post_type' => 'post', 'requirement' => 'invented', 'settings' => ['rule' => 'block'], 'expected_hash' => $task['hash']]]])), 'Bulk prevalidation');
    $assert(Options::read('checklists') === $before, 'Bulk failure unchanged');
    $custom = $ok($call('save-custom-task', ['post_type' => 'post', 'settings' => ['title' => 'MCP fixture review', 'rule' => 'warning', 'editable_by' => ['administrator']]]), 'Create custom');
    $id = $custom['id'];
    $assert(str_starts_with($id, 'custom_item_'), 'Generated native ID');
    $postId = wp_insert_post(['post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'MCP fixture', 'post_content' => 'Saved content'], true);
    $assert(!is_wp_error($postId), 'Create fixture post');
    $post = $ok($call('get-post-checklist', ['post_id' => $postId]), 'Post checklist');
    $rows = array_column($post['items'], null, 'id');
    $assert(isset($rows[$id]) && $rows[$id]['can_complete'], 'Native manual permission');
    $post = $ok($call('set-post-task-state', ['post_id' => $postId, 'states' => [$id => true], 'expected_hash' => $post['hash']]), 'Complete task');
    $assert(get_post_meta($postId, 'pp_checklist_custom_item_' . $id, true) === 'yes', 'Native task meta');
    $assert(is_wp_error($call('set-post-task-state', ['post_id' => $postId, 'states' => ['words_count' => true], 'expected_hash' => $post['hash']])), 'Automatic task cannot be forged');
    if (Options::pro()) {
        $s = $call('get-plugin-settings');
        $s = $ok($call('update-plugin-settings', ['settings' => ['status_filter_enabled' => true, 'taxonomy_filter_enabled' => true, 'taxonomy_filter_taxonomies' => ['category']], 'expected_hash' => $s['hash']]), 'Pro settings');
        $assert(Options::read('settings')['taxonomy_filter_enabled'] === 'on', 'Native Pro on/off');
        $target = $ok($call('get-requirement-targeting', $selector), 'Targeting discovery');
        $target = $ok($call('update-requirement-targeting', $selector + ['targeting' => ['excluded_statuses' => ['draft']], 'expected_hash' => $target['hash']]), 'Targeting save');
        $assert($target['targeting']->excluded_statuses === ['draft'], 'Targeting round trip');
        $task = $call('get-requirement', $selector);
        $duplicate = $ok($call('duplicate-requirement', $selector + ['expected_hash' => $task['hash']]), 'Duplicate native task');
        $dupSelector = ['post_type' => 'post', 'requirement' => $duplicate['id']];
        $dup = $ok($call('get-requirement', $dupSelector), 'Discover duplicate same request');
        $ok($call('delete-duplicate-requirement', $dupSelector + ['expected_hash' => $dup['hash']]), 'Delete native duplicate');
        $assert(is_wp_error($call('get-requirement', $dupSelector)), 'Deleted duplicate absent');
    } else {
        foreach ($inventory['items'] as $item) {
            if (!$item['available']) {
                $unavailable = ['post_type' => 'post', 'requirement' => $item['id']];
                $state = $call('get-requirement', $unavailable);
                $assert(is_wp_error($call('update-requirement', $unavailable + ['settings' => ['rule' => 'block'], 'expected_hash' => $state['hash']])), 'Free placeholder rejected');
                break;
            }
        }
    }
    require __DIR__ . '/format-and-permission-cases.php';
    require __DIR__ . '/comparison-cases.php';
    require __DIR__ . '/adapter-format-audit.php';
    $state = $call('get-requirement', ['post_type' => 'post', 'requirement' => $id]);
    $ok($call('delete-custom-task', ['post_type' => 'post', 'requirement' => $id, 'expected_hash' => $state['hash']]), 'Delete custom');
    $assert(is_wp_error($call('get-requirement', ['post_type' => 'post', 'requirement' => $id])), 'Deleted custom absent');
    get_role('administrator')->remove_cap('manage_checklists');
    wp_set_current_user(0);
    wp_set_current_user($admin->ID);
    $assert(is_wp_error($call('list-requirements', ['post_type' => 'post'])), 'Administrator cannot bypass denied capability');
    get_role('administrator')->add_cap('manage_checklists');
    wp_set_current_user(0);
    $assert(is_wp_error($call('get-plugin-settings')), 'Anonymous settings denied');
    echo 'PASS ' . $checks . ' checks (' . (Options::pro() ? 'Pro' : 'Free') . ")\n";
} finally {
    wp_set_current_user($admin->ID);
    get_role('administrator')->add_cap('manage_checklists');
    if ($postId) {
        wp_delete_post($postId, true);
    }
    foreach ($snapshot as $key => $value) {
        $value === null ? delete_option($key) : update_option($key, $value);
    }
}
