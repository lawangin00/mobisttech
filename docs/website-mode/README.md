# MT-3.7 Website Operating Mode Publication

MT-3.7 promotes the existing Website operating-profile primitives into a backend-owned publication workflow for `digital_only`, `hybrid`, and `commerce_only`.

The workflow separates edit, preview, and publish authority. Preview returns the current/target capability impact before publication; publish creates an immutable `website.mode` configuration revision, updates the singleton profile transactionally, records audit/domain-event evidence, and bumps the versioned Website-mode cache namespace.

Mode changes never delete commerce, digital-service, order, invoice, project, payment, CMS, or publication history. New capability-sensitive operations are blocked when their capability is inactive, while explicitly authorized historical resources remain independently addressable.

Common, digital, commerce, and explicit mode-variant content scopes remain one shared CMS model rather than three copied page trees. Public route/API/CTA/SEO/sitemap consumers can use the same versioned capability snapshot and allowed-scope contract.

One-click rollback restores a prior published mode by creating a new published revision; historical revisions are retained and the cache/publication version advances again. The existing MT-3.2 schema is unchanged by MT-3.7.
