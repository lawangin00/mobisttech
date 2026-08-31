# mobiST Tech project instructions

Read `docs/PROJECT_IMPLEMENTATION_STATUS.md`, its active Source of Truth/roadmap and Git/code before execution. Load `docs/AI_PROJECT_COMMAND_REGISTRY.md` once for the first alias in a session; reuse unless the user invokes Refresh Registry/Refresh/VP:REFRESH-REGISTRY.

The user's canonical Goal is `docs/PROJECT_GOAL.md`; binding implementation Preferences are `docs/PROJECT_PREFERENCES.md`. Read and apply both plus approved `docs/PROJECT_REQUIREMENTS_ADDENDUM_v1.1.md`; clause traceability is in `docs/REQUIREMENTS_ADDENDUM_v1.1_RECONCILIATION.md`. Addendum reconciliation does not rerun initialization or advance implementation. Architecture/execution interpretation is `docs/PROJECT_SOURCE_OF_TRUTH.md`. Current user instructions take precedence. Initialization cannot be marked complete until approved Goal and Preferences are registered and reconciled with the control documents; ask only for genuinely missing critical input.

This is a new independent monorepo at `C:\mobisttech`, not continuation of the original DP-* roadmap. Only this repository may be changed. Original `C:\mobiST\mobiST-POS` and `C:\mobiST\mobiST-Website`, their `.git`, remotes, databases, files and running services are protected read-only references. Never install, build, run tests/migrations, commit, push, fetch/pull, reconfigure or stop/start anything there. Source characterization belongs in isolated exported copies, with external integrations disabled.

Use `git --no-optional-locks` for source inspection. Do not copy secrets, private business data, source `.git`, caches, dependencies or generated binaries into this repository. Do not create nested repositories or reuse source remotes. Never force-push.

Run one verified roadmap point at a time. Finish its applicable gates, documentation, clean intended commit/push to the new remote, then stop. Do not advance a second point without the applicable user command. Initialization only establishes MT-0.1.

Roadmap Markdown is the structural plan; the ledger owns live Completed/In Progress/Pending state and progress counts. Routine point/stage progress updates only the ledger and must not regenerate the DOCX. When roadmap structure/content materially changes, use `tools/docs/build_roadmap_docx.py`, compare content and visually verify the same-basename DOCX. Never edit Word as an independent source.

The target is a shared Laravel 13/MySQL authority, React/TypeScript/Inertia/Tailwind POS, and Next.js/React/TypeScript/Tailwind Website consuming REST APIs. Preserve verified behavior, security, data/history and payment/stock integrity; retire duplication only with parity evidence.

Before replacement, assess reuse, adaptation, refactoring and migration; rewriting requires a verified reason. A frontend/architecture change alone is not a reason to rebuild a working feature. No parallel Node/Express business backend or unjustified microservices/infrastructure. Redis needs a concrete justified role. Use incremental parity/data/integration/regression checks, not a big-bang rewrite. Keep global command rules in the registry and roadmap specification, never duplicate them inside the approved Goal or Preferences.

Roman Urdu is the default only for assistant chat/UI communication. Git-tracked project documentation and technical artifacts must use standard English unless the user explicitly requests another language for a specific artifact. Preserve user-supplied source documents in their original language/content unless transformation is explicitly authorized. Preserve official task titles, identifiers and minimal project status labels exactly. Detailed evidence belongs in Git-tracked docs. Do not claim target tests passed by quoting historical source test results.
