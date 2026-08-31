The goal of the mobiST Tech project is to create a new, independent next-generation platform for mobiST Technologies by migrating the completed existing mobiST POS and Website applications into a clean, integrated monorepo architecture.

The existing completed POS and Website repositories must remain completely unchanged. They are verified migration/reference sources only and must not be modified, restructured, deleted, committed to, pushed from, or otherwise altered as part of this new project.

Create the new project locally at:

C:\mobisttech

The new project must use one Git repository/monorepo rooted at:

C:\mobisttech

Use the following high-level structure:

C:\mobisttech\
├── backend\
├── website\
├── tools\
│   └── mobist-control\
├── brand\
├── docs\
├── .github\
└── README.md

Create one new independent GitHub repository/remote for this complete monorepo. Do not reuse, repoint, overwrite, or modify either of the original POS or Website GitHub repositories or their remotes.

This is a controlled architecture migration, not a clean-slate rebuild.

Preserve and reuse valid existing functionality, business rules, integrations, data models, APIs, payment logic, authentication, Website administration/CMS functionality, customer functionality, POS functionality, inventory rules, warranty functionality, reporting, completed Dynamic Platform functionality, and other verified implementation wherever technically appropriate.

Do not carry forward obsolete duplication merely because it exists in both old repositories.

The final target architecture must be:

BACKEND + POS

Location:

C:\mobisttech\backend

Technology stack:
- Laravel 13
- REST API
- React
- TypeScript
- Inertia
- Tailwind CSS

The backend application must serve two roles:

1. It must contain the internal mobiST POS portal using React + TypeScript + Inertia + Tailwind CSS.

2. It must act as the single authoritative Laravel backend and business-logic layer for the complete mobiST POS + e-commerce ecosystem.

Its responsibilities include, where applicable:
- products;
- product variants/units;
- inventory;
- stock movements;
- sales;
- Website orders;
- shared customer data;
- payments;
- warranties;
- returns;
- reports;
- authentication/authorization;
- shared business rules;
- integrations;
- shared transactional functionality;
- REST APIs consumed by the Website.

WEBSITE

Location:

C:\mobisttech\website

Technology stack:
- Next.js
- React
- TypeScript
- Tailwind CSS

The Website must be the public/customer-facing e-commerce application.

Its responsibilities include, where applicable:
- public storefront;
- product catalogue presentation;
- product pages;
- search/filtering;
- customer registration/login/account;
- cart;
- checkout;
- customer orders;
- product reviews;
- digital solutions/public content;
- customer-facing payment flows;
- other public Website functionality.

The Website must consume shared business and transactional functionality from the Laravel backend through defined REST APIs.

DATA

Use:
- MySQL as the single master relational database;
- Redis for caching, queues, sessions, and background jobs where appropriate;
- S3-compatible object/file storage where appropriate.

There must be only one authoritative master database for shared POS and Website business data.

Do not create separate authoritative POS and Website databases that require two-way synchronization.

The Laravel backend and master MySQL database must be the authoritative source for shared business data and state.

The intended integration model is:

POS product/inventory change
→ Laravel shared backend
→ MySQL master database
→ Website receives current catalogue, pricing, availability, and relevant state through the REST API

Website customer order
→ Laravel shared backend
→ validation/reservation/payment/order processing
→ MySQL master database
→ inventory/stock/order state updated
→ resulting order and state become available to the POS/admin system

The POS and Website are separate user-facing applications, but they must operate as parts of one integrated platform through the shared Laravel backend and master MySQL database.

BRANDING

Maintain one canonical shared branding directory at:

C:\mobisttech\brand

Use this root-level directory for approved mobiST Technologies master/reference branding assets such as:
- logos;
- icons;
- favicons;
- watermarks;
- approved source/reference brand files;
- other shared brand assets where appropriate.

The duplicate Brand Kit folders currently present in the old POS and Website repositories must not be carried forward as duplicate folders.

Inspect actual runtime branding dependencies during migration and place runtime assets where technically required by the applications, but maintain only one canonical master/reference brand source at the monorepo level.

MOBIST CONTROL

Maintain only one canonical mobiST Control application at:

C:\mobisttech\tools\mobist-control

Do not carry forward separate duplicate Control app copies from the old POS and Website repositories.

Use the existing Control app implementation only as a migration/reference source and adapt it to the new monorepo architecture.

The migrated mobiST Control app must:
- use the current approved mobiST Technologies branding/logo;
- target the new backend path at C:\mobisttech\backend;
- target the new Website path at C:\mobisttech\website;
- correctly start the backend/POS development environment;
- correctly start the Website development environment;
- stop them correctly;
- restart them correctly where supported;
- open the appropriate application pages;
- display reliable Online/Offline or equivalent server status;
- check whether the relevant process/server is already running before starting another instance;
- avoid duplicate server processes and unnecessary duplicate browser tabs/windows where practical.

LOCAL DEVELOPMENT

The local development environment is Windows.

The project is intended to be developed and operated locally from the Windows machine under:

C:\mobisttech

PRODUCTION INFRASTRUCTURE

For future production deployment, use:
- Linux;
- Nginx;
- SSL/TLS;
- automated backups;
- S3-compatible storage where applicable.

Linux/Nginx are production-target requirements and do not replace Windows as the local development environment.

QUALITY

Use:
- Pest / PHPUnit for backend testing;
- Playwright for end-to-end/browser testing;
- GitHub Actions for CI where appropriate.

The completed mobiST Tech project must preserve all valid required functionality from the completed existing POS and Website applications while migrating them into this unified architecture and eliminating unnecessary duplication.