# Dev Test Notes

Date: 2026-03-18
Site: `dev.devenia.com`
Plugin: `mcp-abilities-block-editor`

## Verified on dev

- `wp plugin check` passed after the latest deploy.
- All 14 `gutenberg/*` abilities were registered on `dev`.
- `generate-landing-page` produced a 12-block, 246-word landing-page payload for Bollesen Bakery.
- `parse-content` and `serialize-blocks` round-tripped successfully.
- `create-page-from-blocks` updated the existing `bollesen-bakery-block-test` draft when `upsert_matching_slug` was enabled.
- `create-landing-page` updated the existing published `bollesen-bakery` page when `upsert_matching_slug` was enabled.
- `validate-content` no longer produces false warnings for nested heading and buttons blocks.
- Public acceptance URL now renders the generated content:
  - `https://dev.devenia.com/bollesen-bakery/`

## Current dev state

- Published acceptance page:
  - ID `123`
  - slug `bollesen-bakery`
- Draft block test page:
  - ID `122`
  - slug `bollesen-bakery-block-test`
- Old duplicate test pages were deleted from `dev`.

## What It Still Lacks

- Theme-aware composition is still shallow.
  - The plugin can read palette, theme context, block availability, and patterns, but the actual landing-page generator still uses a baked-in layout and copy recipe instead of adapting to active theme patterns or spacing systems.

- Pattern insertion is read-only.
  - `list-patterns` is useful for discovery, but there is no ability yet to instantiate or merge a chosen pattern into a generated page.

- Media handling is missing.
  - There is no image upload, media lookup, featured image assignment, gallery assembly, or block-level media replacement workflow.

- CTA links are placeholders.
  - Buttons are generated with labels only; there is no URL input enforcement, link validation, or conversion-focused CTA modeling.

- No page-upsert ability by URL/path beyond slug matching.
  - The new slug-safe behavior prevents silent duplicate-page drift, but targeting is still limited to exact slug reuse. There is no richer reconcile step for page ID, canonical URL, or title-plus-slug conflict resolution.

- Validation is still structural, not editorial.
  - It now walks nested blocks, but it does not score accessibility, CTA completeness, broken links, empty anchors, heading hierarchy quality, or brand/legal content quality.

- No reusable section library yet.
  - There is guidance and one landing-page generator, but there are no first-class hero/FAQ/pricing/testimonial/gallery/FAQ recipe abilities that can be inserted independently.

- No visual asset orchestration.
  - The plugin can create “eye candy” with gradients, spacing, columns, quote blocks, and buttons, but it still lacks illustration/image direction, duotone handling, background media, and pattern-driven visual variants.

- No deterministic cleanup inside the plugin itself.
  - Duplicate pages created during earlier test runs were cleaned up manually on `dev`. The plugin prevents the main slug-collision case now, but it does not expose a cleanup or audit ability for pre-existing duplicates.

- Release publication is still pending for this post-`v0.1.0` work.
  - The current expanded build is verified on `dev`, but the newer commits have not yet been pushed and released as a GitHub tag/release in this pass.
