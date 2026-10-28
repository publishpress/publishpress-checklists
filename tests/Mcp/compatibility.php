<?php

/** Missing API and lazy Pro compatibility, without WordPress or installed dependencies. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || function_exists('wp_register_ability')) {
    exit("Run this check independently of WordPress.\n");
}
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__, 2) . '/core/Mcp/Bootstrap.php';
\PublishPress\Checklists\Core\Mcp\Bootstrap::init();
\PublishPress\Checklists\Core\Mcp\Bootstrap::init();
$hooks = [];
function add_action($name, $callback, $priority = 10, $accepted = 1)
{
    $GLOBALS['hooks'][$name][] = $callback;
}
function add_filter($name, $callback, $priority = 10, $accepted = 1)
{
    $GLOBALS['hooks'][$name][] = $callback;
}
if (isset($argv[1])) {
    require $argv[1] . '/src/Mcp/Extension.php';
    \PublishPress\ChecklistsPro\Mcp\Extension::init();
    \PublishPress\ChecklistsPro\Mcp\Extension::init();
    if (count($hooks['publishpress_checklists_mcp_register'] ?? []) !== 1) {
        throw new RuntimeException('Pro must initialize lazily without shared MCP classes.');
    }
}
echo "PASS missing API and lazy Pro compatibility.\n";
