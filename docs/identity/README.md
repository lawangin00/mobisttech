# MT-2.2 - Identity, customer and authorization migration

This checkpoint implements shared-backend identity and authorization with synthetic target-only migration rehearsals. The [implementation ledger](../PROJECT_IMPLEMENTATION_STATUS.md) owns current progress. It does not import private source data or implement the later POS/Website interfaces, product migration or transactional features.

## Reuse and deliberate adaptations

Source authority remains POS `c61e47394e7b3db8a49cfe443c85b63835febc9b` and Website `04e7c49518f9f11f60c83ad44f9f4e2fd2539066`. Inspection used their isolated pinned exports; no original runtime, database or environment was accessed. Source model permission constants and role checks were adapted into `Admin`, `SuperAdmin` and `User`; source account-session device/network classification was adapted into `PosSessions`. Laravel's session guard, encrypted cookies, user provider, password broker, token repository, hashing and CSRF middleware remain the underlying implementations.

| Source identity/behavior | Shared target disposition |
|---|---|
| POS `users` / outlet guard context | `outlets`, never customer credentials; server-selected outlet plus authenticated operator replaces the secondary outlet guard. Source password field remains archived as `legacy_password`, never used for login. |
| POS `admins` | Independent `admin` guard/provider and `admins`; nine operational defaults, nine explicit configuration permissions; assigned open outlets only. |
| POS `super_admins` | Independent `superadmin` guard/provider and `super_admins`; POS permissions only, never Website owner rights. |
| POS `shop_admins` | `outlet_admins`; both parent keys resolved through source-qualified migration identity maps. |
| Website non-admin `users` | Scoped `CustomerAccount` provider in `users`; one explicitly linked `customers.website_user_id`. No email/mobile ownership matching. |
| Website admin `users` | Scoped `WebsiteAdmin` provider and independent `website_admin` guard; owner, manager, content_editor and operations preserve the source matrix. |
| Legacy Website null admin role | Only an explicitly validated imported admin row maps to owner, with `legacy_owner_mapped` audit. Runtime null/unknown roles deny all capabilities; unknown non-null imported roles quarantine. |
| Source passwords | Supported bcrypt/Argon2 hashes retained byte-for-byte during import; PHP verifies them and Laravel can rehash after successful login. No forced bulk reset or invented credential. |
| Source remembered/browser sessions | No source cookie, token or session is imported. New independent application requires sign-in; remembered tokens are cleared on import. This avoids moving active credentials across application keys/realms. |

The old combined POS email-priority login is expressed as explicit realm endpoints to prevent ambiguous same-email privilege selection. The later UI must choose the realm explicitly. Existing operational permissions remain unchanged; no grant is inferred from an account sharing an email with another realm. Exact matrices and negative cross-realm checks are in `IdentitySecurityTest`.

## HTTP/session contract

Customer routes use `/api/v1`; internal identity routes use `/internal/admin`, `/internal/superadmin` and `/internal/website_admin`. Each realm supports GET `/auth/csrf-cookie`, POST `/auth/login`, `/auth/logout`, `/auth/forgot-password`, `/auth/reset-password`, PATCH `/auth/password`, and authenticated GET `/account`. Customer-only POST `/auth/register` creates a customer without accepting privilege fields. POS admin GET `/outlets` and POST `/outlets/select` list/select only assigned, open outlets using public UUIDs and server-derived actor context.

`/sanctum/csrf-cookie` is the customer initialization alias. The implementation follows Sanctum's first-party session/CSRF pattern using the native Laravel components already installed; no bearer-token package, JWT, localStorage token or alternate business backend is needed. Next.js proxies only the fixed target `/api/v1/*` and customer CSRF alias. It neither proxies internal administration nor supplies service credentials.

Session cookies are `mobist_{realm}_session`; customer path is `/`, internal paths are `/internal/{realm}`. Cookies are host-only, HttpOnly, SameSite=Lax and Secure outside local/testing. Customer CSRF cookie is `XSRF-TOKEN`; internal cookies are `XSRF-TOKEN-{realm}`. The encrypted cookie value may be sent as `X-XSRF-TOKEN`, or the initialization response's session token as `X-CSRF-TOKEN`. CSRF is required even on login/logout and even with a same-origin Fetch Metadata header. The test harness exercises actual middleware, without Laravel's usual test CSRF bypass.

Production configuration requires distinct HTTPS customer/admin origin hosts; wrong hosts and forged supplied origins fail closed. Nginx/TLS/proxy deployment validation remains a later gate. Private identity successes and errors use `Cache-Control: private, no-store` and `Referrer-Policy: no-referrer`. DTOs explicitly expose public UUID/name/contact fields, never passwords, tokens, raw permission payloads or internal membership pivots. Malformed JSON returns 400; expired/missing CSRF 419; invalid credentials/fields 422; unauthenticated access 401; inaccessible outlets 404. Throttling returns 429 with Retry-After: five unsafe attempts per realm/path/email/IP per minute plus thirty per realm/IP.

Login, registration and outlet selection rotate sessions. Password replacement rotates remembered credentials and increments `auth_version`; older sessions are rejected on their next authenticated request. Logout invalidates only the current realm. POS retains one desktop plus one mobile at the same source-defined network location; same-device login replaces its previous session, and revoked/missing session records cannot silently re-register. Account-row locking serializes registrations. Tests verify device replacement, competing devices, network changes, logout and revocation; no independent multi-process race acceptance is claimed here.

`Access` rechecks exact grants and outlet membership; future business writes must call it using the authenticated actor and server-owned resource. `ownOrder` authorizes by the source Website `orders.user_id` relationship only. It never grants access through matching contact details, a guest order or an unverified customer observation. This ownership primitive is tested directly; no order-reading business route is introduced at this point.

## Recovery and import boundaries

Each realm has a separate reset-token table and broker, with hashed single-use tokens, sixty-minute expiry and sixty-second resend throttle. Equal emails in other realms cannot consume or replace the intended token. Reset/password-change writes lock the account, audit the action, rotate credentials and revoke sessions. The recovery response does not disclose whether an eligible account exists.

`IDENTITY_RECOVERY_DELIVERY_ENABLED=false` is the safe default; log/array mailers are rejected so a reset link is not accidentally logged. Notification-fake tests exercise issuance, expiry, account exclusion and broker consumption without sending any email. Real SMTP delivery and the reset/login UI remain later integration/interface gates (including applicable HOLDs); generated link paths are contracts for those future forms, not currently served pages. Do not enable delivery until the approved mail transport and those forms are ready.

`IdentityImporter::import` is an internal target rehearsal primitive, not an HTTP/file-import surface or a connection to the originals. It requires a `migration_runs` record explicitly marked `identity_rehearsal` for the connected target database. It supports only the five identity source tables listed above and uses the frozen `docs/schema/COLUMN_DESTINATIONS.json` contract. A deployment using this rehearsal service must include that manifest outside the public web root.

Rows must contain the exact known columns; string lengths, booleans, integers, timestamps, password hash algorithms, role/permission values and parent identities are validated before committing. UTC/Asia-Karachi interpretation is explicit; timestamps convert to UTC. Source row plus timezone determine the replay digest. Identical replay returns the existing mapping; changed input/timezone, duplicate credential identities, unsupported values and unresolved memberships quarantine without partial accounts or silent merging. Quarantine records retain a safe reason and digest, not private row payloads. Website customer links use proven source-account identity only. Matching POS/guest contact observations remain separate.

Only synthetic rows were rehearsed, under test transactions. Actual private-data export/import, collision adjudication, historical customer linking and production cutover remain H-03 and their later migration/recovery gates. This checkpoint does not claim that private source data has been migrated or that every real row is accepted without reconciliation.

## Verification and reproduction

`MT_2_2_VERIFICATION.json` records fresh target tests, schema hashes, source lineage and checked artifact hashes. MT-1.2 DCASE-01 through DCASE-04 are covered by target collision/contact/role/reset tests. Broader CSRF/session checks also prepare for DCASE-05, but do not close its MT-4.1 UI gate. Historical MT-1.1/MT-1.2/MT-2.1 artifacts remain their checkpoint evidence, not live progress claims.

From the repository root, start only the owned runtime with `pwsh -NoProfile -File tools/dev/Database.ps1 Start`; apply migrations to local and testing with `php backend/artisan migrate --no-interaction` and `php backend/artisan migrate --env=testing --no-interaction`. From `backend`, run `php artisan test --no-ansi`, `php vendor/laravel/pint/builds/pint --test`, Composer validation/platform checks, and `npm.cmd run build`. From `website`, run lint, typecheck and build scripts. Never execute these in either protected source repository.

The new migration adds three reset-token tables, minimal identity audit events, credential public UUIDs/auth versions, and session revocation time. MySQL now has 73 tables, 916 columns, 117 foreign keys and 326 indexes. `php tools/migration/verify_mysql_schema.php identity` checks the exact disposable schema, zero business rows, InnoDB, strict SQL/UTC and a normalized schema hash. On that empty test database only, rolling back **one** migration with `php backend/artisan migrate:rollback --env=testing --step=1 --no-interaction` restores the exact MT-2.1 `shared` hash; reapply restores the exact identity hash. This is not permission to roll back populated local/production data.

Fresh gates include the full backend suite, POS TypeScript/Vite, Website lint/typecheck/Next.js, Pint, Composer platform/lock validation, optimize/clear, schema rollback/reapply, source snapshot checks and current dependency audits. Browser access was previously denied by Browser Use URL policy and was not retried or bypassed. HTTP-kernel tests and builds are not browser/UI acceptance. No live Redis/S3/provider, email, payment, source service or production operation occurred.

Initial HTTP test-client failures exposed token rotation and cross-realm CSRF cookie collisions; distinct CSRF cookie names and a per-realm test cookie jar corrected them. A formatting invocation from the repository root encountered an ignored-directory access error; it was rerun correctly from `backend`. All affected gates were rerun successfully. Roadmap Markdown/DOCX, Goal, Preferences, Source of Truth and registry remain unchanged; no Word regeneration is required. Stop before MT-2.3.
