=== MCP Abilities - Block Editor ===
Contributors: devenia
Tags: mcp, gutenberg, block-editor, blocks, automation
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress block-editor abilities for MCP. Parse, validate, inspect, generate, and update Gutenberg content safely.

== Description ==

This plugin adds Gutenberg-focused MCP abilities so block content can be handled as structured data instead of brittle raw markup.

Included abilities:

- `gutenberg/get-theme-context`
- `gutenberg/get-style-guide`
- `gutenberg/list-available-blocks`
- `gutenberg/get-block-categories`
- `gutenberg/get-block-details`
- `gutenberg/list-patterns`
- `gutenberg/get-pattern`
- `gutenberg/block-guidance`
- `gutenberg/get-page-recipes`
- `gutenberg/get-section-recipes`
- `gutenberg/generate-landing-page`
- `gutenberg/generate-section`
- `gutenberg/validate-content`
- `gutenberg/analyze-content`
- `gutenberg/parse-content`
- `gutenberg/serialize-blocks`
- `gutenberg/get-post-blocks`
- `gutenberg/list-templates`
- `gutenberg/get-template`
- `gutenberg/list-template-parts`
- `gutenberg/get-template-part`
- `gutenberg/list-media`
- `gutenberg/set-post-featured-media`
- `gutenberg/create-page-from-blocks`
- `gutenberg/create-page-from-pattern`
- `gutenberg/create-landing-page`
- `gutenberg/insert-pattern-into-post`
- `gutenberg/update-post-blocks`

This is useful when an MCP client needs to:

- inspect the site block/theme/style context before authoring
- inspect block metadata, categories, templates, and template parts
- discover and reuse registered patterns
- choose the right Gutenberg block for a content scenario
- generate reusable sections like hero, FAQ, CTA, testimonial, and stats rows
- generate a structured landing page from business inputs
- validate round-trip safety, page structure, outline, and link/media usage
- inspect Gutenberg content as nested blocks
- inspect media library items and assign featured images
- preserve block formatting and attributes
- update a page without breaking block comment syntax
- round-trip edited blocks back into valid WordPress content

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
