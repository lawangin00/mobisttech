# MT-7.5 W07 pinned source-to-target behavior disposition (25-Sep-2026 LOCAL)

Authority: frozen `docs/migration/SOURCE_SYMBOL_INVENTORY.json`, `FEATURE_PARITY_REGISTER.md` W07, approved fresh-business target and stable `20/W07`. Pinned family comprises **119/119 tagged source files and 15/15 mapped routes**; 36 files and 13 routes are W07-only, the remainder overlap other source families. Mapping is NOT behavior parity, proof of existing legacy data cutover, owner signoff, or W07 DONE. Protected original source inspected READ-ONLY; no legacy business data imported. Earlier W06 `d6cbddf` remains DONE, W07 typed theme `420eb84` and private media `a683160` are accepted only for their documented scopes.

## Frozen 119 source file dispositions

| # | Original Website source path | Approved fresh target / disposition | W07-specific gap |
|---:|---|---|---|
| 001 | `app/Http/Controllers/Admin/WebsiteBrandingController.php` | Canonical BusinessProfile + fixed brand fallback; Website CMS revision | OPEN W07 published branding-role images / history |
| 002 | `app/Http/Controllers/Admin/WebsiteMediaController.php` | Private CMS assets, scoped published image endpoints, safe Admin media lifecycle | ACCEPTED W07 media lifecycle; branding roles still OPEN |
| 003 | `app/Http/Controllers/Admin/WebsiteThemeController.php` | Theme typed WebsiteCms / Next layout + fixed tokens | ACCEPTED W07 theme publication |
| 004 | `app/Models/SiteConfigurationRevision.php` | WebsiteCms presentation, ConfigurationRecovery, site_settings/site_secret_settings | OPEN W07 masked Website secrets/key-mismatch/revision recovery |
| 005 | `app/Models/SiteMediaAsset.php` | Private CMS assets, scoped published image endpoints, safe Admin media lifecycle | ACCEPTED W07 media lifecycle; branding roles still OPEN |
| 006 | `app/Models/SiteMediaUsage.php` | Private CMS assets, scoped published image endpoints, safe Admin media lifecycle | ACCEPTED W07 media lifecycle; branding roles still OPEN |
| 007 | `app/Models/SiteSecretSetting.php` | WebsiteCms presentation, ConfigurationRecovery, site_settings/site_secret_settings | OPEN W07 masked Website secrets/key-mismatch/revision recovery |
| 008 | `app/Models/SiteSetting.php` | WebsiteCms presentation, ConfigurationRecovery, site_settings/site_secret_settings | OPEN W07 masked Website secrets/key-mismatch/revision recovery |
| 009 | `app/Services/SafeWebsiteMedia.php` | Private CMS assets, scoped published image endpoints, safe Admin media lifecycle | ACCEPTED W07 media lifecycle; branding roles still OPEN |
| 010 | `app/Services/WebsiteBranding.php` | Canonical BusinessProfile + fixed brand fallback; Website CMS revision | OPEN W07 published branding-role images / history |
| 011 | `app/Services/WebsiteBusinessIdentity.php` | Canonical BusinessProfile + fixed brand fallback; Website CMS revision | OPEN W07 published branding-role images / history |
| 012 | `app/Services/WebsiteConfigurationRevisions.php` | WebsiteCms presentation, ConfigurationRecovery, site_settings/site_secret_settings | OPEN W07 masked Website secrets/key-mismatch/revision recovery |
| 013 | `app/Services/WebsiteContentRevisions.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 014 | `app/Services/WebsiteMediaSelector.php` | Private CMS assets, scoped published image endpoints, safe Admin media lifecycle | ACCEPTED W07 media lifecycle; branding roles still OPEN |
| 015 | `app/Services/WebsiteSettings.php` | WebsiteCms presentation, ConfigurationRecovery, site_settings/site_secret_settings | OPEN W07 masked Website secrets/key-mismatch/revision recovery |
| 016 | `app/Services/WebsiteTheme.php` | Theme typed WebsiteCms / Next layout + fixed tokens | ACCEPTED W07 theme publication |
| 017 | `app/Support/WebsiteSettingRegistry.php` | WebsiteCms presentation, ConfigurationRecovery, site_settings/site_secret_settings | OPEN W07 masked Website secrets/key-mismatch/revision recovery |
| 018 | `database/migrations/2026_08_24_120000_create_site_settings_table.php` | Fresh canonical target schema / publication revisions; no original business data import | SHARED target schema; W07 media/theme accepted, secret pending |
| 019 | `database/migrations/2026_08_25_100000_seed_homepage_site_settings.php` | Fresh canonical target schema / publication revisions; no original business data import | SHARED target schema; W07 media/theme accepted, secret pending |
| 020 | `database/migrations/2026_08_28_133000_create_site_configuration_revisions_table.php` | Fresh canonical target schema / publication revisions; no original business data import | SHARED target schema; W07 media/theme accepted, secret pending |
| 021 | `database/migrations/2026_08_28_151000_create_site_secret_and_media_foundation.php` | Private CMS assets, scoped published image endpoints, safe Admin media lifecycle | ACCEPTED W07 media lifecycle; branding roles still OPEN |
| 022 | `resources/css/app.css` | Theme typed WebsiteCms / Next layout + fixed tokens | ACCEPTED W07 theme publication |
| 023 | `resources/css/design-tokens.css` | Theme typed WebsiteCms / Next layout + fixed tokens | ACCEPTED W07 theme publication |
| 024 | `resources/js/app.js` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 025 | `resources/views/about.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 026 | `resources/views/admin/about-content.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 027 | `resources/views/admin/activity-log.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 028 | `resources/views/admin/admin-users.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 029 | `resources/views/admin/branding.blade.php` | Canonical BusinessProfile + fixed brand fallback; Website CMS revision | OPEN W07 published branding-role images / history |
| 030 | `resources/views/admin/catalogue-presentation.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 031 | `resources/views/admin/contact-content.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 032 | `resources/views/admin/dashboard.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 033 | `resources/views/admin/header-footer-preview.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 034 | `resources/views/admin/header-footer.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 035 | `resources/views/admin/homepage-sections.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 036 | `resources/views/admin/integration-status.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 037 | `resources/views/admin/layout.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 038 | `resources/views/admin/login.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 039 | `resources/views/admin/managed-pages/form.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 040 | `resources/views/admin/managed-pages/index.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 041 | `resources/views/admin/media-library.blade.php` | Private CMS assets, scoped published image endpoints, safe Admin media lifecycle | ACCEPTED W07 media lifecycle; branding roles still OPEN |
| 042 | `resources/views/admin/navigation-preview.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 043 | `resources/views/admin/navigation.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 044 | `resources/views/admin/order-show.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 045 | `resources/views/admin/orders.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 046 | `resources/views/admin/page-content.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 047 | `resources/views/admin/partials/configuration-history.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 048 | `resources/views/admin/partials/navigation-fields.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 049 | `resources/views/admin/partials/navigation-item-editor.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 050 | `resources/views/admin/partials/promotion-fields.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 051 | `resources/views/admin/payments-checkout.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 052 | `resources/views/admin/payments-easypaisa.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 053 | `resources/views/admin/payments-jazzcash.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 054 | `resources/views/admin/profile.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 055 | `resources/views/admin/project-quote-form.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 056 | `resources/views/admin/project-quotes.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 057 | `resources/views/admin/promotions-preview.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 058 | `resources/views/admin/promotions.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 059 | `resources/views/admin/reviews.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 060 | `resources/views/admin/seo-preview.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 061 | `resources/views/admin/seo.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 062 | `resources/views/admin/service-form.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 063 | `resources/views/admin/service-request-show.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 064 | `resources/views/admin/service-requests.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 065 | `resources/views/admin/services.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 066 | `resources/views/admin/settings.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 067 | `resources/views/admin/theme.blade.php` | Theme typed WebsiteCms / Next layout + fixed tokens | ACCEPTED W07 theme publication |
| 068 | `resources/views/auth/forgot-password.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 069 | `resources/views/auth/reset-password.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 070 | `resources/views/cart.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 071 | `resources/views/checkout.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 072 | `resources/views/compare.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 073 | `resources/views/components/admin/filter-panel.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 074 | `resources/views/components/admin/form-panel.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 075 | `resources/views/components/admin/table.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 076 | `resources/views/contact.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 077 | `resources/views/customer/account.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 078 | `resources/views/customer/login.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 079 | `resources/views/customer/register.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 080 | `resources/views/home.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 081 | `resources/views/home/sections/about.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 082 | `resources/views/home/sections/contact.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 083 | `resources/views/home/sections/hero.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 084 | `resources/views/home/sections/media.blade.php` | Private CMS assets, scoped published image endpoints, safe Admin media lifecycle | ACCEPTED W07 media lifecycle; branding roles still OPEN |
| 085 | `resources/views/home/sections/products.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 086 | `resources/views/home/sections/solutions.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 087 | `resources/views/invoice-demo.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 088 | `resources/views/invoice.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 089 | `resources/views/managed-pages/page.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 090 | `resources/views/managed-pages/standard.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 091 | `resources/views/managed-pages/wide.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 092 | `resources/views/order-payment.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 093 | `resources/views/order-status.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 094 | `resources/views/partials/cart-link.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 095 | `resources/views/partials/payment-options.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 096 | `resources/views/partials/storefront-footer.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 097 | `resources/views/partials/storefront-header.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 098 | `resources/views/partials/storefront-nav.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 099 | `resources/views/partials/storefront-promotion-announcement.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 100 | `resources/views/partials/storefront-promotion-banners.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 101 | `resources/views/partials/website-seo.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 102 | `resources/views/partials/website-theme.blade.php` | Theme typed WebsiteCms / Next layout + fixed tokens | ACCEPTED W07 theme publication |
| 103 | `resources/views/product.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 104 | `resources/views/project-payment-lookup.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 105 | `resources/views/project-payment.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 106 | `resources/views/service-request-status.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 107 | `resources/views/service-request.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 108 | `resources/views/services.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 109 | `resources/views/shop.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 110 | `resources/views/welcome.blade.php` | Protected unified Platform Admin + published Next Website; legacy dedicated Blade/old URL retired | SHARED previously DONE scope, W07 behavior only where distinct |
| 111 | `tests/Feature/WebsiteBrandThemeRecoveryAcceptanceTest.php` | Theme typed WebsiteCms / Next layout + fixed tokens | SHARED prior family / W07 typed theme accepted |
| 112 | `tests/Feature/WebsiteBrandingTest.php` | Canonical BusinessProfile + fixed brand fallback; Website CMS revision | OPEN W07 published branding-role images / history |
| 113 | `tests/Feature/WebsiteBusinessIdentityTest.php` | Canonical BusinessProfile + fixed brand fallback; Website CMS revision | OPEN W07 published branding-role images / history |
| 114 | `tests/Feature/WebsiteConfigurationPermissionAuditTest.php` | Target scoped PHP/Edge evidence; source tests reference behavior only | REVIEW targeted W07 tests; never count source test as target PASS |
| 115 | `tests/Feature/WebsiteConfigurationRevisionTest.php` | WebsiteCms presentation, ConfigurationRecovery, site_settings/site_secret_settings | OPEN W07 masked Website secrets/key-mismatch/revision recovery |
| 116 | `tests/Feature/WebsiteConfigurationSecurityTest.php` | WebsiteCms presentation, ConfigurationRecovery, site_settings/site_secret_settings | OPEN W07 masked Website secrets/key-mismatch/revision recovery |
| 117 | `tests/Feature/WebsiteMediaLibraryTest.php` | Private CMS assets, scoped published image endpoints, safe Admin media lifecycle | ACCEPTED W07 media lifecycle; branding roles still OPEN |
| 118 | `tests/Feature/WebsiteSettingsTest.php` | WebsiteCms presentation, ConfigurationRecovery, site_settings/site_secret_settings | OPEN W07 masked Website secrets/key-mismatch/revision recovery |
| 119 | `tests/Feature/WebsiteThemeTest.php` | Theme typed WebsiteCms / Next layout + fixed tokens | ACCEPTED W07 theme publication |

## Frozen 15 source route dispositions

| # | Original method and route | Target protected replacement / retirement | W07 status |
|---:|---|---|---|
| 01 | `GET|HEAD admin/branding` | Protected Platform Website presentation branding revision and canonical business profile; source direct Blade routes retired | OPEN: published branding-role media, protected preview/recovery and real public rendering |
| 02 | `PUT admin/branding` | Protected Platform Website presentation branding revision and canonical business profile; source direct Blade routes retired | OPEN: published branding-role media, protected preview/recovery and real public rendering |
| 03 | `PUT admin/branding/preview` | Protected Platform Website presentation branding revision and canonical business profile; source direct Blade routes retired | OPEN: published branding-role media, protected preview/recovery and real public rendering |
| 04 | `PUT admin/branding/revisions/{revision}/rollback` | Protected Platform Website presentation branding revision and canonical business profile; source direct Blade routes retired | OPEN: published branding-role media, protected preview/recovery and real public rendering |
| 05 | `GET|HEAD admin/media` | Protected Platform Content Website media library, `/platform/data` | W07 protected upload/alt/new-ID replacement/unused delete accepted; legacy in-place replace intentionally retired |
| 06 | `POST admin/media` | POST `/internal/admin/platform/media` | W07 protected upload/alt/new-ID replacement/unused delete accepted; legacy in-place replace intentionally retired |
| 07 | `DELETE admin/media/{asset}` | DELETE `/internal/admin/platform/media/{media}` (recent auth; unused only) | W07 protected upload/alt/new-ID replacement/unused delete accepted; legacy in-place replace intentionally retired |
| 08 | `PUT admin/media/{asset}/alt-text` | PATCH `/internal/admin/platform/media/{media}/alt` | W07 protected upload/alt/new-ID replacement/unused delete accepted; legacy in-place replace intentionally retired |
| 09 | `PUT admin/media/{asset}/replace` | POST `/internal/admin/platform/media/{media}/replacement` (immutable NEW media ID) | W07 protected upload/alt/new-ID replacement/unused delete accepted; legacy in-place replace intentionally retired |
| 10 | `GET|HEAD admin/page-content` | Unified protected Platform Content/Website settings, original shortcut URL retired | SHARED W06 accepted for page content; W07 secret/settings recovery still OPEN |
| 11 | `GET|HEAD admin/settings` | Unified protected Platform Content/Website settings, original shortcut URL retired | SHARED W06 accepted for page content; W07 secret/settings recovery still OPEN |
| 12 | `GET|HEAD admin/theme` | Protected Platform Website theme builder + shared website.presentation draft/publish/rollback; Next public CSS | W07 typed theme publication/rollback accepted; standalone source Blade/PUT URLs retired |
| 13 | `PUT admin/theme` | Protected Platform Website theme builder + shared website.presentation draft/publish/rollback; Next public CSS | W07 typed theme publication/rollback accepted; standalone source Blade/PUT URLs retired |
| 14 | `PUT admin/theme/preview` | Protected Platform Website theme builder + shared website.presentation draft/publish/rollback; Next public CSS | W07 typed theme publication/rollback accepted; standalone source Blade/PUT URLs retired |
| 15 | `PUT admin/theme/revisions/{revision}/rollback` | Protected Platform Website theme builder + shared website.presentation draft/publish/rollback; Next public CSS | W07 typed theme publication/rollback accepted; standalone source Blade/PUT URLs retired |

## Exact finite remaining evidence

- Published Website branding-role image assets and active/historical role bindings, private media public endpoint/Next fallback; no public enumerability.
- Website-specific permissioned secret masking/change and key mismatch fail-closed; configuration revision/recovery behavior without plaintext in responses, logs or history. Existing `OperationalRecoveryTest` proves shared verify-only snapshot/rotated-secret hashing and encrypted backup rehearsal, not a complete Website-specific settings workflow.
- One current-candidate scoped protected/public three-mode responsive, security/cache and safe-owned fixture join once all W07 non-HOLD behavior is implemented. Previously PASS W06 and typed W07 theme/media cases are reused unless impacted.
- All 119/15 are mapped, NOT 119/15 equivalent behavior PASS. Shared W02-W06 and P07 already accepted gates stay DONE; do not reopen unrelated scope from tag overlap. W07 remains OPEN and MT-7.5 18/27 DONE, 9 OPEN.
