import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { comparisonSlotNames, comparisonSlots, comparisonSlugs } from '../src/lib/compare-selection.ts';

test('four named compare slots preserve order and reject a fifth parameter', () => {
  const selection = comparisonSlots({ a: 'first', b: 'second', c: 'third', d: 'fourth', e: 'fifth' });
  assert.deepEqual(comparisonSlotNames, ['a', 'b', 'c', 'd']);
  assert.deepEqual(comparisonSlugs(selection), ['first', 'second', 'third', 'fourth']);
});

test('source legacy comma-separated products preserve at most four public selections', () => {
  const selection = comparisonSlots({ products: 'first, second, third, fourth, fifth' });
  assert.deepEqual(comparisonSlugs(selection), ['first', 'second', 'third', 'fourth']);
});

test('duplicate, empty, unpublished-like and malformed selections do not amplify lookups', () => {
  const selection = comparisonSlots({ a: 'first', b: 'first', c: '../private', d: 'fourth', products: 'ignored' });
  assert.deepEqual(selection, ['first', 'first', '', 'fourth']);
  assert.deepEqual(comparisonSlugs(selection), ['first', 'fourth']);
});

test('untrusted arrays and oversized legacy input cannot enter comparison slots', () => {
  assert.deepEqual(comparisonSlugs(comparisonSlots({ a: ['first', 'second'], b: 'second' })), ['second']);
  assert.deepEqual(comparisonSlugs(comparisonSlots({ products: 'x'.repeat(644) })), []);
});
