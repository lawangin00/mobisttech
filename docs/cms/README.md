# MT-3.2 Dynamic CMS, Media and Presentation Services

MT-3.2 adds the backend-owned Website CMS publication model on top of the existing shared page, media, navigation and settings primitives.

Presentation changes use immutable draft/publish/rollback revisions with separate content-section permissions and versioned cache invalidation. POS settings remain distinct from Website presentation state.

Managed pages support safe rich content, capability scopes, SEO/social metadata, service associations, consent/disclosure controls and immutable revision history. Draft revisions never replace the current published projection.

Media registration validates actual uploaded bytes, computes MIME/size/hash server-side, verifies image dimensions, uses generated CMS paths and removes orphan objects if persistence fails.

Legal content is typed for Privacy, Terms, Returns/Refunds, Shipping/Delivery, Warranty and conditional Cookie, Digital Services and payment disclosures. Publication requires Website publish authority, recent Admin authentication, business-owner approval, factual review, an effective date and no unresolved decisions. No draft is represented as lawyer-approved.

Software products use one reusable backend template with protected canonical `/software/{slug}` identity, product-specific Overview/Privacy/Terms/FAQ content, immutable product revisions, release history and explicit redirect history for approved slug changes.

Release publication preserves prior versions and enforces documentation-impact review for Overview, Privacy, Terms, FAQ, system requirements and support guidance. Archived products retain revision/release audit history while disappearing from public-safe projections.

Public route rendering, Admin UI and versioned REST contracts remain owned by their later roadmap points; MT-3.2 establishes the authoritative backend CMS model only.
