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
- `gutenberg/get-query-section-recipes`
- `gutenberg/generate-landing-page`
- `gutenberg/generate-section`
- `gutenberg/generate-query-section`
- `gutenberg/validate-content`
- `gutenberg/audit-content`
- `gutenberg/evaluate-design`
- `gutenberg/suggest-design-fixes`
- `gutenberg/evaluate-copy`
- `gutenberg/suggest-copy-fixes`
- `gutenberg/analyze-content`
- `gutenberg/evaluate-render-context`
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
This version also adds authoring helpers for real page builds: theme/style discovery, block metadata, pattern inspection and insertion, section recipes, template and template-part inspection, deeper content analysis, one-step page creation, and render-risk guardrails for static block mutations.

## Requirements

- WordPress `6.9+`
- PHP `8.0+`
- [Abilities API](https://github.com/WordPress/abilities-api)

## Suggested Workflow

1. Use `gutenberg/block-guidance` when the MCP client needs to decide which block fits a scenario.
2. Use `gutenberg/list-available-blocks`, `gutenberg/get-block-categories`, `gutenberg/get-block-details`, and `gutenberg/get-block-style-variations` to understand the site block surface.
3. Use `gutenberg/list-patterns` and `gutenberg/get-pattern` to discover reusable pattern content.
4. Use `gutenberg/get-page-recipes`, `gutenberg/get-section-recipes`, `gutenberg/get-query-section-recipes`, `gutenberg/generate-section`, `gutenberg/generate-query-section`, or `gutenberg/generate-landing-page` to draft content.
5. Use `gutenberg/validate-content`, `gutenberg/audit-content`, `gutenberg/evaluate-design`, `gutenberg/suggest-design-fixes`, `gutenberg/evaluate-copy`, `gutenberg/suggest-copy-fixes`, `gutenberg/analyze-content`, and `gutenberg/evaluate-render-context` to catch weak structure, weak writing, design-coherence drift, missing hierarchy, link/media issues, round-trip problems, static-block mutation guardrails, wrapper-level render-context issues, and CSS layout risks from both embedded styles and inline style attributes before saving.
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
- For editorial split layouts with one dominant column and one smaller support column, keep the section on the shared page width, vertically center the shorter support column when needed, and make any divider structural so it runs the intended full height.
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
- `gutenberg/validate-content` now reports mutation guardrails so static versus dynamic block surfaces are easier to reason about before editing.
- `gutenberg/validate-content` now also reports layout-risk styles found in content so callers can detect overlap/scroll hazards before write attempts.
- `gutenberg/evaluate-render-context` inspects the rendered page around the main content wrapper (`.entry-content` or `.page-content`) so empty wrappers, leading style blocks, alignfull breakout risks, and layout risks from embedded CSS or inline style attributes can be caught even when the block markup itself is valid.

## Changelog

### 0.20.0

- Teach design evaluation to recognize generic shell/container/wrapper selectors as real page-width anchors, so underused inner text lanes are measured against the actual shell instead of being ignored.

### 0.19.9

- Added `fullwidth_section_seam_gap_risk` so `gutenberg/evaluate-design` can flag default flow-layout seams between adjacent full-width sections that should visually touch.
- Expanded guidance and design-fix suggestions so adjacent full-width sections either explicitly reset the seam or separate for a real visual reason.

### 0.19.8

- Added `followup_cluster_detachment_risk` so `gutenberg/evaluate-design` can flag proof rows or support clusters that drift too far below the CTA/copy cluster they belong to.
- Expanded block guidance and design-fix suggestions so AI clients keep follow-up proof visually attached to the selling moment instead of separating it with dead air.

### 0.19.7

- Expanded non-interactive proof guidance so visually prominent proof rows are expected to carry useful supporting lines instead of bare label chips.
- Expanded design-fix suggestions so fake-button issues can be resolved by converting empty tokens into short proof strips, not just by flattening the styling.

### 0.19.6

- Added `noninteractive_control_affordance_risk` so `gutenberg/evaluate-design` can flag inert labels or proof chips that are styled like clickable controls.
- Expanded block guidance and design-fix suggestions so AI clients keep non-clickable metadata visually quieter than real CTAs.

### 0.19.5

- Expanded `internal_measure_mismatch` so `gutenberg/evaluate-design` now catches nested Gutenberg container rows that quietly stay on a narrower usable measure inside a widened section, not just narrow text caps.
- Expanded design-fix guidance so AI clients are nudged to remove stacked width constraints such as default-sized `.wp-block-columns` rows inside a wider shell.

### 0.19.4

- Expanded block guidance and design-fix suggestions for editorial split layouts so AI clients are nudged to center visibly shorter support columns and use full-height structural dividers instead of short borders on nested text wrappers.

### 0.19.3

- Expanded `gutenberg/evaluate-design` with `internal_measure_mismatch` so widened sections can still be flagged when their inner quote or text measure stays artificially narrow.
- Added `subtle_tilt_ambiguity` so small decorative rotations that read like mistakes can be surfaced before publish.
- Added `repeated_object_treatment_inconsistency` so repeated sibling modules are flagged when matching objects receive different containment treatments.

### 0.19.1

- Made `evaluate-render-context` and other editable-post helpers work reliably from WP-CLI validation runs by binding to the first administrator when CLI is running without a current user.

### 0.19.0

- Added `spacing_rhythm_drift` so the design layer can flag pages whose top-level Gutenberg sections use too many unrelated spacer heights, paddings, or margins.
- Expanded block guidance with vertical-rhythm advice so AI clients are nudged toward a smaller spacing token set instead of improvising every section gap.

### 0.18.9

- Added `row_treatment_inconsistency` so the design layer can catch repeated rows where one sibling becomes a stray boxed/card-like module while the others stay open.
- Expanded block guidance with row-coherence advice so AI clients are told to keep repeated columns in one treatment family unless a spotlight item is clearly intentional.

### 0.18.8

- Tightened `card_monotony_risk` so the design layer can flag page-wide overuse of boxed section treatments, not just isolated card selectors.
- The design evaluator now looks at repeated boxed section families and component spread, which better catches the "everything became cards" failure mode common in AI-generated layouts.

### 0.18.7

- Added `card_monotony_risk` so the design layer can flag when too many sections fall back to the same rounded-box/card treatment.

### 0.18.6

- Expanded the design layer to flag design-token sprawl when a page uses too many different border-radius or shadow treatments across its Gutenberg styling.

### 0.18.5

- Added `gutenberg/suggest-design-fixes` so design issues now come back with concrete remediation guidance instead of only scores and warnings.

### 0.18.4

- Expanded `gutenberg/evaluate-design` to flag weak button contrast and trailing bottom-gap styling on main content wrappers.
- Extended embedded CSS analysis so nearly invisible CTA treatments and stray bottom padding can be surfaced as design-coherence issues before publishing.

### 0.18.3

- Added `gutenberg/evaluate-design` so the plugin can score design coherence and flag repeated-row treatment mismatches, width-rhythm drift, and risky full-width breakout combinations.
- Added sibling-treatment CSS heuristics so rows where only some cards/columns receive accent styling can be identified before the page feels unfinished.

### 0.18.2

- Added width-system analysis so `gutenberg/validate-content` and `gutenberg/evaluate-render-context` can warn when embedded Gutenberg styling defines multiple conflicting fixed content measures across major sections.
- Added content-level `alignfull_breakout_risk` detection and now block writes when embedded Gutenberg styling mixes `alignfull` blocks with shell-level full-width CSS, a pattern that can produce sideways scrolling.
- Expanded block guidance with explicit advice for keeping one coherent interior content width across multi-section page builds.

### 0.18.1

- Added `alignfull_breakout_risk` detection to `gutenberg/evaluate-render-context` so the plugin can warn when `alignfull` Gutenberg blocks are combined with CSS that already forces the page shell to full width, a pattern that can produce sideways scrolling.
- Write flows now hard-fail on high-confidence layout-risk styles such as negative-margin overlap, explicit scroll-region creation, common viewport-overflow patterns, aggressive translate offsets, and absolute/fixed overlay positioning.
- `gutenberg/extract-synced-pattern` lets an MCP client promote an existing block subtree into a reusable `wp_block` pattern and optionally replace the source with a `core/block` reference.
- `gutenberg/insert-synced-pattern-into-post` inserts true reusable pattern references into existing posts instead of flattening pattern content.
- `gutenberg/find-synced-pattern-usage` makes reusable blocks safer by showing where a synced pattern is referenced before editing it.
- `gutenberg/get-query-section-recipes` and `gutenberg/generate-query-section` add dynamic query-loop composition so an MCP client can build post grids, editorial lists, and magazine-style feeds without hand-writing `core/query` markup.
- Synced patterns (`wp_block`) and block-theme template entities are now writable from the plugin.
- Site-editor navigation entities (`wp_navigation`) are now readable and writable from the plugin.
- Site-editor reference analysis is now exposed so templates, parts, and navigation relationships can be inspected in both directions.
- Lock attributes, `allowedBlocks`, `templateLock`, nested path mutations, block bindings, text replacement, and block style-variation data are now exposed directly.
- `gutenberg/mutate-block-tree` now blocks unsafe attr-only changes on static blocks unless the caller explicitly opts in with `allow_unsafe_static_markup`.
- Generic media management stays in the main plugin to avoid overlapping `content/*` and `media/*` abilities.
- This version still does not handle media upload itself, deep block migrations/deprecations, or advanced site-editor rewrites like automatically updating all template references after a navigation swap.

## Current Gaps

- Media upload and attachment-creation flows are still outside the plugin.
- Pattern insertion currently works at the whole-pattern level, not block-by-block merge granularity.
- Section generation now covers more long-tail layouts including pricing, team, timeline, gallery, contact-map, and dynamic query-loop sections, but it still does not cover interactive or heavily data-driven layouts.
- Transform support is stronger now, but still does not cover automatic Gutenberg deprecations/migrations or arbitrary semantic transforms across the full tree.
- Navigation support now covers `wp_navigation` entities, but not every higher-level menu relationship or template reference workflow yet.
