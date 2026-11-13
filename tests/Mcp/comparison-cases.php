<?php

/** Native coverage and cross-plugin contract regressions. */
declare(strict_types=1);

use PublishPress\Checklists\Core\Mcp\Options;
use PublishPress\Checklists\Core\Mcp\Registry;
use PublishPress\Checklists\Core\Utils\CustomItemsMeta;

$selector = ['post_type' => 'post', 'requirement' => 'words_count'];
$raw = Options::read('checklists');
$raw['words_count_min']['page'] = 500;
$raw['extension_payload'] = ['page' => ['raw' => '<b>opaque extension data</b>', 'enabled' => true]];
$ok(Options::save('checklists', $raw), 'Seed unrelated native values');
$before = Options::read('checklists');
$state = $call('get-requirement', $selector);
$after = $ok($call('update-requirement', $selector + ['settings' => ['min' => 19], 'expected_hash' => $state['hash']]), 'Partial field write');
$actual = Options::read('checklists');
$expected = $before;
$expected['words_count_min']['post'] = '19';
$assert($actual === $expected, 'Task patch preserves all unrelated fields, types and post types');
$new = $ok($call('save-custom-task', ['post_type' => 'post', 'settings' => ['title' => 'Comparison review', 'rule' => 'warning', 'editable_by' => ['administrator']]]), 'Custom creation preserves unrelated values');
$afterCreate = Options::read('checklists');
foreach ($before as $key => $value) {
    if ($key === 'custom_items' || $key === 'words_count_min') {
        continue;
    }
    $assert(($afterCreate[$key] ?? null) === $value, 'Custom creation preserves ' . $key);
}
$state = $call('get-requirement', ['post_type' => 'post', 'requirement' => $new['id']]);
$ok($call('delete-custom-task', ['post_type' => 'post', 'requirement' => $new['id'], 'expected_hash' => $state['hash']]), 'Remove comparison task');
$support = Options::legacy()->modules->settings->post_type_support;
$hadSupport = post_type_supports('page', $support);
add_post_type_support('page', $support);
try {
    $rawSettings = Options::read('settings');
    $rawSettings['post_types']['page'] = 'off';
    $ok(Options::save('settings', $rawSettings), 'Seed code-locked post type');
    $settings = $call('get-plugin-settings');
    $assert(in_array('page', $settings['settings']->post_types, true), 'Effective settings honor code-enforced post type');
    $assert(in_array('page', $settings['schema']['properties']['post_types']['locked'], true), 'Discover code locks');
    $types = $call('list-checklist-post-types');
    $pageType = array_column($types['items'], null, 'name')['page'];
    $assert($pageType['enabled'] && $pageType['locked'], 'Post-type inventory reports effective lock');
    $preview = $ok($call('update-plugin-settings', ['settings' => ['post_types' => ['post']], 'expected_hash' => $settings['hash'], 'dry_run' => true]), 'Preview locked type');
    $assert(in_array('page', $preview['settings']->post_types, true), 'Preview retains forced post types');
    remove_post_type_support('page', $support);
    $assert(is_wp_error($call('update-plugin-settings', ['settings' => ['disable_publish_button' => false], 'expected_hash' => $settings['hash']])), 'Revision detects changed code locks');
} finally {
    $hadSupport ? add_post_type_support('page', $support) : remove_post_type_support('page', $support);
}
// Actual optional integration classes, without pretending Yoast is installed or running its evaluator.
$seo = new \PublishPress\Checklists\Yoastseo\Requirement\Seo_Analysis(Options::legacy()->checklists->module, 'post');
$seo->init_language();
$seoFields = $seo->get_configuration_fields();
$assert(!is_wp_error(Registry::validate(['dropdown' => ''], Registry::object($seoFields))), 'Unset native dropdown has a valid exact schema');
$assert(!is_wp_error(Registry::validate(['dropdown' => '41'], Registry::object($seoFields))), 'Native dropdown uses string choice IDs');
$assert(is_wp_error(Registry::validate(['dropdown' => 41], Registry::object($seoFields))), 'Reject coercion of dropdown choices');
$denyMeta = static function ($caps, $requested, $userId, $args) use ($postId) {
    return $requested === 'edit_post_meta' && (int) ($args[0] ?? 0) === $postId ? ['do_not_allow'] : $caps;
};
add_filter('map_meta_cap', $denyMeta, 100, 4);
try {
    $post = $call('get-post-checklist', ['post_id' => $postId]);
    $assert(is_wp_error($call('set-post-task-state', ['post_id' => $postId, 'states' => [$id => true], 'expected_hash' => $post['hash']])), 'Individual metadata capability denial');
} finally {
    remove_filter('map_meta_cap', $denyMeta, 100);
}
$nativeFilter = static function ($values) {
    if (isset($values['openai_api_key']) || isset($values['license_key'])) {
        throw new RuntimeException('Credentials must not reach module validation.');
    }
    $values['show_warning_icon_submit'] = 'no';
    return $values;
};
add_filter('publishpress_checklists_validate_module_settings', $nativeFilter, 99);
try {
    $state = $call('get-plugin-settings');
    $beforeSettings = Options::read('settings');
    $saved = $ok($call('update-plugin-settings', ['settings' => ['show_warning_icon_submit' => true], 'expected_hash' => $state['hash']]), 'Native validation filter applies');
    $assert($saved['settings']->show_warning_icon_submit === false, 'Native filter controls saved setting');
    $expectedSettings = $beforeSettings;
    $expectedSettings['show_warning_icon_submit'] = 'no';
    $assert(Options::read('settings') === $expectedSettings, 'Settings validation preserves every omitted native value');
} finally {
    remove_filter('publishpress_checklists_validate_module_settings', $nativeFilter, 99);
}
foreach (['subscriber', 'editor'] as $role) {
    $login = 'mcp_check_' . str_replace('-', '', wp_generate_uuid4());
    $fixtureUser = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(32), 'user_email' => $login . '@example.test', 'role' => $role]);
    $assert(!is_wp_error($fixtureUser), 'Create permission fixture user');
    try {
        wp_set_current_user($fixtureUser);
        $assert(is_wp_error($call('get-plugin-settings')), $role . ' cannot read site settings');
        $assert(is_wp_error($call('list-requirements', ['post_type' => 'post'])), $role . ' cannot configure checklists');
        $post = $call('get-post-checklist', ['post_id' => $postId]);
        if ($role === 'subscriber') {
            $assert(is_wp_error($post), 'Subscriber cannot read another user post checklist');
        } else {
            $assert(!is_wp_error($post), 'Editor can read an editable post checklist without settings access');
            $assert(is_wp_error($call('set-post-task-state', ['post_id' => $postId, 'states' => [$id => true], 'expected_hash' => $post['hash']])), 'Editor respects administrator-only custom task');
        }
    } finally {
        wp_set_current_user($admin->ID);
        wp_delete_user($fixtureUser);
    }
}
if (Options::pro()) {
    $selector = ['post_type' => 'post', 'requirement' => 'approved_by'];
    $state = $call('get-requirement', $selector);
    $state = $ok($call('update-requirement', $selector + ['settings' => ['rule' => 'warning', 'roles' => ['editor']], 'expected_hash' => $state['hash']]), 'Configure native role approval');
    $duplicate = $ok($call('duplicate-requirement', $selector + ['expected_hash' => $state['hash']]), 'Duplicate role approval');
    $dupSelector = ['post_type' => 'post', 'requirement' => $duplicate['id']];
    $dup = $ok($call('get-requirement', $dupSelector), 'Read duplicated role approval');
    $assert($dup['requirement']->requires_pro, 'Duplicate metadata identifies Pro');
    $post = $call('get-post-checklist', ['post_id' => $postId]);
    $rows = array_column($post['items'], null, 'id');
    $assert(isset($rows[$duplicate['id']]) && !$rows[$duplicate['id']]['can_complete'], 'Duplicate retains native role denial');
    $assert(CustomItemsMeta::isRestrictedItem('post', $duplicate['id']), 'Duplicate approval is restricted metadata');
    $dup = $ok($call('update-requirement', $dupSelector + ['settings' => ['roles' => ['administrator']], 'expected_hash' => $dup['hash']]), 'Permit current role on duplicate');
    $post = $call('get-post-checklist', ['post_id' => $postId]);
    $ok($call('set-post-task-state', ['post_id' => $postId, 'states' => [$duplicate['id'] => true], 'expected_hash' => $post['hash']]), 'Authorized duplicate approval completion');
    $settings = $call('get-plugin-settings');
    $ok($call('update-plugin-settings', ['settings' => ['duplicate_checklist_enabled' => false], 'expected_hash' => $settings['hash']]), 'Disable duplication');
    $assert(is_wp_error($call('get-requirement', $dupSelector)), 'Cached duplicate unavailable after disabling its feature');
    $settings = $call('get-plugin-settings');
    $ok($call('update-plugin-settings', ['settings' => ['duplicate_checklist_enabled' => true], 'expected_hash' => $settings['hash']]), 'Enable duplication');
    $selector = ['post_type' => 'post', 'requirement' => 'approved_by_user'];
    $state = $call('get-requirement', $selector);
    $state = $ok($call('update-requirement', $selector + ['settings' => ['rule' => 'warning', 'users' => [(string) $admin->ID]], 'expected_hash' => $state['hash']]), 'Configure native user approval');
    $duplicate = $ok($call('duplicate-requirement', $selector + ['expected_hash' => $state['hash']]), 'Duplicate user approval');
    $assert(CustomItemsMeta::userCanComplete('post', $duplicate['id']), 'Duplicate retains native user allowance');
    $assert(!CustomItemsMeta::userCanComplete('post', $duplicate['id'], new WP_User()), 'Duplicate denies a different user');
}

if (Options::pro()) {
    $statusKey = \PublishPress\ChecklistsPro\StatusFilter\StatusFilter::OPTION_NAME;
    $termsKey = \PublishPress\ChecklistsPro\TaxonomyFilter\TaxonomyFilter::OPTION_NAME;
    $originalStatus = get_option($statusKey, null);
    $originalTerms = get_option($termsKey, null);
    update_option($statusKey, ['comparison' => ['changed']]);
    update_option($termsKey, ['comparison' => ['changed']]);
    $throwRestore = static function () {
        throw new RuntimeException('Fixture restore hook failure.');
    };
    add_filter('pre_update_option_' . $statusKey, $throwRestore);
    try {
        $failure = \PublishPress\ChecklistsPro\Mcp\Duplicates::recover([$statusKey => $originalStatus, $termsKey => $originalTerms]);
        $assert(is_wp_error($failure) && str_contains($failure->get_error_message(), 'incomplete'), 'Recovery reports a hook failure honestly');
        $assert(get_option($termsKey, null) === $originalTerms, 'Recovery continues to remaining native options');
    } finally {
        remove_filter('pre_update_option_' . $statusKey, $throwRestore);
        $originalStatus === null ? delete_option($statusKey) : update_option($statusKey, $originalStatus);
        $originalTerms === null ? delete_option($termsKey) : update_option($termsKey, $originalTerms);
    }
}

$features = $call('list-features');
foreach ($features['features'] as $feature) {
    $assert(isset($feature['read_capabilities'], $feature['write_capabilities']), 'Feature permission metadata');
}
$beforeAbilities = count(wp_get_abilities());
\PublishPress\Checklists\Core\Mcp\Bootstrap::category();
\PublishPress\Checklists\Core\Mcp\Bootstrap::register();
\PublishPress\Checklists\Core\Mcp\Bootstrap::register();
$assert(count(wp_get_abilities()) === $beforeAbilities, 'Repeated native registration is idempotent');
