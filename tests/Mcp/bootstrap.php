<?php

/** Native integration runner; use an isolated WordPress database only. */
declare(strict_types=1);

if (getenv('CHECKLISTS_MCP_TESTS') !== '1' || !getenv('WP_LOAD_PATH')) {
    throw new RuntimeException('Set CHECKLISTS_MCP_TESTS=1 and WP_LOAD_PATH for a disposable WordPress fixture.');
}
require getenv('WP_LOAD_PATH');
if ($GLOBALS['wpdb']->prefix !== 'mcp_smoke_') {
    throw new RuntimeException('This runner requires the disposable mcp_smoke_ table prefix.');
}
require __DIR__ . '/integration.php';
