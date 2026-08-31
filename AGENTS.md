# mobiST Tech project instructions

Read `docs/PROJECT_IMPLEMENTATION_STATUS.md`, its active Source of Truth/roadmap and Git/code before execution. Load `docs/AI_PROJECT_COMMAND_REGISTRY.md` once for the first alias in a session; reuse unless the user invokes Refresh Registry/Refresh/VP:REFRESH-REGISTRY.

User's canonical Goal is `docs/PROJECT_GOAL.md`. Architecture/execution interpretation is `docs/PROJECT_SOURCE_OF_TRUTH.md`. Current user instructions take precedence.

This is a new independent monorepo at `C:\mobisttech`, not continuation of the original DP-* roadmap. Only this repository may be changed. Original `C:\mobiST\mobiST-POS` and `C:\mobiST\mobiST-Website`, their `.git`, remotes, databases, files and running services are protected read-only references. Never install, build, run tests/migrations, commit, push, fetch/pull, reconfigure or stop/start anything there. Source characterization belongs in isolated exported copies, with external integrations disabled.

Use `git --no-optional-locks` for source inspection. Do not copy secrets, private business data, source `.git`, caches, dependencies or generated binaries into this repository. Do not create nested repositories or reuse source remotes. Never force-push.

Run one verified roadmap point at a time. Finish its applicable gates, documentation, clean intended commit/push to the new remote, then stop. Do not advance a second point without the applicable user command. Initialization only establishes MT-0.1.

Roadmap Markdown and same-basename DOCX must remain synchronized. Use `tools/docs/build_roadmap_docx.py`, compare content and visually verify the rendered document whenever roadmap changes. Never edit Word as an independent source.

The target is a shared Laravel 13/MySQL authority, React/TypeScript/Inertia/Tailwind POS, and Next.js/React/TypeScript/Tailwind Website consuming REST APIs. Preserve verified behavior, security, data/history and payment/stock integrity; retire duplication only with parity evidence.

Default user-facing language is Roman Urdu. Preserve official task titles, identifiers and minimal project status labels exactly. Detailed evidence belongs in Git-tracked docs. Do not claim target tests passed by quoting historical source test results.
