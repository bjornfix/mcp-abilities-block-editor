# MCP Abilities - Block Editor

WordPress block-editor abilities for MCP. This add-on makes Gutenberg content usable as structured data instead of fragile raw HTML strings.

## What It Adds

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
- `gutenberg/create-page-from-blocks`
- `gutenberg/create-page-from-pattern`
- `gutenberg/create-landing-page`
- `gutenberg/insert-pattern-into-post`
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
2. Use `gutenberg/list-available-blocks`, `gutenberg/get-block-categories`, and `gutenberg/get-block-details` to understand the site block surface.
3. Use `gutenberg/list-patterns` and `gutenberg/get-pattern` to discover reusable pattern content.
4. Use `gutenberg/get-page-recipes`, `gutenberg/get-section-recipes`, `gutenberg/generate-section`, or `gutenberg/generate-landing-page` to draft content.
5. Use `gutenberg/validate-content` and `gutenberg/analyze-content` to catch weak structure, missing hierarchy, link/media issues, and round-trip problems.
6. Use `gutenberg/create-page-from-blocks`, `gutenberg/create-page-from-pattern`, or `gutenberg/create-landing-page` to create the page.
7. Use `gutenberg/list-templates`, `gutenberg/get-template`, `gutenberg/list-template-parts`, and `gutenberg/get-template-part` when the active block theme matters.
8. Use the main plugin's generic media abilities for uploads, media lookup, and featured-image updates.
9. Use `gutenberg/get-post-blocks`, `gutenberg/insert-pattern-into-post`, and `gutenberg/update-post-blocks` to iterate safely.

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
- Generic media management stays in the main plugin to avoid overlapping `content/*` and `media/*` abilities.
- This version still does not handle media upload itself, advanced block transforms/migrations, or deep theme-intelligence beyond the currently active block/theme registries.

## Current Gaps

- Media upload and attachment-creation flows are still outside the plugin.
- Pattern insertion currently works at the whole-pattern level, not block-by-block merge granularity.
- Section generation covers common marketing sections, but not the full long-tail of pricing, team, map, timeline, or interactive layouts yet.
- Template inspection is read-only in this version; template writing and synchronization are not exposed.
