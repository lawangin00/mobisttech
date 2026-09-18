# MT-4.4 Verification

- Point: MT-4.4 - Website CMS and platform administration interfaces
- Protected Platform Administration uses the existing Laravel Admin realm, effective permissions, outlet/delegation ceilings, session policy and recent-auth middleware.
- Website CMS: three-way mode draft/preview/publish/rollback; presentation/branding/theme/navigation/SEO revisions; managed pages/content/media; typed policy revisions/effective dates; safe historical preservation.
- POS configuration: source-backed document/output defaults, theme and branding authority with revision draft/preview/publish/rollback, audit/cache invalidation, safe PNG/WebP branding media validation and role-specific constraints, canonical Business Profile.
- Documents/payments: six Invoice/Warranty Email/WhatsApp templates; masked POS Payment Destinations with optimistic-version edits; no provider/internal secrets projected.
- Team/integrations: delegated Team Member and Custom Role management, protected Full Access handling, Gmail status/management entry point with existing authorization/recent-auth boundaries.
- Retail settings: promotions/coupons and optional loyalty controls delegate to existing Laravel services.
- Software administration: reusable product create/edit, overview/features/media/platform/system requirements/privacy/terms/FAQ/SEO, revisions, publish/rollback/archive, release/version plus six-area documentation-impact review, canonical slug-change workflow and history.
- Focused HTTP acceptance: 3 tests / 58 assertions PASS.
- Targeted Platform Playwright: 1/1 PASS.
- Affected regression: 31 tests / 337 assertions PASS.
- Full backend regression: 238 tests / 7346 assertions PASS.
- Full Playwright suite: 7/7 PASS.
- Build/style closure: production Vite build PASS with PlatformAdmin in the client resolver graph; backend TypeScript typecheck PASS; Composer validate --strict PASS; Composer platform requirements PASS; scoped Pint PASS; git diff check PASS.
- Production/browser defects fixed during acceptance: CMS policy projection schema mismatches; missing PlatformAdmin Inertia resolver registration. Test-only recovery fixes: page-route interceptor fallback, Playwright destination locator, comprehensive-journey timeout.
- No historical records are deleted by publication/rollback workflows; Dynamic Platform mode publication continues to revalidate its existing cache/SEO/sitemap domains.
