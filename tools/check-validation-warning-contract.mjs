#!/usr/bin/env node

import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const generation = await readFile(new URL('../includes/generation.php', import.meta.url), 'utf8');
const analysis = await readFile(new URL('../includes/content-analysis.php', import.meta.url), 'utf8');

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

console.log('Validation warning contract: OK');
