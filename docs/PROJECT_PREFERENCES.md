Use the following implementation preferences for the mobiST Tech project:

1. Treat this as an incremental controlled architecture migration, not a fresh rebuild.

2. Keep the completed original mobiST POS and Website repositories completely untouched. Use them only as verified migration/reference sources.

3. Perform all new project implementation inside:

C:\mobisttech

4. Use a single Git repository/monorepo rooted at:

C:\mobisttech

with this structure:

C:\mobisttech\
├── backend\
├── website\
├── tools\
│   └── mobist-control\
├── brand\
├── docs\
├── .github\
└── README.md

5. Create one new independent GitHub repository/remote for the complete new monorepo. Never repoint, overwrite, or modify the original POS or Website repositories or their remotes.

6. Preserve existing verified functionality wherever technically sound. Do not rebuild a working feature merely because the architecture or frontend technology is changing.

7. Before replacing existing implementation, determine whether it can be:
- reused;
- adapted;
- refactored;
- migrated;
or whether it genuinely requires rewriting.

8. Use the following approved target stack unless a verified technical blocker makes a change unavoidable.

Backend + POS:
- Laravel 13
- REST API
- React
- TypeScript
- Inertia
- Tailwind CSS

Website:
- Next.js
- React
- TypeScript
- Tailwind CSS

Data:
- MySQL as the single master relational database
- Redis for caching, queues, sessions, and background processing where appropriate
- S3-compatible storage where appropriate

Local development:
- Windows

Future production:
- Linux
- Nginx
- SSL/TLS
- automated backups

Testing and CI:
- Pest / PHPUnit
- Playwright
- GitHub Actions

9. Use the Laravel application inside C:\mobisttech\backend as the single authoritative backend and shared business-logic layer for the complete POS + Website ecosystem.

10. Keep the internal POS frontend inside the Laravel backend application using React + TypeScript + Inertia + Tailwind CSS.

11. Keep the public/customer-facing Website as the separate Next.js application inside:

C:\mobisttech\website

12. The Website must consume shared backend functionality through defined Laravel REST APIs.

13. Do not introduce Node/Express or another parallel business backend. Laravel 13 must remain the authoritative shared backend.

14. Use one master MySQL database for shared business and transactional data.

15. Do not create independent authoritative POS and Website databases that require two-way synchronization.

16. Products, variants/units, inventory, stock movements, shared customer records, Website orders, POS sales, payments, warranties, returns, reporting data, and other shared transactional records must have one authoritative source through the Laravel backend and master MySQL database.

17. POS-side inventory/product changes must become authoritative through the shared backend/database and be reflected to the Website through the API.

18. Website orders must pass through the shared Laravel backend so validation, reservation, payment processing, stock updates, order state, and POS visibility remain consistent.

19. Preserve valid existing payment integrations, authentication, customer accounts, Website administration/CMS functionality, catalogue functionality, reviews, checkout/order functionality, POS sales/inventory functionality, warranties, reports, backups, Dynamic Platform functionality, and established business rules unless a verified reason requires modification.

20. Migrate existing database structures and business data to MySQL in a controlled and testable way while preserving valid relationships, constraints, identifiers, and business rules.

21. Use Redis only where it provides a justified role such as caching, queues, sessions, locks, or background processing. Do not add infrastructure complexity without a concrete purpose.

22. Migrate incrementally and verify feature parity, data consistency, backend/Website integration, and regression behavior throughout the migration. Avoid a big-bang rewrite.

23. Prefer stable, conventional, maintainable solutions over unnecessary microservices or architectural complexity.

24. Keep backend and Website logically separated inside the monorepo while allowing coordinated commits, shared tooling, documentation, branding, CI, and project-level resources.

25. Maintain one canonical root-level branding directory:

C:\mobisttech\brand

Do not migrate the duplicate Brand Kit folders from the old POS and Website repositories as two separate copies.

26. Use the root-level `brand` directory as the canonical source for approved mobiST Technologies master/reference branding assets, including logos, icons, favicons, watermarks, and relevant source/reference files.

27. Runtime applications may place or generate framework-specific assets where technically required, but do not maintain unnecessary duplicate master Brand Kits in backend and Website.

28. Maintain only one canonical mobiST Control application:

C:\mobisttech\tools\mobist-control

Do not maintain separate copies inside backend and Website.

29. When migrating the mobiST Control app, use the existing copies only as reference and consolidate the useful implementation into the single canonical Control app.

30. Update the Control app to use the current approved mobiST Technologies logo and branding rather than the older logo currently present in the legacy Control app.

31. Adapt the Control app to the new paths:
- Backend/POS: C:\mobisttech\backend
- Website: C:\mobisttech\website

32. The Control app should provide reliable control/status behavior for both development applications, including as appropriate:
- Start;
- Stop;
- Restart;
- Open;
- Status;
- Start All;
- Stop All.

33. Before starting a backend or Website server, the Control app should determine whether the corresponding service/process is already running and avoid launching duplicate instances.

34. Avoid unnecessary duplicate browser tabs/windows when opening already-running applications where practical.

35. Use Windows as the local development environment. Linux and Nginx apply to future production deployment, not to the local development machine.

36. Do not automatically carry every legacy folder/file into the new monorepo. Inspect its actual role and dependencies first and retain only what remains useful, required, authoritative, or historically necessary.

37. Eliminate unnecessary duplication during migration without removing required functionality or authoritative assets.

38. Verify migrated functionality, builds, tests, data behavior, integration, and relevant user flows before considering migration work complete.

39. Follow the existing account-level project instructions and the current Git-backed global project command/roadmap rules when `Initialize Project` is invoked. Do not duplicate those global project-control specifications inside this project's Goal or Preferences.