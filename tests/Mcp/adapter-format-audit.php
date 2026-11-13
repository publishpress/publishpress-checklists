<?php

/** Save alternative values and reject malformed patches through the released adapter. */
declare(strict_types=1);

use PublishPress\Checklists\Core\Mcp\Catalog;
use PublishPress\Checklists\Core\Mcp\Options;
use PublishPress\Checklists\Core\Mcp\Registry;

if (!getenv('MCP_ADAPTER_PATH')) {
    throw new RuntimeException('The format audit requires a released MCP Adapter fixture.');
}
$auditTag = wp_insert_term('MCP audit tag ' . wp_generate_uuid4(), 'post_tag');
$assert(!is_wp_error($auditTag), 'Create isolated native tag choice');
try {
$tools = [];
$auditedSettings = 0;
$auditedTaskFields = 0;
$adapt = static function (string $name, array $input = []) use (&$tools) {
    $tools[$name] ??= \WP\MCP\Domain\Tools\McpTool::fromAbility(wp_get_ability('publishpress-checklists/' . $name));
    if (is_wp_error($tools[$name])) {
        throw new RuntimeException('Adapter could not project ' . $name);
    }
    return $tools[$name]->execute($input);
};
$settings = $ok($adapt('get-plugin-settings'), 'Adapter settings discovery');
$rootTool = \WP\MCP\Domain\Tools\McpTool::fromAbility(wp_get_ability('publishpress-checklists/get-plugin-settings'));
$ok($rootTool->execute((object) []), 'Adapter accepts a genuine root JSON object');
$originalSettings = (array) $settings['settings'];
foreach ($settings['schema']['properties'] as $name => $field) {
    if (($field['available'] ?? true) === false) {
        $before = Options::read('settings');
        $current = $adapt('get-plugin-settings');
        $assert(is_wp_error($adapt('update-plugin-settings', ['settings' => [$name => true], 'expected_hash' => $current['hash']])), 'Unavailable setting rejects adapter write ' . $name);
        $assert(Options::read('settings') === $before, 'Unavailable write unchanged');
        continue;
    }
    ++$auditedSettings;
    $values = match ($field['type']) {
        'boolean' => [true, false],
        'array' => $name === 'post_types' ? [['post'], ['post', 'page'], []] : [['category'], []],
        default => isset($field['enum']) ? $field['enum'] : (str_contains($name, 'color') ? ['', '#AAbb01'] : ['', 'dashicons-saved']),
    };
    foreach ($values as $value) {
        $state = $adapt('get-plugin-settings');
        $before = Options::read('settings');
        $args = ['settings' => [$name => $value], 'expected_hash' => $state['hash']];
        $preview = $ok($adapt('update-plugin-settings', $args + ['dry_run' => true]), 'Adapter settings preview ' . $name);
        $assert(Options::read('settings') === $before, 'Preview has no writes ' . $name);
        $saved = $ok($adapt('update-plugin-settings', $args), 'Adapter settings save ' . $name);
        $native = Options::read('settings')[$name];
        $expected = isset($field['storage']) ? ($value ? ($field['storage'] === 'yes-no' ? 'yes' : 'on') : ($field['storage'] === 'yes-no' ? 'no' : 'off')) : $value;
        if ($name === 'post_types') {
            $expected = array_fill_keys($field['items']['enum'], 'off');
            foreach ($value as $type) {
                $expected[$type] = 'on';
            }
            $expected = Options::legacy()->settings->clearPostTypesOptions($expected, Options::legacy()->modules->settings->post_type_support);
            $expected = array_merge((array) ($before[$name] ?? []), $expected);
        }
        $assert($native === $expected, 'Exact native stored format ' . $name);
        $assert($saved['settings']->$name === $preview['settings']->$name, 'Preview and save agree ' . $name);
        $read = $adapt('get-plugin-settings');
        $assert($read['settings']->$name === $saved['settings']->$name, 'Adapter read-back agrees ' . $name);
        $expectedAll = $before;
        $expectedAll[$name] = $native;
        $assert(Options::read('settings') === $expectedAll, 'Preserve omitted native settings ' . $name);
    }
    $state = $adapt('get-plugin-settings');
    $ok($adapt('update-plugin-settings', ['settings' => [$name => $originalSettings[$name]], 'expected_hash' => $state['hash']]), 'Restore setting ' . $name);
    $invalid = match ($field['type']) {
        'boolean' => ['false', 'true', 0, 1, null, [], (object) []],
        'array' => ['false', false, 1, null, (object) [], ['not-a-native-choice']],
        default => [false, 1, null, [], (object) []],
    };
    if (isset($field['enum']) || isset($field['pattern'])) {
        $invalid[] = 'not-a-valid-choice';
    }
    foreach ($invalid as $value) {
        $state = $adapt('get-plugin-settings');
        $before = Options::read('settings');
        $other = $name === 'show_warning_icon_submit' ? 'disable_publish_button' : 'show_warning_icon_submit';
        $assert(is_wp_error($adapt('update-plugin-settings', ['settings' => [$other => !(bool) $state['settings']->$other, $name => $value], 'expected_hash' => $state['hash']])), 'Reject mixed malformed adapter patch ' . $name);
        $assert(Options::read('settings') === $before, 'Malformed adapter patch performs no write');
    }
}
$state = $adapt('get-plugin-settings');
$before = Options::read('settings');
$objectPreview = $ok($adapt('update-plugin-settings', ['settings' => (object) ['show_warning_icon_submit' => false], 'expected_hash' => $state['hash'], 'dry_run' => true]), 'Adapter accepts a genuine nested JSON object');
$assert($objectPreview['settings']->show_warning_icon_submit === false && Options::read('settings') === $before, 'JSON object preview has exact boolean semantics');
$assert(is_wp_error($adapt('update-plugin-settings', ['settings' => ['invented_field' => false, 'disable_publish_button' => true], 'expected_hash' => $state['hash']])), 'Adapter rejects unknown setting');
$assert(Options::read('settings') === $before, 'Unknown field patch unchanged');
$requirements = $adapt('list-requirements', ['post_type' => 'post', 'per_page' => 100]);
foreach ($requirements['items'] as $row) {
    if (!$row['available']) {
        continue;
    }
    $selector = ['post_type' => 'post', 'requirement' => $row['id']];
    $original = $ok($adapt('get-requirement', $selector), 'Adapter requirement discovery ' . $row['id']);
    foreach ($original['schema']['properties'] as $name => $field) {
        if ($name === 'can_ignore' && !$adapt('get-plugin-settings')['settings']->who_can_ignore_option) {
            continue;
        }
        ++$auditedTaskFields;
        if (isset($field['anyOf'])) {
            $value = ($field['anyOf'][0]['type'] ?? '') === 'number' ? 7.25 : 7;
            $patch = ['min' => $value, 'max' => 100];
        } elseif (($field['type'] ?? '') === 'array') {
            $choice = $ok($adapt('get-requirement-choices', $selector + ['field' => $name, 'per_page' => 1]), 'Adapter choices ' . $name);
            $value = $choice['items'] ? [(string) $choice['items'][0]['value']] : (in_array($name, ['users', 'roles', 'editable_by', 'can_ignore'], true) || Catalog::taxonomy(Catalog::instances('post')[$row['id']]) ? [] : ['audit phrase']);
            $patch = [$name => $value];
        } else {
            $value = $field['enum'][0] ?? ($name === 'value' ? '09:30' : ($name === 'title' ? "Audit title\nSecond line" : 'Audit label'));
            $patch = [$name => $value];
        }
        $state = $adapt('get-requirement', $selector);
        $args = $selector + ['settings' => $patch, 'expected_hash' => $state['hash']];
        $before = Options::read('checklists');
        $preview = $ok($adapt('update-requirement', $args + ['dry_run' => true]), 'Adapter requirement preview ' . $row['id'] . '/' . $name);
        $assert(Options::read('checklists') === $before, 'Requirement preview performs no write');
        $saved = $ok($adapt('update-requirement', $args), 'Adapter requirement save ' . $row['id'] . '/' . $name);
        $native = Options::read('checklists');
        foreach ($patch as $suffix => $inputValue) {
            $expected = is_array($inputValue) ? array_map('sanitize_text_field', $inputValue) : ($suffix === 'title' ? sanitize_textarea_field($inputValue) : sanitize_text_field($inputValue));
            $assert(($native[$row['id'] . '_' . $suffix]['post'] ?? null) === $expected, 'Exact task native storage ' . $row['id'] . '/' . $suffix);
        }
        $assert((array) $preview['settings'] === (array) $saved['settings'], 'Requirement preview equals native save');
        $state = $adapt('get-requirement', $selector);
        $assert((array) $state['settings'] === (array) $saved['settings'], 'Requirement adapter read-back');
        $invalidValues = isset($field['anyOf']) ? ['false', '7', true, null, [], (object) []]
            : (($field['type'] ?? '') === 'array' ? ['false', false, null, (object) [], [false], [123]] : [false, 123, null, [], (object) []]);
        if (isset($field['enum']) || isset($field['pattern'])) {
            $invalidValues[] = 'not-a-valid-choice';
        }
        if (isset($field['items']['enum'])) {
            $invalidValues[] = ['not-a-native-choice'];
        } elseif ($name === 'users') {
            $invalidValues[] = ['999999999'];
        } elseif ($name === 'multiple' && Catalog::taxonomy(Catalog::instances('post')[$row['id']])) {
            $invalidValues[] = ['999999999__Unknown'];
        }
        foreach ($invalidValues as $invalidValue) {
            $current = $adapt('get-requirement', $selector);
            $beforeInvalid = Options::read('checklists');
            $otherField = $name === 'custom_label' ? 'editor_label' : 'custom_label';
            $invalidPatch = [$otherField => 'Must not save', $name => $invalidValue];
            $assert(is_wp_error($adapt('update-requirement', $selector + ['settings' => $invalidPatch, 'expected_hash' => $current['hash']])), 'Adapter rejects malformed task field ' . $row['id'] . '/' . $name);
            $assert(Options::read('checklists') === $beforeInvalid, 'Malformed task patch is atomic');
        }
    }
    $state = $adapt('get-requirement', $selector);
    $restore = (array) $original['settings'];
    foreach ($original['schema']['properties'] as $name => $field) {
        if (($field['available'] ?? true) === false) {
            unset($restore[$name]);
        }
    }
    $ok($adapt('update-requirement', $selector + ['settings' => $restore, 'expected_hash' => $state['hash']]), 'Restore requirement ' . $row['id']);
}
$textTask = $ok($adapt('save-custom-task', ['post_type' => 'post', 'settings' => ['title' => 'false']]), 'Literal string false is valid task text');
$textSelector = ['post_type' => 'post', 'requirement' => $textTask['id']];
$textRead = $adapt('get-requirement', $textSelector);
$before = Options::read('checklists');
$assert(is_wp_error($adapt('save-custom-task', ['post_type' => 'post', 'id' => $textTask['id'], 'kind' => 'openai', 'settings' => ['title' => 'Do not convert silently'], 'expected_hash' => $textRead['hash']])), 'Reject mismatched existing task kind');
$assert(Options::read('checklists') === $before, 'Contradictory task kind performs no write');

$assert($textRead['settings']->title === 'false', 'Literal false string is not a boolean');
$before = Options::read('checklists');
$assert(is_wp_error($adapt('update-requirement', $textSelector + ['settings' => ['title' => '<i></i>', 'rule' => 'block'], 'expected_hash' => $textRead['hash']])), 'Reject sanitized-empty title in mixed existing-task patch');
$assert(Options::read('checklists') === $before, 'Existing title rejection has no writes');
$ok($adapt('delete-custom-task', $textSelector + ['expected_hash' => $textRead['hash']]), 'Remove text audit task');
$postState = $ok($adapt('get-post-checklist', ['post_id' => $postId]), 'Adapter post checklist discovery');
$stateTool = \WP\MCP\Domain\Tools\McpTool::fromAbility(wp_get_ability('publishpress-checklists/set-post-task-state'));
$beforeMeta = get_post_meta($postId);
$ok($stateTool->execute((object) ['post_id' => $postId, 'states' => (object) [$id => false], 'expected_hash' => $postState['hash'], 'dry_run' => true]), 'Adapter object-map task state preview');
$assert(get_post_meta($postId) === $beforeMeta, 'Object task-state preview has no writes');
foreach ([false, true] as $stateValue) {
    $currentPost = $adapt('get-post-checklist', ['post_id' => $postId]);
    $ok($stateTool->execute((object) ['post_id' => $postId, 'states' => (object) [$id => $stateValue], 'expected_hash' => $currentPost['hash']]), 'Adapter actual object-map state save');
    $assert(get_post_meta($postId, \PublishPress\Checklists\Core\Utils\CustomItemsMeta::getMetaKey($id), true) === ($stateValue ? 'yes' : 'no'), 'Adapter state uses native yes/no metadata');
}

$assert(is_wp_error($adapt('get-post-checklist', ['post_id' => (string) $postId])), 'Numeric-string post IDs are rejected');
$selector = ['post_type' => 'post', 'requirement' => 'words_count'];
$legacyOptions = Options::read('checklists');
$legacyOptions['words_count_min']['post'] = '';
$legacyOptions['min_words_count_value']['post'] = '23';
$ok(Options::save('checklists', $legacyOptions), 'Seed native legacy counter');
$legacyRead = $adapt('get-requirement', $selector);
$assert($legacyRead['settings']->min === 23, 'Discovery matches native legacy range fallback');
$before = Options::read('checklists');
$preview = $ok($adapt('update-requirement', $selector + ['settings' => ['min' => ''], 'expected_hash' => $legacyRead['hash'], 'dry_run' => true]), 'Preview native legacy fallback');
$assert($preview['settings']->min === 23 && Options::read('checklists') === $before, 'Empty minimum preview preserves native legacy semantics');
$state = $adapt('get-plugin-settings');
$ignoreValue = $state['settings']->who_can_ignore_option;
$state = $ok($adapt('update-plugin-settings', ['settings' => ['who_can_ignore_option' => false], 'expected_hash' => $state['hash']]), 'Disable role exception control');
$before = Options::read('checklists');
$assert(is_wp_error($adapt('save-custom-task', ['post_type' => 'post', 'settings' => ['title' => 'Disabled role exceptions', 'can_ignore' => ['administrator']]])), 'Creation respects disabled role exception control');
$assert(Options::read('checklists') === $before, 'Disabled-control creation has no writes');
$ok($adapt('update-plugin-settings', ['settings' => ['who_can_ignore_option' => $ignoreValue], 'expected_hash' => $state['hash']]), 'Restore role exception control');
$state = $adapt('get-requirement', $selector);
$before = Options::read('checklists');
$assert(is_wp_error($adapt('bulk-update-requirements', ['changes' => [$selector + ['settings' => ['custom_label' => 'Never save this'], 'expected_hash' => $state['hash']], ['post_type' => 'post', 'requirement' => 'not-a-task', 'settings' => ['rule' => 'block'], 'expected_hash' => $state['hash']]]])), 'Adapter bulk prevalidates every target');
$assert(Options::read('checklists') === $before, 'Adapter mixed bulk has no writes');
$assert(is_wp_error($adapt('bulk-update-requirements', ['changes' => [$selector + ['settings' => ['custom_label' => 'Must remain a preview'], 'expected_hash' => $state['hash'], 'dry_run' => true]]])), 'Per-target dry_run is rejected instead of ignored');
$assert(Options::read('checklists') === $before, 'Misplaced bulk preview flag cannot write');

$assert(is_wp_error($adapt('update-requirement', $selector + ['settings' => ['min' => 12], 'expected_hash' => str_repeat('0', 64)])), 'Adapter rejects stale task revision');
$assert(Options::read('checklists') === $before, 'Stale adapter request has no writes');
if (getenv('CAPABILITIES_MCP_SOURCE')) {
    require_once getenv('CAPABILITIES_MCP_SOURCE') . '/includes/application-passwords.php';
    $scopeOptions = ['cme_capabilities_application_password_capabilities', pp_capabilities_application_password_option_name()];
    $scopeSnapshot = [];
    foreach ($scopeOptions as $key) {
        $scopeSnapshot[$key] = get_option($key, null);
    }
    $passwordEntry = WP_Application_Passwords::create_new_application_password($admin->ID, ['name' => 'Checklists isolated audit']);
    $assert(!is_wp_error($passwordEntry), 'Create isolated application-password record');
    $entry = $passwordEntry[1];
    unset($passwordEntry);
    $scoper = PP_Capabilities_Application_Password_Capabilities::instance();
    try {
        update_option('cme_capabilities_application_password_capabilities', 1);
        do_action('application_password_did_authenticate', $admin, $entry);
        $key = pp_capabilities_application_password_storage_key($admin->ID, $entry['uuid']);
        foreach (['manage_options' => ['get-plugin-settings', []], 'manage_checklists' => ['list-requirements', ['post_type' => 'post']], 'edit_posts' => ['get-post-checklist', ['post_id' => $postId]]] as $denied => [$action, $args]) {
            update_option(pp_capabilities_application_password_option_name(), [$key => ['user_id' => $admin->ID, 'uuid' => $entry['uuid'], 'denied' => [$denied => true]]]);
            $assert(!current_user_can($denied), 'Native application-password service denies ' . $denied);
            $assert(is_wp_error($adapt($action, $args)), 'Adapter respects native application-password denial ' . $denied);
            $assert(get_current_user_id() === $admin->ID, 'Scope test retains authenticated Administrator identity');
        }
    } finally {
        remove_filter('user_has_cap', [$scoper, 'filter_user_capabilities'], PHP_INT_MAX);
        remove_action('application_password_did_authenticate', [$scoper, 'capture_application_password'], 10);
        WP_Application_Passwords::delete_application_password($admin->ID, $entry['uuid']);
        foreach ($scopeSnapshot as $key => $value) {
            $value === null ? delete_option($key) : update_option($key, $value);
        }
    }
}
$state = $adapt('get-requirement', $selector);
$previewArgs = $selector + ['settings' => ['custom_label' => 'Repeated preview'], 'expected_hash' => $state['hash'], 'dry_run' => true];
$ok($adapt('update-requirement', $previewArgs), 'Initialize native task validators');
$countValidators = static fn() => array_sum(array_map('count', $GLOBALS['wp_filter']['publishpress_checklists_validate_requirement_settings']->callbacks));
$beforeCount = $countValidators();
$ok($adapt('update-requirement', $previewArgs), 'Repeat native validation preview');
$ok($adapt('update-requirement', $previewArgs), 'Repeat native validation again');
$assert($countValidators() === $beforeCount, 'Repeated task operations do not accumulate duplicate validators');
$typeInventory = $ok($adapt('list-checklist-post-types'), 'Adapter native post-type inventory');
$assert(in_array('post', array_column($typeInventory['items'], 'name'), true), 'Adapter discovers native Posts');
$word = ['post_type' => 'post', 'requirement' => 'words_count'];
$title = ['post_type' => 'post', 'requirement' => 'title_count'];
$wordState = $adapt('get-requirement', $word);
$titleState = $adapt('get-requirement', $title);
$changes = [$word + ['settings' => ['custom_label' => 'Bulk word label'], 'expected_hash' => $wordState['hash']], $title + ['settings' => ['custom_label' => 'Bulk title label'], 'expected_hash' => $titleState['hash']]];
$before = Options::read('checklists');
$ok($adapt('bulk-update-requirements', ['changes' => $changes, 'dry_run' => true]), 'Adapter valid bulk preview');
$assert(Options::read('checklists') === $before, 'Bulk preview unchanged');
$ok($adapt('bulk-update-requirements', ['changes' => $changes]), 'Adapter valid bulk save');
$assert(Options::read('checklists')['words_count_custom_label']['post'] === 'Bulk word label' && Options::read('checklists')['title_count_custom_label']['post'] === 'Bulk title label', 'Adapter bulk native read-back');
$state = $adapt('get-requirement', $word);
$before = Options::read('checklists');
$ok($adapt('reorder-requirements', ['post_type' => 'post', 'requirements' => ['words_count', 'title_count'], 'expected_hash' => $state['hash'], 'dry_run' => true]), 'Adapter reorder preview');
$assert(Options::read('checklists') === $before, 'Reorder preview unchanged');
$ok($adapt('reorder-requirements', ['post_type' => 'post', 'requirements' => ['words_count', 'title_count'], 'expected_hash' => $state['hash']]), 'Adapter native shared reorder');
$storedOrder = array_keys((array) get_option(Options::key('checklists')));
$assert(array_slice($storedOrder, 0, 2) === ['words_count_rule', 'title_count_rule'], 'Native option insertion order is retained');
$features = $adapt('list-features');
foreach ($features['features'] as $feature) {
    if (isset($feature['switchable']) && !$feature['switchable']) {
        $assert(is_wp_error($adapt('set-feature-enabled', ['feature' => $feature['feature'], 'enabled' => false, 'expected_hash' => $features['hash'], 'dry_run' => true])), 'Adapter respects native core module lock');
        break;
    }
}
if (Options::pro()) {
    $target = $ok($adapt('get-requirement-targeting', $word), 'Adapter targeting discovery');
    $originalTarget = (array) $target['targeting'];
    $choices = $ok($adapt('get-targeting-choices', $word + ['field' => 'excluded_terms', 'per_page' => 1]), 'Adapter targeting choices');
    $targetPatch = ['excluded_statuses' => ['pending'], 'excluded_terms' => $choices['items'] ? [$choices['items'][0]['value']] : []];
    $targetKeys = [\PublishPress\ChecklistsPro\StatusFilter\StatusFilter::OPTION_NAME, \PublishPress\ChecklistsPro\TaxonomyFilter\TaxonomyFilter::OPTION_NAME];
    $beforeTarget = array_map(static fn($key) => get_option($key, []), $targetKeys);
    $ok($adapt('update-requirement-targeting', $word + ['targeting' => $targetPatch, 'expected_hash' => $target['hash'], 'dry_run' => true]), 'Adapter targeting preview');
    $assert(array_map(static fn($key) => get_option($key, []), $targetKeys) === $beforeTarget, 'Targeting preview unchanged');
    $target = $ok($adapt('update-requirement-targeting', $word + ['targeting' => $targetPatch, 'expected_hash' => $target['hash']]), 'Adapter targeting save');
    foreach ($targetKeys as $index => $key) {
        $field = $index === 0 ? 'excluded_statuses' : 'excluded_terms';
        $assert(get_option($key)['post']['words_count'] === $targetPatch[$field], 'Exact native targeting representation');
    }
    foreach (['false', false, (object) [], [123], null] as $badTerms) {
        $beforeTarget = array_map(static fn($key) => get_option($key, []), $targetKeys);
        $assert(is_wp_error($adapt('update-requirement-targeting', $word + ['targeting' => ['excluded_statuses' => ['draft'], 'excluded_terms' => $badTerms], 'expected_hash' => $target['hash']])), 'Adapter rejects malformed mixed targeting');
        $assert(array_map(static fn($key) => get_option($key, []), $targetKeys) === $beforeTarget, 'Malformed targeting is atomic');
    }
    $ok($adapt('update-requirement-targeting', $word + ['targeting' => $originalTarget, 'expected_hash' => $target['hash']]), 'Restore targeting through adapter');
    $state = $adapt('get-requirement', $word);
    $before = [Options::read('checklists'), \PublishPress\ChecklistsPro\Mcp\Duplicates::revision()];
    $ok($adapt('duplicate-requirement', $word + ['expected_hash' => $state['hash'], 'dry_run' => true]), 'Adapter duplicate preview');
    $assert([Options::read('checklists'), \PublishPress\ChecklistsPro\Mcp\Duplicates::revision()] === $before, 'Duplicate preview does not write either option');
    $copy = $ok($adapt('duplicate-requirement', $word + ['expected_hash' => $state['hash']]), 'Adapter duplicate save');
    $copySelector = ['post_type' => 'post', 'requirement' => $copy['id']];
    $copyState = $ok($adapt('get-requirement', $copySelector), 'Adapter duplicate native read-back');
    $ok($adapt('delete-duplicate-requirement', $copySelector + ['expected_hash' => $copyState['hash']]), 'Adapter duplicate deletion');
    $assert(is_wp_error($adapt('get-requirement', $copySelector)), 'Adapter deleted duplicate is unavailable');
}
$settings = $adapt('get-plugin-settings');
$before = Options::read('checklists');
$ok($adapt('reset-requirement-labels', ['expected_hash' => $settings['checklist_hash'], 'dry_run' => true]), 'Adapter label-reset preview');
$assert(Options::read('checklists') === $before, 'Label-reset preview unchanged');
$ok($adapt('reset-requirement-labels', ['expected_hash' => $settings['checklist_hash']]), 'Adapter native label reset');
$assert(!isset(Options::read('checklists')['words_count_custom_label']), 'Native label reset read-back');
$assert((Options::read('checklists')[$id . '_title'] ?? null) === ($before[$id . '_title'] ?? null), 'Global label reset preserves task titles');
// Native sanitization must not turn a valid-looking task title into an unusable saved task.
$before = Options::read('checklists');
$assert(is_wp_error($adapt('save-custom-task', ['post_type' => 'post', 'settings' => ['title' => '<b></b>']])), 'Reject a title empty after native sanitization');
$assert(Options::read('checklists') === $before, 'Rejected sanitized title performs no write');
$assert(is_wp_error($adapt('save-custom-task', ['post_type' => 'post', 'settings' => ['title' => '0']])), 'Native empty-string-zero title semantics');
$assert(Options::read('checklists') === $before, 'Native-empty zero title performs no write');

echo 'Adapter format audit: ' . $auditedSettings . ' available settings; ' . $auditedTaskFields . " task fields.\n";
} finally {
    wp_delete_term($auditTag['term_id'], 'post_tag');
}
