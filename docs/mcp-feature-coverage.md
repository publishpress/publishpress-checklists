# Checklists MCP feature coverage

All actions use the `publishpress-checklists/` namespace. Discovery reflects native post-type support and actual active modules.

| Native screen or feature | Discovery/read | Supported writes | Permission and availability |
| --- | --- | --- | --- |
| Checklists: words, title, internal/external links, link validation, excerpt | Requirement inventory, fields and saved-post results | Rules, ranges, labels, role exceptions | Filtered checklist management; saved post requires `edit_post` |
| Featured image, alternate text, caption and image checks | Native requirement classes and schemas | Native configuration fields | Same configuration permission; post-type support applies |
| Category/tag counts and required/prohibited terms; custom taxonomy counts | Requirement/choice discovery | Ranges and native term-token lists | Checklist management; term selectors also require `assign_terms` |
| Approved by role | Role choices, configuration and saved task state | Rule/roles and authorized manual approval | Checklist management for configuration; per-post edit/meta and native role checks for completion |
| Custom tasks | Native configured IDs, title, editable roles and saved state | Create, patch, remove per post type; manual completion | Configuration and per-post permissions remain distinct |
| OpenAI tasks | Instructions, rule, saved native result | Create, patch or remove instructions | Checklist management; no remote invocation or forged result |
| Who can ignore | Per-task role exceptions | Patch `can_ignore` | Native setting must enable the control |
| Task labels and ordering | Admin/editor labels, native ordering | Rename, global reset, shared reorder | Checklist management; reset requires `manage_options` |
| Settings: Post Types, General, Publishing Options, Appearance | Exact typed settings | Partial settings updates | `manage_options`; native post-type support and discovered code locks retained |
| Optional modules, including Permalinks and Yoast SEO | Feature and requirement discovery | Native module switch and available task configuration | `manage_options` for switch; integration/post-type gates for tasks |
| Pro image dimensions/counts/captions, audio/video, headings, accessibility, required/prohibited words, publishing time | Native Pro task fields and results | Rules, ranges, words/headings/time and labels | Active Pro and native module/post-type availability |
| Pro Approved by user | Eligible users and task state | User selector; authorized manual approval | `list_users` for selector; task user restriction and per-post checks for completion |
| Pro WooCommerce product requirements | Native product task schemas | Requirement ranges and backorder choices | Active WooCommerce/Pro module and supported product type; does not edit product data |
| Pro ACF requirements | Native field-derived tasks | Rules and supported counter ranges | Active ACF/Pro module and native field/type availability; no arbitrary field writes |
| Pro Rank Math / All in One SEO | Native score task schemas | Requirement configuration | Active dependency and native module gates; no SEO-content writes |
| Pro status/taxonomy filters | Targeting state and bounded live choices | Per-task/post-type exclusions | Active Pro, enabled filter, checklist management and `manage_options`; term assignment permission |
| Pro duplicated tasks | Native duplicate IDs, inherited fields and approval authority | Native duplication; global duplicate removal; permitted manual approval completion | Active Pro duplication, checklist management and `manage_options` |
| Licensing, credentials, upgrades, cleanup, external AI calls and editor UI | Configuration availability only where relevant | Outside MCP scope | Use native administration/editor flows |

## Verification

`tests/Mcp/bootstrap.php` runs against a disposable WordPress installation using the real plugin entrypoint. It requires `CHECKLISTS_MCP_TESTS=1`, `WP_LOAD_PATH` and table prefix `mcp_smoke_`. Load either Free or the actual Pro distribution in that fixture. Set `MCP_ADAPTER_PATH` to test a released adapter's private discovery, execution and schema projection.

The suite covers typed settings and task fields, previews, stale revisions, preserved credentials, bulk prevalidation, manual role/meta/object restrictions, failed persistence, Free/Pro boundaries and Pro duplication/targeting. `comparison-cases.php` also checks native validation filters, unrelated-data preservation, effective code locks, dropdown formats, restricted user accounts and duplicated approval permissions. Optional WooCommerce, ACF and SEO dependencies require their own full integration fixtures; schema inheritance alone does not prove their external evaluators. `tests/Mcp/compatibility.php` checks missing-API and lazy Pro initialization without WordPress.

The adapter format audit in `tests/Mcp/adapter-format-audit.php` saves representative alternative values for each available native field, compares preview/save/read-back and native storage, and rejects malformed mixed patches before writes. It requires `MCP_ADAPTER_PATH`. Set `CAPABILITIES_MCP_SOURCE` to an isolated Capabilities source tree to exercise its actual application-password restriction service with a temporary native credential record; no password is printed or retained. This checks the native restriction service and adapter execution, rather than a separate HTTP authentication handshake.
