# mobiST Tech

mobiST Technologies ka naya, independent monorepo. Yeh completed mobiST POS aur Website ki controlled architecture migration hai; clean-slate rebuild nahi.

## Project control

- [Canonical project Goal](docs/PROJECT_GOAL.md) - user ki original file, byte-for-byte preserved.
- [Binding project Preferences](docs/PROJECT_PREFERENCES.md) - approved implementation preferences, byte-for-byte preserved; Goal ke saath apply hon.
- [Architecture aur execution boundaries](docs/PROJECT_SOURCE_OF_TRUTH.md)
- [Active roadmap](docs/PROJECT_IMPLEMENTATION_ROADMAP.md) / [Word mirror](docs/PROJECT_IMPLEMENTATION_ROADMAP.docx)
- [Verified implementation status](docs/PROJECT_IMPLEMENTATION_STATUS.md)
- [Project command registry](docs/AI_PROJECT_COMMAND_REGISTRY.md)
- [Source baseline aur migration risks](docs/SOURCE_BASELINE.md)
- [MT-0.1 Preferences reconciliation](docs/INITIALIZATION_PREFERENCES_RECONCILIATION.md)

## Target structure

```text
backend/               Laravel 13, REST API, React, TypeScript, Inertia, Tailwind CSS
website/               Next.js, React, TypeScript, Tailwind CSS
tools/mobist-control/   Aik canonical Windows Control application
brand/                 Approved master/reference branding
docs/                  Goal, roadmap, registry aur verified evidence
.github/               Monorepo CI
```

Backend aur MySQL shared business data ke authoritative owners honge. Website REST APIs use karegi. Redis aur S3-compatible storage munasib roles mein use honge. Local development Windows par; production target Linux, Nginx aur TLS hai.

Is checkpoint par sirf project initialization hai. Application code, data migration aur runnable development servers abhi implement nahi hue. Khali component directories ko application completion na samjhein.

Purane `C:\mobiST\mobiST-POS` aur `C:\mobiST\mobiST-Website` aur unke GitHub remotes protected read-only references hain. Un par edit, install, build, migration, commit, push ya remote changes mana hain.

Naya remote: `https://github.com/lawangin00/mobisttech` (private). Git state aur push verification ke liye status ledger aur actual Git check karein.
