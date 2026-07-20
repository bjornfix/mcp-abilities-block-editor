#!/usr/bin/env node

import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../includes/generation.php', import.meta.url), 'utf8');

assert.match(source, /function mcp_abilities_gutenberg_has_semantic_heading\( array \$blocks \): bool/,
  'validation must centralize semantic heading recognition');
assert.match(source, /'generateblocks\/headline' === \$block_name[\s\S]*preg_match\( '\/<h\[1-6\]\\b\/i', \$inner_html \)/,
  'GenerateBlocks headlines must be recognized from their semantic saved element');
assert.match(source, /if \( ! mcp_abilities_gutenberg_has_semantic_heading\( \$normalized \) \)/,
  'the missing-heading warning must use the semantic heading Interface');
assert.doesNotMatch(source, /\$warnings\[\]\s*=\s*'Markup-bearing blocks are present;/,
  'static-markup mutation guidance belongs only in mutation_guardrails, not page warnings');
assert.match(source, /'static_attr_changes_require_markup_regeneration'\s*=>\s*! empty\( \$markup_bearing_block_names \)/,
  'structured static-markup mutation guidance must remain available');

console.log('Validation warning contract: OK');
