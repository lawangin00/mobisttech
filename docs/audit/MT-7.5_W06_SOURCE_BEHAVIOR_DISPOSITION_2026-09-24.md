# MT-7.5 W06 pinned source-to-target behavior disposition (24-Sep-2026; LOCAL)

Scope: existing `19/W06` ONLY. Immutable `docs/migration/SOURCE_SYMBOL_INVENTORY.json`: exactly **72 W06-tagged Website source files and 45 resolved routes**. This records an explicit approved fresh-business disposition, **not** a claim of equivalent behavior or a new test PASS. Original source repositories and business records were not touched or copied. Shared overlaps with previously DONE W02/W03/W04/W05 are not reopened. No new code/tests/providers/CI were run for this mapping.

## Pinned source files: 72 of 72

| # | Legacy file (frozen inventory) | Target behavior / unresolved acceptance |
|---:|---|---|
| 01 | `app/Http/Controllers/Admin/WebsiteAdminPageController.php` | About/contact editable content => protected Platform Content managed-page purposes; direct legacy forms retired. Owner copy remains HOLD. |
| 02 | `app/Http/Controllers/Admin/WebsiteHeaderFooterController.php` | Header/footer presentation draft/publish/rollback => WebsiteCms presentation + Next SiteHeader/SiteFooter; GUI structured-JSON parity and preview need real acceptance. |
| 03 | `app/Http/Controllers/Admin/WebsiteManagedPageController.php` | Managed pages/legal => protected Content page editor and typed policy publication with recent-auth; publication/preview/slug collision negative still W06. |
| 04 | `app/Http/Controllers/Admin/WebsiteNavigationController.php` | Navigation CRUD/order/visibility/destination/preview => WebsiteCms presentation navigation revision + mode-filtered API/SiteHeader; guided-builder/preview parity not accepted. |
| 05 | `app/Http/Controllers/Admin/WebsitePromotionController.php` | Website banner/promotion presentation => website.presentation promotion; current Next SiteHeader/SiteFooter/home contain no banner renderer: missing internal I. Commercial coupons remain separate PromotionServices/W04; legacy banner workflow unverified. |
| 06 | `app/Http/Controllers/Admin/WebsiteSeoController.php` | Global/route SEO presentation => permission-scoped WebsiteCms seo revisions and Next metadata; editable SEO/public field propagation remains OPEN [I/E]. |
| 07 | `app/Http/Controllers/HomeController.php` | Public root => Next / homepage via published managed homepage, profile, service and catalogue; old source content not imported. |
| 08 | `app/Http/Controllers/ManagedPageController.php` | Public catch-all => Next /[slug] published page/policy, CMS collision/scope/404 rules; template-specific visual parity OPEN. |
| 09 | `app/Models/SiteManagedPage.php` | Versioned target site_managed_pages/site_page_revisions, typed policies; no historical row import. |
| 10 | `app/Models/SiteNavigationItem.php` | Target site_navigation_items with scope, ordering, destination; no original row import. |
| 11 | `app/Services/WebsiteHeaderFooter.php` | WebsiteCms presentation + shared Next site-shell; actual structured configuration/browser checks OPEN. |
| 12 | `app/Services/WebsiteHomepageSections.php` | Published homepage structured_content + presentation homepage and Next root; legacy section order/visibility behavior OPEN. |
| 13 | `app/Services/WebsiteManagedContentSanitizer.php` | WebsiteCms rich()/safeTree() and safe validated Next HTML; targeted injection/media checks OPEN. |
| 14 | `app/Services/WebsiteManagedPageSeo.php` | WebsiteCms pageSnapshot SEO/sitemap and Next /[slug] generateMetadata; field propagation and social-image checks OPEN. |
| 15 | `app/Services/WebsiteManagedPageTemplates.php` | WebsiteCms managed-page lifecycle/templates + Next /[slug] generic renderer; original standard/wide visual equivalence not asserted. |
| 16 | `app/Services/WebsiteManagedPages.php` | WebsiteCms managed-page lifecycle/templates + Next /[slug] generic renderer; original standard/wide visual equivalence not asserted. |
| 17 | `app/Services/WebsiteNavigationDestinationRegistry.php` | WebsiteCms applyNavigation(), mode-scoped WebsiteApi contentIndex(), SiteHeader links; nested route/depth/cycle/destination check OPEN. |
| 18 | `app/Services/WebsiteNavigationManager.php` | WebsiteCms applyNavigation(), mode-scoped WebsiteApi contentIndex(), SiteHeader links; nested route/depth/cycle/destination check OPEN. |
| 19 | `app/Services/WebsiteNavigationRuntime.php` | WebsiteCms applyNavigation(), mode-scoped WebsiteApi contentIndex(), SiteHeader links; nested route/depth/cycle/destination check OPEN. |
| 20 | `app/Services/WebsitePromotions.php` | WebsiteCms presentation promotion only; target public SiteHeader/SiteFooter/home do not render configured banners: confirmed I. Commercial PromotionServices is a different coupon contract. |
| 21 | `app/Services/WebsiteSeo.php` | WebsiteCms presentation/managed/Software SEO and Next generateMetadata/sitemap; explicit Software SEO is persisted but Admin cannot independently edit and public overview ignores it: confirmed I. |
| 22 | `app/Support/WebsiteHomepageSectionRegistry.php` | Published homepage structured_content + presentation homepage and Next root; legacy section order/visibility behavior OPEN. |
| 23 | `database/migrations/2026_08_25_100000_seed_homepage_site_settings.php` | Fresh target website.presentation/homepage configuration and canonical site_settings; source bootstrap contents intentionally not copied. |
| 24 | `database/migrations/2026_08_28_230000_create_site_navigation_items_table.php` | Fresh target site_navigation_items and revisions; no old navigation row migration. |
| 25 | `database/migrations/2026_08_28_235500_create_site_managed_pages_table.php` | Fresh target site_managed_pages/site_page_revisions typed SEO/purpose/scope; no old page row import. |
| 26 | `database/migrations/2026_08_29_003000_add_content_and_seo_to_site_managed_pages.php` | Fresh target site_managed_pages/site_page_revisions typed SEO/purpose/scope; no old page row import. |
| 27 | `database/migrations/2026_08_29_094100_add_content_purpose_to_site_managed_pages.php` | Fresh target site_managed_pages/site_page_revisions typed SEO/purpose/scope; no old page row import. |
| 28 | `resources/views/about.blade.php` | Published purpose-scoped /[slug] or public profile/contact; no source copy or dedicated URL parity assumed. |
| 29 | `resources/views/admin/about-content.blade.php` | Protected ContentTab purpose-scoped managed-page forms; source dedicated page controls replaced, actual frontend equivalence OPEN. |
| 30 | `resources/views/admin/contact-content.blade.php` | Protected ContentTab purpose-scoped managed-page forms; source dedicated page controls replaced, actual frontend equivalence OPEN. |
| 31 | `resources/views/admin/header-footer-preview.blade.php` | Protected WebsiteTab presentation revision and shared public site-shell; source preview/editor workflow not yet equivalent. |
| 32 | `resources/views/admin/header-footer.blade.php` | Protected WebsiteTab presentation revision and shared public site-shell; source preview/editor workflow not yet equivalent. |
| 33 | `resources/views/admin/homepage-sections.blade.php` | Protected WebsiteTab homepage presentation + managed homepage purpose; section builder UI/order/visibility acceptance OPEN. |
| 34 | `resources/views/admin/managed-pages/form.blade.php` | Protected ContentTab managed-page draft form/list and publish API; protected preview/publish/rollback/collision checks OPEN. |
| 35 | `resources/views/admin/managed-pages/index.blade.php` | Protected ContentTab managed-page draft form/list and publish API; protected preview/publish/rollback/collision checks OPEN. |
| 36 | `resources/views/admin/navigation-preview.blade.php` | Protected WebsiteTab navigation JSON draft + backend validators/public SiteHeader; rich item editor/reorder/preview equivalence OPEN. |
| 37 | `resources/views/admin/navigation.blade.php` | Protected WebsiteTab navigation JSON draft + backend validators/public SiteHeader; rich item editor/reorder/preview equivalence OPEN. |
| 38 | `resources/views/admin/page-content.blade.php` | Protected ContentTab purpose-scoped managed-page forms; source dedicated page controls replaced, actual frontend equivalence OPEN. |
| 39 | `resources/views/admin/partials/navigation-fields.blade.php` | Protected WebsiteTab navigation JSON draft + backend validators/public SiteHeader; rich item editor/reorder/preview equivalence OPEN. |
| 40 | `resources/views/admin/partials/navigation-item-editor.blade.php` | Protected WebsiteTab navigation JSON draft + backend validators/public SiteHeader; rich item editor/reorder/preview equivalence OPEN. |
| 41 | `resources/views/admin/partials/promotion-fields.blade.php` | Website presentation promotion and protected commercial coupon controls; source public banner renderer/preview/authoring not yet implemented, internal I. |
| 42 | `resources/views/admin/promotions-preview.blade.php` | Website presentation promotion and protected commercial coupon controls; source public banner renderer/preview/authoring not yet implemented, internal I. |
| 43 | `resources/views/admin/promotions.blade.php` | Website presentation promotion and protected commercial coupon controls; source public banner renderer/preview/authoring not yet implemented, internal I. |
| 44 | `resources/views/admin/seo-preview.blade.php` | Protected WebsiteTab SEO JSON draft + per-page/backend SEO fields; explicit editable field UI, preview/public metadata/sitemap OPEN. |
| 45 | `resources/views/admin/seo.blade.php` | Protected WebsiteTab SEO JSON draft + per-page/backend SEO fields; explicit editable field UI, preview/public metadata/sitemap OPEN. |
| 46 | `resources/views/contact.blade.php` | Published purpose-scoped /[slug] or public profile/contact; no source copy or dedicated URL parity assumed. |
| 47 | `resources/views/home.blade.php` | Next / published homepage/profile/services/catalogue; section ordering/media and mode publication remain W06 acceptance. |
| 48 | `resources/views/home/sections/about.blade.php` | Next / published homepage/profile/services/catalogue; section ordering/media and mode publication remain W06 acceptance. |
| 49 | `resources/views/home/sections/contact.blade.php` | Next / published homepage/profile/services/catalogue; section ordering/media and mode publication remain W06 acceptance. |
| 50 | `resources/views/home/sections/hero.blade.php` | Next / published homepage/profile/services/catalogue; section ordering/media and mode publication remain W06 acceptance. |
| 51 | `resources/views/home/sections/media.blade.php` | Next / published homepage/profile/services/catalogue; section ordering/media and mode publication remain W06 acceptance. |
| 52 | `resources/views/home/sections/products.blade.php` | Next / published homepage/profile/services/catalogue; section ordering/media and mode publication remain W06 acceptance. |
| 53 | `resources/views/home/sections/solutions.blade.php` | Next / published homepage/profile/services/catalogue; section ordering/media and mode publication remain W06 acceptance. |
| 54 | `resources/views/managed-pages/page.blade.php` | Next /[slug] managed page generic renderer; original template-standard/wide equivalence pending source-specific rendering checks. |
| 55 | `resources/views/managed-pages/standard.blade.php` | Next /[slug] managed page generic renderer; original template-standard/wide equivalence pending source-specific rendering checks. |
| 56 | `resources/views/managed-pages/wide.blade.php` | Next /[slug] managed page generic renderer; original template-standard/wide equivalence pending source-specific rendering checks. |
| 57 | `resources/views/partials/storefront-footer.blade.php` | Next SiteHeader/SiteFooter and published navigation; source promotion banner partials have no corresponding public render yet [I]. |
| 58 | `resources/views/partials/storefront-header.blade.php` | Next SiteHeader/SiteFooter and published navigation; source promotion banner partials have no corresponding public render yet [I]. |
| 59 | `resources/views/partials/storefront-nav.blade.php` | Next SiteHeader/SiteFooter and published navigation; source promotion banner partials have no corresponding public render yet [I]. |
| 60 | `resources/views/partials/storefront-promotion-announcement.blade.php` | Next SiteHeader/SiteFooter and published navigation; source promotion banner partials have no corresponding public render yet [I]. |
| 61 | `resources/views/partials/storefront-promotion-banners.blade.php` | Next SiteHeader/SiteFooter and published navigation; source promotion banner partials have no corresponding public render yet [I]. |
| 62 | `resources/views/partials/website-seo.blade.php` | Next generateMetadata, sitemap/robots and authoritative business origin; editable page/Software/global SEO and media still OPEN. |
| 63 | `tests/Feature/WebsiteHeaderFooterTest.php` | Source test is a behavioral reference, NOT a target PASS; target WebsiteCms presentation and real Next shell check pending. |
| 64 | `tests/Feature/WebsiteHomepageSectionRegistryTest.php` | Source test reference; target homepage publishing/structured section mode/browser join pending. |
| 65 | `tests/Feature/WebsiteLegalContentTest.php` | Source test reference; target typed policy/recent-auth and approved-only public evidence reused, exact legal production copy HOLD. |
| 66 | `tests/Feature/WebsiteManagedPageContentSeoTest.php` | Source test reference; target WebsiteCmsTest and real managed-page browser checks partially accepted, template/SEO/collision parity OPEN. |
| 67 | `tests/Feature/WebsiteManagedPageFoundationTest.php` | Source test reference; target WebsiteCmsTest and real managed-page browser checks partially accepted, template/SEO/collision parity OPEN. |
| 68 | `tests/Feature/WebsiteNavigationAdminBuilderTest.php` | Source test reference; target navigation validators and actual protected/public destination/ordering checks OPEN. |
| 69 | `tests/Feature/WebsiteNavigationFoundationTest.php` | Source test reference; target navigation validators and actual protected/public destination/ordering checks OPEN. |
| 70 | `tests/Feature/WebsiteNavigationPagePublishAcceptanceTest.php` | Source test reference; target navigation validators and actual protected/public destination/ordering checks OPEN. |
| 71 | `tests/Feature/WebsitePromotionsTest.php` | Source test reference; target presentation and distinct coupon backend not yet banner parity PASS. |
| 72 | `tests/Feature/WebsiteSeoTest.php` | Source test reference; target Next metadata and editable SEO/browser checks OPEN [I/E]. |

## Resolved source routes: 45 of 45

Legacy admin methods/URLs need not remain publicly mounted: each disposition below identifies the approved target workflow, and separately names proof still missing. Deprecated original route security/content may not be silently re-enabled for a superficial 1:1 link.

| # | Legacy method and path | Target contract and evidence limit |
|---:|---|---|
| 01 | `GET|HEAD /` | Next / published home + WebsiteApi content/profile; original homepage copy no import. |
| 02 | `GET|HEAD admin/about` | Protected Platform ContentTab managed about/contact pages and versioned revisions; separate legacy admin URLs retired; direct preview/rollback GUI equivalence OPEN. |
| 03 | `PUT admin/about` | Protected Platform ContentTab managed about/contact pages and versioned revisions; separate legacy admin URLs retired; direct preview/rollback GUI equivalence OPEN. |
| 04 | `GET|HEAD admin/contact` | Protected Platform ContentTab managed about/contact pages and versioned revisions; separate legacy admin URLs retired; direct preview/rollback GUI equivalence OPEN. |
| 05 | `PUT admin/contact` | Protected Platform ContentTab managed about/contact pages and versioned revisions; separate legacy admin URLs retired; direct preview/rollback GUI equivalence OPEN. |
| 06 | `PUT admin/contact/revisions/{revision}/rollback` | Protected Platform ContentTab managed about/contact pages and versioned revisions; separate legacy admin URLs retired; direct preview/rollback GUI equivalence OPEN. |
| 07 | `GET|HEAD admin/header-footer` | Protected /internal/admin/platform presentation revisions (draft/publish/rollback), Next SiteHeader/SiteFooter; legacy PUT/preview workflows replaced; browser parity OPEN. |
| 08 | `PUT admin/header-footer` | Protected /internal/admin/platform presentation revisions (draft/publish/rollback), Next SiteHeader/SiteFooter; legacy PUT/preview workflows replaced; browser parity OPEN. |
| 09 | `PUT admin/header-footer/preview` | Protected /internal/admin/platform presentation revisions (draft/publish/rollback), Next SiteHeader/SiteFooter; legacy PUT/preview workflows replaced; browser parity OPEN. |
| 10 | `PUT admin/header-footer/revisions/{revision}/rollback` | Protected /internal/admin/platform presentation revisions (draft/publish/rollback), Next SiteHeader/SiteFooter; legacy PUT/preview workflows replaced; browser parity OPEN. |
| 11 | `GET|HEAD admin/legal-content` | Protected Platform Content legal typed policies and recent-auth publishing; approved-only public policy. Owner factual legal copy HOLD. |
| 12 | `GET|HEAD admin/managed-pages` | Protected Platform Content managed-page create/draft/publish/rollback via POST JSON; public /[slug] after publication; legacy per-page GET/PUT/SEO/unpublish routes retired; per-field UX and denial proof OPEN. |
| 13 | `POST admin/managed-pages` | Protected Platform Content managed-page create/draft/publish/rollback via POST JSON; public /[slug] after publication; legacy per-page GET/PUT/SEO/unpublish routes retired; per-field UX and denial proof OPEN. |
| 14 | `GET|HEAD admin/managed-pages/create` | Protected Platform Content managed-page create/draft/publish/rollback via POST JSON; public /[slug] after publication; legacy per-page GET/PUT/SEO/unpublish routes retired; per-field UX and denial proof OPEN. |
| 15 | `PUT admin/managed-pages/{page}` | Protected Platform Content managed-page create/draft/publish/rollback via POST JSON; public /[slug] after publication; legacy per-page GET/PUT/SEO/unpublish routes retired; per-field UX and denial proof OPEN. |
| 16 | `GET|HEAD admin/managed-pages/{page}/edit` | Protected Platform Content managed-page create/draft/publish/rollback via POST JSON; public /[slug] after publication; legacy per-page GET/PUT/SEO/unpublish routes retired; per-field UX and denial proof OPEN. |
| 17 | `GET|HEAD admin/managed-pages/{page}/preview` | Protected Platform Content managed-page create/draft/publish/rollback via POST JSON; public /[slug] after publication; legacy per-page GET/PUT/SEO/unpublish routes retired; per-field UX and denial proof OPEN. |
| 18 | `PUT admin/managed-pages/{page}/publish` | Protected Platform Content managed-page create/draft/publish/rollback via POST JSON; public /[slug] after publication; legacy per-page GET/PUT/SEO/unpublish routes retired; per-field UX and denial proof OPEN. |
| 19 | `PUT admin/managed-pages/{page}/revisions/{revision}/rollback` | Protected Platform Content managed-page create/draft/publish/rollback via POST JSON; public /[slug] after publication; legacy per-page GET/PUT/SEO/unpublish routes retired; per-field UX and denial proof OPEN. |
| 20 | `PUT admin/managed-pages/{page}/seo` | Protected Platform Content managed-page create/draft/publish/rollback via POST JSON; public /[slug] after publication; legacy per-page GET/PUT/SEO/unpublish routes retired; per-field UX and denial proof OPEN. |
| 21 | `PUT admin/managed-pages/{page}/unpublish` | Protected Platform Content managed-page create/draft/publish/rollback via POST JSON; public /[slug] after publication; legacy per-page GET/PUT/SEO/unpublish routes retired; per-field UX and denial proof OPEN. |
| 22 | `GET|HEAD admin/navigation` | Protected Platform Website presentation navigation revision (JSON) + WebsiteCms applyNavigation, public mode-scoped SiteHeader; no 1:1 legacy CRUD routes; nested/reorder/preview/visibility parity OPEN. |
| 23 | `POST admin/navigation` | Protected Platform Website presentation navigation revision (JSON) + WebsiteCms applyNavigation, public mode-scoped SiteHeader; no 1:1 legacy CRUD routes; nested/reorder/preview/visibility parity OPEN. |
| 24 | `GET|HEAD admin/navigation/preview` | Protected Platform Website presentation navigation revision (JSON) + WebsiteCms applyNavigation, public mode-scoped SiteHeader; no 1:1 legacy CRUD routes; nested/reorder/preview/visibility parity OPEN. |
| 25 | `PUT admin/navigation/publish` | Protected Platform Website presentation navigation revision (JSON) + WebsiteCms applyNavigation, public mode-scoped SiteHeader; no 1:1 legacy CRUD routes; nested/reorder/preview/visibility parity OPEN. |
| 26 | `PUT admin/navigation/reorder` | Protected Platform Website presentation navigation revision (JSON) + WebsiteCms applyNavigation, public mode-scoped SiteHeader; no 1:1 legacy CRUD routes; nested/reorder/preview/visibility parity OPEN. |
| 27 | `PUT admin/navigation/revisions/{revision}/rollback` | Protected Platform Website presentation navigation revision (JSON) + WebsiteCms applyNavigation, public mode-scoped SiteHeader; no 1:1 legacy CRUD routes; nested/reorder/preview/visibility parity OPEN. |
| 28 | `PUT admin/navigation/{item}` | Protected Platform Website presentation navigation revision (JSON) + WebsiteCms applyNavigation, public mode-scoped SiteHeader; no 1:1 legacy CRUD routes; nested/reorder/preview/visibility parity OPEN. |
| 29 | `DELETE admin/navigation/{item}` | Protected Platform Website presentation navigation revision (JSON) + WebsiteCms applyNavigation, public mode-scoped SiteHeader; no 1:1 legacy CRUD routes; nested/reorder/preview/visibility parity OPEN. |
| 30 | `PATCH admin/navigation/{item}/enabled` | Protected Platform Website presentation navigation revision (JSON) + WebsiteCms applyNavigation, public mode-scoped SiteHeader; no 1:1 legacy CRUD routes; nested/reorder/preview/visibility parity OPEN. |
| 31 | `PATCH admin/navigation/{item}/visibility` | Protected Platform Website presentation navigation revision (JSON) + WebsiteCms applyNavigation, public mode-scoped SiteHeader; no 1:1 legacy CRUD routes; nested/reorder/preview/visibility parity OPEN. |
| 32 | `GET|HEAD admin/page-content` | Protected Platform ContentTab managed pages; legacy admin page-content shortcut retired. |
| 33 | `GET|HEAD admin/promotions` | Protected Platform website.presentation promotion and separate PromotionServices coupons; source promotional banners have no target public renderer yet [I]. |
| 34 | `POST admin/promotions` | Protected Platform website.presentation promotion and separate PromotionServices coupons; source promotional banners have no target public renderer yet [I]. |
| 35 | `GET|HEAD admin/promotions-preview` | Protected Platform website.presentation promotion and separate PromotionServices coupons; source promotional banners have no target public renderer yet [I]. |
| 36 | `POST admin/promotions/publish` | Protected Platform website.presentation promotion and separate PromotionServices coupons; source promotional banners have no target public renderer yet [I]. |
| 37 | `PUT admin/promotions/revisions/{revision}/rollback` | Protected Platform website.presentation promotion and separate PromotionServices coupons; source promotional banners have no target public renderer yet [I]. |
| 38 | `PUT admin/promotions/{promotionId}` | Protected Platform website.presentation promotion and separate PromotionServices coupons; source promotional banners have no target public renderer yet [I]. |
| 39 | `DELETE admin/promotions/{promotionId}` | Protected Platform website.presentation promotion and separate PromotionServices coupons; source promotional banners have no target public renderer yet [I]. |
| 40 | `GET|HEAD admin/seo` | Protected website.presentation SEO revisions and managed-page/Software SEO; original global SEO editor and public metadata not yet demonstrated end-to-end; confirmed Software edit/render gap [I]. |
| 41 | `PUT admin/seo` | Protected website.presentation SEO revisions and managed-page/Software SEO; original global SEO editor and public metadata not yet demonstrated end-to-end; confirmed Software edit/render gap [I]. |
| 42 | `PUT admin/seo/preview` | Protected website.presentation SEO revisions and managed-page/Software SEO; original global SEO editor and public metadata not yet demonstrated end-to-end; confirmed Software edit/render gap [I]. |
| 43 | `PUT admin/seo/revisions/{revision}/rollback` | Protected website.presentation SEO revisions and managed-page/Software SEO; original global SEO editor and public metadata not yet demonstrated end-to-end; confirmed Software edit/render gap [I]. |
| 44 | `GET|HEAD admin/settings` | Protected unified Platform Admin Website/Content settings; original separate admin/settings route retired. |
| 45 | `GET|HEAD {slug}` | Next /[slug] published managed page/policy, protected roots and unsupported scopes denied; targeted collisions/templates/metadata OPEN. |

## First demonstrated non-HOLD implementation gap

`backend/app/Cms/WebsiteCms.php::softwareSnapshot()` validates/persists independent `seo.title`, `seo.description`, canonical, social title/description/image and sitemap. But `backend/resources/js/pages/platform-admin.tsx::SoftwareTab::softwareInput()` unconditionally substitutes product name/summary and nulls for those editable SEO values; it offers no per-product SEO field controls. `website/src/app/software/[slug]/page.tsx::generateMetadata()` derives title/description only from name/summary rather than the approved saved SEO. This is **internal I/E**, not real-domain/H-02 HOLD. Current W06 next action is one bounded product-specific Admin SEO edit -> real published Next metadata/sitemap/second-product isolation acceptance, including safe canonical and social-image behavior where supported. Independently verify API shape and missing media rendering before deciding scope; do not declare 72/45 source behavioral PASS from this disposition. Additional concrete internal gap: `website/src/components/site-shell.tsx` and `website/src/app/page.tsx` do not render the published Website promotion/banner setting at all, and the public navigation projection currently flattens parent relations; nested menu behavior cannot be declared equivalent. Navigation/banner/media/permissions/cache/collisions/modes gates remain OPEN until documented implementation and observable tests. Previously accepted second Software public six-route browser 4/4 and target CMS service tests remain useful unchanged evidence.

Status: `19/W06` OPEN [I,E]; MT-7.5 **17/27 DONE, 10 OPEN**, Q01 independent exact-candidate clean-schema OPEN; H-02 official merchant inputs/callback/refund/settlement and separate activation PRE-LAUNCH PENDING, external payment channels OFF. Owner-approved actual production content remains separate PRE-LAUNCH HOLD.

## 25-Sep-2026 evidence supersession: real published presentation

Source dispositions above retain their original mapping/history. Real protected Admin -> Next published nested navigation (including ancestor-hidden/mode-filtered links), mode-scoped safe-text announcements/banners, private revisions and audited rollback now have joined Edge 6/6 evidence in `MT-7.5_W06_PRESENTATION_ADMIN_PUBLIC_JOIN_2026-09-25.md`; old statements that these exact tests are unverified are superseded. This does not assert equivalence for remaining original controls, global SEO, managed media, role/security, or overall W06 closure.

### W06 current target acceptance supersession (25-Sep-2026)

Global SEO is now editable through protected Admin and versioned/published; Next homepage/root reads the published value. Managed-page social images are revision-scoped, private at rest and served only by a published, mode-allowed page via a same-origin endpoint. Actual protected Admin browser checks confirm both; reserved Next application roots are blocked from CMS page creation and non-SEO roles cannot author global SEO. Evidence: `MT-7.5_W06_GLOBAL_SEO_SOCIAL_MEDIA_SECURITY_2026-09-25.md`. This supersedes only prior implementation-gap labels for these exact behaviors, not the original immutable 72-file/45-route mapping or the remaining W06 finite closure gates.
