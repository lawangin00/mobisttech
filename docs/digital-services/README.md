# MT-3.5 Digital service catalogue and lead services

MT-3.5 extends the existing Digital Service and Service Request identities without replacing their historical rows. Active service discovery is governed by the published Website digital capability.

Service pricing is server-owned. Base services support quote, fixed, starting-from or package semantics; reusable packages and add-ons use versioned stable identities. Submitted enquiries persist immutable label, price-type, PKR amount and source-version snapshots so later catalogue edits do not rewrite lead history.

Progressive enquiries keep nonessential fields optional. Public submission is idempotent and uses a database-serialized per-fingerprint hourly rate bucket. Client-supplied price fields are rejected.

Reference uploads are byte-validated, MIME/size bounded, SHA-256 verified and stored through the existing private-object boundary. Public/admin projections never expose private object keys or storage paths.

Consultation/callback requests store timezone-aware preferences as UTC and are checked against optional configured availability. External calendar integration remains disabled unless a later approved integration explicitly enables it.

The lightweight lead pipeline owns assignment, follow-up dates, notes, consultation state and append-only event history with optimistic version checks. It is intentionally not a general-purpose CRM.

Website mode changes block new inactive digital discovery/enquiries while preserving authorized existing lead history. Project/proposal/milestone lifecycle remains MT-3.6 scope.
