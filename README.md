# mobiST Tech

A new, independent mobiST Technologies monorepo. This is a controlled architecture migration of the completed mobiST POS and Website applications, not a clean-slate rebuild.

## Project control

- [Canonical project Goal](docs/PROJECT_GOAL.md) - the user's original file, preserved byte-for-byte.
- [Binding project Preferences](docs/PROJECT_PREFERENCES.md) - approved implementation preferences, preserved byte-for-byte and applied alongside the Goal.
- [Architecture and execution boundaries](docs/PROJECT_SOURCE_OF_TRUTH.md)
- [Active roadmap](docs/PROJECT_IMPLEMENTATION_ROADMAP.md) / [Word mirror](docs/PROJECT_IMPLEMENTATION_ROADMAP.docx)
- [Verified implementation status](docs/PROJECT_IMPLEMENTATION_STATUS.md)
- [Project command registry](docs/AI_PROJECT_COMMAND_REGISTRY.md)
- [Source baseline and migration risks](docs/SOURCE_BASELINE.md)
- [MT-0.1 Preferences reconciliation](docs/INITIALIZATION_PREFERENCES_RECONCILIATION.md)
- [Source inventory and feature parity register](docs/migration/FEATURE_PARITY_REGISTER.md)
- [Isolated source characterization evidence](docs/migration/CHARACTERIZATION.md)
- [Unified data, API and security design](docs/design/README.md)

## Target structure

```text
backend/               Laravel 13, REST API, React, TypeScript, Inertia, Tailwind CSS
website/               Next.js, React, TypeScript, Tailwind CSS
tools/mobist-control/   One canonical Windows Control application
brand/                  Approved master/reference branding
docs/                   Goal, roadmap, registry and verified evidence
.github/                Monorepo CI
```

The backend and MySQL are the authoritative owners of shared business data. The Website consumes REST APIs. Redis and S3-compatible storage are used only for appropriate, justified roles. Local development runs on Windows; the production target is Linux, Nginx and TLS.

Initialization, MT-1.1 source inventory and MT-1.2 unified data/API/security design are complete (3/32 points). Application code, data migration and runnable development servers have not yet been implemented. Fresh isolated source tests do not represent target completion, and empty component directories must not be treated as applications. The next pending point is `MT-1.3 - Windows toolchain and application foundations`.

The original `C:\mobiST\mobiST-POS` and `C:\mobiST\mobiST-Website` repositories and their GitHub remotes are protected read-only references. No edits, installs, builds, migrations, commits, pushes or remote changes are allowed there.

New remote: `https://github.com/lawangin00/mobisttech` (private). Verify repository status and synchronization from the status ledger and actual Git state.
