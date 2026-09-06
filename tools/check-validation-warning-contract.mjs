#!/usr/bin/env node

import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const generation = await readFile(new URL('../includes/generation.php', import.meta.url), 'utf8');
const analysis = await readFile(new URL('../includes/content-analysis.php', import.meta.url), 'utf8');
const coreBlocks = await readFile(new URL('../includes/core-blocks.php', import.meta.url), 'utf8');

assert.match(coreBlocks, /function mcp_abilities_gutenberg_collect_markup_issues\( string \$content \): array/,
  'the Block Editor Module must expose one owning Interface for malformed saved HTML');
assert.match(coreBlocks, /closing_tag_delimiter_missing|attribute_separator_missing/,
  'malformed block text markup must have explicit stable issue codes');
assert.match(coreBlocks, /mcp_abilities_gutenberg_collect_markup_issues\( \$content \)/,
  'the canonical Gutenberg validity Interface must invoke the malformed-markup guard');
assert.match(generation, /mcp_abilities_gutenberg_collect_markup_issues\( \$content \)/,
  'content validation must report the same malformed-markup issues before a write is attempted');

assert.match(generation, /function mcp_abilities_gutenberg_has_semantic_heading\( array \$blocks \): bool/,
  'validation must centralize semantic heading recognition');
assert.match(generation, /return ! empty\( mcp_abilities_gutenberg_collect_outline\( \$blocks \) \);/,
  'heading validation must consume the provider-neutral outline Interface');
assert.match(analysis, /function mcp_abilities_gutenberg_block_semantic_tag\( array \$block \): string/,
  'block semantics must be projected from saved HTML instead of vendor block names');
assert.match(generation, /if \( ! mcp_abilities_gutenberg_has_semantic_heading\( \$normalized \) \)/,
  'the missing-heading warning must use the semantic heading Interface');
assert.match(generation, /if \( ! mcp_abilities_gutenberg_has_semantic_action\( \$normalized \) \)/,
  'the missing-button warning must use the semantic action Interface');
assert.doesNotMatch(generation, /\$warnings\[\]\s*=\s*'Markup-bearing blocks are present;/,
  'static-markup mutation guidance belongs only in mutation_guardrails, not page warnings');
assert.match(generation, /'static_attr_changes_require_markup_regeneration'\s*=>\s*! empty\( \$markup_bearing_block_names \)/,
  'structured static-markup mutation guidance must remain available');
assert.match(analysis, /'visual_review_required'\s*=>\s*true/,
  'static design evaluation must declare that rendered visual review is still required');
assert.match(analysis, /\$technical_caps\s*=\s*array/,
  'copy evaluation must distinguish standard technical acronyms from shouty copy');

console.log('Validation warning contract: OK');
