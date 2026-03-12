<?php
/**
 * Enable all FREE PublishPress Checklists requirements as "Required" for active post types.
 *
 * Usage:
 *   wp eval-file tests/scripts/checklists-enable-all-free.php
 */

if (!defined('ABSPATH')) {
    $dir = getcwd();
    while ($dir !== dirname($dir)) {
        $wpLoad = $dir . '/wp-load.php';
        if (file_exists($wpLoad)) {
            require_once $wpLoad;
            break;
        }
        $dir = dirname($dir);
    }
}

if (!function_exists('get_option') || !function_exists('update_option')) {
    fwrite(STDERR, "WordPress is not loaded. Run from inside a WP install.\n");
    exit(1);
}

$options = get_option('publishpress_checklists_checklists_options');
if (!is_object($options)) {
    $options = new stdClass();
}

$settings = get_option('publishpress_checklists_settings_options');
$activePostTypes = [];
if (is_object($settings) && isset($settings->post_types)) {
    foreach ((array)$settings->post_types as $postType => $status) {
        if ($status === 'on') {
            $activePostTypes[] = (string)$postType;
        }
    }
}
if (empty($activePostTypes)) {
    $activePostTypes = ['post'];
}

$baseDir = dirname(__DIR__, 2);
$scanDirs = [
    $baseDir . '/core/Requirement',
    $baseDir . '/modules',
];

$requirementIds = [];
$counterIds = [];
$files = [];

foreach ($scanDirs as $scanDir) {
    if (!is_dir($scanDir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        /** @var SplFileInfo $fileInfo */
        if (!$fileInfo->isFile()) {
            continue;
        }

        $path = $fileInfo->getPathname();
        if (substr($path, -4) !== '.php') {
            continue;
        }

        if (strpos($path, '/Requirement/') === false && strpos($path, '/core/Requirement/') === false) {
            continue;
        }

        $files[] = $path;
    }
}

$ignoreRequirementIds = [
    'custom_item' => true,
    'openai_item' => true,
];

foreach ($files as $path) {
    $content = @file_get_contents($path);
    if ($content === false) {
        continue;
    }

    if (preg_match('/class\\s+\\w+\\s+extends\\s+Pro_Requirement\\b/', $content)) {
        continue;
    }

    if (preg_match('/(?:\\$this->name|(?:public|protected)\\s+\\$name)\\s*=\\s*[\"\']([^\"\']+)[\"\']\\s*;/', $content, $match)) {
        $id = trim($match[1]);
        if ($id === '' || isset($ignoreRequirementIds[$id])) {
            continue;
        }

        $requirementIds[$id] = true;

        if (preg_match('/class\\s+\\w+\\s+extends\\s+Base_counter\\b/', $content)) {
            $counterIds[$id] = true;
        }
    }
}

if (empty($requirementIds)) {
    fwrite(STDERR, "No free requirements detected.\n");
    exit(1);
}

$enabledCount = 0;
$counterDefaultsSet = 0;

foreach (array_keys($requirementIds) as $requirementId) {
    $ruleKey = $requirementId . '_rule';
    if (!isset($options->{$ruleKey}) || !is_array($options->{$ruleKey})) {
        $options->{$ruleKey} = [];
    }

    foreach ($activePostTypes as $postType) {
        $options->{$ruleKey}[$postType] = 'block';
        $enabledCount++;
    }

    if (!isset($counterIds[$requirementId])) {
        continue;
    }

    $minKey = $requirementId . '_min';
    $maxKey = $requirementId . '_max';

    if (!isset($options->{$minKey}) || !is_array($options->{$minKey})) {
        $options->{$minKey} = [];
    }
    if (!isset($options->{$maxKey}) || !is_array($options->{$maxKey})) {
        $options->{$maxKey} = [];
    }

    foreach ($activePostTypes as $postType) {
        $hasMin = array_key_exists($postType, $options->{$minKey}) && $options->{$minKey}[$postType] !== '';
        $hasMax = array_key_exists($postType, $options->{$maxKey}) && $options->{$maxKey}[$postType] !== '';

        if (!$hasMin && !$hasMax) {
            $options->{$minKey}[$postType] = 1;
            $options->{$maxKey}[$postType] = 2;
            $counterDefaultsSet++;
            continue;
        }

        if ($hasMin && (int)$options->{$minKey}[$postType] < 1) {
            $options->{$minKey}[$postType] = 1;
            $counterDefaultsSet++;
        }

        if ($hasMax && (int)$options->{$maxKey}[$postType] !== 2) {
            $options->{$maxKey}[$postType] = 2;
            $counterDefaultsSet++;
        }
    }
}

$updated = update_option('publishpress_checklists_checklists_options', $options);

echo "Free requirements set to Required: {$enabledCount}\n";
echo "Counter defaults initialized/normalized: {$counterDefaultsSet}\n";
echo 'Post types: ' . implode(', ', $activePostTypes) . "\n";
echo $updated ? "Option updated.\n" : "No option diff (already up to date).\n";
