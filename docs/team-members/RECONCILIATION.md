# MT-2.19 Team Member and Session Security Reconciliation

Date: 2026-09-01

## Authority and boundary

The approved source is `docs/PROJECT_REQUIREMENTS_TEAM_MEMBERS_SESSION_POLICY_v1.0.md`, preserved byte-for-byte with SHA-256 `94b42b6462b8e0e70a85991a3609d3de0ef21bd8f3e87ddea600744b8803b472`. It extends the unified Admin architecture and does not reopen or rewrite completed MT-2.18 or MT-2.7 evidence. The command registry has no conflict and is unchanged.

Before reconciliation, Git-backed state proved MT-2.7 complete, no point In Progress, and `MT-2.9 - Supplier and procurement services` first Pending. Roadmap v1.8 inserts only `MT-2.19 - Team member roles, delegated access and session security remediation` between MT-2.7 and MT-2.9. MT-2.9 now depends on MT-2.19; later order remains unchanged.

## Canonical interpretation

- Human credential realms remain exactly Admin and Customer. `Full Access`, `Manager`, `Store Manager`, `Sales Associate`, `Cashier`, `Inventory Manager`, `Service & Warranty`, `Online Store Editor`, `Merchandiser`, `Customer Support`, `Digital Operations` and `Custom Role` are authorization roles/templates, never guards or providers.
- Internal users are Team Members. Each uses one individual Admin credential across permitted POS and Website administration surfaces. Optional Job Title is descriptive and never contributes to effective permissions.
- Permissions remain the server-side boundary. Effective permissions are the union of explicitly preserved legacy direct permissions and explicit relational Role permissions. New Team Members receive named Role assignments; new permission definitions are never silently added to existing Custom Roles.
- Outlet assignments remain a separate dimension. Delegated administration cannot assign a permission or outlet outside the actor's own effective ceiling. Full Access requires both the protected Role and its explicit assignment permission; Custom Roles cannot smuggle that permission.
- Administration and operational audit events retain actor name and Role snapshots, plus outlet context where applicable, so later role changes cannot falsify historical attribution.
- Laravel owns session policy. Admin uses 30 minutes of true human inactivity, browser-close cookies and no remember login. Customer uses 120 minutes, browser-close cookies when not remembered and an optional 30-day maximum remembered login. Background requests do not advance the human-activity clock.
- One recent-authentication timestamp, refreshed only by login or confirmed password, gates classified sensitive Admin operations for ten minutes. Password replacement rotates the remember token/auth version and revokes existing account sessions.

## Requirement traceability

| Requirement group | Canonical implementation/evidence | Later consumer |
|---|---|---|
| A, B, K: realms and Team Members | `config/auth.php`, `Admin`, Team Member API/service; no new guard/provider | MT-4.1, MT-4.4, FINAL-AUDIT |
| C, D: supplied and Custom Roles | `permission_definitions`, `roles`, `role_permissions`, `admin_roles`; explicit seeded snapshots | MT-4.4, MT-7.2 |
| E: delegated administration | `TeamMemberAdministration`; permission/outlet subset checks, self-change block, protected Full Access check | MT-4.4, MT-7.2 |
| F: workforce/outlet/history | individual `Admin`, independent `outlet_admins`, `team_member_audit_events`, enriched `identity_audit_events` | MT-2.9 onward, MT-4.1, FINAL-AUDIT |
| G, H: Admin session policy/gap | `RealmSessionPolicy`, `IdentityContext`, `IdentityAuthenticated`, `PosSessions`; 30-minute human clock | MT-4.1, MT-4.4, MT-7.2 |
| I: sensitive re-authentication | `RecentlyAuthenticated`, confirm-password route, sensitive route middleware | MT-4.4, MT-7.2 |
| J: Customer session policy | same centralized policy with 120 minutes and 30-day remember cap; existing auth-version revocation | MT-5.2, MT-7.2 |
| L: UI/API consumers | roadmap scopes explicitly consume Laravel authority and warning/continuation contract | MT-4.1, MT-4.4, MT-5.2 |
| M, N: control documents | Source of Truth v1.8, roadmap v1.8 + DOCX, ledger v1.8; registry unchanged | current checkpoint |
| O, P, Q: implementation/negative acceptance | `TeamMemberSessionSecurityTest` plus existing identity, migration, integration, schema and full suites | MT-2.19 verification |

## Reuse and migration decision

Existing Admin credentials, direct permission JSON, outlet memberships, auth versions, isolated cookies, CSRF/origin checks, password brokers and device/network records are adapted rather than rebuilt. Relational Role tables are additive because per-account JSON alone cannot provide named/default/custom bundles or safe delegation. Existing direct permissions remain valid during controlled migration; no completed migration is rewritten. Existing Superadmin/Website-admin target providers remain removed.

No source repository/runtime/database, private business data, external provider, production system or customer communication is accessed by this remediation.
