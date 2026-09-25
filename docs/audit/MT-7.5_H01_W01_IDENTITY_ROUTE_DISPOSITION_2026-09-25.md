# H01 W01 frozen Website Admin/Customer original route disposition (25-Sep-2026 LOCAL)

W01 was independently accepted in existing backend and actual browser evidence; H01 reuses that proof without rerunning it. All **46/46 W01 original routes**, including five routes also owned by W06, have exact original method/URI review below. Historical Website Admin is superseded by one protected Admin realm and separate Customer realm; old source identity rows, credentials, business data and public bearer access are not imported. An original route may be intentionally retired while a necessary function survives under an approved target service. **Explicit source-behavior exceptions below remain H01 review items, not family PASS claims.**

| # | Frozen source method / URI | Approved target behavior or bounded discrepancy | Existing proof / outstanding classification |
|---:|---|---|---|
| 01 | `GET\|HEAD admin` | Unified /internal/admin/platform entry and Admin realm; retired separate Website Admin login | REUSE W01 identity + Platform Admin browser; original separate credential retired |
| 02 | `GET\|HEAD admin/about` | Protected /internal/admin/platform managed About page, draft/publish | REUSE W06 CMS exact source route; owner copy approval separate |
| 03 | `PUT admin/about` | Protected /internal/admin/platform/pages/draft + /pages/{revision}/publish | REUSE W06 managed About draft/publish; old Blade editor retired |
| 04 | `GET\|HEAD admin/activity` | Website Admin activity log: website.audit.view permission and identity_audit_events exist, but no user-facing Website audit viewer | OPEN I/E: original 50/page search/method private operator log not substituted by POS-only audit viewer |
| 05 | `GET\|HEAD admin/administrators` | GET /internal/admin/team-members with delegated roles and outlet scope | REUSE W01 TeamMemberAdmin+cross-role denial; old separate Website admin accounts retired |
| 06 | `POST admin/administrators` | POST /internal/admin/team-members with ceiling checks | REUSE W01/MT-2.19 protected team creation; old Website credentials retired |
| 07 | `PUT admin/administrators/{user}` | PATCH /internal/admin/team-members/{member:public_id} with ceiling/last-owner checks | REUSE W01 protected Team Member edits, disable/archive not hard delete |
| 08 | `GET\|HEAD admin/contact` | Protected Platform managed Contact editor and public /contact content model | REUSE W06 managed Contact page; old separate Blade editor retired |
| 09 | `PUT admin/contact` | POST /internal/admin/platform/pages/draft + publish | REUSE W06 Contact draft/publish and approval controls |
| 10 | `PUT admin/contact/revisions/{revision}/rollback` | POST /internal/admin/platform/pages/{revision}/rollback | REUSE W06 managed-page rollback and current-only public content |
| 11 | `GET\|HEAD admin/dashboard` | Protected /internal/admin/platform, /website-commerce and /digital-operations dashboards | E: source Website combined orders/digital KPI chart versus accepted separate target dashboards/conversion API not independently line-by-line compared |
| 12 | `GET\|HEAD admin/dashboard/report.csv` | GET /internal/admin/website-commerce/orders.csv plus GET /digital-operations/conversions | E: separated report scopes are accepted, source combined dashboard CSV composition needs original field/metric disposition |
| 13 | `GET\|HEAD admin/home-content` | Website CMS protected presentation/homepage and managed homepage | REUSE W06 homepage content revision/current-only public Next |
| 14 | `PUT admin/home-content` | POST /internal/admin/platform/presentation/draft + /presentation/{revision}/publish | REUSE W06 homepage draft/publish and typed editor |
| 15 | `PUT admin/home-content/preview` | Protected W06 managed homepage private preview | REUSE W06 private preview; legacy URL retired |
| 16 | `PUT admin/home-content/revisions/{revision}/rollback` | POST /internal/admin/platform/presentation/{revision}/rollback | REUSE W06 homepage rollback revision and cache proof |
| 17 | `GET\|HEAD admin/login` | GET /internal/admin/pos/login and single Admin credential realm | REUSE W01 signed-in Admin browser; legacy Website Admin login page retired |
| 18 | `POST admin/login` | POST /internal/admin/auth/login | REUSE W01 Admin login/session/role tests; separate Website Admin credential retired |
| 19 | `POST admin/logout` | POST /internal/admin/auth/logout with CSRF | REUSE W01 session revocation; no legacy public GET logout |
| 20 | `GET\|HEAD admin/profile` | GET /internal/admin/manage-account | REUSE W01 individual Admin self profile/account UI |
| 21 | `PATCH admin/profile/password` | PATCH /internal/admin/auth/password | REUSE W01 password change/session revocation with current-password checks |
| 22 | `PATCH admin/profile/photo` | POST /internal/admin/manage-account/photo | REUSE W01 private self-scoped Admin image validation and audit |
| 23 | `DELETE admin/profile/photo` | DELETE /internal/admin/manage-account/photo | REUSE W01 private photo removal; related read GET remains self-only |
| 24 | `GET\|HEAD admin/project-quotes` | GET /internal/admin/digital-operations/data project proposal list | REUSE W05 protected actual project/proposal UI; legacy public reference edit retired |
| 25 | `POST admin/project-quotes` | POST /internal/admin/digital-operations/projects/{project}/proposals | REUSE W05 versioned project owned proposal creation with scoped Admin permissions |
| 26 | `GET\|HEAD admin/project-quotes/create` | GET /internal/admin/digital-operations project/proposal create controls | REUSE W05 shared proposal editor; standalone quote create URL retired |
| 27 | `PUT admin/project-quotes/{projectQuote}` | POST /internal/admin/digital-operations/projects/{project}/proposals; approved revision lifecycle | REUSE W05 immutable proposal revisions; no in-place rewrite of published quote |
| 28 | `GET\|HEAD admin/project-quotes/{projectQuote}/edit` | GET /internal/admin/digital-operations/data project/proposal edit controls | REUSE W05 editor; separate old form route retired |
| 29 | `GET\|HEAD admin/service-requests` | GET /internal/admin/digital-operations/data leads | REUSE W05 protected digital lead list/status UI |
| 30 | `GET\|HEAD admin/service-requests/{serviceRequest}` | GET /internal/admin/digital-operations/leads/{lead} | REUSE W05 permissioned lead detail and private files |
| 31 | `PATCH admin/service-requests/{serviceRequest}/status` | PATCH /internal/admin/digital-operations/leads/{lead} | REUSE W05 lead state transition/history with scope denial |
| 32 | `GET\|HEAD admin/services` | GET /internal/admin/digital-operations/data services | REUSE W05 service catalogue Admin UI |
| 33 | `POST admin/services` | POST /internal/admin/digital-operations/services | REUSE W05 validated service create; old separate Blade form retired |
| 34 | `GET\|HEAD admin/services/create` | GET /internal/admin/digital-operations services create controls | REUSE W05 actual Admin form; legacy separate create URL retired |
| 35 | `PUT admin/services/{service}` | POST /internal/admin/digital-operations/services with service identifier | REUSE W05 service update with version/protected authorization |
| 36 | `GET\|HEAD admin/services/{service}/edit` | GET /internal/admin/digital-operations service edit controls | REUSE W05 actual service edit UI; old separate Blade edit URL retired |
| 37 | `GET\|HEAD customer/account` | Next /account + authenticated GET /api/v1/account | REUSE W01 Customer signed history/profile/photo; distinct from Admin realm |
| 38 | `GET\|HEAD customer/login` | Next /account sign-in panel | REUSE W01 Customer browser; old Laravel auth page retired |
| 39 | `POST customer/login` | POST /api/v1/auth/login via /api/customer/auth/login proxy | REUSE W01 Customer session/CSRF/ownership; no old user ID merge |
| 40 | `POST customer/logout` | POST /api/v1/auth/logout via Next same-origin proxy | REUSE W01 Customer session revocation |
| 41 | `GET\|HEAD customer/register` | Next /account register pane | REUSE W01 Customer registered identity browser and role denial |
| 42 | `POST customer/register` | POST /api/v1/auth/register via Next proxy | REUSE W01 Customer registration and identity collision guards |
| 43 | `GET\|HEAD forgot-password` | Next /account recovery pane | REUSE W01 local recovery form and external email separately unverified |
| 44 | `POST forgot-password` | POST /api/v1/auth/forgot-password via Next proxy | REUSE W01 mock delivery and truthful provider-disabled fail; no real Gmail PASS |
| 45 | `POST reset-password` | POST /api/v1/auth/reset-password via Next proxy | REUSE W01 one-use token/password/session reset |
| 46 | `GET\|HEAD reset-password/{token}` | Next /reset-password route with single-use token | REUSE W01 explicit Customer reset form, foreign realm denial |

## Exact outstanding H01 behavior (do not reopen unrelated W01 PASS)

- Row 04: Original Website `admin/activity` provided a protected searchable 50/page Website Admin audit list with method filter. Current `website.audit.view` role permission and durable `identity_audit_events`/`admin_audit_logs` exist, but `PosAuditViewer` reads POS-only logs and no Website-specific bounded UI/route was found. Determine whether the source operator-facing Website activity remains required; unless explicitly approved as retired, expose a strictly permissioned, redacted Website-audit view using the existing records (new focused auth/filter/browser tests only). Do not promote `website.audit.view` to a broad Admin bypass.
- Rows 11/12: A source combined Website dashboard chart/KPI and CSV combined order/revenue/digital aggregates; current protected `/website-commerce/orders.csv` and `/digital-operations/conversions` are independently accepted, but their exact composition against original fields was not compared here. Read original pinned dashboard/export computation against existing target service/output, documenting approved split or concrete missing calculated values; avoid duplicating an already accepted reporting system.
- Existing W01 login/profile/photo/Customer/session and W05/W06/W03 journey PASS is reused without new tests. Authentic email and production owner inputs remain separately external-HOLD; P01 historical outlet permission acceptance is independently OPEN.
