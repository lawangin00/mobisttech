# MT-7.5 W05 — bounded source behavior disposition (24-Sep-2026)

Source: pinned `docs/migration/SOURCE_SYMBOL_INVENTORY.json` (W05-tagged Website entries). Approved fresh-business system uses Customer-account ownership instead of public bearer-token lookups; no legacy identity, data or secret migration. This is an evidence crosswalk, not a functional acceptance PASS.

## Exactly 24 tagged source files

| # | Frozen source file | Target responsibility / disposition |
|---:|---|---|
| 01 | `app/Http/Controllers/CompanyPageController.php` | Company pages: public business/contact information; new Next homepage/profile. Actual content equivalence W06. |
| 02 | `app/Http/Controllers/ProjectPaymentController.php` | Historical project payment: owner-bound account project/payment REST; old public token intentionally retired; owned journey W05/W04. |
| 03 | `app/Http/Controllers/ServiceRequestController.php` | Service enquiry: Next `/enquiry` and server `/api/v1/enquiries`; privacy/mode joined acceptance W05. |
| 04 | `app/Models/DigitalService.php` | Service model: versioned public catalogue and protected Admin digital-services. |
| 05 | `app/Models/ProjectQuote.php` | Quote model: approved proposal revisions, exact schedule and owned quote/payment in ClientProjectServices. |
| 06 | `app/Models/ServiceRequest.php` | Request model: private lead and immutable event/history in DigitalServiceLeads. |
| 07 | `database/migrations/2026_08_24_000200_create_project_quotes_table.php` | Historical quote schema: new shared proposal/milestone/quote data; no legacy row import. |
| 08 | `database/migrations/2026_08_24_000300_create_service_requests_table.php` | Historical request schema: new shared lead and private reference storage; no legacy row import. |
| 09 | `database/migrations/2026_08_25_090000_add_service_request_id_to_project_quotes_table.php` | Historical request-to-quote linkage: new lead-to-project conversion and immutable proposal linkage. |
| 10 | `database/seeders/DigitalServiceSeeder.php` | Source seeder retired: newly authored protected digital service publication; no copied demo/business rows. |
| 11 | `public/css/company-pages.css` | Source company-page CSS replaced by responsive Next layout; public copy W06. |
| 12 | `resources/views/admin/project-quote-form.blade.php` | Original Admin quote form replaced by protected Digital Operations proposal editor; actual API-browser join pending. |
| 13 | `resources/views/admin/project-quotes.blade.php` | Original Admin quotes list replaced by protected project revision list; actual API-browser join pending. |
| 14 | `resources/views/admin/service-form.blade.php` | Original Admin service form replaced by protected service editor; actual API-browser join pending. |
| 15 | `resources/views/admin/service-request-show.blade.php` | Original Admin request detail replaced by protected lead detail and private files; actual API-browser join pending. |
| 16 | `resources/views/admin/service-requests.blade.php` | Original Admin requests list replaced by permission-scoped lead pipeline; actual API-browser join pending. |
| 17 | `resources/views/admin/services.blade.php` | Original Admin services list replaced by protected digital services listing; actual API-browser join pending. |
| 18 | `resources/views/home/sections/solutions.blade.php` | Original solutions section replaced by public Next `/services` and detail; rendered public journey pending. |
| 19 | `resources/views/project-payment-lookup.blade.php` | Original anonymous payment lookup replaced by owned `/account/projects`; public bearer lookup retired by new-account privacy design. |
| 20 | `resources/views/project-payment.blade.php` | Original anonymous token pay replaced by owner-scoped milestone payment; real provider H-02, actual joined UI pending. |
| 21 | `resources/views/service-request-status.blade.php` | Original request-status page replaced by authenticated owner/history view; anonymous reference lookup not assumed equivalent. |
| 22 | `resources/views/service-request.blade.php` | Original request form replaced by `/enquiry` with server validation/private upload; real public-to-Admin join pending. |
| 23 | `resources/views/services.blade.php` | Original service list replaced by public `/services`; catalogue publication and mode behavior require joined review. |
| 24 | `tests/Feature/BusinessMessagingTest.php` | Original messaging test is source characterization only; current target consent/privacy/API tests and actual journey required. |

## Exactly nine tagged source routes

| # | Historical method / route | Target behavior and acceptance limit |
|---:|---|---|
| 01 | `GET\|HEAD about` | Public company/about content now shared public business profile/home; original copy and dedicated route not implied; W06 content reconciliation. |
| 02 | `GET\|HEAD contact` | Public contact content now published business profile and contact/enquiry entry; dedicated URL equivalence not accepted; W06. |
| 03 | `GET\|HEAD digital-solutions/request` | Service-request form now Next `/enquiry`; consent/private upload and three-mode journey W05. |
| 04 | `POST digital-solutions/request` | Service-request POST now `/api/v1/enquiries`; server-controlled pricing and private lead history, real browser join pending. |
| 05 | `GET\|HEAD digital-solutions/request/{reference}` | Reference-based request-status lookup replaced with account-owned project history; original anonymous access NOT retained; new-customer privacy boundary W05. |
| 06 | `GET\|HEAD project-payment` | Public payment lookup retired: authenticated `/account/projects` and `/api/customer/projects`; no old token/data import. |
| 07 | `POST project-payment` | Public payment reference POST retired: signed-in owner-scoped project listing/detail; no anonymous discovery. |
| 08 | `GET\|HEAD project-payment/{token}` | Bearer-token payment detail retired: `/account/projects/{project}` and owner-only project REST; unauthorized access denial requires joined acceptance. |
| 09 | `POST project-payment/{token}` | Bearer-token payment POST retired: owner-bound `/api/customer/project-milestones/pay` and continuation; immutable amount/expiry/replay verified at service level; genuine provider H-02. |

## Remaining acceptance

This source behavior disposition is complete as a 24-file/9-route inventory, not W05 closure. Earlier backend owner/expiry/schedule/privacy tests and real Customer portal tests are reused. First missing non-HOLD evidence remains one protected **real** Admin API -> owned Customer lifecycle, proposal/quote and private delivery/browser join with negative foreign owner and mode checks. Existing Admin UI browser intercepts its API and cannot establish this join. Public bearer-token access is retired rather than enabled; vendor payment remains W04/H-02 HOLD. No tests/DB/provider operations were performed for this documentation-only mapping.
