# MCP Abilities - Block Editor

WordPress block-editor abilities for MCP. This add-on makes Gutenberg content usable as structured data instead of fragile raw HTML strings.

## What It Adds

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

## Why This Exists

Gutenberg content is stored as block markup with comment delimiters, nested blocks, and attribute payloads. That is easy to break when an MCP client edits HTML directly.

This plugin provides a round-trip workflow:

1. Read block content as a normalized block tree.
2. Edit the tree in MCP.
3. Serialize it back into valid Gutenberg content.
4. Write it back to the post safely.

It also provides block-choice guidance so an MCP client can pick the right block for the content instead of faking layout with paragraphs and ad hoc HTML.
This version also adds authoring helpers for real page builds: theme/style discovery, block metadata, pattern inspection and insertion, section recipes, template and template-part inspection, deeper content analysis, and one-step page creation.

## Requirements

- WordPress `6.9+`
- PHP `8.0+`
- [Abilities API](https://github.com/WordPress/abilities-api)

## Suggested Workflow

1. Use `gutenberg/block-guidance` when the MCP client needs to decide which block fits a scenario.
2. Use `gutenberg/list-available-blocks`, `gutenberg/get-block-categories`, `gutenberg/get-block-details`, and `gutenberg/get-block-style-variations` to understand the site block surface.
3. Use `gutenberg/list-patterns` and `gutenberg/get-pattern` to discover reusable pattern content.
4. Use `gutenberg/get-page-recipes`, `gutenberg/get-section-recipes`, `gutenberg/generate-section`, or `gutenberg/generate-landing-page` to draft content.
5. Use `gutenberg/validate-content`, `gutenberg/audit-content`, `gutenberg/evaluate-copy`, `gutenberg/suggest-copy-fixes`, and `gutenberg/analyze-content` to catch weak structure, weak writing, missing hierarchy, link/media issues, and round-trip problems.
6. Use `gutenberg/create-page-from-blocks`, `gutenberg/create-page-from-pattern`, or `gutenberg/create-landing-page` to create the page.
7. Use `gutenberg/get-site-editor-summary`, `gutenberg/get-site-editor-references`, `gutenberg/list-templates`, `gutenberg/get-template`, `gutenberg/create-template`, `gutenberg/update-template`, `gutenberg/list-template-parts`, `gutenberg/get-template-part`, `gutenberg/list-navigations`, `gutenberg/get-navigation`, `gutenberg/find-navigation-usage`, `gutenberg/find-template-part-usage`, and `gutenberg/find-synced-pattern-usage` when the active block theme or reusable block relationships matter.
8. Use the main plugin's generic media abilities for uploads, media lookup, and featured-image updates.
9. Use `gutenberg/list-synced-patterns`, `gutenberg/get-synced-pattern`, `gutenberg/create-synced-pattern`, `gutenberg/update-synced-pattern`, `gutenberg/extract-synced-pattern`, and `gutenberg/insert-synced-pattern-into-post` for reusable block content.
10. Use `gutenberg/get-post-blocks`, `gutenberg/insert-pattern-into-post`, `gutenberg/transform-blocks`, `gutenberg/mutate-block-tree`, `gutenberg/set-block-lock`, `gutenberg/set-allowed-blocks`, `gutenberg/set-template-lock`, `gutenberg/insert-inner-block`, `gutenberg/duplicate-block`, `gutenberg/move-block`, `gutenberg/replace-block-text`, `gutenberg/get-block-bindings`, `gutenberg/set-block-bindings`, `gutenberg/normalize-heading-levels`, and `gutenberg/update-post-blocks` to iterate safely.

## Which Block To Use

- `core/paragraph`: default for normal body copy and supporting text.
- `core/heading`: section titles and document hierarchy.
- `core/list`: steps, checklists, features, and grouped bullet content.
- `core/quote`: real quotations and testimonials.
- `core/buttons`: primary calls to action.
- `core/image`: a single standalone image.
- `core/media-text`: one image paired closely with one text region.
- `core/columns`: side-by-side comparisons or short parallel sections.
- `core/group`: a wrapper for blocks that belong together visually or semantically.
- `core/cover`: hero/banner sections with background media.
- `core/separator`: visible section division.
- `core/spacer`: whitespace only.
- Plugin/custom blocks: use these when the feature depends on special rendering or schema, such as FAQs or plugin-owned components.

## Scenario Guidance

- For plain article text, use `core/paragraph`, not bold text inside random wrappers.
- For headings, use `core/heading`, not enlarged paragraphs.
- For CTAs, use `core/buttons`, not inline links pretending to be buttons.
- For paired image-plus-copy layouts, prefer `core/media-text` before reaching for manual columns.
- For reusable or semantic features owned by plugins, prefer the plugin block instead of imitating it with core blocks.

## Block Shape

The plugin exposes blocks in a normalized shape:

```json
{
  "block_name": "core/paragraph",
  "attrs": {},
  "inner_blocks": [],
  "inner_html": "<p>Hello world</p>",
  "inner_content": ["<p>Hello world</p>"]
}
```

`serialize-blocks` and `update-post-blocks` accept both this normalized shape and the native `parse_blocks()` shape.

## Notes

- The write ability requires edit access to the target post.
- `update-post-blocks` can save either `content` directly or serialize `blocks`.
- `gutenberg/block-guidance` returns machine-readable recommendations for common scenarios.
- `gutenberg/generate-landing-page` intentionally uses core blocks and conservative theme-compatible markup.
- `gutenberg/create-page-from-pattern` and `gutenberg/insert-pattern-into-post` expose pattern content as a writable workflow, not just discovery.
- `gutenberg/analyze-content` adds outline, link, media-reference, and block-usage analysis on top of basic validation.
- `gutenberg/audit-content` adds Gutenberg-specific QA for heading structure, empty buttons, missing alt text, and oversized spacers.
- `gutenberg/evaluate-copy` adds lightweight editorial heuristics for vague CTAs, generic headings, dense paragraphs, shouty copy, and readability drift.
- `gutenberg/suggest-copy-fixes` turns those editorial issues into block-level rewrite suggestions and replacement options.
- `gutenberg/extract-synced-pattern` lets an MCP client promote an existing block subtree into a reusable `wp_block` pattern and optionally replace the source with a `core/block` reference.
- `gutenberg/insert-synced-pattern-into-post` inserts true reusable pattern references into existing posts instead of flattening pattern content.
- `gutenberg/find-synced-pattern-usage` makes reusable blocks safer by showing where a synced pattern is referenced before editing it.
- Synced patterns (`wp_block`) and block-theme template entities are now writable from the plugin.
- Site-editor navigation entities (`wp_navigation`) are now readable and writable from the plugin.
- Site-editor reference analysis is now exposed so templates, parts, and navigation relationships can be inspected in both directions.
- Lock attributes, `allowedBlocks`, `templateLock`, nested path mutations, block bindings, text replacement, and block style-variation data are now exposed directly.
- Generic media management stays in the main plugin to avoid overlapping `content/*` and `media/*` abilities.
- This version still does not handle media upload itself, deep block migrations/deprecations, or advanced site-editor rewrites like automatically updating all template references after a navigation swap.

## Current Gaps

- Media upload and attachment-creation flows are still outside the plugin.
- Pattern insertion currently works at the whole-pattern level, not block-by-block merge granularity.
- Section generation now covers more long-tail layouts including pricing, team, timeline, gallery, and contact-map, but it still does not cover interactive or data-driven layouts.
- Transform support is stronger now, but still does not cover automatic Gutenberg deprecations/migrations or arbitrary semantic transforms across the full tree.
- Navigation support now covers `wp_navigation` entities, but not every higher-level menu relationship or template reference workflow yet.
