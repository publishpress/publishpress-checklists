# Checklists MCP integration

PublishPress Checklists exposes private WordPress Abilities API actions in category `publishpress`, under `publishpress-checklists/`. PublishPress Hub provides the connection through the separately installed WordPress MCP Adapter. A compatible authenticated MCP client can use the actions selected in Hub. Checklists does not require a particular AI provider.

## Discovery and configuration

Feature discovery includes native capability requirements and related read/write actions. Start with `list-features`, `list-checklist-post-types` and `list-requirements`. Use `get-requirement` to read one task's exact fields, current values and revision. Requirements belong to a post type; changing Posts does not change Pages. The inventory distinguishes usable requirements from Free's Pro previews. Optional integrations appear through their native modules when available.

`update-requirement` patches one task. `bulk-update-requirements` validates every task/post-type target before saving the combined configuration. Supply each target's `expected_hash` from a fresh read. `dry_run: true` previews native validation without saving; read again after a real save. Unknown fields, wrong JSON types, invalid choices and stale revisions are rejected. Boolean fields require JSON true/false, not strings such as "false". Lists use JSON arrays, not objects; null is not a reset value. Use an empty list or string only where discovery allows it. Bulk previews use the top-level dry_run flag, not a flag inside each target.

Rules use the native values `off` (Disabled), `warning` (Recommended) and `block` (Required). The legacy `only_display` rule is also recognized. Ranges accept JSON numbers of the discovered type or an empty string; a zero or empty maximum has no upper limit. When a modern minimum is empty, discovery follows the native legacy minimum if one is stored. Role and user selectors are arrays, not comma-separated text; numeric user choice IDs remain strings. `get-requirement-choices` returns bounded pages of live choices. User selection requires `list_users`; taxonomy selection requires the taxonomy's `assign_terms` capability. Taxonomy requirement values use native `ID__Name` tokens; use current discovery values.

`save-custom-task` creates a custom checkbox or an OpenAI instruction task, or updates an existing one. Creation requires a title that remains non-empty after native sanitization, generates an ID, and defaults to Disabled. Existing tasks retain their discovered kind; changing between a checkbox and an OpenAI task requires creating a new task. Native validation applies only to supplied fields and post types; unrelated configuration is retained. Custom checkboxes default to the editable roles returned by the plugin; set `editable_by` explicitly to limit completion. An empty role list permits nobody. Removing a custom task affects only the selected post type and retains historic post metadata. OpenAI instruction configuration does not invoke an AI service or certify a result.

`reorder-requirements` changes the native shared default order, which also affects other post types. Appearance's Priority/Alphabetical modes still apply. `reset-requirement-labels` resets renamed admin/editor labels across post types; use `checklist_hash` from `get-plugin-settings` and preview first. Custom task titles are retained.

## Settings

`get-plugin-settings` describes Post Types, General, Publishing Options and Appearance fields. `update-plugin-settings` preserves omitted fields and credentials. Boolean JSON inputs map to native `yes`/`no` or `on`/`off` values; `post_types` is a list of enabled names. Code-defined post-type support still applies: effective settings include forced post types, and discovery identifies their locks. Sorting uses `default`, `required_recommended` or `alphabetical`. Colors use six-digit hex values or an empty string. OpenAI and license credentials are not tool inputs or outputs.

`set-feature-enabled` controls discovered optional modules. Core/autoloaded modules cannot be disabled. Module and Pro filter switches take effect on a fresh request; reconnect the client if it caches the action inventory.

## Post checklists and permissions

`get-post-checklist` reads tasks for one editable saved post. Native ignored-role rules and active Pro exclusions apply. Results are saved-content server snapshots: they do not certify unsaved Gutenberg content, browser-only checks or successful publication. The integration does not publish posts or bypass editor checks.

`set-post-task-state` completes or reopens only explicitly supplied enabled manual checkboxes and approvals. It requires `edit_post`, `edit_post_meta`, the current checklist revision and the task's native role/user completion permission. Administrator status does not bypass restrictions. Automatic checks and OpenAI results cannot be set through this action. All tasks are validated before writes, and failed persistence is checked and compensated where possible.

Global requirement configuration requires the filtered `publishpress_checklists_manage_checklist_cap` capability, normally `manage_checklists`. Settings and global label reset require `manage_options`. Hub exposure never grants these permissions. Failures that report incomplete recovery require reading the affected state before retrying.

## Pro

Pro extends the same actions with native requirements, ranges and selectors. It also provides status/taxonomy exclusions and duplication. The active Pro package must contain the compatible shared Free implementation. See the Pro repository's MCP documentation for its additional controls.

## Scope

The integration does not provide arbitrary option, metadata, PHP or SQL access; installation, licensing, credential changes, migration/cleanup jobs and browser interactions remain outside these actions. Product/ACF/SEO requirement configuration does not edit the underlying product, custom field or SEO content. Use a separate authorized content tool for those changes.
