# MCP Abilities - Block Editor

WordPress block-editor abilities for MCP. This add-on makes Gutenberg content usable as structured data instead of fragile raw HTML strings.

## What It Adds

- `gutenberg/get-theme-context`
- `gutenberg/get-style-guide`
- `gutenberg/list-available-blocks`
- `gutenberg/list-patterns`
- `gutenberg/block-guidance`
- `gutenberg/get-page-recipes`
- `gutenberg/generate-landing-page`
- `gutenberg/validate-content`
- `gutenberg/parse-content`
- `gutenberg/serialize-blocks`
- `gutenberg/get-post-blocks`
- `gutenberg/create-page-from-blocks`
- `gutenberg/create-landing-page`
- `gutenberg/update-post-blocks`

## Why This Exists

Gutenberg content is stored as block markup with comment delimiters, nested blocks, and attribute payloads. That is easy to break when an MCP client edits HTML directly.

This plugin provides a round-trip workflow:

1. Read block content as a normalized block tree.
2. Edit the tree in MCP.
3. Serialize it back into valid Gutenberg content.
4. Write it back to the post safely.

It also provides block-choice guidance so an MCP client can pick the right block for the content instead of faking layout with paragraphs and ad hoc HTML.
This version also adds authoring helpers for real page builds: theme/style discovery, available-block discovery, pattern listing, page recipes, landing-page generation, content validation, and one-step page creation.

## Requirements

- WordPress `6.9+`
- PHP `8.0+`
- [Abilities API](https://github.com/WordPress/abilities-api)

## Suggested Workflow

1. Use `gutenberg/block-guidance` when the MCP client needs to decide which block fits a scenario.
2. Use `gutenberg/list-available-blocks` and `gutenberg/list-patterns` to understand the site context.
3. Use `gutenberg/get-page-recipes` or `gutenberg/generate-landing-page` to draft a structure.
4. Use `gutenberg/validate-content` to catch weak structure or round-trip problems.
5. Use `gutenberg/create-page-from-blocks` or `gutenberg/create-landing-page` to create the page.
6. Use `gutenberg/get-post-blocks` to inspect the saved result.
7. Use `gutenberg/update-post-blocks` to iterate safely.

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
- This version focuses on safe block parsing, round-tripping, guidance, recipes, validation, and page generation. It still does not handle Markdown conversion, media upload orchestration, or custom-theme layout intelligence.
