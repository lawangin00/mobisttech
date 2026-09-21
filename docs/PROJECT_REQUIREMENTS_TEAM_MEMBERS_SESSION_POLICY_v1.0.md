NEW APPROVED REQUIREMENT — TEAM MEMBER ROLE MODEL, DELEGATED ACCESS,
AND REALM-SPECIFIC SESSION SECURITY

This instruction is being provided directly inside the already-active
Mobisttech whole-step implementation project.

This instruction authorizes:
- structural reconciliation of this approved requirement; and
- implementation and verification of the resulting remediation point only.

Do NOT start MT-2.9 as part of this instruction.

Do NOT require a separate RESUME / VERIFY command before processing this
instruction.

Before implementing any change:

1. Read `docs/PROJECT_IMPLEMENTATION_STATUS.md`.
2. Identify and read the active canonical Source of Truth and roadmap.
3. Read the current unified Admin authority and implementation evidence,
   including:
   - `docs/PROJECT_REQUIREMENTS_UNIFIED_ADMIN_GOOGLE_v1.0.md`
   - `docs/remediation/RECONCILIATION.md`
   - relevant MT-2.18 verification evidence.
4. Verify that MT-2.7 is already completed and that the current genuinely
   pending point before this requirement is:
   `MT-2.9 - Supplier and procurement services`.
5. Verify current Git HEAD/history and actual implementation state.
6. Reconcile this requirement structurally before MT-2.9.
7. Do not rewrite or falsify completed MT-2.18 or MT-2.7 historical evidence.
8. Follow the global/project command, roadmap, ledger and verification rules.

======================================================================
A. FINAL AUTHENTICATION REALMS
======================================================================

Keep exactly two human authentication/credential realms:

1. Admin
2. Customer

Do NOT create separate authentication providers/realms for:

- Superadmin
- Owner
- Business Admin
- Website Admin
- Manager
- Store Manager
- Sales Associate
- Cashier
- Inventory Manager
- Service & Warranty
- Online Store Editor
- Merchandiser
- Customer Support
- Digital Operations
- other employee/job titles

All internal business personnel authenticate through the single Admin realm.

Customer/public/client accounts remain isolated in the Customer realm.

One human must have one individual credential identity.

Never use one shared generic shop/staff credential for multiple employees.

The same Admin credential may operate POS and Website administration
according to that person's effective permissions and outlet assignments.

======================================================================
B. TEAM MEMBER TERMINOLOGY
======================================================================

Use the user-facing term:

Team Members

for internal employees/personnel who authenticate through the Admin realm.

A Team Member record must support at minimum:

- individual identity/account;
- active/disabled state;
- one or more assigned Roles where the chosen RBAC model supports them;
- deterministic effective permissions;
- outlet assignments where applicable;
- auditable role/permission/outlet changes;
- auditable operational actions.

The technical Admin realm must not be treated as the employee's job title.

Example UI semantics:

Ali Khan
Role: Sales Associate
Outlet: Quaidabad
Status: Active

Underlying authentication realm: Admin

Where useful, support an optional human-readable Job Title separately from
authorization Role. Job Title must never grant permissions.

======================================================================
C. ROLE AND PERMISSION MODEL
======================================================================

Use Roles as named permission bundles.

Permissions remain the true server-side authorization boundary.

Outlet assignment is a separate authorization dimension and must not be
inferred merely from a Role name.

Provide maintainable default role templates suitable for the combined POS +
Website platform.

Recommended initial role catalogue:

1. Full Access
   - protected primary administrative authority;
   - complete approved POS, Website, configuration, integration,
     reporting and Team Member administration capabilities.

2. Manager
   - trusted cross-business management role;
   - only explicitly delegated capabilities;
   - may manage subordinate Team Members only within delegated authority.

3. Store Manager
   - operational management of assigned outlet(s);
   - outlet sales, stock, staff and reporting permissions as assigned.

4. Sales Associate
   - normal mobile-retail/POS sales employee;
   - sales, invoice and customer-facing transaction capabilities as assigned.

5. Cashier
   - register, collection and cash-operation focused access as assigned.

6. Inventory Manager
   - products, inventory, receiving, stocktake, transfers and related
     stock operations as assigned.

7. Service & Warranty
   - warranty intake, claims, service/repair and related history as assigned.

8. Online Store Editor
   - Website pages, CMS, navigation, content and media editing as assigned.

9. Merchandiser
   - catalogue, products, categories, pricing/presentation and merchandising
     operations as assigned.

10. Customer Support
    - authorized customer/order support without unrelated administrative
      authority.

11. Digital Operations
    - digital leads, projects, proposals, milestones, consultations and
      approved client-file operations as assigned.

12. Custom Role
    - administrator-defined permission bundle for legitimate business needs.

These are authorization roles/templates, not authentication account types.

Do not hard-code every employee category into a separate credential model.

======================================================================
D. SYSTEM ROLES AND CUSTOM ROLES
======================================================================

Full Access must be a protected system role/authority.

Other supplied roles are default business-ready templates.

Support safe Custom Roles so the business is not forced to create new
application code whenever responsibilities change.

Role names alone must never bypass permission checks.

Where role editing/customization is supported:

- validate every permission server-side;
- audit role creation/editing/deletion;
- prevent privilege escalation;
- prevent orphaned Team Members;
- preserve historical actor/action evidence after later role changes.

Do not silently grant new future permissions to existing custom roles.

New capabilities must follow an explicit safe migration/default policy.

======================================================================
E. DELEGATED TEAM MEMBER ADMINISTRATION
======================================================================

A Manager or another delegated Team Member may create/manage subordinate
Team Members only if explicitly authorized.

Implement server-side delegation ceilings.

A delegated Team Member must NEVER be able to:

- grant a permission outside their delegation authority;
- create/edit a role above their delegation ceiling;
- assign Full Access without protected authority;
- promote themselves;
- increase their own effective permissions;
- modify protected Full Access authority without explicit protected access;
- bypass outlet restrictions;
- assign another person to an outlet they are not authorized to administer;
- gain privilege indirectly through Custom Role creation/editing.

User-management permission must not automatically mean unlimited privilege
delegation.

Team Member creation, role changes, permission changes, outlet assignments,
activation/deactivation and security-sensitive changes must be auditable.

======================================================================
F. OUTLET / POS WORKFORCE MODEL
======================================================================

An Outlet is a business/location entity, not a shared human credential.

Every employee operating the POS must use their own Team Member credential.

Operational records must retain the acting Team Member and outlet context
where applicable, including:

- sales;
- invoices;
- returns;
- inventory adjustments;
- receiving;
- stocktakes;
- transfers;
- warranties/claims/service;
- cash operations;
- approvals;
- relevant reports/exports.

Example audit/presentation semantics:

Processed by: Ali Khan
Role: Sales Associate
Outlet: Quaidabad

Historical operational evidence must not become misleading merely because
the Team Member's current role later changes.

======================================================================
G. ADMIN REALM SESSION POLICY
======================================================================

All Team Members, including Full Access, Manager, Store Manager and every
other Admin-realm role, use one consistent administrative session-security
policy across POS and Website administration.

Required Admin policy:

- true inactivity timeout: 30 minutes;
- Remember Me / persistent login: disabled;
- closing the browser ends the normal Admin login session;
- same Admin identity/session policy across POS and Website administration;
- password change/reset revokes relevant existing sessions;
- remembered credentials for Admin must not silently recreate a session;
- disabled/archived accounts lose access promptly;
- existing device/network/session protections are preserved or improved;
- background polling, health checks, automatic refreshes or heartbeats must
  not keep an otherwise inactive Admin session alive indefinitely.

Where an authenticated Admin UI exists, provide an inactivity warning
approximately 5 minutes before expiry.

Example:

Your session will expire in 5 minutes.
[ Continue Session ]

The warning itself must not silently extend the session.

Only genuine user continuation/activity may extend the permitted session.

======================================================================
H. CURRENT ADMIN SESSION GAP
======================================================================

The current backend foundation uses a shared:

SESSION_LIFETIME=120

and the existing login contract currently accepts `remember` for both
Customer and Admin realms.

The existing Admin `PosSessions` activity window is also derived from the
shared session lifetime.

This must be reconciled.

Do NOT simply change the one global session lifetime from 120 to 30 because
Customer and Admin require different policies.

Create/use a centralized realm-specific identity/session policy so Admin
uses 30 minutes while Customer retains its separately approved behavior.

Ensure Admin device/session validation uses the Admin-specific inactivity
window rather than accidentally inheriting the Customer lifetime.

======================================================================
I. SENSITIVE ADMIN RE-AUTHENTICATION
======================================================================

Sensitive administrative actions must require recent authentication even if
the normal 30-minute Admin session remains valid.

Use one centralized, testable recent-authentication mechanism.

Use an appropriately short window, preferably approximately 5–10 minutes,
for high-risk operations such as applicable:

- Team Member creation or sensitive account administration;
- role/permission/delegation changes;
- Full Access assignment or protected authority changes;
- password/security changes;
- canonical business/security configuration;
- Gmail connect/reconnect/disconnect;
- Google Drive connect/reconnect/disconnect;
- payment credential/provider configuration;
- destructive backup/restore operations;
- data reset operations;
- other explicitly classified high-risk administrative actions.

Do not implement inconsistent per-screen ad hoc password checks.

======================================================================
J. CUSTOMER REALM SESSION POLICY
======================================================================

Customer security/convenience must remain independent from Admin security.

Required Customer policy:

- normal inactivity timeout: 120 minutes;
- Remember Me: optional;
- remembered-login lifetime capped at approximately 30 days, or a documented
  safer equivalent if required by the selected framework implementation;
- without Remember Me, closing the browser ends the normal Customer session;
- password change/reset revokes applicable sessions and remembered
  credentials;
- sensitive account/security changes require appropriate re-authentication.

Do not rely on an effectively unlimited framework-default remember-cookie
duration.

Customer convenience must never weaken Admin security.

Authentication expiry must not unnecessarily destroy durable business state.

Cart, order history, invoices, wishlist, approved client/project history or
other state that should survive authentication expiry must be persisted by
the proper owned server/business mechanism rather than only inside the
authentication session.

======================================================================
K. ADMIN / CUSTOMER ISOLATION
======================================================================

Preserve the already-approved unified identity architecture:

- exactly one Admin credential identity per internal human;
- same Admin credential usable for POS and Website administration;
- Admin permissions determine actual capability;
- outlet assignments determine location scope;
- Customer remains a completely separate realm;
- Customer identity never inherits Admin permission;
- matching legacy email addresses never union privileges;
- removed Superadmin and Website-admin credential providers must not return.

This requirement extends the unified Admin architecture; it does not reverse
MT-2.18.

======================================================================
L. LATER UI / API CONSUMERS
======================================================================

Ensure the resulting canonical requirement is consumed by later roadmap
points rather than implementing competing role/session models later.

At minimum reconcile traceability for:

- MT-4.1 - POS shell, authentication and navigation
- MT-4.4 - Website CMS and platform administration interfaces
- MT-5.2 - Customer account, cart, orders and reviews
- MT-7.2 - Security, performance and resilience audit
- FINAL-AUDIT - Independent final project audit

Do not duplicate the authentication authority in separate POS and Website
frontends.

The Laravel backend remains the authorization/session authority.

======================================================================
M. ROADMAP / PROJECT-CONTROL RECONCILIATION
======================================================================

MT-2.18 and MT-2.7 are already completed verified checkpoints.

Do not reopen, rewrite or falsify them.

Before the currently pending MT-2.9, create one new superseding remediation
point:

MT-2.19 - Team member roles, delegated access and session security remediation

Dependencies: MT-2.7

Then change:

MT-2.9 - Supplier and procurement services

so its dependency becomes:

Dependencies: MT-2.19

The explicit roadmap order must therefore become:

MT-2.18
→ MT-2.7
→ MT-2.19
→ MT-2.9
→ remaining dependency chain

MT-2.19 must cover at minimum:

- Team Members workforce model;
- Admin/Customer realm preservation;
- default role templates;
- protected Full Access authority;
- Custom Roles;
- granular backend permissions;
- outlet assignments;
- delegated Team Member administration;
- delegation ceilings;
- privilege-escalation prevention;
- per-human auditability;
- historical actor attribution;
- Admin 30-minute true inactivity timeout;
- Admin Remember Me removal;
- Admin browser-close session behavior;
- inactivity warning behavior;
- background-polling non-extension;
- sensitive-operation recent re-authentication;
- Customer 120-minute inactivity policy;
- optional approximately 30-day Customer Remember Me;
- Customer browser-close behavior when not remembered;
- password/reset/session/remember-token revocation;
- durable Customer business-state survival across auth expiry;
- focused identity/RBAC/session security tests;
- regression/build verification.

======================================================================
N. CANONICAL DOCUMENTATION CHANGES
======================================================================

Preserve this approved requirement as a Git-tracked canonical requirement
file using a clear repository-consistent path such as:

docs/PROJECT_REQUIREMENTS_TEAM_MEMBERS_SESSION_POLICY_v1.0.md

Create appropriate reconciliation/verification evidence for MT-2.19.

Update the canonical Source of Truth to incorporate the approved target
behavior.

Because one new roadmap point and dependency are being added, this is a
material structural roadmap change.

Therefore:

- update canonical roadmap Markdown;
- increment its version appropriately;
- regenerate the same-basename roadmap DOCX;
- verify Markdown/DOCX body/content parity;
- render the final DOCX;
- visually inspect every rendered page;
- fix any layout/content issue;
- update the implementation-status ledger;
- preserve completed historical checkpoint evidence.

Do not modify the project command registry merely because of this business/
security requirement.

Only modify the registry if an actual command-protocol conflict is discovered.

Do not modify account-level Custom Instructions for this requirement.

======================================================================
O. IMPLEMENTATION REQUIREMENTS
======================================================================

After structural reconciliation, implement and verify MT-2.19 in this same
authorized requirement run.

Do not start MT-2.9.

Prefer a centralized maintainable RBAC/session design over scattered
hard-coded checks.

Do not create additional authentication realms merely to represent roles.

Where current per-Admin JSON permissions are insufficient for maintainable
named/custom Roles, introduce the minimum conventional relational role/
permission structure required while preserving existing verified permission
semantics and migration history.

Do not silently weaken existing security.

Preserve or improve the currently implemented:

- device/session protections;
- customer/Admin cookie isolation;
- CSRF/origin boundaries;
- auth-version/session revocation;
- password recovery behavior;
- outlet access enforcement;
- auditability.

======================================================================
P. REQUIRED NEGATIVE SECURITY TESTS
======================================================================

Fresh verification must include negative cases proving at minimum:

- Customer cannot authenticate/use Admin authority;
- Team Member cannot grant permissions beyond delegation ceiling;
- Manager cannot assign protected Full Access without authority;
- Team Member cannot promote themselves;
- Custom Role cannot bypass permission validation;
- outlet assignment cannot be bypassed;
- direct API access remains denied when UI/menu is hidden;
- Admin Remember Me cannot recreate a persistent Admin login;
- Admin expires after 30 minutes of true inactivity;
- background polling does not indefinitely extend Admin inactivity;
- Customer remains valid under its separate 120-minute policy;
- non-remembered Customer browser session is non-persistent;
- remembered Customer authentication respects the approved maximum lifetime;
- password reset/change invalidates applicable old sessions/remembered state;
- stale/revoked/disabled Admin sessions fail;
- sensitive actions reject stale recent-authentication state.

======================================================================
Q. FINAL ACCEPTANCE
======================================================================

Identity:

- only Admin and Customer credential realms exist;
- internal workforce is presented as Team Members;
- employee roles are not credential providers;
- one human uses one individual credential.

Roles:

- Full Access
- Manager
- Store Manager
- Sales Associate
- Cashier
- Inventory Manager
- Service & Warranty
- Online Store Editor
- Merchandiser
- Customer Support
- Digital Operations
- safe Custom Roles

are represented as authorization roles/templates rather than authentication
realms.

Authorization:

- permissions are enforced server-side;
- outlet assignments are enforced independently;
- delegation ceilings prevent privilege escalation;
- protected Full Access authority cannot be obtained indirectly;
- relevant changes/actions are auditable.

Admin sessions:

- 30-minute true inactivity expiry;
- no Remember Me;
- browser-close session behavior;
- POS + Website administration use the same Admin policy;
- warning before expiry where UI is available;
- background polling does not keep inactive users alive;
- sensitive actions require recent authentication;
- password/reset/revocation behavior passes.

Customer sessions:

- 120-minute normal inactivity policy;
- optional remembered login with approved capped lifetime;
- non-remembered browser session ends on browser close;
- password/reset revocation passes;
- appropriate durable customer/business state survives auth expiry.

Project governance:

- MT-2.18 remains completed historical evidence;
- MT-2.7 remains completed historical evidence;
- new MT-2.19 is inserted after MT-2.7;
- MT-2.9 depends on MT-2.19;
- Source of Truth, roadmap, DOCX and ledger are reconciled;
- required focused/full security, migration, schema, frontend/backend and
  regression gates pass;
- repository is clean and synchronized after the verified checkpoint;
- MT-2.9 remains Pending.

After successful completion return:

Completed: MT-2.19 - Team member roles, delegated access and session security remediation
Next: MT-2.9 - Supplier and procurement services
Proceed? Y/N