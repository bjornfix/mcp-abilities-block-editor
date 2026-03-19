# MCP Abilities - Block Editor

Gutenberg and block-editor automation for WordPress via MCP.

[![GitHub release](https://img.shields.io/github/v/release/bjornfix/mcp-abilities-block-editor)](https://github.com/bjornfix/mcp-abilities-block-editor/releases)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

**Tested up to:** 6.9
**Stable tag:** 0.20.7
**Requires PHP:** 8.0
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## What It Does

This add-on plugin exposes Gutenberg and block-editor functionality through MCP (Model Context Protocol). Your AI assistant can inspect block metadata, generate sections and landing pages, validate block content, mutate block trees safely, work with templates and template parts, and manage reusable synced patterns without dropping into brittle raw HTML editing.

**Part of the [MCP Expose Abilities](https://github.com/bjornfix/mcp-expose-abilities) ecosystem.**

This is one piece of a bigger open WordPress automation stack that lets AI agents do real Gutenberg work inside WordPress instead of guessing at markup strings or leaving humans a cleanup job.

## Why This Is Cool

Gutenberg is a solid editor, but it is easy for AI tooling to misuse it by faking layouts with paragraphs, random wrappers, or raw HTML edits that break block markup.

This add-on closes that gap. You can ask an agent to choose the right block, inspect the theme and site-editor context, generate sections, evaluate design and copy quality, update nested block trees safely, and reuse synced patterns without collapsing the content structure.

## Documentation

- [Core Plugin: MCP Expose Abilities](https://github.com/bjornfix/mcp-expose-abilities)
- [MCP Wiki Home](https://github.com/bjornfix/mcp-expose-abilities/wiki)
- [Why Teams Use It](https://github.com/bjornfix/mcp-expose-abilities/wiki/Why-Teams-Use-It)
- [Use Cases](https://github.com/bjornfix/mcp-expose-abilities/wiki/Use-Cases)
- [Getting Started](https://github.com/bjornfix/mcp-expose-abilities/wiki/Getting-Started)

## Requirements

- WordPress 6.9+
- PHP 8.0+
- [Abilities API](https://github.com/WordPress/abilities-api) plugin
- [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin
- [MCP Expose Abilities](https://github.com/bjornfix/mcp-expose-abilities) core plugin

## Installation

1. Install and activate MCP Expose Abilities
2. Download the latest release from [Releases](https://github.com/bjornfix/mcp-abilities-block-editor/releases)
3. Upload via WordPress Admin > Plugins > Add New > Upload Plugin
4. Activate the plugin

## Abilities (65)

| Ability | Description |
|---------|-------------|
| `gutenberg/get-theme-context` | Return active-theme and block-theme context relevant to Gutenberg authoring |
| `gutenberg/get-style-guide` | Summarize theme spacing, typography, palette, and layout guidance |
| `gutenberg/get-style-book` | Return block-style-book style context and styled block data |
| `gutenberg/get-site-editor-summary` | Summarize site-editor objects such as templates, parts, styles, and navigations |
| `gutenberg/get-site-editor-references` | Inspect cross-references between site-editor objects |
| `gutenberg/list-available-blocks` | List registered Gutenberg blocks available on the site |
| `gutenberg/get-block-categories` | List Gutenberg block categories |
| `gutenberg/get-block-details` | Return metadata for a specific block type |
| `gutenberg/get-block-style-variations` | List style variations for a block type |
| `gutenberg/list-patterns` | List registered block patterns |
| `gutenberg/get-pattern` | Get a specific registered pattern |
| `gutenberg/list-synced-patterns` | List reusable synced patterns (`wp_block`) |
| `gutenberg/get-synced-pattern` | Get a synced pattern by ID |
| `gutenberg/block-guidance` | Recommend the right Gutenberg block or layout pattern for a scenario |
| `gutenberg/get-page-recipes` | Return landing-page or full-page recipe options |
| `gutenberg/get-section-recipes` | Return reusable section recipe options |
| `gutenberg/get-query-section-recipes` | Return dynamic query-loop section recipe options |
| `gutenberg/generate-landing-page` | Generate a structured Gutenberg landing page |
| `gutenberg/generate-section` | Generate a reusable section from recipe-style inputs |
| `gutenberg/generate-query-section` | Generate a dynamic `core/query` section |
| `gutenberg/validate-content` | Validate block-tree shape, mutation safety, and layout-risk styles |
| `gutenberg/audit-content` | Run Gutenberg-specific QA checks on content structure |
| `gutenberg/evaluate-design` | Score design coherence and flag layout/design issues |
| `gutenberg/suggest-design-fixes` | Turn design findings into concrete remediation suggestions |
| `gutenberg/evaluate-copy` | Score copy quality and flag weak writing patterns |
| `gutenberg/suggest-copy-fixes` | Turn copy findings into rewrite suggestions |
| `gutenberg/analyze-content` | Analyze outline, links, media usage, and block usage |
| `gutenberg/evaluate-render-context` | Inspect the rendered page wrapper/context around Gutenberg content |
| `gutenberg/parse-content` | Parse Gutenberg content into a normalized block tree |
| `gutenberg/serialize-blocks` | Serialize normalized blocks back into valid Gutenberg markup |
| `gutenberg/get-post-blocks` | Read a post or page as structured Gutenberg blocks |
| `gutenberg/list-templates` | List block-theme templates |
| `gutenberg/get-template` | Get a block-theme template |
| `gutenberg/create-template` | Create a block-theme template |
| `gutenberg/update-template` | Update a block-theme template |
| `gutenberg/list-template-parts` | List block-theme template parts |
| `gutenberg/get-template-part` | Get a template part |
| `gutenberg/create-template-part` | Create a template part |
| `gutenberg/update-template-part` | Update a template part |
| `gutenberg/list-navigations` | List `wp_navigation` entities |
| `gutenberg/get-navigation` | Get a navigation entity |
| `gutenberg/create-navigation` | Create a navigation entity |
| `gutenberg/update-navigation` | Update a navigation entity |
| `gutenberg/find-navigation-usage` | Find where a navigation is referenced |
| `gutenberg/find-template-part-usage` | Find where a template part is referenced |
| `gutenberg/find-synced-pattern-usage` | Find where a synced pattern is used |
| `gutenberg/create-page-from-blocks` | Create a page directly from Gutenberg blocks |
| `gutenberg/create-synced-pattern` | Create a synced pattern (`wp_block`) |
| `gutenberg/update-synced-pattern` | Update a synced pattern |
| `gutenberg/extract-synced-pattern` | Extract existing blocks into a synced pattern |
| `gutenberg/insert-synced-pattern-into-post` | Insert a reusable synced pattern reference into a post |
| `gutenberg/create-page-from-pattern` | Create a page from a registered pattern |
| `gutenberg/create-landing-page` | Create a landing page from business inputs |
| `gutenberg/insert-pattern-into-post` | Insert a registered pattern into a post |
| `gutenberg/transform-blocks` | Apply structural block transforms |
| `gutenberg/mutate-block-tree` | Edit nested block trees by path |
| `gutenberg/set-block-lock` | Set block lock attributes |
| `gutenberg/set-allowed-blocks` | Set allowed child blocks for container blocks |
| `gutenberg/set-template-lock` | Set template-lock behavior |
| `gutenberg/insert-inner-block` | Insert a child block at a path |
| `gutenberg/duplicate-block` | Duplicate a block at a path |
| `gutenberg/move-block` | Move a block in the tree |
| `gutenberg/replace-block-text` | Replace text content inside matching blocks |
| `gutenberg/get-block-bindings` | Inspect Gutenberg block bindings |
| `gutenberg/set-block-bindings` | Update Gutenberg block bindings |
| `gutenberg/normalize-heading-levels` | Normalize heading hierarchy |
| `gutenberg/update-post-blocks` | Save structured Gutenberg blocks back to a post |

## Usage Examples

### Get block guidance for a content scenario

```json
{
  "ability_name": "gutenberg/block-guidance",
  "parameters": {
    "scenario": "hero section for a local service page"
  }
}
```

### Generate a section

```json
{
  "ability_name": "gutenberg/generate-section",
  "parameters": {
    "recipe": "pricing",
    "business_name": "Devenia",
    "tone": "clear and commercially sharp"
  }
}
```

### Evaluate design before saving

```json
{
  "ability_name": "gutenberg/evaluate-design",
  "parameters": {
    "post_id": 231
  }
}
```

### Update a page from structured blocks

```json
{
  "ability_name": "gutenberg/update-post-blocks",
  "parameters": {
    "id": 123,
    "blocks": [
      {
        "block_name": "core/paragraph",
        "attrs": {},
        "inner_blocks": [],
        "inner_html": "<p>Hello world</p>",
        "inner_content": ["<p>Hello world</p>"]
      }
    ]
  }
}
```

## Notes

- This plugin is Gutenberg-specific and intentionally does not duplicate generic `content/*` or `media/*` abilities from the core plugin.
- It is designed to keep Gutenberg content as structured data instead of brittle HTML strings.
- `gutenberg/evaluate-design`, `gutenberg/evaluate-copy`, and `gutenberg/evaluate-render-context` exist to catch weak output before publishing, not just after.
- `gutenberg/validate-content` now includes mutation guardrails for static-block edits and layout-risk style detection.
- `gutenberg/evaluate-render-context` inspects the rendered page wrapper around `.entry-content` or `.page-content` so wrapper-induced problems can be surfaced even when the block markup itself is valid.

## Changelog

### 0.20.7
- Refined `support_module_cramp_risk` so clearly stacked/list-based proof groups are not treated like cramped horizontal strips
- Reinforced the calmer vertical proof-list fallback when support copy is too substantial for equal-width strip modules

### 0.20.6
- Tightened `alignfull_breakout_risk` so custom page-level width systems with multiple `alignfull` sections now fail unless the content explicitly neutralizes Gutenberg breakout margins
- Expanded block guidance so AI clients know to neutralize `alignfull` or switch to non-breakout full-width wrappers when a page introduces its own shell sizing

### 0.20.4
- Added `faq_schema_missing_risk` so the design evaluator can fail visually real FAQ sections that still lack matching FAQ schema
- Expanded block guidance and design-fix suggestions so AI clients preserve a good FAQ layout and add matching `FAQPage` JSON-LD underneath it instead of flattening the design

### 0.20.3
- Fixed support-row cramp evaluation so distinct Gutenberg rows no longer collapse into one generic selector

### 0.20.2
- Added `support_module_cramp_risk` so the evaluator can flag process rows, proof strips, and similar support modules that still feel too narrow

### 0.20.1
- Parsed width and max-width declarations inside CSS functions like `min(...)`, so shell measures count as real page-width anchors during design evaluation

### 0.20.0
- Taught design evaluation to recognize generic shell/container/wrapper selectors as real page-width anchors, so underused inner text lanes are measured against the actual shell instead of being ignored

### 0.19.9
- Added `fullwidth_section_seam_gap_risk` so `gutenberg/evaluate-design` can flag seams between adjacent full-width sections that should visually touch

### 0.19.8
- Added `followup_cluster_detachment_risk` so `gutenberg/evaluate-design` can flag proof rows or support clusters that drift too far below the selling moment they belong to

### 0.19.7
- Expanded non-interactive proof guidance so visually prominent proof rows are expected to carry useful supporting lines instead of bare label chips

### 0.19.6
- Added `noninteractive_control_affordance_risk` so `gutenberg/evaluate-design` can flag inert labels or proof chips that are styled like clickable controls

### 0.19.5
- Expanded `internal_measure_mismatch` so widened sections can still fail when nested container rows quietly stay on a narrower usable measure

### 0.19.4
- Expanded editorial split-layout guidance so AI clients are nudged to center visibly shorter support columns and use full-height structural dividers

### 0.19.3
- Expanded `gutenberg/evaluate-design` with `internal_measure_mismatch`
- Added `subtle_tilt_ambiguity`
- Added `repeated_object_treatment_inconsistency`

### 0.19.1
- Made `evaluate-render-context` and other editable-post helpers work reliably from WP-CLI validation runs by binding to the first administrator when CLI is running without a current user

### 0.19.0
- Added `spacing_rhythm_drift` so the design layer can flag pages whose top-level Gutenberg sections use too many unrelated spacer heights, paddings, or margins

### 0.18.9
- Added `row_treatment_inconsistency` so the design layer can catch repeated rows where one sibling becomes a stray boxed/card-like module while the others stay open

### 0.18.8
- Tightened `card_monotony_risk` so the design layer can flag page-wide overuse of boxed section treatments

### 0.18.7
- Added `card_monotony_risk` so the design layer can flag when too many sections fall back to the same rounded-box/card treatment

### 0.18.6
- Expanded the design layer to flag design-token sprawl when a page uses too many different border-radius or shadow treatments

### 0.18.5
- Added `gutenberg/suggest-design-fixes` so design issues now come back with concrete remediation guidance instead of only scores and warnings

## License

GPL-2.0+

## Author

[Devenia](https://devenia.com) - We've been doing SEO and web development since 1993.

## Free and Open

Like the rest of the ecosystem, this add-on is free for everyone, fully open, and built for real production Gutenberg work rather than demo-only automation.

## Star and Share

If this add-on helps, please star the repo, share the ecosystem, and point people to the main wiki:

- https://github.com/bjornfix/mcp-expose-abilities
- https://github.com/bjornfix/mcp-expose-abilities/wiki

## Links

- [Core Plugin (MCP Expose Abilities)](https://github.com/bjornfix/mcp-expose-abilities)
- [Main Wiki](https://github.com/bjornfix/mcp-expose-abilities/wiki)
- [Getting Started](https://github.com/bjornfix/mcp-expose-abilities/wiki/Getting-Started)
