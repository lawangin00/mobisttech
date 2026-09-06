# mobiST Tech - Software Product Publishing Requirement

Version: 1.0 | Approved: 2026-09-06

## Purpose and authority

This requirement adds a reusable first-party software publishing model to the existing mobiST Tech CMS and Website plan. It does not create a separate application, database, CMS or roadmap stage.

The requirement applies to mobiST-owned software products such as mobiST POS and to future software products launched through the same Website and Admin platform.

The existing `docs/reference/mobiST POS-IMS/` documents are factual seed/reference material for the first software entry. They are not automatically published without review.

## Canonical public route family

Software pages use one scalable namespace rather than unrelated root-level pages:

- `/software/{software-slug}` - canonical software overview/product page.
- `/software/{software-slug}/privacy` - product-specific Privacy Policy.
- `/software/{software-slug}/terms` - product-specific Terms of Service/Use.
- `/software/{software-slug}/faq` - product-specific FAQ.
- `/software/{software-slug}/releases` - version history and release notes.
- `/software/{software-slug}/releases/{version}` - optional canonical detail route for a published release.

For mobiST POS, the planned canonical slug is `mobist-pos`; final production URLs depend on the separately authorized live domain.
## Software content model

Each software product has a stable identity and a reusable content template. At minimum it supports:

- product name, protected unique slug, short summary and full overview;
- lifecycle state: Draft, Published or Archived;
- current public version and release date;
- supported platforms and system requirements;
- logo/icon, hero media, screenshots and optional demonstration media;
- key features/capabilities and important limitations;
- support/contact information and optional download/purchase/contact CTA;
- product-specific Privacy Policy, Terms and FAQ content;
- release/version history;
- SEO title/description, canonical URL, social metadata and sitemap state.

The overview page is the primary public software page. Product-specific policy and FAQ content remains linked to that software identity instead of being mixed into unrelated company-wide policy records.

A published slug is protected. If a later approved slug change is unavoidable, the old public route must redirect safely to the new canonical route rather than becoming an unrelated page.
## Admin workflow and reusable template

The protected Website CMS includes a `Software` administration area with a list of software products and a `New Software` action.

`New Software` creates a new Draft from one canonical software-page template rather than requiring a developer to hand-build routes or copy another product page.

The editor groups fields into clear areas such as Overview, Features, Media, Platform/Requirements, Documentation & Legal, Releases, SEO and Publication.

Administrators can save drafts, preview the complete public route family, publish an approved revision, create a later revision and roll back to a prior published revision where safe.

Create/edit and publish permissions remain separate. Policy publication keeps its stronger authorization/recent-authentication boundary where required by the existing legal-content model.

Archiving a software product stops new public promotion/discovery according to the approved publication state but does not delete its audit trail, revisions or intentionally preserved historical release information.
## Version and release lifecycle

Software updates are represented as release records, not by silently overwriting history. A release supports version, release date, publication state, summary and structured Added/Changed/Fixed/Security notes when applicable.

Publishing a new release may update the software product's current public version while preserving every earlier published release record.

Before a release is published, the Admin workflow must surface a documentation-impact review: does the change require updates to the Overview, Privacy Policy, Terms, FAQ, system requirements or support guidance?

A release that materially changes personal-data handling, external integrations, licensing/commercial terms or supported behavior cannot bypass the applicable policy/content review.

Release notes are customer-readable and must not expose secrets, private infrastructure details, signing material, internal credentials or unsafe diagnostic information.
## Public software page template

The responsive public template should present, as applicable:

- product identity, current version and concise value proposition;
- overview and key feature sections;
- screenshots/media;
- supported platforms and system requirements;
- download/purchase/contact/support action where applicable;
- documentation links for Privacy, Terms, FAQ and Releases;
- current release summary with access to version history;
- canonical SEO/social metadata and structured product/software information where appropriate.

The template is shared structurally but each software product owns its own approved content. Adding a new software product must not require a new hard-coded page tree.
## Domain and OAuth publication boundary

No live domain is required to design or implement this model. Live production URLs remain under the existing H-01 domain/TLS authorization boundary.

Once the mobiST production domain is active, an approved software overview route and its product-specific Privacy Policy route may be used as the public application homepage/privacy links for an external OAuth application where the provider accepts them and all provider verification requirements are satisfied.

Do not substitute temporary file-sharing URLs for the owned production-domain routes in final public configuration.

## Implementation ownership

- `MT-3.2` owns the backend CMS entities, reusable software template/content model, product policies, FAQs, revisions, releases and protected route identities.
- `MT-3.4` exposes versioned public software/content contracts for Next.js without leaking draft/private fields.
- `MT-4.4` owns the protected Admin list, New Software template workflow, editing, preview, release management and publication controls.
- `MT-5.4` owns the responsive public `/software/{slug}` route family and its SEO/sitemap behavior.
- `MT-7.2` audits security, content safety, policy/data-flow accuracy, release-note disclosure and performance.
- `MT-7.5` proves end-to-end creation, preview, publication, update, rollback and public route behavior.
- `MT-7.6` documents the administrator workflow in the final product manual.
- `FINAL-AUDIT` verifies this approved requirement is fully closed.
## Acceptance requirements

Acceptance must prove at least:

- unique/protected slugs and safe route handling;
- Draft -> Preview -> Publish and later revision/rollback behavior;
- separate create/edit versus publish authorization;
- one reusable template can create more than one software product without code duplication;
- product-specific Overview, Privacy, Terms, FAQ and Releases remain correctly associated;
- current-version updates preserve historical release records;
- documentation-impact review is enforced for material releases;
- draft/private content never leaks through public APIs, sitemap, metadata or caches;
- safe rich content/media validation, audit history and cache/SEO revalidation;
- responsive public pages and mode-aware discovery meet the existing Website performance gates;
- the mobiST POS reference documents can be reconciled into the first software entry without silently changing their factual meaning.

This requirement does not authorize live domain purchase, DNS/TLS changes, provider verification, production publication or customer-data migration. Those remain subject to their existing HOLD/authorization gates.