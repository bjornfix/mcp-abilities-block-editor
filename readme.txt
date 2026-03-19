=== MCP Abilities - Block Editor ===
Contributors: devenia
Tags: mcp, gutenberg, block-editor, blocks, automation
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.19.9
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
- generate reusable sections like hero, FAQ, CTA, testimonial, stats rows, pricing, team, timeline, gallery, contact-map, and dynamic query-loop layouts
- generate a structured landing page from business inputs
- validate round-trip safety, page structure, outline, link/media usage, Gutenberg-specific QA issues, copy quality, static-block mutation risks, and layout-risk styles before saving
- evaluate rendered page chrome around Gutenberg content for wrapper-induced layout issues plus overlap and scroll-risk smells from embedded CSS and inline style attributes
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

= 0.19.9 =
* Added `fullwidth_section_seam_gap_risk` so `gutenberg/evaluate-design` can flag default flow-layout seams between adjacent full-width sections that should visually touch.
* Expanded guidance and design-fix suggestions so adjacent full-width sections either explicitly reset the seam or separate for a real visual reason.

= 0.19.8 =
* Added `followup_cluster_detachment_risk` so `gutenberg/evaluate-design` can flag proof rows or support clusters that drift too far below the CTA/copy cluster they belong to.
* Expanded block guidance and design-fix suggestions so AI clients keep follow-up proof visually attached to the selling moment instead of separating it with dead air.

= 0.19.7 =
* Expanded non-interactive proof guidance so visually prominent proof rows are expected to carry useful supporting lines instead of bare label chips.
* Expanded design-fix suggestions so fake-button issues can be resolved by converting empty tokens into short proof strips, not just by flattening the styling.

= 0.19.6 =
* Added `noninteractive_control_affordance_risk` so `gutenberg/evaluate-design` can flag inert labels or proof chips that are styled like clickable controls.
* Expanded block guidance and design-fix suggestions so AI clients keep non-clickable metadata visually quieter than real CTAs.

= 0.19.5 =
* Expanded `internal_measure_mismatch` so `gutenberg/evaluate-design` now catches nested Gutenberg container rows that quietly stay on a narrower usable measure inside a widened section, not just narrow text caps.
* Expanded design-fix guidance so AI clients are nudged to remove stacked width constraints such as default-sized `.wp-block-columns` rows inside a wider shell.

= 0.19.4 =
* Expanded block guidance and design-fix suggestions for editorial split layouts so AI clients are nudged to center visibly shorter support columns and use full-height structural dividers instead of short borders on nested text wrappers.

= 0.19.3 =
* Expanded `gutenberg/evaluate-design` with `internal_measure_mismatch` so widened sections can still be flagged when their inner quote or text measure stays artificially narrow.
* Added `subtle_tilt_ambiguity` so small decorative rotations that read like mistakes can be surfaced before publish.
* Added `repeated_object_treatment_inconsistency` so repeated sibling modules are flagged when matching objects receive different containment treatments.

= 0.19.1 =
* Made `evaluate-render-context` and other editable-post helpers work reliably from WP-CLI validation runs by binding to the first administrator when CLI is running without a current user.

= 0.19.0 =
* Added `spacing_rhythm_drift` so the design layer can flag pages whose top-level Gutenberg sections use too many unrelated spacer heights, paddings, or margins.
* Expanded block guidance with vertical-rhythm advice so AI clients are nudged toward a smaller spacing token set instead of improvising every section gap.

= 0.18.9 =
* Added `row_treatment_inconsistency` so the design layer can catch repeated rows where one sibling becomes a stray boxed/card-like module while the others stay open.
* Expanded block guidance with row-coherence advice so AI clients are told to keep repeated columns in one treatment family unless a spotlight item is clearly intentional.

= 0.18.8 =
* Tightened `card_monotony_risk` so the design layer can flag page-wide overuse of boxed section treatments, not just isolated card selectors.
* The design evaluator now looks at repeated boxed section families and component spread, which better catches the "everything became cards" failure mode common in AI-generated layouts.

= 0.17.0 =
- Expanded hard-fail layout guardrails to also block aggressive translate offsets and absolute/fixed overlay positioning patterns.

= 0.18.7 =
* Added `card_monotony_risk` so the design layer can flag when too many sections fall back to the same rounded-box/card treatment.

= 0.18.6 =
* Expanded the design layer to flag design-token sprawl when a page uses too many different border-radius or shadow treatments across its Gutenberg styling.

= 0.18.5 =
* Added `gutenberg/suggest-design-fixes` so design issues now come back with concrete remediation guidance instead of only scores and warnings.

= 0.18.4 =
* Expanded `gutenberg/evaluate-design` to flag weak button contrast and trailing bottom-gap styling on main content wrappers.
* Extended embedded CSS analysis so nearly invisible CTA treatments and stray bottom padding can be surfaced as design-coherence issues before publishing.

= 0.18.3 =
* Added `gutenberg/evaluate-design` so the plugin can score design coherence and flag repeated-row treatment mismatches, width-rhythm drift, and risky full-width breakout combinations.
* Added sibling-treatment CSS heuristics so rows where only some cards/columns receive accent styling can be identified before the page feels unfinished.

= 0.18.2 =
* Added width-system analysis so `gutenberg/validate-content` and `gutenberg/evaluate-render-context` can warn when embedded Gutenberg styling defines multiple conflicting fixed content measures across major sections.
* Added content-level `alignfull_breakout_risk` detection and now block writes when embedded Gutenberg styling mixes `alignfull` blocks with shell-level full-width CSS, a pattern that can produce sideways scrolling.
* Expanded block guidance with explicit advice for keeping one coherent interior content width across multi-section page builds.

= 0.18.1 =
* Added `alignfull_breakout_risk` detection to `gutenberg/evaluate-render-context` so the plugin can warn when `alignfull` Gutenberg blocks are combined with CSS that already forces the page shell to full width, a pattern that can produce sideways scrolling.

= 0.18.0 =
- Added dynamic query-section recipes and generation for `core/query`-based post grids, compact lists, and magazine-style content feeds.

= 0.16.0 =
- Added hard-fail write guardrails for high-confidence layout-risk styles so unsafe content is blocked before save.
- Extended `validate-content` to report layout-risk styles found in content.

= 0.15.0 =
- Expanded render-context evaluation to scan inline `style` attributes inside rendered content, not just embedded `<style>` blocks.
- Kept the layout-risk checks focused on overlap, overflow, scroll-region, and large offset smells.

= 0.14.0 =
- Extended rendered-page context evaluation so it recognizes `.page-content` shells like Hello Elementor.
- Added embedded CSS layout-risk checks for negative-margin overlap, explicit scroll regions, and viewport-width overflow smells.

= 0.13.0 =
- Added rendered-page context evaluation so wrapper-level layout issues around Gutenberg content can be detected from the live page.

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
