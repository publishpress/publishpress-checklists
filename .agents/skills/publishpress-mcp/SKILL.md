---
name: publishpress-mcp
description: Implement or extend Checklists actions through the WordPress Abilities API. Use for action registration, exact settings schemas, permissions, task configuration, Pro integrations and native integration tests.
---

# PublishPress Checklists MCP integration

## Shared connection contract

Hub owns the optional connection through the separately installed WordPress MCP Adapter. Plugin actions register independently in shared category `publishpress`; Hub registration changes are unnecessary. Register outside admin-only paths, check API availability, guard repeated initialization, and preserve older WordPress behavior. All participating plugins must coexist under the shared category.

Use explicit input/output schemas and private `meta.public`, `meta.mcp.public` and `meta.show_in_rest` values. Describe native screen labels, exact fields, types, choices, scope and edition requirements. An annotation never grants authorization. Never expose arbitrary PHP, SQL, options, metadata or credentials.

## Native ownership

Follow Checklists' Core autoloader, legacy modules, requirement classes and PHP 8.2 compatibility. Shared actions live under Free `core/Mcp`; Pro extends them through its native initializer and hooks. Keep administration screen initialization separate from lazy native module access. Do not simulate POST forms, nonces or account switching. Update Pro's shared Free requirement through normal packaging; do not edit production vendor files or lock files as a shortcut.

Derive requirement fields from native classes and approved extension metadata. Preserve per-post-type option shapes, rule values, native range semantics, role/user arrays and taxonomy tokens. Settings booleans map to native yes/no or on/off formats; Pro's presence-based checkbox validator needs an explicit safe payload without license credentials. Retain omitted fields and unrelated post types. Use native constants and services rather than copying option names or business logic.

## Permissions and safety

Require the filtered checklist management capability for task configuration, manage_options for settings/global reset, and both native management permissions for Pro targeting/duplication. User selectors require list_users; taxonomy selectors require assign_terms. Per-post reads/writes require edit_post and enabled native post-type support. Completion additionally requires edit_post_meta and CustomItemsMeta's native role/user rules. Administrator roles never bypass denied capabilities.

Pro-only and optional integration tasks require actual active native implementations, not Free teaser classes. Never forge automatic or OpenAI results or invoke paid AI services through a configuration action. Post evaluations describe saved native snapshots and cannot certify unsaved Gutenberg/browser state or publication.

Use fresh revisions for edits, no-write previews, prevalidation of every bulk target, verified persistence and checked compensation. Report incomplete recovery honestly. Shared ordering and duplicate removal have global effects; describe their scope before mutation. Do not silently convert malformed JSON types or invent identifiers.

## Coverage and verification

Maintain docs/mcp-feature-coverage.md against every native screen and relevant editor feature. Pair reads with supported typed writes; describe intentional exclusions and external-dependency limits. Maintain mcp-prompt-examples.md with common user wording, ambiguity handling, previews, writes and read-back expectations. Documentation describes current usage, not development history.

Run the native disposable WordPress suite in tests/Mcp against Free and the actual Pro distribution. Verify all setting field round trips, bad input, stale revisions, unrelated-data/secret preservation, manual task restrictions, denied Administrators, post/meta object permissions, failure recovery and Free/Pro boundaries. Test missing API, lazy Pro compatibility, repeated registration and released-adapter private discovery/execution. Separate full optional dependency tests from schema-only contract checks. Never test writes on a live connected site without explicit authorization.
