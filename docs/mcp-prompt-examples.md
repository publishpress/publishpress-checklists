# Checklists MCP testing prompts

Use a test site and specify the post type or post ID. Ask the client to discover available fields, preview changes and read saved values back. The expected action sequence is shown for each example.

| Prompt | Expected behavior |
| --- | --- |
| "Show me the checklist requirements available for Posts, including which need Pro." | Inventory distinguishes real requirements and unavailable previews. |
| "Require Posts to have 300-1500 words. Leave Pages unchanged. Preview first." | Read counter; preview/update range and rule with revision; read back. |
| "Make the featured-image and excerpt tasks recommended for Pages." | Discover both; prevalidate and apply one bulk change. |
| "Require the News category on Posts." | Resolve a live native term token, check term permission, then update. |
| "Add a recommended Editorial review checkbox for Posts that only Editors can complete." | Resolve role; create custom task with explicit editable roles; read back. |
| "Complete Editorial review on post 123." | Require exact post/task, edit/meta permission and matching allowed role. |
| "Mark the word-count task complete even though the post is too short." | Reject automatic-result forgery. |
| "Add an OpenAI task asking whether the introduction is clear." | Configure instructions only; do not claim remote execution. |
| "Enable checklists for Pages and hide the publish button until required tasks pass." | Typed settings patch; retain unrelated settings/credentials. |
| "Use alphabetical task ordering and change incomplete required tasks to red." | Discover native appearance keys and valid color format, preview and save. |
| "Reset renamed task labels everywhere." | Explain global scope; use checklist revision and preview reset. |
| "Duplicate the word-count requirement for Posts." | Pro/native duplication; discover generated ID and inherited fields. |
| "Hide the word-count task for drafts, and exclude posts in News." | Pro filter settings, targeting choice discovery, explicit task/post-type exclusions and read back. |
| "Only user Jane may approve this post." | Pro eligible-user discovery; resolve ambiguity; update approval task, then enforce user permission for completion. |
| "Require a featured image at least 1200 pixels wide." | Active Pro width task, integer range and rule. |
| "Require the phrase editorial policy and prohibit click here." | Active Pro word lists, not comma-separated scalar guesses. |
| "Require a product discount between 10.5 and 30 percent and disallow backorders." | Active WooCommerce/Pro task discovery; numeric ranges and native backorder enum; no product-data change. |
| "Require the ACF field Summary to be filled." | Resolve the actual active field-derived requirement; no invented field identifiers. |

For permission checks, repeat configuration requests with `manage_checklists` denied, settings with `manage_options` denied, and task requests with the individual post or metadata permission denied. All must fail even for an Administrator. Repeat manual completion with a role/user outside the task's allowed list. A stale hash must require a fresh read rather than silently overwriting another user's changes.
