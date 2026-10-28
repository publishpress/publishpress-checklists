<?php

/** Round trips, exact formats, stale revisions, object permissions and failure compensation. */
declare(strict_types=1);

use PublishPress\Checklists\Core\Mcp\Options;
use PublishPress\Checklists\Core\Mcp\Settings;

$settings = $call('get-plugin-settings');
foreach ((array) $settings['settings'] as $name => $value) {
    if (isset($settings['schema']['properties'][$name]['available']) && !$settings['schema']['properties'][$name]['available']) {
        continue;
    }
    $before = Options::read('settings');
    $preview = $ok($call('update-plugin-settings', ['settings' => [$name => $value], 'expected_hash' => $settings['hash'], 'dry_run' => true]), 'Settings format ' . $name);
    $assert(Options::read('settings') === $before, 'Settings preview preserves database ' . $name);
    $assert($preview['settings']->$name === $value, 'Settings exact round trip ' . $name);
}
$inventory = $call('list-requirements', ['post_type' => 'post', 'per_page' => 100]);
foreach ($inventory['items'] as $row) {
    if (!$row['available']) {
        continue;
    }
    $selection = ['post_type' => 'post', 'requirement' => $row['id']];
    $state = $ok($call('get-requirement', $selection), 'Requirement discovery ' . $row['id']);
    foreach ((array) $state['settings'] as $name => $value) {
        if ($name === 'can_ignore' && !Settings::get()['settings']->who_can_ignore_option) {
            continue;
        }
        $before = Options::read('checklists');
        $preview = $ok($call('update-requirement', $selection + ['settings' => [$name => $value], 'expected_hash' => $state['hash'], 'dry_run' => true]), 'Requirement format ' . $row['id'] . '/' . $name);
        $assert(Options::read('checklists') === $before, 'Requirement preview preserves database');
        $assert($preview['settings']->$name === $value, 'Requirement exact round trip ' . $row['id'] . '/' . $name);
    }
}
$selector = ['post_type' => 'post', 'requirement' => 'words_count'];
$state = $call('get-requirement', $selector);
$before = Options::read('checklists');
$denySave = static fn($value, $previous) => $previous;
add_filter('pre_update_option_' . Options::key('checklists'), $denySave, 10, 2);
try {
    $assert(is_wp_error($call('update-requirement', $selector + ['settings' => ['min' => 17], 'expected_hash' => $state['hash']])), 'Detect failed native option write');
    $assert(Options::read('checklists') === $before, 'Failed write preserves original values');
} finally {
    remove_filter('pre_update_option_' . Options::key('checklists'), $denySave, 10);
}
$manual = ['post_type' => 'post', 'requirement' => $id];
$state = $call('get-requirement', $manual);
$ok($call('update-requirement', $manual + ['settings' => ['editable_by' => []], 'expected_hash' => $state['hash']]), 'Deny manual roles');
$post = $call('get-post-checklist', ['post_id' => $postId]);
$assert(is_wp_error($call('set-post-task-state', ['post_id' => $postId, 'states' => [$id => false], 'expected_hash' => $post['hash']])), 'Administrator respects manual role restriction');
$state = $call('get-requirement', $manual);
$ok($call('update-requirement', $manual + ['settings' => ['editable_by' => ['administrator']], 'expected_hash' => $state['hash']]), 'Restore manual roles');
$post = $call('get-post-checklist', ['post_id' => $postId]);
$denyMeta = static function ($check, $objectId, $key) use ($id) {
    return $key === 'pp_checklist_custom_item_' . $id ? true : $check;
};
add_filter('update_post_metadata', $denyMeta, 10, 3);
try {
    $assert(is_wp_error($call('set-post-task-state', ['post_id' => $postId, 'states' => [$id => false], 'expected_hash' => $post['hash']])), 'Detect failed metadata write');
    $assert(get_post_meta($postId, 'pp_checklist_custom_item_' . $id, true) === 'yes', 'Task failure restores original state');
} finally {
    remove_filter('update_post_metadata', $denyMeta, 10);
}
$denyEdit = static function ($caps, $cap, $userId, $args) use ($postId) {
    return $cap === 'edit_post' && (int) ($args[0] ?? 0) === $postId ? ['do_not_allow'] : $caps;
};
add_filter('map_meta_cap', $denyEdit, 100, 4);
try {
    $assert(is_wp_error($call('get-post-checklist', ['post_id' => $postId])), 'Object-specific post read denial');
    $assert(is_wp_error($call('set-post-task-state', ['post_id' => $postId, 'states' => [$id => false], 'expected_hash' => $post['hash']])), 'Object-specific post write denial');
} finally {
    remove_filter('map_meta_cap', $denyEdit, 100);
}
foreach (['manage_options' => ['get-plugin-settings', []], 'manage_checklists' => ['list-requirements', ['post_type' => 'post']]] as $cap => [$name, $input]) {
    $deny = static function ($caps) use ($cap) {
        $caps[$cap] = false;
        return $caps;
    };
    add_filter('user_has_cap', $deny, 100);
    try {
        $assert(is_wp_error($call($name, $input)), 'Denied Administrator ' . $cap);
    } finally {
        remove_filter('user_has_cap', $deny, 100);
    }
}
$settings = $call('get-plugin-settings');
$raw = Options::read('checklists');
$raw['words_count_custom_label']['post'] = 'A changed label';
$ok(Options::save('checklists', $raw), 'Concurrent label write');
$assert(is_wp_error($call('reset-requirement-labels', ['expected_hash' => $settings['checklist_hash']])), 'Label reset checks checklist revision');
$openai = $ok($call('save-custom-task', ['post_type' => 'post', 'kind' => 'openai', 'settings' => ['title' => "Check this text\nWithout inventing results", 'rule' => 'warning']]), 'OpenAI instructions');
$assert(!isset(Options::read('checklists')[$openai['id'] . '_editable_by']), 'OpenAI uses its actual fields');
$post = $call('get-post-checklist', ['post_id' => $postId]);
$assert(is_wp_error($call('set-post-task-state', ['post_id' => $postId, 'states' => [$openai['id'] => true], 'expected_hash' => $post['hash']])), 'Cannot forge OpenAI result');
$categorySelector = ['post_type' => 'post', 'requirement' => 'required_categories'];
$categoryState = $call('get-requirement', $categorySelector);
if (!is_wp_error($categoryState) && $categoryState['requirement']->available) {
    $choices = $ok($call('get-requirement-choices', $categorySelector + ['field' => 'multiple', 'per_page' => 1]), 'Live native category choices');
    $assert(count($choices['items']) <= 1, 'Bounded category choices');
    $token = $choices['items'][0]['value'];
    $state = $call('get-requirement', $categorySelector);
    $ok($call('update-requirement', $categorySelector + ['settings' => ['multiple' => [$token]], 'expected_hash' => $state['hash']]), 'Native taxonomy token');
    $state = $call('get-requirement', $categorySelector);
    $assert(is_wp_error($call('update-requirement', $categorySelector + ['settings' => ['multiple' => ['1__Invented name']], 'expected_hash' => $state['hash']])), 'Reject invented term label');
    $taxonomyCap = get_taxonomy('category')->cap->assign_terms;
    $denyTerms = static function ($caps, $requested) use ($taxonomyCap) {
        return $requested === $taxonomyCap ? ['do_not_allow'] : $caps;
    };
    add_filter('map_meta_cap', $denyTerms, 100, 2);
    try {
        $assert(is_wp_error($call('get-requirement-choices', $categorySelector + ['field' => 'multiple'])), 'Taxonomy choice permission');
        $assert(is_wp_error($call('update-requirement', $categorySelector + ['settings' => ['multiple' => [$token]], 'expected_hash' => $state['hash']])), 'Taxonomy write permission');
    } finally {
        remove_filter('map_meta_cap', $denyTerms, 100);
    }
}
if (Options::pro()) {
    // Exercise real Pro schema classes without claiming that optional dependencies are installed.
    $module = Options::legacy()->checklists->module;
    $price = new \PublishPress\ChecklistsPro\WooCommerce\Requirement\Discount($module, 'post');
    $price->init_language();
    $fields = $price->get_configuration_fields();
    $assert(($fields['min']['anyOf'][0]['type'] ?? '') === 'number', 'Real Pro discount schema accepts decimals');
    $backorder = new \PublishPress\ChecklistsPro\WooCommerce\Requirement\Backorder($module, 'post');
    $backorder->init_language();
    $assert($backorder->get_configuration_fields()['backorder']['enum'] === ['no', 'yes', 'notify'], 'Real Pro backorder choices');
    $selector = ['post_type' => 'post', 'requirement' => 'words_count'];
    $state = $call('get-requirement', $selector);
    $raw = Options::read('checklists');
    $raw['words_count_rule']['page'] = 'block';
    $raw['words_count_min']['page'] = '500';
    $raw['words_count_rule']['post'] = 'off';
    $raw['words_count_custom_label']['post'] = 'Scoped duplicate label';
    $ok(Options::save('checklists', $raw), 'Seed separate post type');
    $state = $call('get-requirement', $selector);
    $duplicate = $ok($call('duplicate-requirement', $selector + ['expected_hash' => $state['hash']]), 'Duplicate disabled task');
    $dupId = $duplicate['id'];
    $copied = Options::read('checklists');
    $assert($copied[$dupId . '_rule']['post'] === 'warning', 'Duplicate uses a valid native recommended rule');
    $assert(!isset($copied[$dupId . '_rule']['page']), 'Scoped duplicate does not enable another post type');
    $assert($copied[$dupId . '_custom_label']['post'] === 'Scoped duplicate label', 'Duplicate retains labels');
    $raw = Options::read('checklists');
    unset($raw['featured_image_rule']);
    $ok(Options::save('checklists', $raw), 'Seed unconfigured task');
    $unconfigured = ['post_type' => 'post', 'requirement' => 'featured_image'];
    $state = $call('get-requirement', $unconfigured);
    $created = $ok($call('duplicate-requirement', $unconfigured + ['expected_hash' => $state['hash']]), 'Duplicate never-configured task');
    $assert(Options::read('checklists')[$created['id'] . '_rule']['post'] === 'warning', 'Unconfigured duplicate has a usable native rule');
    $mappingKey = \PublishPress\ChecklistsPro\DuplicateChecklist\DuplicateSettingsManager::OPTION_CLASS_MAPPINGS;
    $beforeOptions = Options::read('checklists');
    $beforeMappings = get_option($mappingKey, []);
    add_filter('pre_update_option_' . $mappingKey, $denySave, 10, 2);
    try {
        $state = $call('get-requirement', $selector);
        $assert(is_wp_error($call('duplicate-requirement', $selector + ['expected_hash' => $state['hash']])), 'Detect second-step mapping failure');
        $assert(Options::read('checklists') === $beforeOptions && get_option($mappingKey, []) === $beforeMappings, 'Recover duplicate multi-option failure');
    } finally {
        remove_filter('pre_update_option_' . $mappingKey, $denySave, 10);
    }
    $taxonomySelector = ['post_type' => 'post', 'requirement' => 'category_count'];
    $taxonomyState = $call('get-requirement', $taxonomySelector);
    if (!is_wp_error($taxonomyState)) {
        $taxonomyState = $ok($call('update-requirement', $taxonomySelector + ['settings' => ['rule' => 'warning', 'min' => 1], 'expected_hash' => $taxonomyState['hash']]), 'Taxonomy counter seed');
        $taxonomyDuplicate = $ok($call('duplicate-requirement', $taxonomySelector + ['expected_hash' => $taxonomyState['hash']]), 'Duplicate parametrized taxonomy');
        $taxonomyDuplicateSelector = ['post_type' => 'post', 'requirement' => $taxonomyDuplicate['id']];
        $taxonomyRead = $ok($call('get-requirement', $taxonomyDuplicateSelector), 'Parametrized duplicate discovery');
        $assert(str_contains($taxonomyRead['requirement']->type, 'category'), 'Taxonomy parameters retained');
        $ok($call('get-post-checklist', ['post_id' => $postId]), 'Parametrized duplicate evaluation');
    }
    $denyUsers = static function ($caps) {
        $caps['list_users'] = false;
        return $caps;
    };
    add_filter('user_has_cap', $denyUsers, 100);
    try {
        $assert(is_wp_error($call('get-requirement-choices', ['post_type' => 'post', 'requirement' => 'approved_by_user', 'field' => 'users'])), 'User-choice permission');
    } finally {
        remove_filter('user_has_cap', $denyUsers, 100);
    }
    $target = $call('get-requirement-targeting', $selector);
    $choices = $ok($call('get-targeting-choices', $selector + ['field' => 'excluded_terms', 'per_page' => 1]), 'Targeting native term choices');
    $assert(count($choices['items']) <= 1, 'Bounded targeting choices');
    $beforeStatus = get_option('ppc_statuses_per_requirement', []);
    $beforeTerms = get_option('ppc_taxonomies_per_requirement', []);
    add_filter('pre_update_option_ppc_taxonomies_per_requirement', $denySave, 10, 2);
    try {
        $token = $choices['items'][0]['value'];
        $assert(is_wp_error($call('update-requirement-targeting', $selector + ['targeting' => ['excluded_statuses' => ['pending'], 'excluded_terms' => [$token]], 'expected_hash' => $target['hash']])), 'Detect second-step targeting failure');
        $assert(get_option('ppc_statuses_per_requirement', []) === $beforeStatus && get_option('ppc_taxonomies_per_requirement', []) === $beforeTerms, 'Recover targeting multi-option failure');
    } finally {
        remove_filter('pre_update_option_ppc_taxonomies_per_requirement', $denySave, 10);
    }
    $raw = Options::read('checklists');
    $raw[$dupId . '0_rule']['post'] = 'warning';
    $ok(Options::save('checklists', $raw), 'Seed neighboring duplicate ID');
    $dupSelection = ['post_type' => 'post', 'requirement' => $dupId];
    $state = $call('get-requirement', $dupSelection);
    $ok($call('delete-duplicate-requirement', $dupSelection + ['expected_hash' => $state['hash']]), 'Delete exact duplicate');
    $assert(isset(Options::read('checklists')[$dupId . '0_rule']['post']), 'Deletion preserves prefix-neighbor requirement');
}
if (getenv('MCP_ADAPTER_PATH')) {
    require_once getenv('MCP_ADAPTER_PATH') . '/mcp-adapter.php';
    $assert(!str_contains(wp_json_encode(\WP\MCP\Abilities\DiscoverAbilitiesAbility::execute([])), 'publishpress-checklists/'), 'Default adapter discovery excludes private actions');
    foreach (wp_get_abilities() as $ability) {
        if (!str_starts_with($ability->get_name(), 'publishpress-checklists/')) {
            continue;
        }
        $tool = \WP\MCP\Domain\Tools\McpTool::fromAbility($ability);
        $assert(!is_wp_error($tool), 'Released adapter projects ' . $ability->get_name());
        $assert(is_wp_error(\WP\MCP\Abilities\ExecuteAbilityAbility::check_permission(['ability_name' => $ability->get_name(), 'parameters' => []])), 'Default adapter rejects execution');
    }
    $tool = \WP\MCP\Domain\Tools\McpTool::fromAbility(wp_get_ability('publishpress-checklists/get-plugin-settings'));
    $assert(!is_wp_error($tool->execute([])), 'Released adapter executes authorized private tool');
}
