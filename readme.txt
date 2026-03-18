=== MCP Abilities - Gutenberg ===
Contributors: devenia
Tags: mcp, gutenberg, block-editor, blocks, automation
Requires at least: 6.9
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gutenberg block-editor abilities for MCP. Parse, serialize, inspect, and update block content safely.

== Description ==

This plugin adds Gutenberg-focused MCP abilities so block content can be handled as structured data instead of brittle raw markup.

Included abilities:

- `gutenberg/block-guidance`
- `gutenberg/parse-content`
- `gutenberg/serialize-blocks`
- `gutenberg/get-post-blocks`
- `gutenberg/update-post-blocks`

This is useful when an MCP client needs to:

- choose the right Gutenberg block for a content scenario
- inspect Gutenberg content as nested blocks
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
