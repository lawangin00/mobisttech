// A single sitemap must not silently omit published listings beyond an arbitrary page limit.
// The caller owns the public, mode-gated catalogue request and the final 50,000-URL XML cap.
type PublishedCataloguePage = {
  items: Array<{ slug: string }>;
  page: { has_more: boolean; next_cursor: string | null };
};

export async function catalogueSitemapSlugs(
  readPage: (after?: string) => Promise<PublishedCataloguePage>,
): Promise<string[]> {
  const slugs: string[] = [];
  const visited = new Set<string>();
  let after: string | undefined;
  for (;;) {
    const result = await readPage(after);
    if (result.items.length > 24 || (result.page.has_more && result.items.length === 0)) {
      throw new Error("Invalid public sitemap catalogue page.");
    }
    for (const product of result.items) {
      if (!product.slug || slugs.length >= 50_000) throw new Error("Public sitemap exceeds single-file capacity.");
      slugs.push(product.slug);
    }
    if (!result.page.has_more) return slugs;
    const next = result.page.next_cursor;
    if (!next || visited.has(next) || next === after) throw new Error("Public sitemap catalogue cursor did not advance.");
    visited.add(next);
    after = next;
  }
}
