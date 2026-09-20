import assert from 'node:assert/strict';
import test from 'node:test';
import { catalogueSitemapSlugs } from '../src/lib/catalogue-sitemap.ts';

const fixture = (count) => Array.from({ length: count }, (_, i) => `mt75-published-${i + 1}`);
const readFixture = (slugs, calls) => async (after) => {
  calls.push(after ?? null);
  const start = after ? Number(after) : 0;
  const items = slugs.slice(start, start + 24).map((slug) => ({ slug }));
  return { items, page: { has_more: start + 24 < slugs.length, next_cursor: String(start + items.length) } };
};

test('241 published products include the page-eleven URL; no silent 240-product cut-off', async () => {
  const calls = [];
  const slugs = await catalogueSitemapSlugs(readFixture(fixture(241), calls));
  assert.equal(slugs.length, 241);
  assert.equal(slugs[240], 'mt75-published-241');
  assert.equal(calls.length, 11);
  assert.equal(calls[10], '240');
});

test('a full 240-product catalogue ends exactly at page ten', async () => {
  const calls = [];
  assert.equal((await catalogueSitemapSlugs(readFixture(fixture(240), calls))).length, 240);
  assert.equal(calls.length, 10);
});

test('rejects empty advancing page, repeated cursor and backend request failure', async () => {
  await assert.rejects(catalogueSitemapSlugs(async () => ({ items: [], page: { has_more: true, next_cursor: 'one' } })), /Invalid public sitemap/);
  await assert.rejects(catalogueSitemapSlugs(async () => ({ items: [{ slug: 'same' }], page: { has_more: true, next_cursor: 'one' } })), /cursor did not advance/);
  await assert.rejects(catalogueSitemapSlugs(async () => { throw new Error('API unavailable'); }), /API unavailable/);
});

test('over-capacity product catalogue rejects rather than returning incomplete XML', async () => {
  const calls = [];
  await assert.rejects(catalogueSitemapSlugs(readFixture(fixture(50_001), calls)), /single-file capacity/);
  assert.equal(calls.length, 2084);
});
