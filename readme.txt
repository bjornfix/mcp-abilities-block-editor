=== MCP Abilities - Block Editor ===
Contributors: devenia
Tags: mcp, gutenberg, block-editor, blocks, automation
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.12.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress block-editor abilities for MCP. Parse, validate, inspect, generate, and update Gutenberg content safely.

== Description ==

This plugin adds Gutenberg-focused MCP abilities so block content can be handled as structured data instead of brittle raw markup.

Included abilities:

- `gutenberg/get-theme-context`
- `gutenberg/get-style-guide`
- `gutenberg/get-style-book`
- `gutenberg/get-site-editor-summary`
- `gutenberg/get-site-editor-references`
- `gutenberg/list-available-blocks`
- `gutenberg/get-block-categories`
- `gutenberg/get-block-details`
- `gutenberg/get-block-style-variations`
- `gutenberg/list-patterns`
- `gutenberg/get-pattern`
- `gutenberg/list-synced-patterns`
- `gutenberg/get-synced-pattern`
- `gutenberg/block-guidance`
- `gutenberg/get-page-recipes`
- `gutenberg/get-section-recipes`
- `gutenberg/generate-landing-page`
- `gutenberg/generate-section`
- `gutenberg/validate-content`
- `gutenberg/audit-content`
- `gutenberg/evaluate-copy`
- `gutenberg/suggest-copy-fixes`
- `gutenberg/analyze-content`
- `gutenberg/parse-content`
- `gutenberg/serialize-blocks`
- `gutenberg/get-post-blocks`
- `gutenberg/list-templates`
- `gutenberg/get-template`
- `gutenberg/create-template`
- `gutenberg/update-template`
- `gutenberg/list-template-parts`
- `gutenberg/get-template-part`
- `gutenberg/create-template-part`
- `gutenberg/update-template-part`
- `gutenberg/list-navigations`
- `gutenberg/get-navigation`
- `gutenberg/create-navigation`
- `gutenberg/update-navigation`
- `gutenberg/find-navigation-usage`
- `gutenberg/find-template-part-usage`
- `gutenberg/find-synced-pattern-usage`
- `gutenberg/create-page-from-blocks`
- `gutenberg/create-synced-pattern`
- `gutenberg/update-synced-pattern`
- `gutenberg/extract-synced-pattern`
- `gutenberg/insert-synced-pattern-into-post`
- `gutenberg/create-page-from-pattern`
- `gutenberg/create-landing-page`
- `gutenberg/insert-pattern-into-post`
- `gutenberg/transform-blocks`
- `gutenberg/mutate-block-tree`
- `gutenberg/set-block-lock`
- `gutenberg/set-allowed-blocks`
- `gutenberg/set-template-lock`
- `gutenberg/insert-inner-block`
- `gutenberg/duplicate-block`
- `gutenberg/move-block`
- `gutenberg/replace-block-text`
- `gutenberg/get-block-bindings`
- `gutenberg/set-block-bindings`
- `gutenberg/normalize-heading-levels`
- `gutenberg/update-post-blocks`

This is useful when an MCP client needs to:

- inspect the site block/theme/style context before authoring
- inspect block metadata, categories, templates, and template parts
- inspect site-editor navigation entities and theme/site-editor summary data
- inspect template, template-part, and navigation reference relationships
- inspect synced-pattern (`core/block`) reference relationships before editing reusable content
- find reverse usage for template parts before editing shared site-editor pieces
- inspect block style variations and style-relevant theme support
- discover and reuse registered patterns and synced patterns
- choose the right Gutenberg block for a content scenario
- generate reusable sections like hero, FAQ, CTA, testimonial, stats rows, pricing, team, timeline, gallery, and contact-map layouts
- generate a structured landing page from business inputs
- validate round-trip safety, page structure, outline, link/media usage, Gutenberg-specific QA issues, copy quality, and static-block mutation risks
- turn weak copy findings into block-level rewrite suggestions
- inspect Gutenberg content as nested blocks
- preserve block formatting and attributes
- create and update block templates, template parts, and synced patterns
- extract existing blocks into synced patterns and reinsert them as reusable `core/block` references
- create and update Gutenberg navigation entities (`wp_navigation`)
- apply structural block transforms without dropping into raw HTML edits
- mutate nested block trees by path and set lock / allowed-block / template-lock attributes
- duplicate, move, and text-edit blocks without dropping into raw HTML workflows
- inspect and set block bindings for Gutenberg's metadata-driven content wiring
- update a page without breaking block comment syntax
- round-trip edited blocks back into valid WordPress content
- combine with the main plugin's generic `content/*` and `media/*` abilities for non-Gutenberg-specific media flows

Common guidance built into the plugin:

- body copy -> `core/paragraph`
- headings -> `core/heading`
- steps or bullets -> `core/list`
- quotes or testimonials -> `core/quote`
- primary calls to action -> `core/buttons`
- image plus text -> `core/media-text`
- side-by-side comparisons -> `core/columns`
- grouped wrapped sections -> `core/group`
- hero/banner sections -> `core/cover`

Requires the Abilities API plugin.

== Installation ==

1. Install and activate Abilities API.
2. Install and activate this plugin.
3. Discover the new `gutenberg/*` abilities through your MCP layer.

== Changelog ==

= 0.12.0 =
- Added a static-block render-risk guard so unsafe attr-only mutations fail fast unless explicitly overridden.
- Extended validation output with mutation guardrails for static versus dynamic blocks.

= 0.1.0 =
- Initial release.
- Added Gutenberg block guidance, parse, serialize, post read, and post update abilities.

= 0.2.0 =
- Added theme style-guide discovery for palette, typography, spacing, and global styles.
- Added block/theme context discovery and pattern listing.
- Added page recipes and landing-page generation helpers.
- Added content validation and page creation abilities.

= 0.3.0 =
- Added block categories and single-block metadata inspection.
- Added pattern retrieval, page creation from patterns, and pattern insertion into posts.
- Added reusable section recipes and section generation helpers.
- Added deeper content analysis with outline, block usage, links, and media references.
- Added block-template and template-part inspection abilities.
- Added media library listing and featured-image assignment.
- Hardened slug-safe page creation and nested-block validation.

= 0.4.0 =
- Added synced pattern (`wp_block`) listing, retrieval, creation, and updating.
- Added writable `wp_template` and `wp_template_part` abilities.
- Added Gutenberg-specific audit checks for heading structure, empty buttons, missing alt text, and spacer overuse.
- Added block-tree transform helpers for group wrapping, insertion, replacement, and removal.
- Kept generic media/content CRUD in the main plugin to avoid ability overlap.

= 0.5.0 =
- Added block style-variation and style-support inspection.
- Added nested path-based block-tree mutation helpers.
- Added block lock and `allowedBlocks` helpers for container/tooling workflows.
- Kept the plugin Gutenberg-specific and non-overlapping with the main plugin's generic content/media abilities.

= 0.6.0 =
- Added style-book summary output for theme-oriented Gutenberg authoring.
- Added `templateLock`, inner-block insertion, duplication, movement, and text-replacement helpers for direct block editing.
- Added block-bindings inspection and update helpers.

= 0.7.0 =
- Added site-editor summary and `wp_navigation` CRUD abilities.
- Fixed navigation summaries so nested navigation items are counted correctly.

= 0.8.0 =
- Added site-editor reference graph inspection.
- Added reverse navigation usage lookup.

= 0.9.0 =
- Added reverse template-part usage lookup.
- Fixed template-part usage lookup so slug-based references work even when no directly retrievable template-part entity is resolved first.

= 0.10.0 =
- Added copy-fix suggestions on top of the existing copy evaluator.
- Added synced-pattern extraction from existing block trees.
- Added synced-pattern insertion into posts as reusable `core/block` references.

= 0.11.0 =
- Added reverse usage lookup for synced patterns referenced as `core/block`.
- Expanded section generation with pricing, team, timeline, gallery, and contact-map layouts.
- Added copy evaluation heuristics for weak headings, vague CTAs, dense paragraphs, and shouty copy.

= 0.7.0 =
- Added site-editor summary output for active theme, styles, templates, parts, navigation entities, and synced patterns.
- Added `wp_navigation` listing, retrieval, creation, and updating.
- Kept the new site-editor coverage Gutenberg-specific and separate from the main plugin's generic content abilities.

= 0.8.0 =
- Added site-editor reference graph inspection for templates and template parts.
- Added navigation-usage lookup so Gutenberg navigation references can be traced across site-editor entities.
- Kept the new reference analysis Gutenberg-specific and tied to block-theme/site-editor workflows.

= 0.9.0 =
- Added reverse usage lookup for template parts.
- Extended site-editor relationship tooling so shared template parts can be traced before editing.
